<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * El acceso de Lotea al panel de un cliente, para dar soporte.
 *
 * Cuando un concesionario llama con «no me aparece esto», la única forma de
 * ayudarlo de verdad es ver su panel. Pero la cuenta de Lotea no pertenece a su
 * empresa —ni debe pertenecer, o contaría como usuario suyo y aparecería en su
 * lista— así que Filament le responde 404.
 *
 * Esto abre esa puerta sin ensuciar los datos del cliente, con tres reglas:
 *
 * - Solo un operador de Lotea puede abrirla.
 * - Se entra **como uno mismo**, no como el cliente. Lo que se toque queda
 *   registrado a nombre de quien lo tocó, no del dueño del concesionario: si
 *   mañana hay un reclamo, el rastro dice la verdad.
 * - Es de una empresa a la vez y se cierra sola al salir de la sesión.
 */
class ModoSoporte
{
    protected const CLAVE = 'soporte.empresa_id';

    /** Si quien pide es de Lotea; se resuelve una vez por request. */
    protected static ?bool $esOperador = null;

    /** Los tests reutilizan el proceso y cambian de usuario en el camino. */
    public static function olvidar(): void
    {
        static::$esOperador = null;
    }

    /** Abre el panel de ese concesionario para el operador que lo pide. */
    public static function entrar(Empresa $empresa): void
    {
        Session::put(self::CLAVE, $empresa->getKey());
    }

    public static function salir(): void
    {
        Session::forget(self::CLAVE);
    }

    /** ¿Hay una sesión de soporte abierta ahora mismo? */
    public static function activo(): bool
    {
        return self::empresaId() !== null && self::loPuedeUsar();
    }

    public static function empresaId(): ?int
    {
        $id = Session::get(self::CLAVE);

        return $id === null ? null : (int) $id;
    }

    /** El concesionario cuyo panel se está viendo. */
    public static function empresa(): ?Empresa
    {
        if (! self::loPuedeUsar()) {
            return null;
        }

        $id = self::empresaId();

        return $id === null
            ? null
            : Empresa::withoutGlobalScopes()->find($id);
    }

    /** ¿Esta empresa es la que el operador abrió para dar soporte? */
    public static function esLaEmpresaAbierta(int|string|null $empresaId): bool
    {
        return $empresaId !== null
            && self::activo()
            && (int) $empresaId === self::empresaId();
    }

    /**
     * Solo el personal de Lotea, y comprobado en cada request.
     *
     * Si a alguien se le quita la bandera de operador, su sesión de soporte deja
     * de valer en el acto sin tener que cerrarla a mano.
     */
    protected static function loPuedeUsar(): bool
    {
        $usuario = Auth::user();

        if (! $usuario instanceof User) {
            return false;
        }

        // Se pregunta a la base y no al modelo: Livewire rehidrata al usuario
        // con solo algunas columnas, y leer una que no vino revienta. Se
        // recuerda por request, que es cuando importa.
        return static::$esOperador ??= (bool) User::query()
            ->whereKey($usuario->getKey())
            ->where('activo', true)
            ->value('es_operador');
    }
}
