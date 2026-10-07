<?php

namespace Tests\Feature\Interfaz;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TraduccionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_idioma_de_la_aplicacion_es_espanol(): void
    {
        $this->assertSame('es', app()->getLocale());
    }

    public function test_todas_las_reglas_de_validacion_tienen_traduccion(): void
    {
        foreach (['validation', 'auth', 'passwords', 'pagination'] as $file) {
            $en = Arr::dot(require base_path("lang/en/{$file}.php"));
            $es = Arr::dot(require base_path("lang/es/{$file}.php"));

            // "custom" y "attributes" son propios de cada idioma.
            $faltantes = array_filter(
                array_diff(array_keys($en), array_keys($es)),
                fn (string $k) => ! str_starts_with($k, 'custom') && ! str_starts_with($k, 'attributes'),
            );
            $this->assertSame([], array_values($faltantes), "Faltan traducciones en lang/es/{$file}.php");
        }
    }

    public function test_mensajes_de_validacion_en_espanol_y_sin_claves(): void
    {
        $v = Validator::make(
            ['email' => 'no-es-correo', 'proveedor_id' => 999],
            ['nombre' => 'required', 'email' => 'email', 'proveedor_id' => 'exists:users,id'],
        );

        $mensajes = $v->errors()->all();
        $this->assertContains('Nombre es obligatorio.', $mensajes);
        $this->assertContains('Correo electrónico debe ser un correo electrónico válido.', $mensajes);
        $this->assertContains('El valor seleccionado en proveedor no existe o ya no está disponible.', $mensajes);
        foreach ($mensajes as $m) {
            $this->assertStringNotContainsString('validation.', $m);
        }
    }

    public function test_login_fallido_responde_en_espanol(): void
    {
        $this->post('/login', ['email' => 'nadie@example.com', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña son incorrectos.']);

        $this->post('/login', [])->assertSessionHasErrors(['email' => 'Correo electrónico es obligatorio.']);
    }
}
