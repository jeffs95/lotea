<?php

namespace App\Support;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * URLs de fotos relativas al dominio que se esté sirviendo.
 *
 * El generador por defecto las devuelve absolutas contra APP_URL. En el portal
 * de un cliente con dominio propio eso pone «lotea» en el src de las fotos de
 * sus propios carros, que es justo lo que la marca blanca no debe hacer. Con la
 * ruta relativa el navegador la resuelve contra el dominio del cliente, y la
 * app es la misma que sirve los dos, así que el archivo está donde debe.
 *
 * Se conserva el query: medialibrary le cuelga ?v= para invalidar caché.
 */
class UrlDeMediaRelativa extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        return static::soloLaRuta(parent::getUrl());
    }

    public function getBaseMediaDirectoryUrl(): string
    {
        return static::soloLaRuta(parent::getBaseMediaDirectoryUrl());
    }

    public function getResponsiveImagesDirectoryUrl(): string
    {
        return static::soloLaRuta(parent::getResponsiveImagesDirectoryUrl());
    }

    protected static function soloLaRuta(string $url): string
    {
        $partes = parse_url($url);

        if (! isset($partes['path'])) {
            return $url;
        }

        return $partes['path'].(isset($partes['query']) ? '?'.$partes['query'] : '');
    }
}
