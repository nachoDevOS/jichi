{{--
| Reporte de un tipo de servicio (ReporteController::pdf). A4 apaisado. DomPDF: todo con tablas.
| Las celdas llegan ya formateadas. Ver docs/modulos/REPORTES.md.
--}}
@php
    $bs = fn ($n) => number_format((float) $n, 2, ',', '.').' Bs';
    $numericas = collect($columnas)->map(fn ($c) => in_array($c['formato'], ['bs', 'kg'], true))->all();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de {{ $servicio }}</title>
    <style>
        @page { margin: 26pt 28pt 34pt 28pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5pt; color: #1e293b; }
        table { border-collapse: collapse; width: 100%; }
        .institucion { font-size: 10.5pt; font-weight: bold; color: #0f3d4c; }
        .sub { font-size: 7.5pt; color: #64748b; }
        .titulo { font-size: 14pt; font-weight: bold; color: #0f3d4c; }
        .linea { border-bottom: 2pt solid #0e7490; height: 6pt; margin-bottom: 8pt; }
        .totales td { border: 0.75pt solid #cbd5e1; padding: 5pt 8pt; width: 25%; }
        .totales .r { font-size: 6.5pt; color: #64748b; text-transform: uppercase; }
        .totales .v { font-size: 11pt; font-weight: bold; }
        .detalle { margin-top: 10pt; }
        .detalle th { background: #0f3d4c; color: #fff; font-size: 6.5pt; text-align: left; padding: 4pt; text-transform: uppercase; }
        .detalle td { padding: 3pt 4pt; border-bottom: 0.5pt solid #e2e8f0; }
        .detalle tr.par td { background: #f8fafc; }
        .detalle tfoot td { font-weight: bold; border-top: 1pt solid #0f3d4c; border-bottom: none; padding-top: 5pt; }
        .der { text-align: right; }
        .nota { font-size: 7.5pt; color: #64748b; margin-top: 8pt; }
    </style>
</head>
<body>

<table>
    <tr>
        <td style="width: 52pt;">@if ($escudo)<img src="{{ $escudo }}" style="width: 44pt; height: 44pt;" alt="">@endif</td>
        <td>
            <div class="institucion">{{ $institucion }}</div>
            <div class="sub">SEDAG · Generado {{ $generado }}</div>
        </td>
        <td style="text-align: right;">
            <div class="titulo">Reporte de {{ $servicio }}</div>
            <div class="sub">{{ $rango }}{{ $estado ? ' · Estado: '.$estado : '' }}</div>
        </td>
    </tr>
</table>
<div class="linea"></div>

<table class="totales">
    <tr>
        <td><div class="r">Trámites</div><div class="v">{{ number_format($totales['cantidad'], 0, ',', '.') }}</div></td>
        <td><div class="r">Monto liquidado</div><div class="v">{{ $bs($totales['monto']) }}</div></td>
        <td><div class="r">Cobrado (con recibo)</div><div class="v" style="color: #047857;">{{ $bs($totales['cobrado']) }}</div></td>
        <td><div class="r">Por cobrar (pendientes)</div><div class="v" style="color: #b45309;">{{ $bs($totales['por_cobrar']) }}</div></td>
    </tr>
</table>

@if (count($filas) === 0)
    <div class="nota">Ningún trámite de este servicio en el rango elegido.</div>
@else
    {{-- DomPDF repite el encabezado de la tabla en cada hoja. --}}
    <table class="detalle">
        <thead>
            <tr>
                @foreach ($columnas as $i => $c)
                    <th class="{{ $numericas[$i] ? 'der' : '' }}">{{ $c['etiqueta'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr class="{{ $loop->even ? 'par' : '' }}">
                    @foreach ($fila as $i => $celda)
                        <td class="{{ $numericas[$i] ? 'der' : '' }}">{{ $celda }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                @foreach ($columnas as $i => $c)
                    @if ($c['clave'] === 'monto')
                        <td class="der">{{ number_format($totales['monto'], 2, ',', '.') }}</td>
                    @elseif ($i === 0)
                        <td>Total ({{ number_format($totales['cantidad'], 0, ',', '.') }})</td>
                    @else
                        <td></td>
                    @endif
                @endforeach
            </tr>
        </tfoot>
    </table>
@endif

</body>
</html>
