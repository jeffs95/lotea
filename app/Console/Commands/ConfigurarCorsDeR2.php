<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Throwable;

/**
 * Deja que el panel pueda leer los archivos del cubo público.
 *
 * El panel vive en app.lotea.dev y los archivos en archivos.lotea.dev: para el
 * navegador son dos sitios distintos. Mostrar una foto con <img> no necesita
 * permiso, pero el panel hace algo más: al abrir la ficha de un carro pide cada
 * archivo por JavaScript para saber su tamaño y poder enseñarlo con su botón de
 * borrar. Sin CORS esa petición se bloquea en silencio y las fotos se quedan en
 * «Cargando…» para siempre, sin poder quitarlas.
 *
 * Solo lectura y solo desde los sitios de Lotea: subir y borrar pasa por el
 * servidor con sus credenciales, nunca desde el navegador.
 *
 *     php artisan lotea:cors-r2
 */
class ConfigurarCorsDeR2 extends Command
{
    protected $signature = 'lotea:cors-r2 {--ver : Solo muestra la política que hay hoy}';

    protected $description = 'Permite que el panel lea los archivos del cubo público de R2';

    public function handle(): int
    {
        if (blank(config('filesystems.disks.r2_publico.key'))) {
            $this->error('No hay credenciales de R2 configuradas.');

            return self::FAILURE;
        }

        $cliente = $this->cliente();

        /*
         * Los dos cubos, no solo el público. Los documentos viven en el
         * privado y el panel los pide igual: su enlace sale del servidor pero
         * redirige a R2, y el navegador aplica la misma regla al seguir un
         * redirect a otro sitio.
         */
        $cubos = array_filter([
            config('filesystems.disks.r2_publico.bucket'),
            config('filesystems.disks.r2_privado.bucket'),
        ]);

        if ($this->option('ver')) {
            foreach ($cubos as $cubo) {
                $this->line("<fg=cyan>{$cubo}</>");
                $this->mostrar($cliente, $cubo);
            }

            return self::SUCCESS;
        }

        foreach ($cubos as $cubo) {
            try {
                $cliente->putBucketCors([
                    'Bucket' => $cubo,
                    'CORSConfiguration' => ['CORSRules' => [[
                        'AllowedOrigins' => $this->origenes(),
                        'AllowedMethods' => ['GET', 'HEAD'],
                        'AllowedHeaders' => ['*'],
                        // Sin esto el navegador deja pasar la respuesta pero
                        // oculta las cabeceras, y el panel sigue sin saber el
                        // tamaño del archivo.
                        'ExposeHeaders' => ['Content-Length', 'Content-Type', 'ETag'],
                        'MaxAgeSeconds' => 3600,
                    ]]],
                ]);

                $this->info("  {$cubo}: listo");
            } catch (Throwable $e) {
                $this->error("  {$cubo}: no se pudo aplicar — ".$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->line('Pueden leer desde:');

        foreach ($this->origenes() as $origen) {
            $this->line("  <fg=cyan>{$origen}</>");
        }

        return self::SUCCESS;
    }

    /**
     * Los sitios desde los que el panel pide archivos.
     *
     * El portal de un cliente no hace falta: ahí las fotos se muestran con
     * <img>, que no pide permiso. Esto es solo para el panel.
     *
     * @return array<int, string>
     */
    protected function origenes(): array
    {
        $extra = array_filter(array_map('trim', explode(',', (string) env('R2_ORIGENES_CORS', ''))));

        return array_values(array_unique(array_filter([
            rtrim((string) config('app.url'), '/'),
            'https://app.lotea.dev',
            ...$extra,
        ])));
    }

    protected function mostrar(S3Client $cliente, string $cubo): int
    {
        try {
            $actual = $cliente->getBucketCors(['Bucket' => $cubo]);
            $this->line(json_encode($actual['CORSRules'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            $this->warn('El cubo no tiene política CORS: '.$e->getAwsErrorCode() ?? $e->getMessage());
        }

        return self::SUCCESS;
    }

    protected function cliente(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => config('filesystems.disks.r2_publico.endpoint'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => config('filesystems.disks.r2_publico.key'),
                'secret' => config('filesystems.disks.r2_publico.secret'),
            ],
        ]);
    }
}
