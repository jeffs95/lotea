<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\Unidad;
use App\Support\QrDeUnidad;
use BackedEnum;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * La hoja de etiquetas para pegar en los parabrisas.
 *
 * Sin precio a propósito: el precio cambia y nadie va a reimprimir cuarenta
 * etiquetas. Lo que cambia lo muestra el QR, que siempre está al día.
 */
class EtiquetasUnidades extends Page
{
    protected static string $resource = UnidadResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $title = 'Etiquetas para el parabrisas';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.unidades.pages.etiquetas';

    /** @var array<int, int> */
    public array $seleccionadas = [];

    public function mount(): void
    {
        $ids = collect(explode(',', (string) request()->query('unidades', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->all();

        $this->seleccionadas = $ids;
    }

    /** @return Collection<int, Unidad> */
    public function getUnidades(): Collection
    {
        return Unidad::query()
            ->when(filled($this->seleccionadas), fn ($q) => $q->whereKey($this->seleccionadas))
            ->when(blank($this->seleccionadas), fn ($q) => $q->enInventario())
            ->with(['marca', 'linea'])
            ->orderBy('stock_no')
            ->get();
    }

    /** El código listo para escribirlo dentro de la etiqueta. */
    public function qr(Unidad $unidad): string
    {
        return QrDeUnidad::svgEnLinea($unidad, 180, 'mx-auto mt-3 h-36 w-36');
    }
}
