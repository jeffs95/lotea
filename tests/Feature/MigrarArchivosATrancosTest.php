<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mover a su sitio lo que ya estaba subido.
 *
 * Cambiar el disco solo dice dónde se guarda de ahora en adelante; lo viejo se
 * queda donde estaba y el sistema lo busca donde no está. Lo que se cuida aquí
 * es que el reparto respete los dos cubos: mandar todo al mismo dejaría los
 * títulos de vehículo donde el CDN los sirve a cualquiera.
 */
class MigrarArchivosATrancosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Declarados y además fingidos: con solo fingirlos, medialibrary no
        // los encuentra al ir a guardar.
        foreach (['viejo', 'nuevo_publico', 'nuevo_privado'] as $disco) {
            config(["filesystems.disks.{$disco}" => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/'.$disco),
                'throw' => false,
            ]]);

            Storage::fake($disco);
        }

        config([
            'media-library.disk_name' => 'viejo',
            'lotea.discos.publico' => null,
            'lotea.discos.privado' => null,
        ]);

        Tenancy::usar((new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']));
    }

    protected function cargarUnidadConTodo(): Unidad
    {
        $unidad = Unidad::factory()->create(['precio_lista' => 90000]);

        $unidad->addMedia(UploadedFile::fake()->image('frente.jpg', 400, 300))->toMediaCollection('fotos');
        $unidad->addMedia(UploadedFile::fake()->image('subasta.jpg', 400, 300))->toMediaCollection('fotos_subasta');
        $unidad->addMedia(UploadedFile::fake()->create('titulo.pdf', 8, 'application/pdf'))->toMediaCollection('documentos');

        return $unidad->refresh();
    }

    /** Cada colección a su cubo, no todas al mismo. */
    public function test_reparte_cada_archivo_al_cubo_que_le_toca(): void
    {
        $unidad = $this->cargarUnidadConTodo();

        config([
            'lotea.discos.publico' => 'nuevo_publico',
            'lotea.discos.privado' => 'nuevo_privado',
        ]);

        $this->artisan('lotea:migrar-archivos')->assertSuccessful();

        $unidad->refresh();

        $this->assertSame('nuevo_publico', $unidad->getFirstMedia('fotos')->disk);
        $this->assertSame('nuevo_privado', $unidad->getFirstMedia('documentos')->disk,
            'Un título de vehículo terminó donde el CDN lo puede servir.');
        $this->assertSame('nuevo_privado', $unidad->getFirstMedia('fotos_subasta')->disk);
    }

    /** Y los archivos llegan de verdad, no solo el registro. */
    public function test_los_bytes_llegan_al_cubo_nuevo(): void
    {
        $unidad = $this->cargarUnidadConTodo();

        config([
            'lotea.discos.publico' => 'nuevo_publico',
            'lotea.discos.privado' => 'nuevo_privado',
        ]);

        $this->artisan('lotea:migrar-archivos')->assertSuccessful();

        $foto = $unidad->refresh()->getFirstMedia('fotos');

        Storage::disk('nuevo_publico')->assertExists($foto->getPathRelativeToRoot());
    }

    /** Correrlo dos veces no hace nada la segunda: se puede reintentar. */
    public function test_correrlo_de_nuevo_no_mueve_lo_que_ya_esta_en_su_sitio(): void
    {
        $this->cargarUnidadConTodo();

        config([
            'lotea.discos.publico' => 'nuevo_publico',
            'lotea.discos.privado' => 'nuevo_privado',
        ]);

        $this->artisan('lotea:migrar-archivos')->assertSuccessful();

        $this->artisan('lotea:migrar-archivos')
            ->expectsOutputToContain('No hay nada que mover')
            ->assertSuccessful();
    }

    /** Con --fingir se puede ver el plan sin tocar nada. */
    public function test_fingir_no_mueve_nada(): void
    {
        $unidad = $this->cargarUnidadConTodo();

        config([
            'lotea.discos.publico' => 'nuevo_publico',
            'lotea.discos.privado' => 'nuevo_privado',
        ]);

        $this->artisan('lotea:migrar-archivos --fingir')->assertSuccessful();

        $this->assertSame('viejo', $unidad->refresh()->getFirstMedia('fotos')->disk);
    }
}
