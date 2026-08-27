<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Unidad;
use App\Support\AlmacenDeArchivos;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Reencontrar archivos que su registro ya no ubica.
 *
 * La ruta lleva dentro el id del registro, y ese id no es el mismo en dos bases
 * distintas. Un archivo subido en una máquina y la fila que lo describe en otra
 * no se encuentran nunca, aunque los dos existan. Pasó de verdad al migrar a R2
 * desde local y desde producción: la ficha quedaba sin fotos con los archivos
 * ahí, intactos, a una carpeta de distancia.
 */
class ReubicarArchivosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['almacen_viejo', 'almacen_nuevo'] as $disco) {
            config(["filesystems.disks.{$disco}" => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/'.$disco),
                'throw' => false,
            ]]);

            Storage::fake($disco);
        }

        config(['media-library.disk_name' => 'almacen_nuevo']);

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));
    }

    /** Deja un archivo en el disco viejo, con una ruta que ya no corresponde. */
    protected function unidadConFotoTraspapelada(): Unidad
    {
        $unidad = Unidad::factory()->create(['precio_lista' => 90000]);

        $media = $unidad->addMedia(UploadedFile::fake()->image('frente.jpg', 400, 300))
            ->toMediaCollection('fotos');

        $contenido = Storage::disk('almacen_nuevo')->get(AlmacenDeArchivos::rutaDe($media));

        // El mismo archivo, pero guardado con los ids de otra instalación.
        Storage::disk('almacen_viejo')->put('autos-del-valle/unidades/77/fotos/99/'.$media->file_name, $contenido);
        Storage::disk('almacen_nuevo')->delete(AlmacenDeArchivos::rutaDe($media));

        return $unidad->refresh();
    }

    public function test_encuentra_el_archivo_por_su_nombre_y_lo_deja_donde_se_lo_busca(): void
    {
        $unidad = $this->unidadConFotoTraspapelada();
        $media = $unidad->getFirstMedia('fotos');

        $this->assertFalse(
            Storage::disk('almacen_nuevo')->exists(AlmacenDeArchivos::rutaDe($media)),
            'El montaje del test no dejó el archivo traspapelado.',
        );

        config(['filesystems.disks.almacen_viejo.driver' => 'local']);

        $this->artisan('lotea:reubicar-archivos')->assertSuccessful();

        Storage::disk('almacen_nuevo')->assertExists(AlmacenDeArchivos::rutaDe($media->refresh()));
    }

    /** No borra el original: si algo sale mal, el archivo sigue donde estaba. */
    public function test_no_borra_el_archivo_de_donde_lo_saco(): void
    {
        $unidad = $this->unidadConFotoTraspapelada();
        $nombre = $unidad->getFirstMedia('fotos')->file_name;

        $this->artisan('lotea:reubicar-archivos')->assertSuccessful();

        Storage::disk('almacen_viejo')->assertExists('autos-del-valle/unidades/77/fotos/99/'.$nombre);
    }

    /** Con todo en su sitio no hace nada y lo dice. */
    public function test_cuando_no_falta_nada_no_toca_nada(): void
    {
        Unidad::factory()->create(['precio_lista' => 90000])
            ->addMedia(UploadedFile::fake()->image('ok.jpg', 300, 200))
            ->toMediaCollection('fotos');

        $this->artisan('lotea:reubicar-archivos')
            ->expectsOutputToContain('Todos los archivos están donde su registro dice')
            ->assertSuccessful();
    }

    /** Y si el archivo de verdad no está en ninguna parte, lo reporta. */
    public function test_avisa_de_los_que_no_aparecen_en_ningun_lado(): void
    {
        $unidad = Unidad::factory()->create(['precio_lista' => 90000]);
        $media = $unidad->addMedia(UploadedFile::fake()->image('perdida.jpg', 300, 200))
            ->toMediaCollection('fotos');

        Storage::disk('almacen_nuevo')->delete(AlmacenDeArchivos::rutaDe($media));

        $this->artisan('lotea:reubicar-archivos')
            ->expectsOutputToContain('no aparece en ningún disco')
            ->assertFailed();
    }

    public function test_fingir_no_mueve_nada(): void
    {
        $unidad = $this->unidadConFotoTraspapelada();
        $media = $unidad->getFirstMedia('fotos');

        $this->artisan('lotea:reubicar-archivos --fingir')->assertSuccessful();

        Storage::disk('almacen_nuevo')->assertMissing(AlmacenDeArchivos::rutaDe($media));
    }
}
