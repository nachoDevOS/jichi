<?php

namespace App\Http\Controllers\Panel;

use App\Enums\CondicionProducto;
use App\Enums\EstadoGuia;
use App\Enums\MedioTransporte;
use App\Enums\TipoTransporte;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarGuiaRequest;
use App\Http\Requests\Panel\AnularGuiaRequest;
use App\Http\Requests\Panel\CargarPagoRequest;
use App\Http\Requests\Panel\EliminarGuiaRequest;
use App\Http\Requests\Panel\EmitirGuiaRequest;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaDetalle;
use App\Models\GuiaMovimiento;
use App\Models\ProductoHidrobiologico;
use App\Services\CargarPagoService;
use App\Services\ConfirmarPagoService;
use App\Services\EmitirGuiaService;
use App\Sireb\SirebException;
use App\Sireb\VistaSireb;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  Guías de movimiento — un traslado de producto (paso 4, rama comercializador)
 *
 * Mismo circuito que la faena: nace PENDIENTE con su liquidación en SIREB y,
 * cuando SIREB la da por pagada, queda aprobada —ahí sale el recibo—; recién
 * entonces ampara el traslado y se puede imprimir.
 */
class GuiaController extends Controller
{
    public function __construct(private readonly EmitirGuiaService $servicio) {}

    /**
     * Listado — GET /panel/guias
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'piscicultura' => $request->has('piscicultura') && $request->input('piscicultura') !== ''
                ? $request->boolean('piscicultura')
                : null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $guias = GuiaMovimiento::query()
            // Por el CARNET: la guía no guarda un beneficiario suelto. Van las
            // cinco partes del nombre y las tres de la cédula, porque
            // `nombreCompleto` y `documento_identidad` las concatenan.
            ->with([
                'recibo:id,recibible_type,recibible_id,numero_recibo',
                'carnet:id,beneficiario_id,tipo_actor,nro,fecha_emision,aprovechamiento_id,estado,fecha_vencimiento',
                'carnet.codigo',
                'carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                'asociacion:id,nombre,sigla',
            ])
            ->when($filtros['buscar'], function ($q, $termino) {
                $operador = Sql::like($q->getConnection());
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtoupper($termino)).'%';
                // SIN los ceros: la columna es un ENTERO y «000308» tecleado tal
                // cual no encuentra al 308. Sin dígitos se omite la condición:
                // un `like '%%'` traería la tabla entera.
                $digitos = ltrim(preg_replace('/\D/', '', $termino), '0');

                $q->where(fn ($s) => $s
                    ->whereHas('carnet.beneficiario', fn ($b) => $b->buscar($termino))
                    ->when($digitos !== '', fn ($n) => $n->orWhere('guias_movimiento.nro', 'like', '%'.$digitos.'%'))
                    // El origen y el destino son lo que un control pregunta:
                    // «¿qué salió para Santa Cruz esta semana?».
                    ->orWhere('origen', $operador, $like)
                    ->orWhere('destino', $operador, $like));
            })
            ->when($filtros['estado'], fn ($q, $estado) => $q->where('guias_movimiento.estado', $estado))
            ->when($filtros['piscicultura'] !== null, fn ($q) => $q->where(
                'guias_movimiento.es_piscicultura',
                $filtros['piscicultura'],
            ))
            // Por la SOLICITUD y no por la emisión: la emisión está en NULL
            // mientras la guía es un borrador, y esas filas irían al fondo.
            ->latest('fecha_solicitud')
            ->latest('id')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through($this->resumir(...));

        return Inertia::render('panel/guias/index', [
            'guias' => $guias,
            'filtros' => $filtros,
            'estados' => EstadoGuia::opciones(),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * Formulario — GET /panel/guias/crear
     */
    public function create(Request $request): Response
    {
        // `?carnet=` llega desde la ficha del carnet: resuelve también a la persona.
        $carnetElegido = $request->integer('carnet')
            ? Carnet::query()->find($request->integer('carnet'))
            : null;

        $beneficiarioId = $carnetElegido?->beneficiario_id ?? $request->integer('beneficiario');

        $beneficiario = $beneficiarioId
            ? Beneficiario::query()->find($beneficiarioId)
            : null;

        return Inertia::render('panel/guias/crear', [
            'beneficiario' => $beneficiario ? [
                'id' => $beneficiario->id,
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,

                // Misma forma que devuelve el autocompletado, para que la
                // pantalla trate igual a la persona preseleccionada y a la que
                // se busca a mano.
                'carnets_vigentes' => $beneficiario->carnets()
                    ->vigentes()
                    ->with(['codigo', 'tipoCarnet:id,nombre'])
                    ->get()
                    ->map(fn (Carnet $c): array => $c->resumenParaEmitir())
                    ->values()
                    ->all(),
            ] : null,

            // Se preelige solo si figura entre los vigentes: el formulario no lo valida.
            'carnetElegido' => $carnetElegido?->id,

            ...$this->catalogosDelFormulario(),
        ]);
    }

    /**
     * Emitir — POST /panel/guias
     */
    public function store(EmitirGuiaRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $guia = $this->servicio->emitir(
                Carnet::query()->findOrFail($datos['carnet_id']),
                $datos,
                $datos['detalles'],
                now()->parse($datos['fecha_solicitud']),
            );
        } catch (PermisoOperativoException $e) {
            // Nada de esto tiene arreglo desde otro campo del formulario: lo
            // que falla es el carnet elegido o el cuadro de productos.
            $campo = str_contains($e->getMessage(), 'detalle') ? 'detalles' : 'carnet_id';

            return back()->withInput()->withErrors([$campo => $e->getMessage()]);
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', "Guía N° {$guia->numero_legible} registrada. Cargue los depósitos y ".
                'envíela a revisión: recién con la firma ampara el traslado.');
    }

    /**
     * Corregir el borrador — GET /panel/guias/{guia}/editar
     */
    public function edit(GuiaMovimiento $guia): Response|RedirectResponse
    {
        if (! $guia->puedeEditarse()) {
            return redirect()
                ->route('guias.show', $guia)
                ->withErrors(['general' => PermisoOperativoException::guiaNoSePuedeEditar(
                    mb_strtolower($guia->estado->etiqueta()),
                )->getMessage()]);
        }

        $guia->load([
            'carnet:id,beneficiario_id,tipo_actor,nro,fecha_emision,aprovechamiento_id,estado,fecha_vencimiento',
            'carnet.codigo',
            'carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'detalles',
        ]);

        return Inertia::render('panel/guias/editar', [
            // La persona y el carnet llegan FIJOS: cambiar de titular no es
            // corregir, es emitir otro.
            'guia' => [
                'id' => $guia->id,
                'numero_legible' => $guia->numero_legible,
                'beneficiario' => $guia->carnet?->beneficiario?->nombreCompleto,
                'documento' => $guia->carnet?->beneficiario?->documento_identidad,
                'foto_url' => $guia->carnet?->beneficiario?->foto_url,
                'carnet_codigo' => $guia->carnet?->codigo_legible,
                'carnet_registro' => $guia->carnet?->registro_legible,

                ...$this->renglonesDelPapel($guia),
                'fecha_solicitud' => $guia->fecha_solicitud?->toDateString(),
                'detalles' => $this->resumirDetalle($guia),
            ],

            // Los que la guía ya usa siguen en la lista aunque hoy estén fuera de uso.
            ...$this->catalogosDelFormulario($guia->detalles->pluck('producto_id')->all()),
        ]);
    }

    /**
     * Guardar la corrección — PATCH /panel/guias/{guia}
     */
    public function update(ActualizarGuiaRequest $request, GuiaMovimiento $guia): RedirectResponse
    {
        $datos = $request->validated();

        try {
            // `validated()` devuelve SOLO las claves que vinieron, así que el
            // servicio normaliza cada renglón con `?? null`. Ver CLAUDE.md.
            $this->servicio->editar($guia, $datos, $datos['detalles']);
        } catch (PermisoOperativoException $e) {
            $campo = str_contains($e->getMessage(), 'detalle') ? 'detalles' : 'general';

            return back()->withInput()->withErrors([$campo => $e->getMessage()]);
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', "Guía N° {$guia->numero_legible} corregida.");
    }

    /**
     * Eliminar — DELETE /panel/guias/{guia}
     */
    public function destroy(EliminarGuiaRequest $request, GuiaMovimiento $guia, ConfirmarPagoService $pagos): RedirectResponse
    {
        $numero = $guia->numero_legible;

        try {
            $this->servicio->eliminar($guia, $request->validated()['motivo']);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        } catch (SirebException $e) {
            if (! $e->frenaPorPago()) {
                throw $e;
            }

            $resultado = $pagos->alNoPoderEliminar($guia);

            return redirect()->route('guias.show', $guia)->with($resultado['aprobado'] ? 'exito' : 'aviso', $resultado['mensaje']);
        }

        return redirect()
            ->route('guias.index')
            ->with('exito', "Guía N° {$numero} eliminada. El motivo quedó en la auditoría, y el ".
                'número del talonario queda con un hueco.');
    }

    /**
     * FICHA — GET /panel/guias/{guia}
     */
    public function show(GuiaMovimiento $guia): Response
    {
        $guia->load([
            'carnet:id,beneficiario_id,tipo_actor,asociacion_id,nro,fecha_emision,aprovechamiento_id,estado,fecha_vencimiento',
            'carnet.codigo',
            'carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'asociacion:id,nombre,sigla',
            'detalles',
            'codigo',
        ]);

        // El recibo del trámite —uno solo— y cuántas boletas faltan controlar.
        $recibo = $guia->recibo;

        return Inertia::render('panel/guias/ver', [
            'guia' => [
                ...$this->resumir($guia),
                // Solo en la ficha: en el listado haría una consulta por fila.
                'codigo' => $guia->codigo_legible,
                'asociacion_nombre' => $guia->asociacion?->nombre,

                'detalles' => $this->resumirDetalle($guia),
            ],

            // El recibo del trámite: uno solo, emitido al enviar a revisión.
            'recibo' => $recibo ? [
                'id' => $recibo->id,
                'numero_recibo' => $recibo->numero_recibo,
                'monto_total' => (float) $recibo->monto_total,
                'emitido_en' => $recibo->created_at?->toIso8601String(),
                'numero_boleta' => $recibo->numero_boleta,
                'entidad_bancaria' => $recibo->entidad_bancaria,
                'fecha_pago' => $recibo->fecha_pago?->toDateString(),
            ] : null,
        ]);
    }

    /**
     * Anular — PATCH /panel/guias/{guia}/anular
     */
    public function anular(AnularGuiaRequest $request, GuiaMovimiento $guia): RedirectResponse
    {
        try {
            $this->servicio->anular($guia, $request->validated()['motivo']);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', 'Guía anulada. El número queda ocupado: la hoja del talonario se gastó.');
    }

    /**
     * Verificar pago — POST /panel/guias/{guia}/verificar-pago
     *
     * Pregunta a SIREB; si está pagado, lo aprueba y emite el recibo.
     */
    public function verificarPago(GuiaMovimiento $guia, ConfirmarPagoService $pagos): RedirectResponse
    {
        $resultado = $pagos->verificar($guia);

        return back()->with($resultado['aprobado'] ? 'exito' : 'aviso', $resultado['mensaje']);
    }

    /**
     * Cargar pago — POST /panel/guias/{guia}/cargar-pago
     *
     * Lo carga en SIREB, que lo valida allá.
     */
    public function cargarPago(CargarPagoRequest $request, GuiaMovimiento $guia, CargarPagoService $carga): RedirectResponse
    {
        $datos = $request->validated();
        $resultado = $carga->cargar($guia, $datos['numero_transaccion'], $datos['banco']);

        return back()->with($resultado['cargado'] ? 'exito' : 'aviso', $resultado['mensaje']);
    }

    //  Auxiliares

    /**
     * Lo que los dos formularios necesitan del servidor.
     *
     * Los catálogos y la tarifa salen de acá y NO escritos en React: el
     * descuento de piscicultura es plata, y duplicarlo deja dos verdades.
     *
     * @return array<string, mixed>
     */
    private function catalogosDelFormulario(array $ademas = []): array
    {
        $precios = app(VistaSireb::class)->preciosPorTarifa() ?? [];

        return [
            'productos' => ProductoHidrobiologico::query()
                ->where(fn ($q) => $q->where('estado', true)->orWhereIn('id', $ademas))
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'tarifa_sireb', 'estado'])
                ->map(fn (ProductoHidrobiologico $p): array => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    // De referencia: el que vale lo congela la emisión. null sin SIREB o sin tarifa.
                    'precio_kg' => $precios[$p->tarifa_sireb] ?? null,
                    'estado' => (bool) $p->estado,
                ])
                ->all(),
            'medios' => MedioTransporte::opciones(),
            'tiposTransporte' => TipoTransporte::opciones(),
            'condiciones' => CondicionProducto::opciones(),
            'diasVigencia' => GuiaMovimiento::DIAS_VIGENCIA,
            'descuentoPiscicultura' => GuiaMovimiento::DESCUENTO_PISCICULTURA,
        ];
    }

    /**
     * Los renglones del papel, como los leen el formulario y la ficha.
     *
     * @return array<string, mixed>
     */
    private function renglonesDelPapel(GuiaMovimiento $guia): array
    {
        return [
            'origen' => $guia->origen,
            'origen_departamento' => $guia->origen_departamento,
            'origen_provincia' => $guia->origen_provincia,
            'origen_distrito' => $guia->origen_distrito,

            'destino' => $guia->destino,
            'destino_departamento' => $guia->destino_departamento,
            'destino_provincia' => $guia->destino_provincia,
            'destino_distrito' => $guia->destino_distrito,

            'medio_transporte' => $guia->medio_transporte?->value,
            'medio_transporte_etiqueta' => $guia->medio_transporte?->etiqueta(),
            'tipo_transporte' => $guia->tipo_transporte?->value,
            'tipo_transporte_etiqueta' => $guia->tipo_transporte?->etiqueta(),
            'transporte_nombre' => $guia->transporte_nombre,
            'transporte_placa' => $guia->transporte_placa,
            'transporte_capacidad_kg' => $guia->transporte_capacidad_kg !== null
                ? (float) $guia->transporte_capacidad_kg
                : null,

            'es_piscicultura' => (bool) $guia->es_piscicultura,
            'observaciones' => $guia->observaciones,
        ];
    }

    /**
     * El cuadro D, fila por fila.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resumirDetalle(GuiaMovimiento $guia): array
    {
        return $guia->detalles
            ->map(fn (GuiaDetalle $d): array => [
                'id' => $d->id,
                'producto_id' => $d->producto_id,
                'especie' => $d->especie,
                'condicion' => $d->condicion->value,
                'condicion_etiqueta' => $d->condicion->etiqueta(),
                'cantidad_kg' => (float) $d->cantidad_kg,
                'precio_kg' => (float) $d->precio_kg,
                'importe_total' => (float) $d->importe_total,
            ])
            ->values()
            ->all();
    }

    /**
     * Los datos de una guía que pintan el listado y la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumir(GuiaMovimiento $guia): array
    {
        $recibo = $guia->recibo;

        return [
            'id' => $guia->id,
            'nro' => $guia->nro,
            // Con los seis ceros del talonario: «000308».
            'numero_legible' => $guia->numero_legible,
            'etiqueta' => $guia->etiqueta,

            // Los dos salen del carnet: la guía no guarda a la persona.
            'carnet_id' => $guia->carnet_id,
            'carnet_codigo' => $guia->carnet?->codigo_legible,
            'carnet_registro' => $guia->carnet?->registro_legible,
            'beneficiario_id' => $guia->carnet?->beneficiario_id,
            'comercializador' => $guia->comercializador()?->nombreCompleto,
            'documento' => $guia->comercializador()?->documento_identidad,
            'foto_url' => $guia->comercializador()?->foto_url,
            'asociacion' => $guia->asociacion?->sigla ?? $guia->asociacion?->nombre,

            ...$this->renglonesDelPapel($guia),

            'ruta' => $guia->ruta,
            'peso_total_kg' => (float) $guia->peso_total_kg,

            // El factor va explícito para que la ficha pueda decir «se cobró al
            // 50%» sin recalcularlo: la regla vive en el modelo, en un solo lado.
            'factor_arancel' => $guia->factorArancel(),

            'estado' => $guia->estado->value,
            'estado_etiqueta' => $guia->etiquetaEstado(),
            'estado_color' => $guia->colorEstado(),
            'vigente' => $guia->estaVigente(),
            'caducada' => $guia->estaCaducada(),
            'horas_restantes' => $guia->horasRestantes(),

            // Las puertas del borrador: estado PENDIENTE y sin un peso cargado.
            // Se resuelven acá para que la pantalla no las recalcule.
            'puede_editarse' => $guia->puedeEditarse(),
            'puede_eliminarse' => $guia->puedeEliminarse(),
            'puede_anularse' => $guia->estado->permiteAnulacion(),
            'ya_fue_aprobada' => $guia->yaFueAprobada(),
            // El cobro está en SIREB: la ficha muestra su estado y ofrece verificar el pago.
            'sireb' => $guia->resumenSireb(),
            'puede_verificar_pago' => $guia->estado->estaAbierto(),
            'puede_cargar_pago' => $guia->puedeCargarPago(),
            // Por qué todavía no ampara. Se resuelve en el servidor: React no
            // vuelve a evaluar el estado.
            'motivo_sin_amparar' => $guia->motivoSinAmparar(),

            'monto' => $guia->montoACobrar(),

            // El recibo existe desde el ENVÍO; null mientras es borrador.
            'recibo_id' => $recibo?->id,
            'recibo_numero' => $recibo?->numero_recibo,

            // Un DÍA: con toDateString().
            'fecha_solicitud' => $guia->fecha_solicitud?->toDateString(),

            /*
             * Estas dos son MOMENTOS —los cinco días se cuentan desde la hora
             * de la firma— así que van con toIso8601String().
             */
            'fecha_emision' => $guia->fecha_emision?->toIso8601String(),
            'fecha_vencimiento' => $guia->fecha_vencimiento?->toIso8601String(),

            'registrado_en' => $guia->created_at?->toIso8601String(),
        ];
    }
}
