<?php

namespace App\Providers\Filament;

use App\Models\Empresa;
use App\Support\AvatarDeIniciales;
use App\Support\MarcaDelCliente;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel de operación del concesionario: /app/{empresa}
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('app')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            // Iniciales dibujadas en casa: el proveedor por defecto de Filament
            // le manda el nombre de cada usuario a ui-avatars.com.
            ->defaultAvatarProvider(AvatarDeIniciales::class)
            // Marca blanca: el concesionario ve su nombre, su logo y su color,
            // no los de Lotea. Van como closures porque el panel se construye
            // una vez y se pinta para clientes distintos en cada request.
            ->brandName(fn (): string => MarcaDelCliente::nombre())
            ->brandLogo(fn (): ?string => MarcaDelCliente::logo())
            ->darkModeBrandLogo(fn (): ?string => MarcaDelCliente::logoOscuro())
            ->brandLogoHeight('2rem')
            ->favicon(fn (): ?string => MarcaDelCliente::favicon())
            // El color va por render hook y no por ->colors(): ver el comentario
            // de la vista. Filament cachea la paleta de ->colors() y en un
            // proceso reusado el segundo cliente heredaría el color del primero.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.paleta-del-cliente', ['paleta' => MarcaDelCliente::paleta()]),
            )
            ->tenant(Empresa::class, slugAttribute: 'slug')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
