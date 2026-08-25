<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Models\Empresa;
use App\Support\AlmacenDeArchivos;
use App\Support\Tenancy;
use App\Support\VariantesDeLogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Las versiones del logo que el sistema saca del archivo del cliente.
 *
 * Un concesionario entrega el logo que usa en Facebook, con su fondo pegado. Ese
 * mismo archivo tiene que servir para la cabecera clara del portal, para el
 * panel en modo oscuro, para el centro de un QR y para la pestaña del navegador.
 * Sin variantes, o se ve un recuadro negro sobre fondo blanco, o el texto blanco
 * desaparece sobre papel.
 */
class VariantesDeLogoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->empresa = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Importadora Gómez',
            'slug' => 'importadora-gomez',
        ]);

        Tenancy::usar($this->empresa);
    }

    /**
     * Un logo de juguete con la misma forma que los de verdad: un símbolo
     * arriba, una franja vacía y el nombre abajo, todo sobre negro.
     */
    protected function logoDePrueba(): string
    {
        $ancho = 600;
        $alto = 600;

        $im = imagecreatetruecolor($ancho, $alto);
        imagefill($im, 0, 0, imagecolorallocate($im, 0, 0, 0));

        $plata = imagecolorallocate($im, 200, 200, 205);
        $rojo = imagecolorallocate($im, 226, 32, 32);
        $blanco = imagecolorallocate($im, 255, 255, 255);

        // El símbolo: una franja plateada con un detalle rojo.
        imagefilledrectangle($im, 120, 150, 480, 200, $plata);
        imagefilledrectangle($im, 200, 205, 260, 220, $rojo);

        // Y el nombre, bien separado, para que se pueda partir en dos piezas.
        imagefilledrectangle($im, 100, 380, 500, 430, $blanco);

        $ruta = sys_get_temp_dir().'/logo-de-prueba-'.uniqid().'.png';
        imagepng($im, $ruta);
        imagedestroy($im);

        return $ruta;
    }

    public function test_saca_el_isologo_el_isotipo_y_el_logotipo(): void
    {
        $variantes = VariantesDeLogo::desde($this->logoDePrueba());

        foreach (['isologo', 'isotipo', 'logotipo', 'isotipo-cuadrado'] as $pieza) {
            $this->assertArrayHasKey($pieza, $variantes, "Falta la variante «{$pieza}».");
            $this->assertArrayHasKey($pieza.'-claro', $variantes);
        }
    }

    /** El fondo negro se va: si no, sobre el portal claro se ve un recuadro. */
    public function test_el_fondo_queda_transparente(): void
    {
        $variantes = VariantesDeLogo::desde($this->logoDePrueba());

        $esquina = imagecolorat($variantes['isologo'], 0, 0);

        $this->assertSame(127, ($esquina >> 24) & 0x7F, 'La esquina debería ser transparente.');
    }

    /**
     * Lo blanco y lo gris se oscurecen para que se lean sobre papel; el rojo de
     * la marca se queda como está. Invertir todo daría un logo cian.
     */
    public function test_la_version_clara_oscurece_el_blanco_y_respeta_el_color(): void
    {
        $variantes = VariantesDeLogo::desde($this->logoDePrueba());

        $original = $this->colorMasFrecuente($variantes['logotipo']);
        $claro = $this->colorMasFrecuente($variantes['logotipo-claro']);

        // El nombre era blanco y ahora es oscuro.
        $this->assertGreaterThan(200, max($original));
        $this->assertLessThan(130, max($claro));

        // Y el rojo del símbolo sigue siendo rojo.
        $this->assertTrue(
            $this->tieneRojo($variantes['isotipo-claro']),
            'La versión clara perdió el color de la marca.',
        );
    }

    public function test_el_isotipo_es_mas_bajo_que_el_isologo(): void
    {
        $variantes = VariantesDeLogo::desde($this->logoDePrueba());

        // El símbolo solo tiene que ser una parte del conjunto, no el conjunto.
        $this->assertLessThan(
            imagesy($variantes['isologo']),
            imagesy($variantes['isotipo']),
        );
    }

    /** En un favicon, una pieza apaisada quedaría de dos píxeles de alto. */
    public function test_la_version_cuadrada_es_cuadrada(): void
    {
        $variantes = VariantesDeLogo::desde($this->logoDePrueba());

        $this->assertSame(
            imagesx($variantes['isotipo-cuadrado']),
            imagesy($variantes['isotipo-cuadrado']),
        );
    }

    public function test_el_comando_guarda_las_variantes_y_las_asigna(): void
    {
        $ruta = 'marcas/logo.png';
        Storage::disk(AlmacenDeArchivos::nombreDelDisco())->put($ruta, file_get_contents($this->logoDePrueba()));

        $this->empresa->update(['logo_path' => $ruta]);

        $this->artisan('lotea:variantes-logo', ['empresa' => $this->empresa->slug])
            ->assertSuccessful();

        $empresa = $this->empresa->fresh();

        $this->assertNotNull($empresa->isotipo_path);
        $this->assertNotNull($empresa->logo_oscuro_path);
        $this->assertNotNull($empresa->favicon_path);

        Storage::disk(AlmacenDeArchivos::nombreDelDisco())->assertExists($empresa->isotipo_path);

        // El símbolo y el favicon van sobre blanco: tienen que ser las versiones
        // oscuras, o se ven desvaídos en el QR y en la pestaña.
        $this->assertStringContainsString('claro', $empresa->isotipo_path);
        $this->assertStringContainsString('claro', $empresa->favicon_path);
    }

    /** Lo que el cliente eligió a mano no se pisa sin pedirlo. */
    public function test_no_sobreescribe_lo_que_el_cliente_ya_puso(): void
    {
        $ruta = 'marcas/logo.png';
        Storage::disk(AlmacenDeArchivos::nombreDelDisco())->put($ruta, file_get_contents($this->logoDePrueba()));

        $this->empresa->update([
            'logo_path' => $ruta,
            'favicon_path' => 'marcas/el-que-subio-el-cliente.png',
        ]);

        $this->artisan('lotea:variantes-logo', ['empresa' => $this->empresa->slug])->assertSuccessful();

        $this->assertSame('marcas/el-que-subio-el-cliente.png', $this->empresa->fresh()->favicon_path);
    }

    public function test_con_forzar_si_lo_reemplaza(): void
    {
        $ruta = 'marcas/logo.png';
        Storage::disk(AlmacenDeArchivos::nombreDelDisco())->put($ruta, file_get_contents($this->logoDePrueba()));

        $this->empresa->update([
            'logo_path' => $ruta,
            'favicon_path' => 'marcas/el-viejo.png',
        ]);

        $this->artisan('lotea:variantes-logo', ['empresa' => $this->empresa->slug, '--forzar' => true])
            ->assertSuccessful();

        $this->assertNotSame('marcas/el-viejo.png', $this->empresa->fresh()->favicon_path);
    }

    public function test_un_concesionario_sin_logo_no_da_error(): void
    {
        $this->artisan('lotea:variantes-logo', ['empresa' => $this->empresa->slug])
            ->expectsOutputToContain('Ningún concesionario con logo')
            ->assertSuccessful();
    }

    // ── Dónde se usa cada variante ──────────────────────────────────────────

    /**
     * Lo que va sobre fondo claro tiene que pedir la variante clara.
     *
     * Antes no existía ese campo y el portal pintaba el archivo original del
     * cliente, con su fondo negro pegado: un recuadro oscuro en medio de una
     * página blanca.
     */
    public function test_el_logo_para_fondo_claro_usa_la_variante_clara(): void
    {
        $this->empresa->update([
            'logo_path' => 'marcas/original.png',
            'logo_claro_path' => 'marcas/variantes/isologo-claro.png',
        ]);

        Storage::disk(AlmacenDeArchivos::nombreDelDisco())->put('marcas/original.png', 'x');
        Storage::disk(AlmacenDeArchivos::nombreDelDisco())->put('marcas/variantes/isologo-claro.png', 'x');

        // Sin atarse a cómo se sirve: lo que importa es que apunte a la clara.
        $this->assertTrue(
            $this->apuntaA($this->empresa->fresh()->logo_url, 'isologo-claro', 'logo'),
            'El logo de fondo claro no está usando la variante clara.',
        );
    }

    /** Sin variante clara se usa el original: mejor eso que nada. */
    public function test_sin_variante_clara_cae_al_original(): void
    {
        Storage::disk(AlmacenDeArchivos::nombreDelDisco())->put('marcas/original.png', 'x');

        $this->empresa->update(['logo_path' => 'marcas/original.png', 'logo_claro_path' => null]);

        $this->assertNotNull($this->empresa->fresh()->logo_url);
    }

    /**
     * La cabecera de la etiqueta va pintada con el color de la marca, que puede
     * ser oscuro o claro. El logo se elige según eso.
     */
    public function test_elige_el_logo_segun_lo_oscuro_del_fondo(): void
    {
        foreach (['logo_path', 'logo_claro_path', 'logo_oscuro_path'] as $campo) {
            Storage::disk(AlmacenDeArchivos::nombreDelDisco())->put("marcas/{$campo}.png", 'x');
        }

        $this->empresa->update([
            'logo_path' => 'marcas/logo_path.png',
            'logo_claro_path' => 'marcas/logo_claro_path.png',
            'logo_oscuro_path' => 'marcas/logo_oscuro_path.png',
        ]);

        $empresa = $this->empresa->fresh();

        // Sobre un rojo oscuro hace falta el trazo claro.
        $this->assertTrue($this->apuntaA($empresa->logoParaFondo('#7f1d1d'), 'logo_oscuro_path', 'logo-oscuro'));

        // Sobre un amarillo, el trazo oscuro.
        $this->assertTrue($this->apuntaA($empresa->logoParaFondo('#fbbf24'), 'logo_claro_path', 'logo'));
    }

    /**
     * La URL de la marca no cambia nunca y se sirve con una semana de caché.
     * Sin un sello de versión, cambiar el logo no se vería.
     */
    public function test_la_url_del_logo_cambia_cuando_cambia_el_archivo(): void
    {
        Storage::disk(AlmacenDeArchivos::nombreDelDisco())->put('marcas/uno.png', 'x');
        Storage::disk(AlmacenDeArchivos::nombreDelDisco())->put('marcas/dos.png', 'x');

        $this->empresa->update(['logo_claro_path' => 'marcas/uno.png']);
        $primera = $this->empresa->fresh()->logo_url;

        $this->empresa->update(['logo_claro_path' => 'marcas/dos.png']);
        $segunda = $this->empresa->fresh()->logo_url;

        $this->assertNotSame($primera, $segunda);
        $this->assertStringContainsString('?v=', $primera);
    }

    /**
     * ¿La URL lleva a este archivo?
     *
     * En un disco local la URL trae la ruta del archivo; en el FTP trae el tipo
     * de marca, porque se sirve por una ruta de Laravel. El test no debería
     * depender de cuál esté configurado.
     */
    protected function apuntaA(?string $url, string $archivo, string $tipo): bool
    {
        if ($url === null) {
            return false;
        }

        return str_contains($url, $archivo) || str_contains($url, "/{$tipo}?");
    }

    /** @return array{int, int, int} */
    protected function colorMasFrecuente(\GdImage $imagen): array
    {
        $cuenta = [];

        for ($y = 0; $y < imagesy($imagen); $y++) {
            for ($x = 0; $x < imagesx($imagen); $x++) {
                $color = imagecolorat($imagen, $x, $y);

                if ((($color >> 24) & 0x7F) > 60) {
                    continue;
                }

                $clave = ($color >> 16 & 0xFF).','.($color >> 8 & 0xFF).','.($color & 0xFF);
                $cuenta[$clave] = ($cuenta[$clave] ?? 0) + 1;
            }
        }

        arsort($cuenta);

        return array_map('intval', explode(',', (string) array_key_first($cuenta)));
    }

    protected function tieneRojo(\GdImage $imagen): bool
    {
        for ($y = 0; $y < imagesy($imagen); $y++) {
            for ($x = 0; $x < imagesx($imagen); $x++) {
                $color = imagecolorat($imagen, $x, $y);

                if ((($color >> 24) & 0x7F) > 60) {
                    continue;
                }

                $r = $color >> 16 & 0xFF;
                $v = $color >> 8 & 0xFF;
                $a = $color & 0xFF;

                if ($r > 150 && $v < 90 && $a < 90) {
                    return true;
                }
            }
        }

        return false;
    }
}
