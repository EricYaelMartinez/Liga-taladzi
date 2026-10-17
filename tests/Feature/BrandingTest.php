<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_directory_receives_the_fixed_proliga_brand(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/LeagueDirectory')
                ->where('brand.name', 'ProLiga')
                ->where('brand.tagline', 'Administra tu liga en un solo espacio')
                ->where('brand.logoUrl', '/images/brand/proliga-logo.png')
                ->where('brand.colors.navy', '#1B2A4A')
                ->where('brand.colors.green', '#2E7D32')
                ->where('brand.colors.gold', '#C5A059'));
    }

    public function test_root_document_exposes_brand_metadata_and_assets(): void
    {
        $response = $this->get('/iniciar-sesion');

        $response
            ->assertOk()
            ->assertSee('/favicon.ico', false)
            ->assertSee('/site.webmanifest', false)
            ->assertSee('Administra tu liga en un solo espacio', false);

        $this->assertFileExists(public_path('images/brand/proliga-logo.png'));
        $this->assertFileExists(public_path('favicon.ico'));
    }
}
