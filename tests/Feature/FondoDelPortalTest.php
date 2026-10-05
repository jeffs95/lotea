<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Support\FondoDelPortal;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Lo que se ve detrás del titular del portal.
 *
 * La gracia es que no sea una foto de archivo que se queda igual tres años:
 * se turnan la portada del concesionario y los carros que tiene publicados,
 * así que el fondo se renueva solo cada vez que entra o sale una unidad.
 */
class FondoDelPortalTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    protected function unidadConFoto(string $nombre = 'frente.jpg'): Unidad
    {
        $unidad = Unidad::factory()->create();

        $unidad->addMedia(UploadedFile::fake()->image($nombre, 1400, 900))
            ->toMediaCollection('fotos');

        return $unidad->refresh();
    }

    /** @param iterable<int, Unidad> $unidades */
    protected function imagenes(iterable $unidades = [], ?int $cuantas = null): Collection
    {
        return FondoDelPortal::imagenes(
            $this->empresa->fresh(),
            Collection::make($unidades),
            $cuantas ?? FondoDelPortal::CUANTAS,
        );
    }

    /** La que el concesionario eligió manda: es la que armó para su portada. */
    public function test_la_portada_del_concesionario_va_primero(): void
    {
        $this->empresa->update(['portada_path' => 'marcas/patio.jpg']);

        $fondos = $this->imagenes([$this->unidadConFoto()]);

        $this->assertCount(2, $fondos);
        $this->assertSame($this->empresa->fresh()->portada_url, $fondos->first());
    }

    public function test_se_completa_con_los_carros_publicados(): void
    {
        $fondos = $this->imagenes([
            $this->unidadConFoto('uno.jpg'),
            $this->unidadConFoto('dos.jpg'),
        ]);

        $this->assertCount(2, $fondos);
        $this->assertSame($fondos->unique()->count(), $fondos->count());
    }

    /** Una unidad sin fotos no deja un hueco negro en la rotación. */
    public function test_una_unidad_sin_fotos_no_aporta_nada(): void
    {
        $fondos = $this->imagenes([
            Unidad::factory()->create(),
            $this->unidadConFoto(),
            Unidad::factory()->create(),
        ]);

        $this->assertCount(1, $fondos);
        $this->assertNotContains(null, $fondos->all());
    }

    /**
     * Cada una es una imagen grande que el navegador acaba bajando: con
     * veinte carros publicados, la portada le costaría los datos del mes a
     * quien entra desde el teléfono.
     */
    public function test_no_se_pasa_del_maximo(): void
    {
        $this->empresa->update(['portada_path' => 'marcas/patio.jpg']);

        $unidades = collect(range(1, 8))->map(fn (int $i) => $this->unidadConFoto("foto-{$i}.jpg"));

        $this->assertCount(3, $this->imagenes($unidades, 3));
        $this->assertCount(FondoDelPortal::CUANTAS, $this->imagenes($unidades));
    }

    public function test_sin_portada_se_arregla_con_los_carros(): void
    {
        $fondos = $this->imagenes([$this->unidadConFoto()]);

        $this->assertCount(1, $fondos);
        $this->assertNull($this->empresa->fresh()->portada_url);
    }

    /** Sin foto de nada, el portal cae al fondo de color y no se rompe. */
    public function test_sin_nada_no_devuelve_nada(): void
    {
        $this->assertTrue($this->imagenes()->isEmpty());
        $this->assertTrue(FondoDelPortal::imagenes(null, Collection::make())->isEmpty());
    }
}
