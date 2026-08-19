<?php

namespace App\Services;

use App\Enums\TipoVehiculo;
use App\Filament\Resources\Unidades\Schemas\UnidadForm;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Lee la tarjeta de circulación, el título americano o la hoja de subasta y
 * saca de ahí los datos del vehículo.
 *
 * Existe porque llenar veinte campos a mano por cada carro es donde el
 * vendedor se cansa y empieza a dejar la ficha incompleta. Lo que devuelve es
 * una propuesta: siempre la revisa una persona antes de guardar.
 */
class LectorDeDocumentos
{
    public function __construct(private ConversorDeDocumentos $conversor) {}

    /** Sin llave configurada, la función simplemente no se ofrece. */
    public function estaDisponible(): bool
    {
        return filled(config('services.openrouter.key'));
    }

    /**
     * Lee uno o varios documentos del mismo vehículo y combina lo que dicen.
     *
     * Van todos en la misma petición y no uno por uno: así el modelo puede
     * cruzarlos —confirmar el VIN que aparece en dos— y avisar cuando se
     * contradicen, que es justo lo que hay que saber.
     *
     * @param  array<int, string>|string  $rutas
     * @return array{datos: array<string, mixed>, documentos: array<int, string>, aviso: ?string}
     */
    public function leer(array|string $rutas): array
    {
        if (! $this->estaDisponible()) {
            throw new RuntimeException('Falta configurar OPENROUTER_API_KEY en el archivo .env.');
        }

        $imagenes = $this->conversor->variosAImagenes(is_array($rutas) ? $rutas : [$rutas]);

        if ($imagenes === []) {
            throw new RuntimeException('No se pudo leer ningún archivo. Probá con fotos en JPG o PNG.');
        }

        return $this->interpretar($this->preguntar($imagenes));
    }

    /** @param array<int, string> $imagenes rutas de imágenes ya listas */
    protected function preguntar(array $imagenes): string
    {
        $contenido = [['type' => 'text', 'text' => $this->instrucciones()]];

        foreach ($imagenes as $imagen) {
            $contenido[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $this->comoDataUri($imagen)],
            ];
        }

        try {
            $respuesta = Http::withToken(config('services.openrouter.key'))
                ->withHeaders([
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ])
                ->timeout(120)
                ->retry(2, 2000, throw: false)
                ->post(config('services.openrouter.url'), [
                    'model' => config('services.openrouter.modelo'),
                    'temperature' => 0,   // datos de un documento: nada de creatividad
                    'messages' => [['role' => 'user', 'content' => $contenido]],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('No se pudo conectar con el servicio de lectura. Revisá tu conexión.');
        }

        if ($respuesta->failed()) {
            Log::warning('OpenRouter respondió con error', [
                'status' => $respuesta->status(),
                'body' => $respuesta->json('error.message') ?? substr($respuesta->body(), 0, 300),
            ]);

            throw new RuntimeException(match ($respuesta->status()) {
                401, 403 => 'La llave de OpenRouter no es válida o no tiene permiso.',
                402 => 'Se agotó el crédito de OpenRouter.',
                429 => 'Demasiadas lecturas seguidas. Esperá un momento y volvé a intentar.',
                default => 'El servicio de lectura no respondió bien. Intentá de nuevo.',
            });
        }

        $texto = $respuesta->json('choices.0.message.content');

        if (blank($texto)) {
            throw new RuntimeException('El servicio no devolvió nada legible.');
        }

        return $texto;
    }

    /** Lo que se le pide al modelo. Explícito para que no invente. */
    protected function instrucciones(): string
    {
        $transmisiones = implode(', ', array_keys(UnidadForm::TRANSMISIONES));
        $combustibles = implode(', ', array_keys(UnidadForm::COMBUSTIBLES));
        $tracciones = implode(', ', array_keys(UnidadForm::TRACCIONES));
        $carrocerias = implode(', ', array_keys(TipoVehiculo::todasLasCarrocerias()));
        $tipos = implode(', ', array_column(TipoVehiculo::cases(), 'value'));
        $titulos = implode(', ', array_keys(UnidadForm::TIPOS_TITULO));

        return <<<TXT
        Sos un asistente que lee documentos de vehículos y extrae sus datos.

        Puede que recibas VARIAS imágenes. Todas son del MISMO vehículo, y cada
        una puede ser una tarjeta de circulación de Guatemala, un título de
        Estados Unidos (certificate of title), la hoja de un lote de subasta
        (Copart, IAAI) o una página distinta del mismo documento.

        Tu trabajo es combinarlas: tomá cada dato de la imagen donde aparezca y
        armá una sola ficha con todo lo que encuentres entre todas.

        Si dos documentos dicen cosas distintas del mismo campo:
        - Quedate con el del documento más formal, en este orden: tarjeta de
          circulación, título de Estados Unidos, hoja de subasta.
        - Y explicá la diferencia en "aviso". No la escondas: que la vea la
          persona que va a guardar la ficha.

        Devolvé ÚNICAMENTE un objeto JSON, sin texto antes ni después, sin bloques
        de código, con exactamente esta forma:

        {
          "documentos": ["tarjeta_circulacion" | "titulo_usa" | "hoja_subasta" | "otro"],
          "datos": {
            "tipo_vehiculo": {$tipos}|null,
            "vin": string|null,
            "marca": string|null,
            "linea": string|null,
            "version": string|null,
            "anio": number|null,
            "color": string|null,
            "motor": string|null,
            "cilindros": number|null,
            "cilindrada_cc": number|null,
            "puertas": number|null,
            "odometro": number|null,
            "odometro_unidad": "mi"|"km"|null,
            "transmision": {$transmisiones}|null,
            "combustible": {$combustibles}|null,
            "traccion": {$tracciones}|null,
            "carroceria": {$carrocerias}|null,
            "tipo_titulo": {$titulos}|null,
            "tipo_dano": string|null,
            "placa": string|null
          },
          "aviso": string|null
        }

        En "documentos" listá los tipos que reconociste, uno por documento
        distinto que hayas visto.

        Reglas:
        - Si un dato no aparece en el documento, poné null. NO lo adivines ni lo
          deduzcas del modelo del carro.
        - El VIN tiene exactamente 17 caracteres, sin las letras I, O ni Q. Si lo
          que ves no cumple eso, devolvé null.
        - "marca" y "linea" van por separado: de "TOYOTA RAV4", marca es "Toyota"
          y linea es "RAV4". Escribilos con mayúscula inicial, no en mayúsculas
          sostenidas.
        - "anio" es el año del modelo, entre 1980 y 2030.
        - El odómetro va en número entero, sin comas ni puntos.
        - Los campos con lista de valores solo aceptan uno de esos valores exactos.
        - Fijate primero si es automóvil, motocicleta o camión, y poné eso en
          "tipo_vehiculo". Cambia lo que tiene sentido pedir:
          · Una MOTOCICLETA no tiene puertas, ni color de interior, ni tracción:
            dejá esos tres en null aunque creas saberlos. En cambio la
            cilindrada en centímetros cúbicos ("cilindrada_cc") es su dato más
            importante; en los documentos aparece como CC, C.C. o "cilindraje".
          · Para moto, "carroceria" es su estilo: scooter, deportiva, naked,
            doble_proposito, cross, touring, custom o tres_ruedas.
          · Un CAMIÓN tampoco lleva color de interior.
        - En "aviso" poné una frase corta en español si algo quedó dudoso, si un
          documento estaba ilegible o si dos documentos se contradicen; si todo
          se leyó bien y no hubo conflictos, poné null.
        TXT;
    }

    protected function comoDataUri(string $ruta): string
    {
        $tipo = mime_content_type($ruta) ?: 'image/jpeg';

        return 'data:'.$tipo.';base64,'.base64_encode(file_get_contents($ruta));
    }

    /** @return array{datos: array<string, mixed>, documentos: array<int, string>, aviso: ?string} */
    protected function interpretar(string $texto): array
    {
        $json = $this->extraerJson($texto);

        if ($json === null) {
            throw new RuntimeException('No se entendió la respuesta del servicio. Probá con fotos más nítidas.');
        }

        return [
            'datos' => ValidadorDeDatosLeidos::limpiar($json['datos'] ?? []),
            'documentos' => $this->tiposReconocidos($json),
            'aviso' => is_string($json['aviso'] ?? null) ? $json['aviso'] : null,
        ];
    }

    /**
     * Acepta tanto la lista nueva como el campo viejo en singular, por si el
     * modelo responde a la antigua.
     *
     * @return array<int, string>
     */
    protected function tiposReconocidos(array $json): array
    {
        $tipos = $json['documentos'] ?? $json['tipo_documento'] ?? [];

        return collect(is_array($tipos) ? $tipos : [$tipos])
            ->filter(fn ($tipo) => is_string($tipo) && $tipo !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** El modelo a veces envuelve el JSON en ```json aunque se le pida que no. */
    protected function extraerJson(string $texto): ?array
    {
        $limpio = trim(preg_replace('/^```(?:json)?|```$/mi', '', trim($texto)));

        $decodificado = json_decode($limpio, true);

        if (is_array($decodificado)) {
            return $decodificado;
        }

        if (preg_match('/\{.*\}/s', $limpio, $coincidencias)) {
            $decodificado = json_decode($coincidencias[0], true);

            return is_array($decodificado) ? $decodificado : null;
        }

        return null;
    }
}
