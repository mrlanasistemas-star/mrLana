<?php

namespace Tests\Feature\Notificaciones;

use App\Enums\NotificationTopic;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesErpData;
use Tests\TestCase;

/**
 * "Ver mis notificaciones" vs. "Ver todas las notificaciones", y avisos de
 * temas filtrados por el alcance del destinatario.
 */
class AlcanceNotificacionesTest extends TestCase
{
    use CreatesErpData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganizacion();
    }

    private function subscribe(User $user, bool $all, array $topics = []): void
    {
        $user->roles->first()->notificationPreference()->create(['receive_all' => $all, 'topics' => $topics]);
    }

    private function directo(User $to, string $title = 'Aviso'): void
    {
        app(NotificationService::class)->notifyDirect(NotificationTopic::Sistema, 'x', $title, 'Mensaje', [$to], url: '/dashboard');
    }

    public function test_cada_quien_ve_y_marca_solo_las_suyas(): void
    {
        $ana = $this->userWith(['notificaciones.ver']);
        $beto = $this->userWith(['notificaciones.ver']);
        $this->directo($ana, 'Para Ana');
        $this->directo($beto, 'Para Beto');
        $deBeto = $beto->notifications()->first();

        $this->actingAs($ana)->getJson(route('notificaciones.recent'))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.title', 'Para Ana');

        // No puede marcar como leída una ajena (no existe para ella).
        $this->actingAs($ana)->patchJson(route('notificaciones.read', $deBeto->id))->assertNotFound();
        $this->assertNull($deBeto->fresh()->read_at);

        // Marcar todas solo afecta las propias.
        $this->actingAs($ana)->postJson(route('notificaciones.readAll'))->assertOk();
        $this->assertSame(0, $ana->unreadNotifications()->count());
        $this->assertSame(1, $beto->unreadNotifications()->count());

        // Sin "Ver todas" no entra a la consulta administrativa.
        $this->actingAs($ana)->get(route('notificaciones.all'))->assertForbidden();
    }

    public function test_ver_todas_consulta_sin_cambiar_lectura_ajena(): void
    {
        $auditor = $this->userWith(['notificaciones.ver_todas']);
        $ana = $this->userWith(['notificaciones.ver']);
        $this->directo($ana, 'Para Ana');

        $this->actingAs($auditor)->get(route('notificaciones.all'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Notificaciones/Todas')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.title', 'Para Ana')
                ->where('notifications.data.0.recipient.id', $ana->id)
                ->where('notifications.data.0.read_at', null));

        $this->assertSame(1, $ana->unreadNotifications()->count(), 'Consultar no marca como leída.');
        // "Ver todas" incluye ver las propias (alcance superior).
        $this->actingAs($auditor)->getJson(route('notificaciones.recent'))->assertOk();
    }

    public function test_un_tema_no_avisa_de_registros_fuera_de_alcance(): void
    {
        $creador = $this->userWith(['requisiciones.ver_propios']);
        $reqB = $this->requisicionEn($this->sucursalB, $creador);

        $propio = $this->userWith(['notificaciones.ver', 'requisiciones.ver_propios'], $this->sucursal);
        $global = $this->userWith(['notificaciones.ver', 'requisiciones.ver_todos'], $this->sucursal);
        $todas = $this->userWith(['notificaciones.ver_todas'], $this->sucursal);
        $this->subscribe($propio, false, ['requisiciones']);
        $this->subscribe($global, false, ['requisiciones']);
        $this->subscribe($todas, true);

        app(NotificationService::class)->notify(
            NotificationTopic::Requisiciones, 'requisicion.enviada', 'Nueva requisición', 'M', url: '/requisiciones',
            canSee: fn (User $u) => $u->can('view', $reqB),
        );

        $this->assertSame(0, $propio->notifications()->count(), 'Sin alcance sobre el registro no recibe el aviso.');
        $this->assertSame(1, $global->notifications()->count());
        $this->assertSame(1, $todas->notifications()->count(), '"Ver todas" + "Recibir todas" recibe eventos globales.');
    }

    public function test_sin_duplicados_con_varios_roles(): void
    {
        $user = $this->userWith(['notificaciones.ver', 'requisiciones.ver_todos']);
        $this->subscribe($user, true);
        $otro = $this->roleWith(['notificaciones.ver']);
        $otro->notificationPreference()->create(['receive_all' => false, 'topics' => ['pagos']]);
        $user->assignRole($otro);

        app(NotificationService::class)->notify(NotificationTopic::Pagos, 'x', 'T', 'M', direct: [$user]);

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_enlaces_de_notificaciones_son_internos(): void
    {
        $ana = $this->userWith(['notificaciones.ver']);
        app(NotificationService::class)->notifyDirect(NotificationTopic::Sistema, 'x', 'T', 'M', [$ana], url: 'https://malicioso.example/robo');

        $this->assertNull($ana->notifications()->first()->data['url']);
    }
}
