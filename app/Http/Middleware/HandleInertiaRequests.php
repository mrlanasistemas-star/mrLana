<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Los permisos se envían solo para construir la interfaz (menú, botones);
     * el backend sigue siendo la fuente de verdad de la autorización.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'empleado_id' => $user->empleado_id,
                    'activo' => (bool) $user->activo,
                    'email_verified_at' => $user->email_verified_at,
                    'roles' => $user->getRoleNames()->values()->all(),
                ] : null,
                'permissions' => $user ? $user->permissionNames() : [],
                // Alcance efectivo por módulo (none|own|sucursal|corporativo|global).
                // La interfaz no debe comprobar permisos de alcance sueltos: un rol
                // guarda solo su nivel más alto y este mapa ya lo resuelve.
                'scopes' => $user ? collect(PermissionCatalog::scopedModules())
                    ->mapWithKeys(fn (string $m) => [$m => AccessScope::for($user, $m)->key()])
                    ->all() : [],
            ],
            'notifications' => fn () => $user && AccessScope::for($user, 'notificaciones')->allows()
                ? ['unread_count' => $user->unreadNotifications()->count()]
                : null,
            'appSettings' => fn () => AppSetting::resolved(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'folio_created_id' => fn () => $request->session()->get('folio_created_id'),
                'folio_updated_id' => fn () => $request->session()->get('folio_updated_id'),
            ],
        ];
    }
}
