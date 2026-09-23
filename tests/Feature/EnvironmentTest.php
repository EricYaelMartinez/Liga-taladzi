<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvironmentTest extends TestCase
{
    public function test_home_page_is_available(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_framework_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_application_uses_spanish_and_mexico_city_timezone(): void
    {
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('America/Mexico_City', config('app.timezone'));
    }
}

