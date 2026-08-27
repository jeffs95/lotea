<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Lead;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lo que se acepta en el formulario del portal.
 *
 * Antes entraba cualquier cosa: bastaban ocho caracteres para el teléfono, así
 * que «aaaaaaaa» quedaba guardado como número y el vendedor se enteraba al
 * intentar llamar. Un prospecto sin teléfono de verdad no es un prospecto: es
 * trabajo perdido y una expectativa que nadie va a cumplir.
 *
 * Lo que no se hace es pasarse de estricto. Aquí buena parte de los compradores
 * llaman desde Estados Unidos para comprarle el carro a su familia, y escriben
 * el número como se les da la gana.
 */
class ValidacionDelFormularioPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar(['nombre' => 'Autos del Valle']);
        Tenancy::usar($this->empresa);
    }

    /** @param array<string, mixed> $cambios */
    protected function enviar(array $cambios = [])
    {
        return $this->post("/v/{$this->empresa->slug}/contacto", [
            'nombre' => 'María Gómez',
            'telefono' => '5555-1234',
            'email' => 'maria@ejemplo.gt',
            'mensaje' => 'Me interesa el Sentra.',
            // El guardián de bots exige unos segundos entre abrir y enviar.
            '_t' => now()->subMinute()->timestamp,
            ...$cambios,
        ]);
    }

    // ── Lo que sí se acepta ─────────────────────────────────────────────────

    public function test_un_prospecto_normal_entra(): void
    {
        $this->enviar()->assertSessionHasNoErrors();

        $this->assertSame(1, Lead::count());
    }

    /** @return array<string, array<int, string>> */
    public static function telefonosQueLaGenteEscribe(): array
    {
        return [
            'con guion' => ['5555-1234'],
            'con espacios' => ['5555 1234'],
            'seguido' => ['55551234'],
            'con código' => ['+502 5555 1234'],
            'código sin más' => ['50255551234'],
            'un fijo de la capital' => ['2222-3344'],
            'un fijo del interior' => ['7761-2233'],
            'desde Estados Unidos' => ['+1 305 555 0199'],
        ];
    }

    #[DataProvider('telefonosQueLaGenteEscribe')]
    public function test_acepta_los_formatos_de_verdad(string $telefono): void
    {
        $this->enviar(['telefono' => $telefono])->assertSessionHasNoErrors();

        $this->assertSame(1, Lead::count(), "Rechazó «{$telefono}», que es un número real.");
    }

    /** Se guarda limpio: el vendedor va a marcarlo desde el teléfono. */
    public function test_el_numero_queda_listo_para_marcar(): void
    {
        $this->enviar(['telefono' => '(502) 5555-1234']);

        $this->assertSame('50255551234', Lead::first()->telefono);
    }

    // ── Lo que no ───────────────────────────────────────────────────────────

    /** @return array<string, array<int, string>> */
    public static function telefonosQueNoSirven(): array
    {
        return [
            'letras' => ['aaaaaaaa'],
            'muy corto' => ['5555'],
            'un solo dígito repetido, corto' => ['111'],
            'demasiados dígitos' => ['5555123456789012345'],
            'símbolos' => ['--------'],
            'un prefijo que no existe aquí' => ['9555-1234'],
        ];
    }

    #[DataProvider('telefonosQueNoSirven')]
    public function test_rechaza_lo_que_no_es_un_telefono(string $telefono): void
    {
        $this->enviar(['telefono' => $telefono])->assertSessionHasErrors('telefono');

        $this->assertSame(0, Lead::count(), "Aceptó «{$telefono}» como teléfono.");
    }

    /** @return array<string, array<int, string>> */
    public static function nombresQueNoSonNombres(): array
    {
        return [
            'números' => ['123456'],
            'puntos' => ['....'],
            'una letra' => ['a'],
            'la misma letra' => ['aaaaaa'],
            'vacío con espacios' => ['   '],
        ];
    }

    #[DataProvider('nombresQueNoSonNombres')]
    public function test_rechaza_lo_que_no_es_un_nombre(string $nombre): void
    {
        $this->enviar(['nombre' => $nombre])->assertSessionHasErrors('nombre');

        $this->assertSame(0, Lead::count(), "Aceptó «{$nombre}» como nombre.");
    }

    /** @return array<string, array<int, string>> */
    public static function correosQueNoExisten(): array
    {
        return [
            'sin arroba' => ['mariaejemplo.gt'],
            'sin dominio' => ['maria@'],
            'sin punto' => ['maria@ejemplo'],
            'con espacio' => ['maria @ejemplo.gt'],
            'solo arroba' => ['@'],
        ];
    }

    #[DataProvider('correosQueNoExisten')]
    public function test_rechaza_un_correo_que_no_existe(string $email): void
    {
        $this->enviar(['email' => $email])->assertSessionHasErrors('email');

        $this->assertSame(0, Lead::count(), "Aceptó «{$email}» como correo.");
    }

    /** El correo sigue siendo opcional: no todos usan. */
    public function test_el_correo_se_puede_dejar_vacio(): void
    {
        $this->enviar(['email' => ''])->assertSessionHasNoErrors();

        $this->assertSame(1, Lead::count());
        $this->assertNull(Lead::first()->email);
    }

    /** Un nombre con acentos, guion o apellido compuesto no es sospechoso. */
    public function test_acepta_nombres_de_aqui(): void
    {
        foreach (['José Pérez', 'María de los Ángeles', 'Ana Sofía López-Ruiz', 'Ixchel Bʼalam'] as $nombre) {
            Lead::query()->delete();

            $this->enviar(['nombre' => $nombre])->assertSessionHasNoErrors();

            $this->assertSame(1, Lead::count(), "Rechazó «{$nombre}».");
        }
    }
}
