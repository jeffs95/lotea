<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\CostoUnidad;
use BackedEnum;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * El estado de resultados de un solo carro.
 *
 * Es la pantalla con la que se vende el producto: el dueño ve, en una sola
 * vista, cuánto le costó de verdad la unidad, en qué se le fue la plata y
 * cuánto ganó. Todo lo demás del sistema existe para llenar esta pantalla.
 */
class RentabilidadUnidad extends Page
{
    protected static string $resource = UnidadResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Rentabilidad';

    protected static ?string $title = 'Rentabilidad';

    protected string $view = 'filament.resources.unidades.pages.rentabilidad';

    public $record;

    public const ETIQUETAS_GRUPO = [
        'compra' => 'Compra',
        'importacion' => 'Importación',
        'taller' => 'Taller y preparación',
        'venta' => 'Venta',
        'otros' => 'Otros',
    ];

    public function mount(int|string $record): void
    {
        $this->record = $this->getResource()::resolveRecordRouteBinding($record);

        abort_unless($this->record !== null, 404);
        abort_unless(auth()->user()?->can('ver_costos_unidad'), 403);
    }

    public function getTitle(): string
    {
        return "Rentabilidad · {$this->record->stock_no}";
    }

    /** Los gastos reales agrupados como los lee un contador. */
    public function getGrupos(): Collection
    {
        $costos = CostoUnidad::query()
            ->where('unidad_id', $this->record->id)
            ->vigentes()
            ->reales()
            ->with(['categoria', 'proveedor'])
            ->get();

        return $costos
            ->groupBy(fn (CostoUnidad $c) => $c->categoria->grupo)
            ->map(fn (Collection $delGrupo, string $grupo) => [
                'grupo' => $grupo,
                'etiqueta' => self::ETIQUETAS_GRUPO[$grupo] ?? $grupo,
                'lineas' => $delGrupo->sortBy(fn (CostoUnidad $c) => $c->categoria->orden)->values(),
                'total' => (float) $delGrupo->where('categoria.afecta_costo', true)->sum('monto_base'),
                'afectaCosto' => $delGrupo->first()->categoria->afecta_costo,
            ])
            ->sortBy(fn (array $g) => array_search($g['grupo'], array_keys(self::ETIQUETAS_GRUPO), true))
            ->values();
    }

    /**
     * Presupuestado contra real, categoría por categoría.
     *
     * Esta comparación es la que por sí sola vende el sistema: el comprador
     * estima el landed cost antes de pujar y después ve en qué se pasó.
     */
    public function getDesviaciones(): Collection
    {
        $costos = CostoUnidad::query()
            ->where('unidad_id', $this->record->id)
            ->vigentes()
            ->queAfectanCosto()
            ->with('categoria')
            ->get();

        return $costos
            ->groupBy('categoria_costo_id')
            ->map(function (Collection $delCategoria) {
                $real = (float) $delCategoria->where('es_presupuesto', false)->sum('monto_base');
                $presupuesto = (float) $delCategoria->where('es_presupuesto', true)->sum('monto_base');

                return [
                    'categoria' => $delCategoria->first()->categoria,
                    'presupuesto' => $presupuesto,
                    'real' => $real,
                    'desviacion' => $real - $presupuesto,
                ];
            })
            ->filter(fn (array $d) => $d['presupuesto'] > 0)
            ->sortByDesc(fn (array $d) => abs($d['desviacion']))
            ->values();
    }

    public function getCostoTotal(): float
    {
        return (float) $this->record->costo_total;
    }

    /** Lo que no encarece el carro pero sí se come la utilidad. */
    public function getGastosDeVenta(): float
    {
        return (float) CostoUnidad::query()
            ->where('unidad_id', $this->record->id)
            ->vigentes()
            ->reales()
            ->whereHas('categoria', fn ($q) => $q->where('afecta_costo', false))
            ->sum('monto_base');
    }

    /** El de cierre si ya se vendió; el de lista mientras tanto. */
    public function getPrecio(): float
    {
        return (float) ($this->record->precio_para_margen ?? 0);
    }

    public function getVenta(): ?\App\Models\Venta
    {
        return $this->record->venta;
    }

    public function getUtilidad(): float
    {
        return $this->getPrecio() - $this->getCostoTotal() - $this->getGastosDeVenta();
    }

    public function getMargen(): ?float
    {
        return $this->getPrecio() > 0
            ? ($this->getUtilidad() / $this->getPrecio()) * 100
            : null;
    }
}
