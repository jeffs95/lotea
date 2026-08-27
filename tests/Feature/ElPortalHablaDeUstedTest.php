<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El portal público se dirige al visitante de usted.
 *
 * El panel interno habla de vos, porque ahí se habla con quien trabaja en el
 * patio todos los días. El portal es otra cosa: lo lee alguien que está por
 * gastarse cien mil quetzales en un carro y todavía no conoce al vendedor.
 *
 * Y como el resto del sistema está escrito en voseo, es facilísimo que se
 * cuele un «escribinos» al agregar una sección. De ahí este test.
 */
class ElPortalHablaDeUstedTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    /** Formas que no van en el portal, con su reemplazo esperado. */
    protected const NO_VAN = [
        'Buscá', 'Mirá', 'Vení', 'Andá', 'Fijate', 'Chequeá', 'Elegí', 'Probá',
        'Calculá', 'Dejanos', 'Escribinos', 'Llamanos', 'Contactanos', 'Seguinos',
        'Encontranos', 'Mandá', 'Pedí', 'Contá', 'Poné', 'Tené', 'Seguí',
        'querés', 'podés', 'tenés', 'preferís', 'sabés', 'necesitás', 'quieras',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Autos del Valle',
            'telefono' => '50000000',
            'whatsapp' => '5000-0000',
            'email' => 'ventas@ejemplo.gt',
        ]);

        Tenancy::usar($this->empresa);

        Sucursal::first()?->update([
            'direccion' => 'Zona 1, Guatemala',
            'horario' => 'Lun a Vie 8:00–18:00',
            'mostrar_en_portal' => true,
        ]);
    }

    /** @return array<int, array<int, string>> */
    public static function paginasDelPortal(): array
    {
        return [['/'], ['/vehiculos'], ['/contacto']];
    }

    #[DataProvider('paginasDelPortal')]
    public function test_la_pagina_no_trata_de_vos_al_visitante(string $ruta): void
    {
        Unidad::factory()->count(2)->publicada()->create();

        $html = $this->get("/v/{$this->empresa->slug}{$ruta}")
            ->assertSuccessful()
            ->getContent();

        $texto = $this->soloElTextoVisible($html);

        $encontradas = $this->formasQueAparecen($texto);

        $this->assertSame(
            [],
            $encontradas,
            "En «{$ruta}» el portal trata de vos al visitante: ".implode(', ', $encontradas)
            .'. El portal va de usted; el panel interno es el que habla de vos.',
        );
    }

    /** La ficha de un vehículo, que es donde la gente decide. */
    public function test_la_ficha_de_un_vehiculo_tampoco(): void
    {
        $unidad = Unidad::factory()->publicada()->create();

        $html = $this->get("/v/{$this->empresa->slug}/vehiculos/{$unidad->slug}")
            ->assertSuccessful()
            ->getContent();

        $texto = $this->soloElTextoVisible($html);

        $this->assertSame([], $this->formasQueAparecen($texto), 'La ficha trata de vos al visitante.');
    }

    /**
     * Las formas que aparecen como palabra entera.
     *
     * Con límites de palabra y no por coincidencia suelta: «Contá» vive dentro
     * de «Contáctenos», que es justo el reemplazo correcto, y sin esto el test
     * se quejaba de la solución.
     *
     * @return array<int, string>
     */
    protected function formasQueAparecen(string $texto): array
    {
        return array_values(array_filter(
            self::NO_VAN,
            fn (string $forma) => preg_match('/(?<![\p{L}])'.preg_quote($forma, '/').'(?![\p{L}])/u', $texto) === 1,
        ));
    }

    /**
     * Todo lo que el visitante lee, atributos incluidos.
     *
     * Quitar las etiquetas no alcanza: el texto de ayuda de un campo vive en un
     * atributo, y ese es exactamente el que se veía mal en el buscador de la
     * portada. Un guardián que no lo mire no habría cazado el caso que lo
     * motivó —comprobado: no lo cazaba—.
     */
    protected function soloElTextoVisible(string $html): string
    {
        $sinScripts = preg_replace('#<(script|style)[^>]*>.*?</\1>#s', ' ', $html) ?? $html;

        // Los atributos que el visitante lee, antes de perder las etiquetas.
        preg_match_all(
            '/\b(?:placeholder|alt|title|aria-label|value)="([^"]*)"/i',
            $sinScripts,
            $atributos,
        );

        return html_entity_decode(
            strip_tags($sinScripts).' '.implode(' ', $atributos[1]),
            ENT_QUOTES | ENT_HTML5,
        );
    }
}
