<?php

namespace Tests;

use App\Support\MarcaDelCliente;
use App\Support\RutaDeArchivos;
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

        // Lo mismo con lo que se recuerda por request: RefreshDatabase
        // reinicia los ids y el segundo test heredaría el mapa del primero.
        MarcaDelCliente::olvidar();
        RutaDeArchivos::olvidar();
    }

    protected function tearDown(): void
    {
        Tenancy::olvidar();
        MarcaDelCliente::olvidar();
        RutaDeArchivos::olvidar();

        parent::tearDown();
    }
}
