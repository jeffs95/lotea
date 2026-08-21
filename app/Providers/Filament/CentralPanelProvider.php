<?php

namespace App\Providers\Filament;

use App\Http\Middleware\LimpiarContextoDeEmpresa;
use App\Support\AvatarDeIniciales;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * El panel de Lotea: /central
 *
 * Aquí se administra el negocio de vender el sistema — concesionarios, planes
 * y cobros — no la operación de ningún concesionario. No tiene tenancy: ve
 * todas las empresas a la vez, que es justo lo contrario del panel del cliente.
 */
class CentralPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('central')
            ->path('central')
            ->login()
            // Iniciales dibujadas en casa: el proveedor por defecto de Filament
            // le manda el nombre de cada usuario a ui-avatars.com.
            ->defaultAvatarProvider(AvatarDeIniciales::class)
            ->brandName('Lotea · Central')
            ->colors([
                // Azul y no ámbar: que se note de un vistazo en qué panel estás.
                'primary' => Color::Indigo,
            ])
            // Mismo tema que el panel de los clientes: las clases de Tailwind
            // de las vistas propias solo existen en el CSS compilado.
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Central/Resources'), for: 'App\Filament\Central\Resources')
            ->discoverPages(in: app_path('Filament/Central/Pages'), for: 'App\Filament\Central\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Central/Widgets'), for: 'App\Filament\Central\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                LimpiarContextoDeEmpresa::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
