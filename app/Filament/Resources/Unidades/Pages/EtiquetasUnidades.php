<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\Unidad;
use App\Support\QrDeUnidad;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Descargar PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->pdf()),
        ];
    }

    /**
     * La hoja como PDF, armada en el servidor.
     *
     * Imprimir desde el navegador pasa por cuatro manos —el CSS, el navegador,
     * el sistema y el driver— y basta que una falle para que salga una hoja en
     * blanco sin decir por qué. Un PDF sale igual en Windows, en Mac y en un
     * teléfono, porque aquí se decide todo. Y se puede guardar y reimprimir.
     */
    public function pdf(): StreamedResponse
    {
        $empresa = Filament::getTenant();
        $unidades = $this->getUnidades();

        $pdf = Pdf::loadView('pdf.etiquetas', [
            'unidades' => $unidades,
            'empresa' => $empresa,
            'nombre' => $empresa?->getFilamentName(),
            'color' => $empresa?->color_de_marca ?? '#111827',
            'logo' => $empresa?->logoIncrustadoParaFondo(),
            // dompdf no dibuja un SVG escrito en el HTML, pero sí uno metido en
            // un <img>. Es justo al revés que el navegador, así que cada salida
            // lleva el suyo.
            'qr' => $unidades->mapWithKeys(fn (Unidad $u) => [
                $u->getKey() => QrDeUnidad::dataUri($u, 180),
            ]),
        ])->setPaper('letter');

        $archivo = 'etiquetas-'.now()->format('Y-m-d').'.pdf';

        return response()->streamDownload(fn () => print $pdf->output(), $archivo);
    }

    /** El código listo para escribirlo dentro de la etiqueta. */
    public function qr(Unidad $unidad): string
    {
        return QrDeUnidad::svgEnLinea($unidad, 180, 'mx-auto mt-3 h-36 w-36');
    }
}
