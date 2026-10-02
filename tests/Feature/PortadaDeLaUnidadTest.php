<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Elegir con qué foto se presenta un carro.
 *
 * La portada no es un detalle: es lo único que ve quien recorre el catálogo. Si
 * la primera que se subió es la del tablero sucio, ese carro no lo abre nadie
 * por buenas que sean las otras catorce.
 *
 * Antes solo se podía arrastrar hasta el principio, que con quince fotos y en
 * un teléfono es pelear con una cuadrícula que se desplaza sola.
 */
class PortadaDeLaUnidadTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        $this->usuario = User::factory()->create();
        $this->usuario->empresas()->attach($this->empresa);

        Tenancy::usar($this->empresa);

        Tenancy::comoEmpresa($this->empresa, function () {
            Role::findByName('dueno', 'web')->syncPermissions(Permission::all());
            $this->usuario->assignRole('dueno');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Un carro con tres fotos, en el orden en que se subieron. */
    protected function unidadConTresFotos(): Unidad
    {
        $unidad = Unidad::factory()->publicada()->create(['precio_lista' => 90000]);
        $unidad->clearMediaCollection('fotos');

        foreach (['tablero', 'frente', 'lateral'] as $nombre) {
            $unidad->addMedia(UploadedFile::fake()->image("{$nombre}.jpg", 600, 400))
                ->usingFileName("{$nombre}.jpg")
                ->toMediaCollection('fotos');
        }

        return $unidad->refresh();
    }

    protected function elegir(Unidad $unidad, int $mediaId): void
    {
        $this->actingAs($this->usuario);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->empresa);

        $pagina = new EditUnidad;
        $pagina->record = $unidad;
        $pagina->hacerPortada($mediaId);
    }

    public function test_la_foto_elegida_pasa_a_ser_la_primera(): void
    {
        $unidad = $this->unidadConTresFotos();

        $this->assertSame('tablero.jpg', $unidad->getFirstMedia('fotos')->file_name);

        $lateral = $unidad->getMedia('fotos')->firstWhere('file_name', 'lateral.jpg');
        $this->elegir($unidad, $lateral->getKey());

        $this->assertSame('lateral.jpg', $unidad->refresh()->getFirstMedia('fotos')->file_name);
    }

    /**
     * Y el resto conserva su orden relativo.
     *
     * Medialibrary renumera lo que se le manda, así que hay que mandarle todo:
     * con solo el elegido, el resto se queda con su orden viejo y la galería
     * sale barajada.
     */
    public function test_las_demas_no_se_barajan(): void
    {
        $unidad = $this->unidadConTresFotos();
        $lateral = $unidad->getMedia('fotos')->firstWhere('file_name', 'lateral.jpg');

        $this->elegir($unidad, $lateral->getKey());

        $this->assertSame(
            ['lateral.jpg', 'tablero.jpg', 'frente.jpg'],
            $unidad->refresh()->getMedia('fotos')->pluck('file_name')->all(),
        );
    }

    /** Lo que de verdad importa: que el cliente vea esa foto primero. */
    public function test_el_portal_muestra_la_elegida(): void
    {
        $unidad = $this->unidadConTresFotos();
        $frente = $unidad->getMedia('fotos')->firstWhere('file_name', 'frente.jpg');

        $this->elegir($unidad, $frente->getKey());

        $html = $this->get("/v/{$this->empresa->slug}/vehiculos")->assertSuccessful()->getContent();

        $primeraEnLaTarjeta = $unidad->refresh()->getFirstMediaUrl('fotos', 'web');

        $this->assertStringContainsString($primeraEnLaTarjeta, $html);
        $this->assertStringContainsString('frente', $primeraEnLaTarjeta, 'La tarjeta no usa la foto elegida.');
    }

    /** Elegir la que ya es portada no rompe ni reordena nada. */
    public function test_elegir_la_que_ya_es_portada_no_cambia_el_orden(): void
    {
        $unidad = $this->unidadConTresFotos();
        $antes = $unidad->getMedia('fotos')->pluck('file_name')->all();

        $this->elegir($unidad, $unidad->getFirstMedia('fotos')->getKey());

        $this->assertSame($antes, $unidad->refresh()->getMedia('fotos')->pluck('file_name')->all());
    }

    /** Un id que no es de esta unidad no mueve nada. */
    public function test_una_foto_ajena_no_hace_nada(): void
    {
        $unidad = $this->unidadConTresFotos();
        $antes = $unidad->getMedia('fotos')->pluck('file_name')->all();

        $otra = Unidad::factory()->create();
        $otra->addMedia(UploadedFile::fake()->image('ajena.jpg', 300, 200))->toMediaCollection('fotos');

        $this->elegir($unidad, $otra->getFirstMedia('fotos')->getKey());

        $this->assertSame($antes, $unidad->refresh()->getMedia('fotos')->pluck('file_name')->all());
    }
}
