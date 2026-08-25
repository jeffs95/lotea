<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Support\AlmacenDeArchivos;
use App\Support\Tenancy;
use App\Support\VariantesDeLogo;
use Illuminate\Console\Command;
use Throwable;

/**
 * Saca del logo de un concesionario las versiones que el sistema necesita.
 *
 * El cliente manda un archivo —el que usa en Facebook, con su fondo pegado— y
 * de ahí salen: la versión sin fondo para el panel oscuro, la versión oscura
 * para el portal claro, el símbolo solo para el QR del parabrisas y el favicon
 * de la pestaña.
 *
 * Las tres que el sistema usa se guardan en la ficha de la empresa; las demás
 * quedan en el disco para quien las quiera bajar y usar en una manta o en una
 * tarjeta de presentación.
 */
class GenerarVariantesDeLogo extends Command
{
    protected $signature = 'lotea:variantes-logo
        {empresa? : Slug del concesionario. Sin esto, todos los que tengan logo}
        {--forzar : Rehace las variantes aunque ya estén puestas}';

    protected $description = 'Genera las versiones del logo de un cliente: sin fondo, para fondo claro, símbolo y favicon';

    public function handle(): int
    {
        $empresas = Empresa::query()
            ->when($this->argument('empresa'), fn ($q, $slug) => $q->where('slug', $slug))
            ->whereNotNull('logo_path')
            ->get();

        if ($empresas->isEmpty()) {
            $this->warn('Ningún concesionario con logo que procesar.');

            return self::SUCCESS;
        }

        $fallidos = 0;

        foreach ($empresas as $empresa) {
            $this->line("<fg=cyan>{$empresa->getFilamentName()}</>");

            try {
                $this->procesar($empresa);
            } catch (Throwable $e) {
                $this->error('  no se pudo: '.$e->getMessage());
                $fallidos++;
            }
        }

        return $fallidos === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function procesar(Empresa $empresa): void
    {
        $origen = Tenancy::comoEmpresa($empresa, fn () => $empresa->archivoDeMarcaLocal('logo_path'));

        if (! $origen) {
            $this->warn('  su logo no está en el disco; nada que hacer.');

            return;
        }

        $disco = AlmacenDeArchivos::disco();
        $carpeta = "marcas/{$empresa->slug}/variantes";
        $rutas = [];

        foreach (VariantesDeLogo::desde($origen, $empresa->color_de_marca) as $nombre => $imagen) {
            $png = VariantesDeLogo::aPng($imagen);

            $ruta = "{$carpeta}/{$nombre}.png";
            $disco->put($ruta, $png);
            $rutas[$nombre] = $ruta;

            $this->line(sprintf('  %-24s %4dx%-4d  %s KB',
                $nombre, imagesx($imagen), imagesy($imagen), number_format(strlen($png) / 1024, 1)));

            imagedestroy($imagen);
        }

        $this->asignar($empresa, $rutas);
    }

    /**
     * Pone en la ficha las variantes que el sistema usa por su cuenta.
     *
     * No se pisa lo que el cliente ya eligió a mano salvo que se pida --forzar:
     * si subió su propio logo para modo oscuro, ese manda.
     *
     * @param  array<string, string>  $rutas
     */
    protected function asignar(Empresa $empresa, array $rutas): void
    {
        $forzar = (bool) $this->option('forzar');

        $asignaciones = [
            // Todo lo que va sobre fondo claro —el portal, las etiquetas, el
            // panel de día— necesita el trazo oscuro.
            'logo_claro_path' => $rutas['isologo-claro'] ?? null,

            // El panel en modo oscuro quiere el logo tal cual, sin su fondo.
            'logo_oscuro_path' => $rutas['isologo'] ?? null,

            // El símbolo del QR va sobre el cuadro blanco: trazo oscuro. La
            // versión plateada ahí se ve desvaída.
            'isotipo_path' => $rutas['isotipo-claro'] ?? null,

            // El de la pestaña lleva fondo propio: la barra del navegador es
            // clara en unos equipos y oscura en otros, y un símbolo suelto
            // desaparece en uno de los dos.
            'favicon_path' => $rutas['favicon'] ?? null,
        ];

        $cambios = [];

        foreach ($asignaciones as $campo => $ruta) {
            if ($ruta && ($forzar || blank($empresa->{$campo}))) {
                $cambios[$campo] = $ruta;
            }
        }

        if ($cambios === []) {
            $this->line('  <fg=gray>la ficha ya tenía sus variantes; se dejaron como estaban</>');

            return;
        }

        $empresa->update($cambios);

        foreach ($cambios as $campo => $ruta) {
            $this->line("  <fg=green>asignado</> {$campo}");
        }
    }
}
