<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Que la suite no hable con el almacenamiento de verdad.
 *
 * Los tests leen el .env de quien los corre. Si ahí están las credenciales de
 * producción —y ahí es donde viven mientras se desarrolla— una corrida puede
 * escribir en el FTP de la DGT o en los cubos de R2 del cliente. No es una
 * hipótesis: pasó al configurar R2, y once tests se pusieron a subir archivos
 * al cubo real antes de que nadie lo notara.
 *
 * Vaciar las credenciales no alcanza: los discos donde se reparten los archivos
 * se eligen con otras variables, y con esas puestas la suite iba igual al sitio
 * equivocado.
 */
class LosTestsNoTocanProduccionTest extends TestCase
{
    /** @return array<int, array<int, string>> */
    public static function variablesQueApuntanAfuera(): array
    {
        return array_map(fn (string $v) => [$v], [
            'FTP_HOST', 'FTP_USERNAME', 'FTP_PASSWORD',
            'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_ENDPOINT',
            'R2_BUCKET_PUBLICO', 'R2_BUCKET_PRIVADO', 'R2_URL_PUBLICA',
            'LOTEA_DISCO_PUBLICO', 'LOTEA_DISCO_PRIVADO',
        ]);
    }

    #[DataProvider('variablesQueApuntanAfuera')]
    public function test_la_configuracion_de_pruebas_neutraliza_la_variable(string $variable): void
    {
        $this->assertSame(
            '',
            (string) env($variable),
            "«{$variable}» llega con valor a los tests. Si es la de producción, la suite "
            .'escribe en el almacenamiento del cliente. Vacíala en phpunit.xml con force="true".',
        );
    }

    /** Y el disco donde se guarda tiene que ser el local de pruebas. */
    public function test_los_archivos_se_guardan_en_un_disco_de_mentira(): void
    {
        $disco = config('media-library.disk_name');

        $this->assertSame('public', $disco);
        $this->assertSame('local', config("filesystems.disks.{$disco}.driver"));
    }
}
