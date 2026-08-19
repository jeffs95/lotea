<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Http\Controllers\Portal\LeadController;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * El formulario del portal está abierto a internet.
 *
 * Sin protección, un script llena el CRM del cliente con cientos de prospectos
 * falsos en una tarde, y el vendedor deja de mirar la bandeja. Es lo primero
 * que rompería alguien con malas intenciones.
 */
class ProteccionDelFormularioPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Unidad $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('leads');

        $this->empresa = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Valle',
            'slug' => 'autos-del-valle',
        ]);

        Tenancy::usar($this->empresa);

        $this->unidad = Unidad::factory()->publicada()->create(['slug' => 'un-carro']);
    }

    protected function enviar(array $datos = [], array $encabezados = [])
    {
        return $this->withServerVariables($encabezados)->post("/v/{$this->empresa->slug}/contacto", [
            'nombre' => 'María López',
            'telefono' => '5555-1234',
            'mensaje' => 'Me interesa este carro.',
            'unidad_id' => $this->unidad->id,
            // Una persona tarda más que el mínimo en llenarlo.
            '_t' => now()->subSeconds(30)->timestamp,
            ...$datos,
        ]);
    }

    public function test_una_consulta_normal_pasa_sin_problema(): void
    {
        $this->enviar()->assertRedirect();

        Tenancy::usar($this->empresa);
        $this->assertSame(1, Lead::count());
    }

    // ---- Honeypot ----

    /** El campo trampa está oculto: si viene lleno, no fue una persona. */
    public function test_descarta_el_envio_si_llenaron_el_campo_trampa(): void
    {
        $this->enviar([LeadController::HONEYPOT => 'https://spam.example'])->assertRedirect();

        Tenancy::usar($this->empresa);
        $this->assertSame(0, Lead::count());
    }

    /** Se responde como si hubiera funcionado: un bot que ve error, reintenta. */
    public function test_al_bot_se_le_responde_como_si_hubiera_funcionado(): void
    {
        $this->enviar([LeadController::HONEYPOT => 'algo'])
            ->assertRedirect()
            ->assertSessionHas('lead_enviado', true);
    }

    public function test_el_campo_trampa_esta_en_el_formulario_pero_oculto(): void
    {
        $html = $this->get("/v/{$this->empresa->slug}/vehiculos/{$this->unidad->slug}")->getContent();

        $this->assertStringContainsString(LeadController::HONEYPOT, $html);
        $this->assertStringContainsString('left:-9999px', $html);
    }

    // ---- Tiempo de llenado ----

    /** Nadie llena un formulario en menos de tres segundos. */
    public function test_descarta_lo_enviado_demasiado_rapido(): void
    {
        $this->enviar(['_t' => now()->timestamp])->assertRedirect();

        Tenancy::usar($this->empresa);
        $this->assertSame(0, Lead::count());
    }

    // ---- Contenido ----

    public function test_descarta_mensajes_con_varios_enlaces(): void
    {
        $this->enviar(['mensaje' => 'Mirá https://uno.example y también https://dos.example'])->assertRedirect();

        Tenancy::usar($this->empresa);
        $this->assertSame(0, Lead::count());
    }

    /** Un solo enlace puede ser legítimo: alguien mandando su Facebook. */
    public function test_un_solo_enlace_si_pasa(): void
    {
        $this->enviar(['mensaje' => 'Escribime a https://facebook.com/miperfil'])->assertRedirect();

        Tenancy::usar($this->empresa);
        $this->assertSame(1, Lead::count());
    }

    public function test_rechaza_un_nombre_con_enlaces(): void
    {
        $this->enviar(['nombre' => 'Comprá seguidores https://spam.example'])
            ->assertSessionHasErrors('nombre');

        Tenancy::usar($this->empresa);
        $this->assertSame(0, Lead::count());
    }

    public function test_rechaza_un_telefono_demasiado_corto(): void
    {
        $this->enviar(['telefono' => '123'])->assertSessionHasErrors('telefono');
    }

    // ---- Límite por IP ----

    /** Cinco por minuto: una persona manda una o dos. */
    public function test_corta_al_sexto_envio_desde_la_misma_ip(): void
    {
        foreach (range(1, 5) as $numero) {
            $this->enviar(['telefono' => '5555-000'.$numero])->assertRedirect();
        }

        $this->enviar(['telefono' => '5555-9999'])->assertStatus(429);

        Tenancy::usar($this->empresa);
        $this->assertSame(5, Lead::count());
    }

    public function test_otra_ip_no_hereda_el_castigo(): void
    {
        foreach (range(1, 5) as $numero) {
            $this->enviar(['telefono' => '5555-000'.$numero]);
        }

        $this->enviar(['telefono' => '5555-7777'], ['REMOTE_ADDR' => '201.0.0.9'])->assertRedirect();

        Tenancy::usar($this->empresa);
        $this->assertSame(6, Lead::count());
    }
}
