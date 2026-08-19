<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * El ciclo de vida de una unidad, de la subasta al cliente.
 *
 * Es la columna vertebral del sistema: cada cambio de estado deja fecha,
 * usuario y nota, y de ahí salen el aging de inventario, el capital dormido y
 * los días de rotación.
 */
enum EstadoUnidad: string implements HasColor, HasIcon, HasLabel
{
    case Comprada = 'comprada';
    case EnTitulo = 'en_titulo';
    case TransitoUsa = 'transito_usa';
    case BodegaUsa = 'bodega_usa';
    case Embarcada = 'embarcada';
    case EnAduana = 'en_aduana';
    case TransitoLocal = 'transito_local';
    case Recibida = 'recibida';
    case EnTaller = 'en_taller';
    case Lista = 'lista';
    case Publicada = 'publicada';
    case Reservada = 'reservada';
    case Vendida = 'vendida';
    case Entregada = 'entregada';
    case EnCartera = 'en_cartera';
    case Baja = 'baja';

    public function getLabel(): string
    {
        return match ($this) {
            self::Comprada => 'Comprada',
            self::EnTitulo => 'En título',
            self::TransitoUsa => 'Tránsito USA',
            self::BodegaUsa => 'Bodega USA',
            self::Embarcada => 'Embarcada',
            self::EnAduana => 'En aduana',
            self::TransitoLocal => 'Tránsito local',
            self::Recibida => 'Recibida',
            self::EnTaller => 'En taller',
            self::Lista => 'Lista',
            self::Publicada => 'Publicada',
            self::Reservada => 'Reservada',
            self::Vendida => 'Vendida',
            self::Entregada => 'Entregada',
            self::EnCartera => 'En cartera',
            self::Baja => 'Baja',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Comprada, self::EnTitulo => 'gray',
            self::TransitoUsa, self::BodegaUsa, self::Embarcada, self::EnAduana, self::TransitoLocal => 'info',
            self::Recibida, self::EnTaller => 'warning',
            self::Lista, self::Publicada => 'success',
            self::Reservada => 'primary',
            self::Vendida, self::Entregada, self::EnCartera => 'success',
            self::Baja => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Comprada => 'heroicon-o-shopping-cart',
            self::EnTitulo => 'heroicon-o-document-text',
            self::TransitoUsa, self::TransitoLocal => 'heroicon-o-truck',
            self::BodegaUsa => 'heroicon-o-building-storefront',
            self::Embarcada => 'heroicon-o-globe-americas',
            self::EnAduana => 'heroicon-o-shield-check',
            self::Recibida => 'heroicon-o-clipboard-document-check',
            self::EnTaller => 'heroicon-o-wrench-screwdriver',
            self::Lista => 'heroicon-o-check-badge',
            self::Publicada => 'heroicon-o-megaphone',
            self::Reservada => 'heroicon-o-bookmark',
            self::Vendida => 'heroicon-o-banknotes',
            self::Entregada => 'heroicon-o-key',
            self::EnCartera => 'heroicon-o-calendar-days',
            self::Baja => 'heroicon-o-x-circle',
        };
    }

    /** En qué etapa del negocio cae, para agrupar el kanban y los reportes. */
    public function etapa(): string
    {
        return match ($this) {
            self::Comprada, self::EnTitulo => 'compra',
            self::TransitoUsa, self::BodegaUsa, self::Embarcada, self::EnAduana, self::TransitoLocal => 'importacion',
            self::Recibida, self::EnTaller => 'preparacion',
            self::Lista, self::Publicada, self::Reservada => 'venta',
            self::Vendida, self::Entregada, self::EnCartera => 'cerrada',
            self::Baja => 'baja',
        };
    }

    /**
     * A qué estados se puede pasar desde este.
     *
     * Se permite retroceder solo donde el negocio lo hace de verdad (un carro
     * vuelve al taller después de estar listo, una reserva se cae). Lo demás se
     * corrige con una anulación, no rebobinando el historial.
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Comprada => [self::EnTitulo, self::TransitoUsa, self::Baja],
            self::EnTitulo => [self::TransitoUsa, self::BodegaUsa, self::Baja],
            self::TransitoUsa => [self::BodegaUsa, self::Embarcada, self::Baja],
            self::BodegaUsa => [self::Embarcada, self::Baja],
            self::Embarcada => [self::EnAduana, self::Baja],
            self::EnAduana => [self::TransitoLocal, self::Recibida, self::Baja],
            self::TransitoLocal => [self::Recibida, self::Baja],
            self::Recibida => [self::EnTaller, self::Lista, self::Baja],
            self::EnTaller => [self::Lista, self::Baja],
            self::Lista => [self::Publicada, self::Reservada, self::EnTaller, self::Vendida, self::Baja],
            self::Publicada => [self::Reservada, self::Vendida, self::EnTaller, self::Lista, self::Baja],
            self::Reservada => [self::Vendida, self::Publicada, self::Lista],
            self::Vendida => [self::Entregada],
            self::Entregada => [self::EnCartera],
            self::EnCartera => [self::Entregada],
            self::Baja => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->siguientes(), strict: true);
    }

    /**
     * Desde aquí la unidad ya se puede publicar en el portal.
     *
     * La preventa es negocio real: el carro se vende mientras viene en el
     * barco y eso recorta los días de inventario.
     */
    public function admitePreventa(): bool
    {
        return in_array($this->etapa(), ['importacion', 'preparacion', 'venta'], strict: true);
    }

    /** ¿Sigue siendo capital dormido en el patio? */
    public function esInventario(): bool
    {
        return ! in_array($this->etapa(), ['cerrada', 'baja'], strict: true);
    }

    public static function opciones(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $e) => [$e->value => $e->getLabel()])->all();
    }
}
