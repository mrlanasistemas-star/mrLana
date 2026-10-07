<?php

namespace Tests\Feature\Notificaciones;

use App\Enums\NotificationTopic;
use App\Models\Ajuste;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ErpNotification;
use App\Services\Notifications\NotificationService;
use App\Support\Permissions\PermissionCatalog;
use App\Support\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

class NotificacionesTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogos();
    }

    private function roleWithPrefs(string $name, bool $all, array $topics, array $perms = ['notificaciones.ver']): Role
    {
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($perms);
        $role->notificationPreference()->create(['receive_all' => $all, 'topics' => $topics]);

        return $role;
    }

    private function send(NotificationTopic $topic, ?User $actor = null): void
    {
        app(NotificationService::class)->notify($topic, 'prueba.evento', 'Título', 'Mensaje', 'info', '/requisiciones', actor: $actor);
    }

    public function test_rol_con_recibir_todas_recibe_cualquier_evento(): void
    {
        $this->roleWithPrefs('Todo', true, []);
        $user = User::factory()->withRole('Todo')->create();

        foreach (NotificationTopic::cases() as $topic) {
            $this->send($topic);
        }

        $this->assertSame(count(NotificationTopic::cases()), $user->notifications()->count());
    }

    public function test_rol_con_un_tema_solo_recibe_ese_tema_y_el_no_suscrito_nada(): void
    {
        $this->roleWithPrefs('SoloPagos', false, ['pagos']);
        $this->roleWithPrefs('Nada', false, []);
        $pagos = User::factory()->withRole('SoloPagos')->create();
        $nada = User::factory()->withRole('Nada')->create();

        $this->send(NotificationTopic::Pagos);
        $this->send(NotificationTopic::Ajustes);
        $this->send(NotificationTopic::Requisiciones);

        $this->assertSame(1, $pagos->notifications()->count());
        $this->assertSame('pagos', $pagos->notifications()->first()->data['category']);
        $this->assertSame(0, $nada->notifications()->count());
    }

    public function test_usuarios_inactivos_o_sin_permiso_de_ver_no_reciben(): void
    {
        $this->roleWithPrefs('SinVer', true, [], ['dashboard.ver']);
        $sinVer = User::factory()->withRole('SinVer')->create();
        $this->roleWithPrefs('Todo2', true, []);
        $inactivo = User::factory()->withRole('Todo2')->inactive()->create();

        $this->send(NotificationTopic::Sistema);

        $this->assertSame(0, $sinVer->notifications()->count());
        $this->assertSame(0, $inactivo->notifications()->count());
    }

    public function test_no_hay_duplicados_con_varios_roles_o_destinatario_directo(): void
    {
        $this->roleWithPrefs('A', true, []);
        $this->roleWithPrefs('B', false, ['ajustes']);
        $user = User::factory()->withRole('A')->create();
        $user->assignRole('B');

        app(NotificationService::class)->notify(
            NotificationTopic::Ajustes, 'x', 'T', 'M', direct: [$user, $user],
        );

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_ajuste_solicitado_llega_a_contabilidad_y_resolucion_al_solicitante(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador);

        $this->actingAs($colaborador)->post(route('requisiciones.ajustes.store', $req), [
            'tipo' => 'FALTANTE', 'monto' => 10, 'motivo' => 'Faltó cambio',
        ])->assertSessionHasNoErrors();

        $aviso = $contador->notifications()->first();
        $this->assertNotNull($aviso, 'Contabilidad debe recibir el ajuste solicitado.');
        $this->assertSame('ajuste.solicitado', $aviso->type);
        $this->assertSame('ajustes', $aviso->data['category']);
        $this->assertSame(0, $colaborador->notifications()->count(), 'Quien solicita no se notifica a sí mismo.');

        $ajuste = Ajuste::firstOrFail();
        $this->actingAs($contador)->patch(route('requisiciones.ajustes.review', $ajuste), [
            'accion' => 'RECHAZAR', 'comentario_revision' => 'Sin evidencia',
        ]);

        $resolucion = $colaborador->notifications()->first();
        $this->assertNotNull($resolucion);
        $this->assertSame('ajuste.rechazado', $resolucion->type);
        $this->assertSame('danger', $resolucion->data['severity']);
        $this->assertSame(route('requisiciones.ajustes', $req, false), $resolucion->data['url']);
    }

    public function test_requisicion_enviada_notifica_a_contabilidad(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);

        $this->actingAs($colaborador)
            ->post(route('requisiciones.storeCaptured'), $this->requisicionPayload($colaborador))
            ->assertSessionHasNoErrors();

        $this->assertSame('requisicion.enviada', $contador->notifications()->first()?->type);
    }

    public function test_aviso_de_comprobantes_en_el_sistema_llega_a_contabilidad(): void
    {
        $colaborador = $this->makeUser(PermissionCatalog::ROLE_COLABORADOR);
        $contador = $this->makeUser(PermissionCatalog::ROLE_CONTABILIDAD);
        $req = $this->makeRequisicion($colaborador, ['status' => 'PAGADA', 'monto_total' => 100]);
        $req->comprobantes()->forceCreate([
            'requisicion_id' => $req->id, 'tipo_doc' => 'FACTURA', 'monto' => 100,
            'estatus' => 'PENDIENTE', 'user_carga_id' => $colaborador->id,
        ]);

        $this->actingAs($colaborador)
            ->post(route('requisiciones.comprobaciones.notify', $req), ['message' => 'Listo para revisión', 'canal' => 'sistema'])
            ->assertSessionHas('success', 'Contabilidad recibió el aviso en el sistema.');

        $this->assertSame('comprobantes.enviados', $contador->notifications()->first()?->type);
        $this->assertSame('POR_COMPROBAR', $req->fresh()->status);
    }

    public function test_marcar_una_y_todas_como_leidas(): void
    {
        $this->roleWithPrefs('Lector', true, []);
        $user = User::factory()->withRole('Lector')->create();
        $otro = User::factory()->withRole('Lector')->create();
        $this->send(NotificationTopic::Pagos);
        $this->send(NotificationTopic::Ajustes);
        $this->send(NotificationTopic::Sistema);

        $ajena = $otro->notifications()->first();

        $first = $user->notifications()->first();
        $this->actingAs($user)
            ->patchJson(route('notificaciones.read', $first->id))
            ->assertOk()
            ->assertJson(['unread_count' => 2]);
        $this->assertNotNull($first->fresh()->read_at);

        // No puede marcar notificaciones de otra persona.
        $this->actingAs($user)->patchJson(route('notificaciones.read', $ajena->id))->assertNotFound();

        $this->actingAs($user)->postJson(route('notificaciones.readAll'))->assertOk()->assertJson(['unread_count' => 0]);
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(3, $otro->unreadNotifications()->count());

        $this->actingAs($user)->getJson(route('notificaciones.recent'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(3, 'items');
    }

    public function test_url_de_notificacion_solo_apunta_a_la_aplicacion(): void
    {
        config(['app.url' => 'https://erp.example.com']);

        $this->assertSame('/requisiciones/5', SafeUrl::internal('/requisiciones/5'));
        $this->assertSame('/a?b=1', SafeUrl::internal('https://erp.example.com/a?b=1'));
        $this->assertNull(SafeUrl::internal('https://malicioso.com/robo'));
        $this->assertNull(SafeUrl::internal('//malicioso.com'));
        $this->assertNull(SafeUrl::internal('javascript:alert(1)'));

        $n = new ErpNotification(NotificationTopic::Sistema, 'x', 'T', 'M', 'info', 'https://malicioso.com');
        $this->assertNull($n->url);
    }
}
