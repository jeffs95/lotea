<?php

namespace Tests\Support;

use Illuminate\Filesystem\FilesystemAdapter;

/**
 * Un disco que cuenta cuántas veces le preguntan algo.
 *
 * Sirve para probar que pintar una página no va al almacenamiento. Contra un
 * disco local esas llamadas son gratis y no se notan; contra R2 cada una es un
 * viaje de red de unos 300 ms, y ahí es donde se esconden los segundos.
 */
class DiscoQueCuenta extends FilesystemAdapter
{
    public int $viajes = 0;

    public function fileExists($ruta)
    {
        $this->viajes++;

        return parent::fileExists($ruta);
    }

    public function exists($ruta)
    {
        $this->viajes++;

        return parent::exists($ruta);
    }

    public function get($ruta)
    {
        $this->viajes++;

        return parent::get($ruta);
    }

    public function size($ruta)
    {
        $this->viajes++;

        return parent::size($ruta);
    }

    public function lastModified($ruta)
    {
        $this->viajes++;

        return parent::lastModified($ruta);
    }

    public function allFiles($directorio = null)
    {
        $this->viajes++;

        return parent::allFiles($directorio);
    }
}
