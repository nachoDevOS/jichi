{{--
    «NO VÁLIDO» encima de toda la hoja: la vista previa del portal de un trámite
    todavía abierto. `fixed` para que salga en cada página. Cuadrada y centrada:
    DomPDF no tiene object-fit, y estirada a la hoja la letra saldría deformada.
    Recibe el tamaño de la hoja en puntos: `ancho` y `alto`. Con `cubrir`, el cuadrado
    toma el lado MAYOR y la hoja recorta lo que sobra: en el carnet apaisado se lee mejor.
--}}
@if (! empty($marcaAgua))
    @php($lado = ($cubrir ?? false) ? max($ancho, $alto) : min($ancho, $alto))
    <img src="{{ $marcaAgua }}" alt=""
         style="position: fixed; left: {{ ($ancho - $lado) / 2 }}pt; top: {{ ($alto - $lado) / 2 }}pt; width: {{ $lado }}pt; height: {{ $lado }}pt; z-index: 1000;">
@endif
