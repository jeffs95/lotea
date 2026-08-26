<?php

namespace App\Support;

/**
 * Cuánto puede pesar un archivo que sube un usuario.
 *
 * Vive en un solo lugar porque el número tiene que cuadrar con tres cosas a la
 * vez, y si una se desalinea el fallo no se ve:
 *
 * - `upload_max_filesize` de PHP. Si el archivo lo supera, PHP lo descarta y
 *   Laravel recibe un formulario sin archivo.
 * - `post_max_size` de PHP, que tiene que ser mayor: cabe el archivo **más** el
 *   resto del formulario. Y si la petición lo supera, PHP la tira entera antes
 *   de que Laravel exista: sin error, sin log, el botón no hace nada. Ya nos
 *   pasó con la imagen de portada.
 * - El límite de Livewire para archivos temporales.
 *
 * El valor no es un gusto: una foto de la cámara de un teléfono actual pesa
 * entre 4 y 12 MB. El navegador intenta reducirla antes de enviarla, pero en
 * móvil eso falla cuando la foto trae más megapíxeles de los que aguanta un
 * canvas —justo el caso de las cámaras de 48 MP—, y entonces sube el original.
 * Si el límite es menor, el usuario ve «no se pudo subir» y no entiende por qué
 * desde la galería sí le funciona.
 */
class LimiteDeSubida
{
    /** Lo que se le pasa a Filament, en kilobytes. */
    public const KILOBYTES = 16 * 1024;

    /** Para los mensajes: «hasta 16 MB». */
    public static function enMegas(): int
    {
        return (int) (self::KILOBYTES / 1024);
    }
}
