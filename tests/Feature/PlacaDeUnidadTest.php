<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Enums\TipoPlaca;
use App\Enums\TipoVehiculo;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La placa identifica al vehículo ya nacionalizado, y su letra dice a qué uso
 * está inscrito, que cambia el precio de reventa.
 *
 * Va nullable a propósito: una unidad recién traída de subasta no tiene placa
 * guatemalteca y eso no es un error, es su estado normal.
 */
class PlacaDeUnidadTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Valle',
            'slug' => 'autos-del-valle',
        ]);

        Tenancy::usar($this->empresa);
    }

    public function test_una_unidad_de_subasta_puede_no_tener_placa(): void
    {
        $unidad = Unidad::factory()->create(['estado' => EstadoUnidad::Embarcada]);

        $this->assertNull($unidad->placa);
        $this->assertFalse($unidad->tienePlaca());
    }

    public function test_el_tipo_se_deduce_de_la_letra_inicial(): void
    {
        $casos = [
            'P123ABC' => TipoPlaca::Particular,
            'C456DEF' => TipoPlaca::Comercial,
            'M789GHI' => TipoPlaca::Motocicleta,
            'A012JKL' => TipoPlaca::Alquiler,
            'U345MNO' => TipoPlaca::UsoAgricola,
            'O678PQR' => TipoPlaca::Oficial,
        ];

        foreach ($casos as $placa => $esperado) {
            $this->assertSame($esperado, TipoPlaca::desdeLaPlaca($placa), "Falló con {$placa}");
        }
    }

    /** CD son dos letras: hay que leerlas antes que la primera sola. */
    public function test_reconoce_la_placa_diplomatica_de_dos_letras(): void
    {
        $this->assertSame(TipoPlaca::Diplomatica, TipoPlaca::desdeLaPlaca('CD0012'));
    }

    public function test_acepta_la_placa_escrita_con_guiones_o_en_minusculas(): void
    {
        $this->assertSame(TipoPlaca::Motocicleta, TipoPlaca::desdeLaPlaca('m-456-xyz'));
        $this->assertSame(TipoPlaca::Particular, TipoPlaca::desdeLaPlaca(' p 123 abc '));
    }

    public function test_una_placa_que_no_empieza_con_letra_conocida_no_se_fuerza(): void
    {
        $this->assertNull(TipoPlaca::desdeLaPlaca('999XXX'));
        $this->assertNull(TipoPlaca::desdeLaPlaca(null));
    }

    public function test_sugiere_el_tipo_segun_la_clase_de_vehiculo(): void
    {
        $this->assertSame(TipoPlaca::Motocicleta, TipoPlaca::sugeridaPara(TipoVehiculo::Motocicleta));
        $this->assertSame(TipoPlaca::Comercial, TipoPlaca::sugeridaPara(TipoVehiculo::Camion));
        $this->assertSame(TipoPlaca::Particular, TipoPlaca::sugeridaPara(TipoVehiculo::Automovil));
    }

    public function test_la_placa_queda_guardada_con_su_tipo(): void
    {
        $unidad = Unidad::factory()->create([
            'placa' => 'P123ABC',
            'tipo_placa' => TipoPlaca::Particular,
        ]);

        $this->assertTrue($unidad->tienePlaca());
        $this->assertSame(TipoPlaca::Particular, $unidad->fresh()->tipo_placa);
    }

    /** El dato del dueño anterior no tiene por qué salir en el escaparate. */
    public function test_la_placa_no_se_publica_en_el_portal(): void
    {
        $unidad = Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'slug' => 'con-placa',
            'placa' => 'P987ZZZ',
        ]);

        $this->get("/v/{$this->empresa->slug}/vehiculos/{$unidad->slug}")
            ->assertOk()
            ->assertDontSee('P987ZZZ');
    }
}
