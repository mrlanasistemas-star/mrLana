<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Models\AppSetting;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración global: apariencia (colores y paleta de gráficas), URL de
 * descarga de la aplicación móvil y logo general. No guarda secretos.
 */
class ConfiguracionController extends Controller
{
    private const HEX = 'regex:/^#[0-9A-Fa-f]{6}$/';

    public function __construct(private NotificationService $notifications) {}

    public function edit(Request $request): Response
    {
        $row = AppSetting::current();

        return Inertia::render('Configuracion/Edit', [
            'settings' => [
                ...collect(AppSetting::COLOR_FIELDS)->mapWithKeys(fn ($f) => [$f => $row->{$f}])->all(),
                'chart_palette' => $row->chart_palette,
                'mobile_app_url' => $row->mobile_app_url,
                'logo_url' => $row->logo_path ? Storage::disk('public')->url($row->logo_path) : null,
            ],
            'resolved' => AppSetting::resolved(),
            'defaults' => AppSetting::DEFAULTS,
            'mobileAppFallback' => config('erp.mobile_app_url'),
            'canEdit' => $request->user()->can('configuracion.administrar'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'chart_palette' => ['nullable', 'array', 'min:3', 'max:8'],
            'chart_palette.*' => ['required', 'string', self::HEX],
            'mobile_app_url' => ['nullable', 'string', 'max:500', 'url:https,http'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
        foreach (AppSetting::COLOR_FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', self::HEX];
        }

        $data = $request->validate($rules, [
            '*.regex' => 'Usa un color HEX válido de 6 dígitos, por ejemplo #2563EB.',
            'chart_palette.*.regex' => 'Cada color de la paleta debe ser HEX de 6 dígitos, por ejemplo #2563EB.',
            'chart_palette.min' => 'La paleta de gráficas necesita al menos 3 colores.',
            'chart_palette.max' => 'La paleta de gráficas admite máximo 8 colores.',
            'mobile_app_url.url' => 'Escribe una URL válida que empiece con https:// o http://.',
            'mobile_app_url.max' => 'La URL no debe exceder 500 caracteres.',
            'logo.image' => 'El logo debe ser una imagen.',
            'logo.mimes' => 'El logo debe ser PNG, JPG o WebP.',
            'logo.max' => 'El logo no debe pesar más de 2 MB.',
        ]);

        $row = AppSetting::current();

        $payload = [];
        foreach (AppSetting::COLOR_FIELDS as $field) {
            $payload[$field] = isset($data[$field]) ? strtoupper($data[$field]) : null;
        }
        $payload['chart_palette'] = isset($data['chart_palette'])
            ? array_values(array_map('strtoupper', $data['chart_palette']))
            : null;
        $payload['mobile_app_url'] = $data['mobile_app_url'] ?? null;
        $payload['updated_by'] = $request->user()->id;

        $oldLogo = $row->logo_path;
        if ($request->hasFile('logo')) {
            $payload['logo_path'] = $request->file('logo')->storePublicly('branding', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $payload['logo_path'] = null;
        }

        $row->update($payload);

        if (array_key_exists('logo_path', $payload) && $oldLogo && $oldLogo !== $payload['logo_path']) {
            Storage::disk('public')->delete($oldLogo);
        }

        $this->notifyChange($request, 'Se actualizó la configuración del sistema.');

        return back()->with('success', 'Configuración guardada.');
    }

    /** Restablece la apariencia (colores y paleta). Conserva la URL de descarga y el logo. */
    public function reset(Request $request): RedirectResponse
    {
        $row = AppSetting::current();
        $row->update([
            ...array_fill_keys(AppSetting::COLOR_FIELDS, null),
            'chart_palette' => null,
            'updated_by' => $request->user()->id,
        ]);

        $this->notifyChange($request, 'Se restablecieron los colores predeterminados del sistema.');

        return back()->with('success', 'Se restablecieron los colores predeterminados.');
    }

    private function notifyChange(Request $request, string $message): void
    {
        $this->notifications->notify(
            topic: NotificationTopic::Sistema,
            event: 'sistema.configuracion_actualizada',
            title: 'Configuración actualizada',
            message: "{$request->user()->name}: {$message}",
            url: route('configuracion.edit', [], false),
            actor: $request->user(),
        );
    }
}
