<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Que las extensiones de PHP que el código usa estén pedidas en composer.json.
 *
 * La máquina de quien desarrolla las trae casi todas; un servidor recién hecho
 * no. Si no están declaradas, el despliegue funciona, los tests pasan, y la
 * aplicación revienta en producción la primera vez que alguien pisa esa línea.
 *
 * Pasó: bcmath no estaba declarada y el portal se cayó con «Call to undefined
 * function bcsub()» al abrir una página con una oferta. Y lo que de verdad
 * asusta es lo que no se habia caido todavia: bcmath lleva todo el dinero
 * —ventas, cuotas, pagos, prorrateo de gastos— y nadie lo habia tocado en
 * produccion.
 */
class ExtensionesDePhpDeclaradasTest extends TestCase
{
    /**
     * Qué función delata a cada extensión.
     *
     * @return array<string, string> extensión => prefijo de sus funciones
     */
    protected const DELATORES = [
        'bcmath' => 'bc(?:add|sub|mul|div|comp|mod|pow|sqrt|scale)',
        'gd' => 'image[a-z]+',
        'mbstring' => 'mb_[a-z_]+',
        'intl' => '(?:numfmt_|collator_|transliterator_)[a-z_]*',
        'exif' => 'exif_[a-z]+',
        'zip' => 'zip_[a-z]+',
    ];

    public function test_lo_que_el_codigo_usa_esta_pedido_en_composer(): void
    {
        $pedidas = $this->extensionesPedidas();
        $faltan = [];

        foreach (self::DELATORES as $extension => $patron) {
            $usos = $this->vecesQueSeUsa($patron);

            if ($usos > 0 && ! in_array($extension, $pedidas, true)) {
                $faltan[] = "{$extension} ({$usos} usos en app/)";
            }
        }

        $this->assertSame(
            [],
            $faltan,
            "El código usa extensiones que composer.json no pide:\n  ".implode("\n  ", $faltan)
            ."\n\nEn un servidor que no las traiga, eso revienta en producción con "
            .'«Call to undefined function», no al desplegar.',
        );
    }

    /** Y que el lock las lleve, que es lo que de verdad lee el servidor. */
    public function test_el_lock_arrastra_lo_que_pide_composer(): void
    {
        $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);
        $enElLock = array_keys($lock['platform'] ?? []);

        foreach ($this->extensionesPedidas() as $extension) {
            $this->assertContains(
                'ext-'.$extension,
                $enElLock,
                "«ext-{$extension}» está en composer.json pero no en el lock. "
                .'Corré «composer update --lock»: el servidor lee el lock, no el json.',
            );
        }
    }

    /** @return array<int, string> */
    protected function extensionesPedidas(): array
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        return array_values(array_map(
            fn (string $paquete) => substr($paquete, 4),
            array_filter(
                array_keys($composer['require'] ?? []),
                fn (string $paquete) => str_starts_with($paquete, 'ext-'),
            ),
        ));
    }

    protected function vecesQueSeUsa(string $patron): int
    {
        $total = 0;

        foreach (['app', 'database', 'routes'] as $carpeta) {
            $iterador = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($carpeta))
            );

            foreach ($iterador as $archivo) {
                if ($archivo->isDir() || $archivo->getExtension() !== 'php') {
                    continue;
                }

                $total += preg_match_all(
                    '/(?<![\w$>:])'.$patron.'\s*\(/i',
                    (string) file_get_contents($archivo->getPathname()),
                );
            }
        }

        return $total;
    }
}
