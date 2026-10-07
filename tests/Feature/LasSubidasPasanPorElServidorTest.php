<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El archivo a medio subir se queda en el servidor, no en el cubo.
 *
 * Sin fijarlo, Livewire toma el disco por defecto. Cuando ese disco es R2
 * —como corre en producción— deja de mandar el archivo al servidor y hace
 * que el navegador lo suba directo al cubo con una URL firmada. El navegador
 * pide permiso antes, la política del cubo solo permite leer, y lo que ve el
 * usuario es «Error durante la subida» con un fallo de CORS en la consola
 * que no explica nada.
 *
 * Costó encontrarlo porque en desarrollo no pasa: ahí el disco por defecto
 * ya es local y todo funciona.
 */
class LasSubidasPasanPorElServidorTest extends TestCase
{
    public function test_el_disco_temporal_no_es_el_cubo(): void
    {
        $disco = config('livewire.temporary_file_upload.disk');

        $this->assertSame('local', $disco);
        $this->assertSame(
            'local',
            config("filesystems.disks.{$disco}.driver"),
            'El disco temporal de Livewire apunta a un almacenamiento remoto: '
                .'las subidas van a saltarse el servidor y a chocar con el CORS del cubo.',
        );
    }

    /**
     * Y sigue siendo local aunque el resto del sistema guarde en R2, que es
     * justamente la situación donde esto se rompe.
     */
    public function test_sigue_siendo_local_con_r2_de_por_medio(): void
    {
        Storage::fake('cubo_falso');

        config([
            'filesystems.default' => 'cubo_falso',
            'filesystems.disks.cubo_falso.driver' => 's3',
        ]);

        $this->assertSame('local', config('livewire.temporary_file_upload.disk'));
    }
}
