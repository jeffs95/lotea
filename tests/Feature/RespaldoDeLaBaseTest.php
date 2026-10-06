<?php

namespace Tests\Feature;

use App\Support\AlmacenDeArchivos;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La copia de la base, fuera del servidor.
 *
 * Un respaldo en el mismo disco que la base no es un respaldo: es el mismo
 * archivo dos veces. Si el servidor se pierde se van los dos juntos, y con
 * ellos el inventario, las ventas y los documentos de los compradores.
 *
 * El volcado en sí lo hace pg_dump y no tiene sentido fingirlo aquí; lo que
 * se prueba es lo que decidimos nosotros: dónde se deja y qué se borra.
 */
class RespaldoDeLaBaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(AlmacenDeArchivos::discoPrivado());
    }

    /**
     * Un pg_dump de mentira que escribe ruido.
     *
     * Tiene que ser ruido y no ceros: gzip deja los ceros en nada y el
     * volcado saldría por debajo del mínimo, que es justo la comprobación
     * que no queremos disparar aquí.
     */
    protected function volcadoQueFunciona(): void
    {
        $falso = tempnam(sys_get_temp_dir(), 'pgdump');
        file_put_contents($falso, "#!/bin/sh\nhead -c 20000 /dev/urandom\n");
        chmod($falso, 0o755);

        config(['lotea.pg_dump' => $falso]);
    }

    protected function volcadoQueFalla(): void
    {
        config(['lotea.pg_dump' => '/no/existe/pg_dump']);
    }

    public function test_sube_el_volcado_al_cubo_privado(): void
    {
        $this->volcadoQueFunciona();

        $this->artisan('lotea:respaldar')->assertSuccessful();

        $disco = Storage::disk(AlmacenDeArchivos::discoPrivado());
        $subidos = $disco->files('respaldos');

        $this->assertCount(1, $subidos);
        $this->assertStringEndsWith('.sql.gz', $subidos[0]);
    }

    /** Se guardan los últimos, no los de hace ocho meses. */
    public function test_borra_los_respaldos_viejos_y_deja_los_recientes(): void
    {
        $this->volcadoQueFunciona();

        $disco = Storage::disk(AlmacenDeArchivos::discoPrivado());

        $disco->put('respaldos/viejo.sql.gz', 'x');
        touch($disco->path('respaldos/viejo.sql.gz'), now()->subDays(40)->getTimestamp());

        $disco->put('respaldos/reciente.sql.gz', 'x');

        $this->artisan('lotea:respaldar', ['--conservar' => 14])->assertSuccessful();

        $disco->assertMissing('respaldos/viejo.sql.gz');
        $disco->assertExists('respaldos/reciente.sql.gz');
    }

    /**
     * El que de verdad importa: si el volcado de hoy falló, los de antes se
     * quedan donde están.
     *
     * Borrar primero y respaldar después deja una noche mala sin ninguna
     * copia: la vieja borrada y la nueva que nunca se escribió.
     */
    public function test_si_el_volcado_falla_no_toca_los_que_ya_estaban(): void
    {
        $this->volcadoQueFalla();

        $disco = Storage::disk(AlmacenDeArchivos::discoPrivado());
        $disco->put('respaldos/de-ayer.sql.gz', 'x');
        touch($disco->path('respaldos/de-ayer.sql.gz'), now()->subYears(2)->getTimestamp());

        $this->artisan('lotea:respaldar', ['--conservar' => 1])->assertFailed();

        $disco->assertExists('respaldos/de-ayer.sql.gz');
    }

    /** Con --conservar=0 no se toca nada de lo que ya está. */
    public function test_sin_dias_de_retencion_no_borra_nada(): void
    {
        $this->volcadoQueFunciona();

        $disco = Storage::disk(AlmacenDeArchivos::discoPrivado());
        $disco->put('respaldos/antiguisimo.sql.gz', 'x');
        touch($disco->path('respaldos/antiguisimo.sql.gz'), now()->subYears(2)->getTimestamp());

        $this->artisan('lotea:respaldar', ['--conservar' => 0])->assertSuccessful();

        $disco->assertExists('respaldos/antiguisimo.sql.gz');
    }

    /** Contra una base que no es PostgreSQL se para en vez de inventar. */
    public function test_con_otra_base_avisa_y_no_hace_nada(): void
    {
        config(['database.default' => 'sqlite']);

        $this->artisan('lotea:respaldar')
            ->expectsOutputToContain('solo sabe respaldar PostgreSQL')
            ->assertFailed();
    }

    /**
     * Y va al disco privado, el que no sirve el CDN.
     *
     * En producción son cubos distintos; aquí los dos caen al mismo disco de
     * prueba, así que lo que se comprueba es que el comando pida el privado.
     */
    public function test_el_destino_es_el_disco_privado(): void
    {
        $this->volcadoQueFunciona();

        Storage::fake('otro_cubo');
        config(['lotea.discos.privado' => 'otro_cubo']);

        $this->artisan('lotea:respaldar')->assertSuccessful();

        $this->assertCount(1, Storage::disk('otro_cubo')->files('respaldos'));
    }
}
