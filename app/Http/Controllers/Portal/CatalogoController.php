<?php

namespace App\Http\Controllers\Portal;

use App\Models\Marca;
use App\Models\Sucursal;
use App\Models\Unidad;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * El escaparate. Solo ve unidades publicadas: el resto del inventario no es
 * asunto del público.
 */
class CatalogoController
{
    public const ORDENES = [
        'recientes' => 'Recién ingresados',
        'precio_asc' => 'Precio: de menor a mayor',
        'precio_desc' => 'Precio: de mayor a menor',
        'anio_desc' => 'Año: más nuevos primero',
        'km_asc' => 'Menor kilometraje',
    ];

    public function inicio(): View
    {
        return view('portal.inicio', [
            'destacadas' => $this->publicadas()->where('destacado', true)->take(6)->get(),
            'recientes' => $this->publicadas()->latest('fecha_lista')->take(8)->get(),
            'marcas' => $this->marcasConStock(),
            'total' => $this->publicadas()->count(),
        ]);
    }

    public function catalogo(Request $request): View
    {
        $consulta = $this->publicadas();

        $consulta->when($request->filled('marca'), fn ($q) => $q->whereHas('marca', fn ($m) => $m->where('slug', $request->string('marca'))));
        $consulta->when($request->filled('anio_min'), fn ($q) => $q->where('anio', '>=', $request->integer('anio_min')));
        $consulta->when($request->filled('anio_max'), fn ($q) => $q->where('anio', '<=', $request->integer('anio_max')));
        $consulta->when($request->filled('precio_max'), fn ($q) => $q->where('precio_lista', '<=', $request->integer('precio_max')));
        $consulta->when($request->filled('transmision'), fn ($q) => $q->where('transmision', $request->string('transmision')));
        $consulta->when($request->filled('carroceria'), fn ($q) => $q->where('carroceria', $request->string('carroceria')));
        $consulta->when($request->filled('tipo_vehiculo'), fn ($q) => $q->where('tipo_vehiculo', $request->string('tipo_vehiculo')));
        $consulta->when($request->filled('sucursal'), fn ($q) => $q->whereHas('sucursal', fn ($s) => $s->where('codigo', $request->string('sucursal'))));

        $consulta->when($request->filled('q'), function ($q) use ($request) {
            $texto = '%'.$request->string('q').'%';

            $q->where(fn ($sub) => $sub
                ->where('stock_no', 'ilike', $texto)
                ->orWhere('version', 'ilike', $texto)
                ->orWhereHas('marca', fn ($m) => $m->where('nombre', 'ilike', $texto))
                ->orWhereHas('linea', fn ($l) => $l->where('nombre', 'ilike', $texto)));
        });

        match ($request->string('orden')->toString()) {
            'precio_asc' => $consulta->orderBy('precio_lista'),
            'precio_desc' => $consulta->orderByDesc('precio_lista'),
            'anio_desc' => $consulta->orderByDesc('anio'),
            'km_asc' => $consulta->orderBy('odometro'),
            default => $consulta->latest('fecha_lista'),
        };

        return view('portal.catalogo', [
            'unidades' => $consulta->paginate(12)->withQueryString(),
            'marcas' => $this->marcasConStock(),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
            'ordenes' => self::ORDENES,
        ]);
    }

    /**
     * El slug se lee de la ruta y no del argumento: las rutas del portal
     * existen en dos formas (con y sin el prefijo de empresa) y la inyección
     * posicional se confunde entre las dos.
     */
    public function unidad(Request $request): View
    {
        $unidad = $this->publicadas()
            ->where('slug', $request->route('slug'))
            ->firstOrFail();

        return view('portal.unidad', [
            'unidad' => $unidad,
            'similares' => $this->publicadas()
                ->where('id', '!=', $unidad->id)
                ->where('marca_id', $unidad->marca_id)
                ->take(3)
                ->get(),
        ]);
    }

    /**
     * Publicadas incluye las que vienen en camino: la preventa es negocio
     * real y es lo que recorta los días de inventario.
     */
    protected function publicadas()
    {
        return Unidad::query()
            ->where('publicado', true)
            ->publicables()
            ->with(['marca', 'linea', 'sucursal', 'media']);
    }

    protected function marcasConStock()
    {
        return Marca::whereHas('lineas.unidades', fn ($q) => $q->where('publicado', true))
            ->orWhereHas('unidades', fn ($q) => $q->where('publicado', true))
            ->orderBy('nombre')
            ->get();
    }
}
