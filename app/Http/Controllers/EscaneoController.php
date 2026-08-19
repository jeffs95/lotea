<?php

namespace App\Http\Controllers;

use App\Models\Unidad;
use App\Support\CodigoDeUnidad;
use App\Support\PortalUrl;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * A dónde va quien escanea el QR pegado en el parabrisas.
 *
 * El mismo código sirve para las dos cosas: si lo escanea un cliente cae en la
 * ficha pública con fotos y precio; si lo escanea alguien del concesionario,
 * cae en la ficha interna con el botón de vender. Una sola etiqueta y nadie
 * tiene que saber cuál escanear.
 */
class EscaneoController
{
    public function __invoke(Request $request, string $codigo): RedirectResponse|View
    {
        // Se busca sin contexto de empresa: el código llega solo, sin saber
        // todavía de qué concesionario es el carro.
        $unidad = Tenancy::sinFiltro(
            fn () => Unidad::with(['empresa', 'marca', 'linea'])
                ->where('codigo_qr', CodigoDeUnidad::normalizar($codigo))
                ->first()
        );

        abort_if($unidad === null, 404);

        $empresa = $unidad->empresa;

        abort_unless($empresa->puedeOperar(), 404);

        if ($this->esDeLaCasa($unidad)) {
            Tenancy::usar($empresa);

            return redirect()->to(
                \App\Filament\Resources\Unidades\UnidadResource::getUrl('edit', [
                    'record' => $unidad,
                    'tenant' => $empresa,
                ])
            );
        }

        if ($unidad->publicado && $unidad->estado->admitePreventa() && filled($unidad->slug)) {
            return redirect()->to(PortalUrl::unidad($empresa, $unidad->slug));
        }

        // Existe pero no está a la venta en línea: mejor decirlo que dar 404.
        return view('escaneo.no-disponible', [
            'empresa' => $empresa,
            'unidad' => $unidad,
        ]);
    }

    /** ¿Quien escaneó trabaja en el concesionario dueño del carro? */
    protected function esDeLaCasa(Unidad $unidad): bool
    {
        $usuario = Auth::user();

        return $usuario !== null
            && $usuario->activo
            && $usuario->empresas()->whereKey($unidad->empresa_id)->exists();
    }
}
