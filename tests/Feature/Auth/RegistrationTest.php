<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El registro público está desactivado por defecto (ERP_ALLOW_REGISTRATION):
 * las cuentas se crean desde el módulo Usuarios.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_disabled_by_default(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
