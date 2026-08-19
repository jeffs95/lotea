<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Los tres planes con los que sale Lotea. */
class PlanesSeeder extends Seeder
{
    public const PLANES = [
        [
            'slug' => 'basico',
            'nombre' => 'Básico',
            'descripcion' => 'Para el importador de un solo patio que quiere saber su costo real.',
            'precio_mensual' => 595,
            'max_sucursales' => 1,
            'max_usuarios' => 3,
            'max_unidades_activas' => 40,
            'modulos' => ['unidades', 'importacion', 'costeo', 'portal'],
            'orden' => 10,
        ],
        [
            'slug' => 'pro',
            'nombre' => 'Pro',
            'descripcion' => 'Para concesionarios con varias sucursales y equipo de ventas.',
            'precio_mensual' => 1295,
            'max_sucursales' => 3,
            'max_usuarios' => 10,
            'max_unidades_activas' => 150,
            'modulos' => ['unidades', 'importacion', 'costeo', 'portal', 'taller', 'ventas', 'comisiones', 'cartera'],
            'orden' => 20,
        ],
        [
            'slug' => 'full',
            'nombre' => 'Full',
            'descripcion' => 'Todo el sistema, sin límites, con soporte prioritario.',
            'precio_mensual' => 2495,
            'max_sucursales' => null,
            'max_usuarios' => null,
            'max_unidades_activas' => null,
            'modulos' => ['unidades', 'importacion', 'costeo', 'portal', 'taller', 'ventas', 'comisiones', 'cartera', 'nomina', 'inversionistas', 'reportes'],
            'orden' => 30,
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANES as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
