<?php

namespace Tests\Feature;

use App\Support\FotoLigera;
use Tests\TestCase;

/**
 * Lo que se guarda de cada foto.
 *
 * El formulario ya las encoge en el navegador, pero encoger no es comprimir:
 * reescala y vuelve a guardar a calidad casi máxima, de modo que del celular
 * salen tres megas y tres megas se almacenan. Sobre eso se paga el cubo todos
 * los meses, y las fotos de los carros ya vendidos no se borran.
 */
class FotoLigeraTest extends TestCase
{
    protected string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carpeta = sys_get_temp_dir().'/fotos-'.uniqid();
        mkdir($this->carpeta);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->carpeta.'/*') as $archivo) {
            unlink($archivo);
        }

        rmdir($this->carpeta);

        parent::tearDown();
    }

    /** Una imagen con ruido y degradados, que es lo que no comprime solo. */
    protected function foto(int $ancho, int $alto, int $calidad = 95): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);

        for ($y = 0; $y < $alto; $y += 2) {
            for ($x = 0; $x < $ancho; $x += 2) {
                $color = imagecolorallocate(
                    $imagen,
                    max(0, min(255, (int) (128 + 100 * sin($x / 180) + mt_rand(-28, 28)))),
                    max(0, min(255, (int) (120 + 90 * cos($y / 140) + mt_rand(-28, 28)))),
                    max(0, min(255, (int) (110 + 80 * sin(($x + $y) / 220) + mt_rand(-28, 28)))),
                );
                imagefilledrectangle($imagen, $x, $y, $x + 1, $y + 1, $color);
            }
        }

        $ruta = $this->carpeta.'/'.uniqid().'.jpg';
        imagejpeg($imagen, $ruta, $calidad);
        imagedestroy($imagen);

        return $ruta;
    }

    /** El caso corriente: 1920 px, dentro del ancho, pero pesadísima. */
    public function test_una_foto_de_celular_se_guarda_mucho_mas_liviana(): void
    {
        $ruta = $this->foto(1920, 1440);
        $antes = filesize($ruta);

        $this->assertGreaterThan(1_500_000, $antes, 'La foto de prueba no es representativa.');
        $this->assertTrue(FotoLigera::encogerArchivo($ruta));

        clearstatcache(true, $ruta);

        $this->assertLessThan($antes * 0.5, filesize($ruta),
            'La foto no se redujo ni a la mitad: no vale la pena el paso.');
    }

    public function test_una_foto_enorme_se_recorta_al_ancho_maximo(): void
    {
        // 4:3 como cualquier foto: al recortarse a 2000 de ancho quedan 1500 de alto.
        $ruta = $this->foto(2600, 1950);

        FotoLigera::encogerArchivo($ruta);

        [$ancho, $alto] = getimagesize($ruta);

        $this->assertSame(FotoLigera::ANCHO_MAXIMO, $ancho);
        $this->assertSame(1500, $alto, 'Se deformó: la proporción no se respetó.');
    }

    /**
     * Volver a comprimir lo que ya está liviano no ahorra y sí quita calidad:
     * cada pasada de JPEG pierde un poco más.
     */
    public function test_lo_que_ya_esta_liviano_no_se_toca(): void
    {
        $ruta = $this->foto(800, 600, 60);
        $antes = md5_file($ruta);

        $this->assertLessThan(FotoLigera::PESO_QUE_VALE_LA_PENA, filesize($ruta));
        $this->assertFalse(FotoLigera::encogerArchivo($ruta));
        $this->assertSame($antes, md5_file($ruta));
    }

    /**
     * El formato se mira por dentro, no por el nombre.
     *
     * Al bajar una foto del cubo para comprimirla queda en un temporal sin
     * extensión, y mirando el nombre la librería no sabe en qué formato
     * guardarla. Eso dejó al comando diciendo «cero liberados» sin tocar un
     * solo archivo y sin quejarse de nada.
     */
    public function test_una_foto_sin_extension_en_el_nombre_tambien_se_comprime(): void
    {
        $conNombre = $this->foto(1920, 1440);
        $sinNombre = $this->carpeta.'/'.uniqid();
        rename($conNombre, $sinNombre);

        $antes = filesize($sinNombre);

        $this->assertTrue(FotoLigera::encogerArchivoOFallar($sinNombre));

        clearstatcache(true, $sinNombre);

        $this->assertLessThan($antes * 0.5, filesize($sinNombre));
        $this->assertSame(IMAGETYPE_JPEG, getimagesize($sinNombre)[2],
            'Se guardó en otro formato del que tenía.');
    }

    /** Lo que no se sabe comprimir se deja en paz, no se rompe. */
    public function test_un_formato_que_no_se_maneja_se_deja_como_esta(): void
    {
        $ruta = $this->carpeta.'/mapa.bmp';
        $imagen = imagecreatetruecolor(2400, 1800);
        imagebmp($imagen, $ruta);
        imagedestroy($imagen);

        $antes = md5_file($ruta);

        $this->assertFalse(FotoLigera::encogerArchivoOFallar($ruta));
        $this->assertSame($antes, md5_file($ruta));
    }

    /** Que falle una foto no puede tumbar la subida entera. */
    public function test_un_archivo_que_no_es_imagen_no_revienta(): void
    {
        $ruta = $this->carpeta.'/documento.pdf';
        file_put_contents($ruta, str_repeat('no soy una imagen ', 50_000));

        $this->assertFalse(FotoLigera::encogerArchivo($ruta));
        $this->assertFileExists($ruta);
    }

    public function test_un_archivo_que_no_existe_no_revienta(): void
    {
        $this->assertFalse(FotoLigera::encogerArchivo($this->carpeta.'/no-esta.jpg'));
    }
}
