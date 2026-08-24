<?php

namespace Tests\Feature;

use App\Actions\CrearEmpresa;
use App\Enums\EstadoUnidad;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Support\Coordenadas;
use App\Support\Tenancy;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lo que el comprador necesita del portal además del carro: encontrar el patio
 * y saber por dónde escribir.
 */
class PortalContactoYSegmentosTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->empresa = (new CrearEmpresa)->ejecutar([
            'nombre' => 'Importadora Gómez',
            'slug' => 'importadora-gomez',
            'telefono' => '2222-3333',
        ]);

        Tenancy::usar($this->empresa);
    }

    protected function publicar(array $atributos = []): Unidad
    {
        return Unidad::factory()->publicada()->create([
            'estado' => EstadoUnidad::Publicada,
            'precio_lista' => 95000,
            ...$atributos,
        ]);
    }

    protected function url(string $ruta = ''): string
    {
        return "/v/{$this->empresa->slug}{$ruta}";
    }

    public function test_la_pagina_de_contacto_carga(): void
    {
        $this->get($this->url('/contacto'))
            ->assertSuccessful()
            ->assertSee('Encontranos aquí')
            ->assertSee('Contactanos');
    }

    public function test_muestra_la_sucursal_con_su_direccion_y_horario(): void
    {
        Sucursal::first()->update([
            'nombre' => 'Patio Roosevelt',
            'direccion' => 'Calzada Roosevelt 25-50, zona 11',
            'horario' => 'Lun a Vie 8:00–18:00',
        ]);

        $this->get($this->url('/contacto'))
            ->assertSee('Patio Roosevelt')
            ->assertSee('Calzada Roosevelt 25-50, zona 11')
            ->assertSee('Lun a Vie 8:00–18:00', escape: false);
    }

    /** Aquí casi todo el mundo maneja con Waze. */
    public function test_con_coordenadas_salen_los_botones_de_mapa(): void
    {
        Sucursal::first()->update(['latitud' => 14.6231, 'longitud' => -90.5566]);

        $this->get($this->url('/contacto'))
            ->assertSee('Cómo llegar')
            ->assertSee('Abrir en Waze')
            ->assertSee('waze.com/ul?ll=14.6231,-90.5566', escape: false)
            ->assertSee('query=14.6231,-90.5566', escape: false);
    }

    /** El mapa es lo que la gente mira primero para decidir si le queda cerca. */
    public function test_el_mapa_se_ve_incrustado_en_la_pagina(): void
    {
        Sucursal::first()->update(['latitud' => 14.6231, 'longitud' => -90.5566]);

        $this->get($this->url('/contacto'))
            ->assertSee('<iframe', escape: false)
            ->assertSee('maps.google.com/maps?q=14.6231,-90.5566', escape: false)
            // Sin lazy, tres sucursales cargarían tres mapas de golpe.
            ->assertSee('loading="lazy"', escape: false);
    }

    /** Sin coordenadas la tarjeta no puede verse rota: se rellena con la marca. */
    public function test_sin_coordenadas_no_hay_iframe_pero_la_tarjeta_se_sostiene(): void
    {
        Sucursal::first()->update(['latitud' => null, 'longitud' => null, 'nombre' => 'Patio sin mapa']);

        $this->get($this->url('/contacto'))
            ->assertDontSee('<iframe', escape: false)
            ->assertSee('Patio sin mapa')
            ->assertSee('Llamanos y te damos la referencia exacta para llegar.');
    }

    public function test_sin_coordenadas_no_hay_botones_de_mapa(): void
    {
        Sucursal::first()->update(['latitud' => null, 'longitud' => null]);

        $this->get($this->url('/contacto'))->assertDontSee('Abrir en Waze');
    }

    /** Una bodega que no recibe clientes no tiene por qué salir. */
    public function test_una_sucursal_oculta_no_aparece(): void
    {
        Sucursal::first()->update(['nombre' => 'Bodega interna', 'mostrar_en_portal' => false]);

        $this->get($this->url('/contacto'))->assertDontSee('Bodega interna');
    }

    public function test_muestra_las_redes_que_el_cliente_lleno(): void
    {
        $this->empresa->update([
            'facebook' => 'importadoragomez',
            'instagram' => '@importadoragomez',
        ]);

        $this->get($this->url('/contacto'))
            ->assertSee('facebook.com/importadoragomez', escape: false)
            ->assertSee('instagram.com/importadoragomez', escape: false)
            ->assertDontSee('TikTok');
    }

    public function test_el_menu_lleva_a_encontranos(): void
    {
        $this->get($this->url())
            ->assertSuccessful()
            ->assertSee('Encontranos');
    }

    // ── Segmentación por tipo ───────────────────────────────────────────────

    public function test_los_chips_muestran_solo_los_tipos_con_stock(): void
    {
        $this->publicar(['tipo_vehiculo' => 'automovil', 'carroceria' => 'sedan']);
        $this->publicar(['tipo_vehiculo' => 'motocicleta', 'carroceria' => 'scooter']);

        $this->get($this->url('/vehiculos'))
            ->assertSee('Carros')
            ->assertSee('Motos')
            ->assertDontSee('Camiones y pesados');
    }

    public function test_filtrar_por_tipo_deja_solo_ese_tipo(): void
    {
        $auto = $this->publicar(['tipo_vehiculo' => 'automovil', 'stock_no' => 'AUT1']);
        $moto = $this->publicar(['tipo_vehiculo' => 'motocicleta', 'stock_no' => 'MOT1']);

        $this->get($this->url('/vehiculos?tipo_vehiculo=motocicleta'))
            ->assertSee($moto->stock_no)
            ->assertDontSee($auto->stock_no);
    }

    /** «¿Tenés camionetas?» es la pregunta real de un comprador. */
    public function test_las_carrocerias_salen_bajo_el_tipo_elegido(): void
    {
        $this->publicar(['tipo_vehiculo' => 'automovil', 'carroceria' => 'sedan']);
        $this->publicar(['tipo_vehiculo' => 'automovil', 'carroceria' => 'suv']);
        $this->publicar(['tipo_vehiculo' => 'motocicleta', 'carroceria' => 'scooter']);

        // Se miran los enlaces de los chips y no el texto suelto: el <select>
        // de la barra lateral lista todas las carrocerías que existen, tenga
        // stock o no.
        $this->get($this->url('/vehiculos?tipo_vehiculo=automovil'))
            ->assertSee('carroceria=sedan', escape: false)
            ->assertSee('carroceria=suv', escape: false)
            ->assertDontSee('carroceria=scooter', escape: false);
    }

    public function test_filtrar_por_carroceria_deja_solo_esa(): void
    {
        $sedan = $this->publicar(['carroceria' => 'sedan', 'stock_no' => 'SED1']);
        $suv = $this->publicar(['carroceria' => 'suv', 'stock_no' => 'SUV1']);

        $this->get($this->url('/vehiculos?carroceria=sedan'))
            ->assertSee($sedan->stock_no)
            ->assertDontSee($suv->stock_no);
    }

    /** Con un solo tipo los botones son ruido: no hay nada entre qué elegir. */
    public function test_con_un_solo_tipo_no_se_pintan_los_chips(): void
    {
        $this->publicar(['tipo_vehiculo' => 'motocicleta']);

        $this->get($this->url('/vehiculos'))->assertDontSee('Camiones y pesados');
    }

    // ── Coordenadas y WhatsApp ──────────────────────────────────────────────

    #[DataProvider('enlacesDeMapas')]
    public function test_saca_las_coordenadas_del_enlace(string $enlace, ?float $latitud): void
    {
        $punto = Coordenadas::desde($enlace);

        $latitud === null
            ? $this->assertNull($punto)
            : $this->assertSame($latitud, $punto['latitud']);
    }

    /** @return array<string, array{string, float|null}> */
    public static function enlacesDeMapas(): array
    {
        return [
            'el centro del mapa' => ['https://www.google.com/maps/@14.6034,-90.5539,17z', 14.6034],
            'el punto del lugar' => ['https://www.google.com/maps/place/X/data=!3d14.6349!4d-90.5069', 14.6349],
            'una búsqueda' => ['https://maps.google.com/?q=14.5891,-90.5515', 14.5891],
            'escritas a mano' => ['14.6349, -90.5069', 14.6349],
            'texto cualquiera' => ['por la Roosevelt', null],
            'el Atlántico, que es «sin dato»' => ['0.0, 0.0', null],
            'fuera del planeta' => ['200.0, 300.0', null],
        ];
    }

    #[DataProvider('numerosDeTelefono')]
    public function test_el_whatsapp_lleva_codigo_de_pais(?string $numero, ?string $esperado): void
    {
        $this->assertSame($esperado, WhatsApp::internacional($numero));
    }

    /** @return array<string, array{string|null, string|null}> */
    public static function numerosDeTelefono(): array
    {
        return [
            'local con guion' => ['5555-1234', '50255551234'],
            'ya trae el código' => ['502 5555 1234', '50255551234'],
            'con el signo más' => ['+502 5555-1234', '50255551234'],
            'de otro país' => ['1 305 555 0199', '13055550199'],
            'vacío' => ['', null],
            'nulo' => [null, null],
        ];
    }

    /** El código estaba escrito a mano en las vistas y se duplicaba. */
    public function test_no_se_duplica_el_codigo_de_pais_en_el_portal(): void
    {
        $this->empresa->update(['whatsapp' => '502 3780 4805']);

        $this->get($this->url())
            ->assertSee('wa.me/50237804805', escape: false)
            ->assertDontSee('wa.me/502502', escape: false);
    }
}
