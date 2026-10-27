<?php

namespace Tests\Feature\Seo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_login_es_indexable_y_lleva_la_marca(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('<html lang="es">', false)
            ->assertSee('<title inertia>Iniciar sesión · ERP Mr. Lana</title>', false)
            ->assertSee('<meta name="description" content="ERP Mr. Lana:', false)
            ->assertSee('<meta name="robots" content="index, follow', false)
            ->assertSee('<link rel="canonical" href="'.route('login').'">', false)
            ->assertSee('<meta property="og:site_name" content="ERP Mr. Lana">', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('"alternateName":["Mr Lana ERP","MR LANA","mrLana"', false)
            ->assertSee('"@type":"SoftwareApplication"', false);
    }

    public function test_robots_txt_apunta_al_sitemap_y_bloquea_rutas_internas(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Allow: /login')
            ->assertSee('Disallow: /dashboard')
            ->assertSee('Disallow: /requisiciones')
            ->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    public function test_sitemap_lista_solo_urls_publicas(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.route('login').'</loc>', false)
            ->assertDontSee('/dashboard');
    }

    public function test_pantallas_autenticadas_llevan_noindex(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('application/ld+json', false);
    }

    public function test_otras_pantallas_publicas_no_se_indexan(): void
    {
        $this->get('/forgot-password')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
