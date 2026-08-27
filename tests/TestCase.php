<?php

namespace Tests;

use App\Support\MarcaDelCliente;
use App\Support\ModoSoporte;
use App\Support\RutaDeArchivos;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

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
        ModoSoporte::olvidar();

        /*
         * Y el disco, que hasta ahora era el de verdad.
         *
         * Los tests escribían en storage/app/public y nadie limpiaba: había casi
         * tres mil archivos acumulados de corridas viejas. Eso no solo ensucia,
         * hace que un test falle por lo que dejó otro —justo lo que pasó cuando
         * dos usaron el mismo id de unidad—, y ese fallo aparece o no según el
         * orden en que se sorteen, que es la peor clase de test frágil.
         */
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Tenancy::olvidar();
        MarcaDelCliente::olvidar();
        RutaDeArchivos::olvidar();
        ModoSoporte::olvidar();

        parent::tearDown();
    }
}
