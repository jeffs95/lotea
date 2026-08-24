<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\Actions\CambiarEstadoAction;
use App\Filament\Resources\Unidades\Actions\LeerDocumentoAction;
use App\Filament\Resources\Unidades\Pages\Concerns\AvisaSobreElPortal;
use App\Filament\Resources\Unidades\UnidadResource;
use App\Filament\Resources\Ventas\VentaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUnidad extends EditRecord
{
    use AvisaSobreElPortal;

    protected static string $resource = UnidadResource::class;

    protected function afterSave(): void
    {
        $this->avisarSobreElPortal();
    }

    public function getTitle(): string
    {
        return "{$this->record->stock_no} · {$this->record->descripcion}";
    }

    protected function getHeaderActions(): array
    {
        return [
            // Lo primero que necesita el vendedor que acaba de escanear el QR
            // con el cliente al lado.
            Action::make('vender')
                ->label('Vender esta unidad')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->estado->esInventario() && ! $this->record->venta()->exists())
                ->url(fn () => VentaResource::getUrl('create', ['unidad' => $this->record->id])),

            CambiarEstadoAction::make(),

            LeerDocumentoAction::make(),

            Action::make('etiqueta')
                ->label('Imprimir etiqueta')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->url(fn () => EtiquetasUnidades::getUrl().'?unidades='.$this->record->id)
                ->openUrlInNewTab(),

            DeleteAction::make(),
        ];
    }
}
