{{--
    La hoja de etiquetas en PDF.

    Aparte de la vista del panel, y no por gusto: esto lo arma dompdf, que no
    entiende grid ni flex ni SVG en línea. Va con tablas y medidas en
    milímetros, como se maquetaba antes, porque es lo que ese motor sí sabe
    componer. Y el QR va como <img> con el dibujo dentro, que es justo al revés
    de lo que necesita el navegador.

    Nada de esto pide nada por red: un PDF se arma en el servidor y no puede
    salir a buscar una imagen.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiquetas · {{ $empresa?->getFilamentName() }}</title>
    <style>
        @page { margin: 8mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            color: #111827;
            margin: 0;
        }

        table.hoja { width: 100%; border-collapse: separate; border-spacing: 1.5mm; }
        table.hoja td { width: 33.33%; vertical-align: top; }

        .etiqueta { border: 0.4mm solid #d1d5db; border-radius: 2mm; }

        /* La banda va del color del cliente; el logo, sobre blanco. Su logo
           puede llevar ese mismo color dentro y desaparecería encima. */
        .banda { padding: 1.5mm; text-align: center; }
        .placa-del-logo { background: #ffffff; border-radius: 1mm; padding: 1mm 2mm; }
        .placa-del-logo img { height: 7mm; }

        .cuerpo { padding: 1.5mm 1.5mm 2mm; text-align: center; }
        .stock { font-size: 6pt; letter-spacing: 0.5mm; color: #9ca3af; text-transform: uppercase; }
        .titulo { font-size: 8.5pt; font-weight: bold; margin: 0.5mm 0 1.5mm; }
        .codigo { font-size: 11pt; font-weight: bold; letter-spacing: 1mm; margin-top: 1mm; }
        .pie { font-size: 5.5pt; color: #6b7280; border-top: 0.2mm dashed #d1d5db; padding-top: 0.8mm; margin-top: 1mm; }
        .sin-logo { color: #ffffff; font-weight: bold; font-size: 7pt; letter-spacing: 0.4mm; text-transform: uppercase; }
    </style>
</head>
<body>
<table class="hoja">
    @foreach ($unidades->chunk(3) as $fila)
        <tr>
            @foreach ($fila as $unidad)
                <td>
                    <div class="etiqueta">
                        <div class="banda" style="background: {{ $color }}">
                            @if ($logo)
                                <span class="placa-del-logo"><img src="{{ $logo }}" alt=""></span>
                            @else
                                <span class="sin-logo">{{ $nombre }}</span>
                            @endif
                        </div>

                        <div class="cuerpo">
                            <div class="stock">Stock {{ $unidad->stock_no }}</div>
                            <div class="titulo">{{ $unidad->descripcion }}</div>

                            <img src="{{ $qr[$unidad->getKey()] }}" width="94" height="94" alt="">

                            <div class="codigo">{{ $unidad->codigo_qr }}</div>

                            <div class="pie">Escaneá con la cámara: fotos, ficha y precio</div>
                        </div>
                    </div>
                </td>
            @endforeach

            {{-- Celdas vacías para que la última fila no se estire --}}
            @for ($i = $fila->count(); $i < 3; $i++)
                <td></td>
            @endfor
        </tr>
    @endforeach
</table>
</body>
</html>
