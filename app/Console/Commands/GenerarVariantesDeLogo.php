<?php

namespace App\Console\Commands;

use App\Actions\GenerarVariantesDeMarca;
use App\Models\Empresa;
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
        $cambios = app(GenerarVariantesDeMarca::class)->ejecutar($empresa, (bool) $this->option('forzar'));

        if ($cambios === []) {
            $this->line('  <fg=gray>sin cambios: o su logo no está en el disco, o ya tenía sus versiones puestas</>');

            return;
        }

        foreach ($cambios as $campo => $ruta) {
            $this->line("  <fg=green>asignado</> {$campo}  <fg=gray>{$ruta}</>");
        }
    }
}
