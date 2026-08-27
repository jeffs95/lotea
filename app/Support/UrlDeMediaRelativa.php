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
        /*
         * Si el archivo vive en un almacenamiento con dominio propio —el cubo
         * público de R2 detrás del CDN— la URL apunta ahí y se va entera, con
         * su dominio. Es el único caso en que no se recorta: el archivo no lo
         * sirve esta aplicación, así que una ruta relativa no llevaría a nada.
         *
         * Esto es lo que saca las fotos del catálogo de encima de PHP. Antes
         * cada una arrancaba Laravel, abría sesión, consultaba la base y bajaba
         * el archivo del FTP; ahora las entrega el borde de Cloudflare.
         */
        if ($this->seSirveDesdeSuPropioDominio()) {
            return $this->urlDelAlmacenamiento();
        }

        /*
         * Lo demás pasa por la ruta que decide quién puede verlo.
         *
         * La pregunta es por el disco de **este** archivo y no por el general:
         * desde que hay dos cubos, el de la aplicación puede ser uno y el del
         * archivo otro, y mirando el general un documento en R2 salía con una
         * URL directa de /storage que no lleva a ninguna parte.
         */
        if (! $this->seSirveSinIntermediario()) {
            return static::soloLaRuta(route('archivo', array_filter([
                'media' => $this->media->getKey(),
                'conversion' => $this->conversion?->getName(),
            ])));
        }

        return static::soloLaRuta(parent::getUrl());
    }

    /** ¿El navegador puede pedir este archivo directo al disco donde está? */
    protected function seSirveSinIntermediario(): bool
    {
        $disco = $this->media->disk;

        return config("filesystems.disks.{$disco}.driver") === 'local'
            && filled(config("filesystems.disks.{$disco}.url"));
    }

    /**
     * ¿Este archivo lo entrega un CDN y no nosotros?
     *
     * No basta con que el disco tenga una URL: el disco local también la tiene
     * y ahí la sirve esta misma aplicación, así que esa se recorta para que en
     * el portal de un cliente no aparezca «lotea» en el src de sus fotos. Lo
     * que distingue al CDN es que el archivo vive fuera.
     */
    protected function seSirveDesdeSuPropioDominio(): bool
    {
        $disco = $this->media->disk;

        return $disco === AlmacenDeArchivos::discoPublico()
            && config("filesystems.disks.{$disco}.driver") !== 'local'
            && filled(config("filesystems.disks.{$disco}.url"));
    }

    protected function urlDelAlmacenamiento(): string
    {
        $base = rtrim((string) config("filesystems.disks.{$this->media->disk}.url"), '/');

        return $base.'/'.ltrim(AlmacenDeArchivos::rutaDe($this->media, $this->conversion?->getName()), '/');
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
