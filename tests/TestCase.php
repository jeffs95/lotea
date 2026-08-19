<?php

namespace Tests;

use App\Support\Tenancy;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // El contexto de empresa es estático: si un test lo deja puesto,
        // el siguiente arrancaría creyendo que es otro cliente.
        Tenancy::olvidar();
    }

    protected function tearDown(): void
    {
        Tenancy::olvidar();

        parent::tearDown();
    }
}
