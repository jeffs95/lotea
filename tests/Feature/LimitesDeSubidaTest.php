<?php

namespace Tests\Feature;

use App\Support\LimiteDeSubida;
use Tests\TestCase;

/**
 * Que los límites de subida cuadren entre sí.
 *
 * Son tres números en tres archivos distintos —el formulario, PHP y Livewire— y
 * si se desalinean el fallo no se ve: cuando una petición supera post_max_size,
 * PHP la descarta entera antes de que Laravel arranque. No hay excepción, no hay
 * log, el botón simplemente no hace nada. Ya pasó con la imagen de portada, y
 * otra vez con las fotos tomadas desde el teléfono.
 */
class LimitesDeSubidaTest extends TestCase
{
    /** @return array<string, int> los ajustes del .user.ini, en kilobytes */
    protected function ajustesDePhp(): array
    {
        // En public/ y no en la raíz: PHP lee el .user.ini del directorio del
        // script que está corriendo, que es el document root. En la raíz del
        // proyecto se queda ahí sin que nadie lo mire.
        $archivo = public_path('.user.ini');

        $this->assertFileExists(
            $archivo,
            'Falta public/.user.ini: Heroku deja PHP en 2 MB por archivo, y una foto de '
            .'teléfono pesa entre 4 y 12 MB.',
        );

        $this->assertFileDoesNotExist(
            base_path('.user.ini'),
            'Hay un .user.ini en la raíz del proyecto: PHP no lo lee de ahí, así que '
            .'los límites que declare no se aplican y nadie se entera.',
        );

        $texto = (string) file_get_contents($archivo);
        $ajustes = [];

        foreach (['upload_max_filesize', 'post_max_size', 'memory_limit'] as $clave) {
            $this->assertMatchesRegularExpression(
                "/^\s*{$clave}\s*=\s*(\d+)M/mi",
                $texto,
                "El .user.ini no declara {$clave}.",
            );

            preg_match("/^\s*{$clave}\s*=\s*(\d+)M/mi", $texto, $coincidencias);
            $ajustes[$clave] = ((int) $coincidencias[1]) * 1024;
        }

        return $ajustes;
    }

    public function test_lo_que_pide_el_formulario_cabe_en_lo_que_acepta_php(): void
    {
        $php = $this->ajustesDePhp();

        $this->assertLessThanOrEqual(
            $php['upload_max_filesize'],
            LimiteDeSubida::KILOBYTES,
            'El formulario acepta archivos más grandes de lo que PHP deja pasar: '
            .'el usuario los elige y luego se pierden sin explicación.',
        );
    }

    /**
     * El que muerde en silencio: post_max_size tiene que dejar sitio al archivo
     * **y** al resto del formulario.
     */
    public function test_el_tamano_total_de_la_peticion_deja_margen_sobre_el_archivo(): void
    {
        $php = $this->ajustesDePhp();

        $this->assertGreaterThan(
            $php['upload_max_filesize'],
            $php['post_max_size'],
            'post_max_size no supera a upload_max_filesize: un archivo del tamaño máximo '
            .'más los campos del formulario tira la petición entera sin dejar rastro.',
        );
    }

    /** Redimensionar una foto grande necesita memoria de sobra. */
    public function test_hay_memoria_para_procesar_una_foto_de_camara(): void
    {
        $php = $this->ajustesDePhp();

        $this->assertGreaterThanOrEqual(
            256 * 1024,
            $php['memory_limit'],
            'Con poca memoria la conversión de una foto de muchos megapíxeles muere a medias.',
        );
    }

    /** Y Livewire, que tiene su propio tope para el archivo temporal. */
    public function test_livewire_no_rechaza_antes_lo_que_el_formulario_permite(): void
    {
        $reglas = config('livewire.temporary_file_upload.rules') ?: ['required', 'file', 'max:12288'];

        $tope = null;

        foreach ($reglas as $regla) {
            if (is_string($regla) && str_starts_with($regla, 'max:')) {
                $tope = (int) substr($regla, 4);
            }
        }

        $this->assertNotNull($tope, 'No se pudo leer el tope de Livewire.');

        $this->assertGreaterThanOrEqual(
            LimiteDeSubida::KILOBYTES,
            $tope,
            'Livewire corta el archivo temporal antes que el formulario: el usuario ve '
            .'«no se pudo subir» sin más explicación.',
        );
    }
}
