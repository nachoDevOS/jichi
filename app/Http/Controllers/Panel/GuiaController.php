<?php

namespace App\Http\Controllers\Panel;

use App\Enums\CondicionProducto;
use App\Enums\EstadoGuia;
use App\Enums\MedioTransporte;
use App\Enums\TipoTransporte;
use App\Exceptions\CobroInvalidoException;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Http\Requests\Panel\ActualizarGuiaRequest;
use App\Http\Requests\Panel\AnularGuiaRequest;
use App\Http\Requests\Panel\CerrarGuiaRequest;
use App\Http\Requests\Panel\EliminarGuiaRequest;
use App\Http\Requests\Panel\EmitirGuiaRequest;
use App\Http\Requests\Panel\RechazarGuiaRequest;
use App\Http\Requests\Panel\RegistrarDepositosRequest;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaDetalle;
use App\Models\GuiaMovimiento;
use App\Models\Pago;
use App\Models\Recibo;
use App\Services\CobrarService;
use App\Services\EmitirGuiaService;
use App\Services\RevisarGuiaService;
use App\Support\Archivos;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  GUÍAS DE MOVIMIENTO — un traslado de producto (paso 4, rama comercializador)
 *
 * Mismo circuito que la faena: nace PENDIENTE, se le cargan los depósitos, se
 * presenta a revisión —ahí sale el recibo— y recién con la firma ampara el
 * traslado y se puede imprimir.
 */
class GuiaController extends Controller
{
    public function __construct(private readonly EmitirGuiaService $servicio) {}

    /**
     * LISTADO — GET /panel/guias
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
                'carnet:id,beneficiario_id,tipo_actor,nro_registro,fecha_emision',
                'carnet.codigo',
                'carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                'asociacion:id,nombre,sigla',
                // El recibo del listado sale de los pagos ya cargados: ver reciboDe().
                'pagos:id,pagable_type,pagable_id,recibo_id,monto_parcial',
                'pagos.recibo:id,numero_recibo',
            ])
            // Evita una consulta agregada POR FILA al calcular el saldo.
            ->withSum('pagos', 'monto_parcial')
            ->when($filtros['buscar'], function ($q, $termino) {
                $operador = Sql::like($q->getConnection());
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtoupper($termino)).'%';
                // SIN los ceros: la columna es un ENTERO y «000308» tecleado tal
                // cual no encuentra al 308. Sin dígitos se omite la condición:
                // un `like '%%'` traería la tabla entera.
                $digitos = ltrim(preg_replace('/\D/', '', $termino), '0');

                $q->where(fn ($s) => $s
                    ->whereHas('carnet.beneficiario', fn ($b) => $b->buscar($termino))
                    ->when($digitos !== '', fn ($n) => $n->orWhere('numero_guia', 'like', '%'.$digitos.'%'))
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
     * FORMULARIO — GET /panel/guias/crear
     */
    public function create(Request $request): Response
    {
        $beneficiario = $request->integer('beneficiario')
            ? Beneficiario::query()->find($request->integer('beneficiario'))
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
                    ->map($this->resumirCarnetParaEmitir(...))
                    ->values()
                    ->all(),
            ] : null,

            ...$this->catalogosDelFormulario(),
        ]);
    }

    /**
     * EMITIR — POST /panel/guias
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
     * CORREGIR EL BORRADOR — GET /panel/guias/{guia}/editar
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
            'carnet:id,beneficiario_id,tipo_actor,nro_registro,fecha_emision',
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

            ...$this->catalogosDelFormulario(),
        ]);
    }

    /**
     * GUARDAR LA CORRECCIÓN — PATCH /panel/guias/{guia}
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
     * ELIMINAR — DELETE /panel/guias/{guia}
     */
    public function destroy(EliminarGuiaRequest $request, GuiaMovimiento $guia): RedirectResponse
    {
        $numero = $guia->numero_legible;

        try {
            $this->servicio->eliminar($guia, $request->validated()['motivo']);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
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
            'carnet:id,beneficiario_id,tipo_actor,asociacion_id,nro_registro,fecha_emision',
            'carnet.codigo',
            'carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'asociacion:id,nombre,sigla',
            'detalles',
        ]);

        // El recibo del trámite —uno solo— y cuántas boletas faltan controlar.
        $recibo = $guia->recibos()->first();
        $sinValidar = $guia->pagos()->sinValidar()->count();

        return Inertia::render('panel/guias/ver', [
            'guia' => [
                ...$this->resumir($guia),
                'asociacion_nombre' => $guia->asociacion?->nombre,

                // Las del circuito, resueltas en el servidor: React no vuelve a
                // evaluar el estado.
                'puede_enviarse' => $guia->puedeEnviarseARevision(),
                'puede_revisarse' => $guia->puedeRevisarse(),
                'puede_aprobarse' => $guia->puedeRevisarse() && $sinValidar === 0,
                'pagos_sin_validar' => $sinValidar,

                'detalles' => $this->resumirDetalle($guia),
            ],

            // El detalle de lo cobrado: una fila por boleta, con su control.
            'pagos' => $guia->pagos()
                // Precargados: si no, cinco depósitos son diez consultas.
                ->with(['registradoPor:id,name', 'validadoPor:id,name'])
                ->latest('created_at')
                ->get()
                // El trámite se le pone a mano: `admiteControl()` le pregunta
                // al `pagable`, y sin esto cada fila lo va a buscar a la base.
                ->each(fn (Pago $p) => $p->setRelation('pagable', $guia))
                ->map(fn (Pago $p): array => [
                    'id' => $p->id,
                    'monto_parcial' => (float) $p->monto_parcial,
                    'nro_transaccion' => $p->nro_transaccion,
                    'fecha_deposito' => $p->fecha_deposito?->toDateString(),
                    'comprobante_url' => $p->comprobante_url,
                    'cobrado_en' => $p->created_at?->toIso8601String(),
                    'estado_validacion' => $p->estado_validacion->value,
                    'estado_validacion_etiqueta' => $p->estado_validacion->etiqueta(),
                    'estado_validacion_color' => $p->estado_validacion->color(),
                    'observacion' => $p->observacion,
                    'registrado_por' => $p->registradoPor?->name,
                    'validado_por' => $p->validadoPor?->name,
                    'validado_en' => $p->validado_en?->toIso8601String(),
                    'puede_validarse' => $p->admiteControl(),
                    'puede_corregirse' => $p->admiteCorreccion(),
                ])
                ->all(),

            // El recibo del trámite: uno solo, emitido al enviar a revisión.
            'recibo' => $recibo ? [
                'id' => $recibo->id,
                'numero_recibo' => $recibo->numero_recibo,
                'monto_total' => (float) $recibo->monto_total,
                'emitido_en' => $recibo->created_at?->toIso8601String(),
                'pagos_count' => $recibo->pagos()->count(),
            ] : null,
        ]);
    }

    /**
     *  CARGAR LOS DEPÓSITOS — POST /panel/guias/{guia}/pagos
     *
     * Mismo circuito que el carnet, el cupo y la faena: las boletas entran
     * juntas, tienen que cubrir el arancel entero y, si el operador lo pide, la
     * guía queda presentada en el mismo acto.
     */
    public function pagar(
        RegistrarDepositosRequest $request,
        GuiaMovimiento $guia,
        CobrarService $caja,
        RevisarGuiaService $revision,
    ): RedirectResponse {
        $datos = $request->validated();

        // ANTES de subir nada: descubrirlo adentro obligaría a borrar los
        // archivos ya escritos, que una transacción no deshace.
        if (! $guia->admitePagos()) {
            return back()->withErrors([
                'pagos' => CobroInvalidoException::noAdmiteDepositos(
                    'La guía',
                    $guia->estado->etiqueta(),
                )->getMessage(),
            ]);
        }

        $suma = round(array_sum(array_map(
            static fn (array $p): float => round((float) $p['monto'], 2),
            $datos['pagos'],
        )), 2);

        $saldo = $guia->saldoPendiente();

        if ($suma < $saldo) {
            return back()->withInput()->withErrors([
                'pagos' => CobroInvalidoException::noCubreElMonto('esta guía', $suma, $saldo)->getMessage(),
            ]);
        }

        $subidos = [];

        try {
            foreach ($datos['pagos'] as $i => $pago) {
                $subidos[$i] = app(StorageController::class)
                    ->file($request->file("pagos.{$i}.comprobante"), 'comprobantes');
            }

            $depositos = [];

            foreach ($datos['pagos'] as $i => $pago) {
                $depositos[] = [
                    'monto' => (float) $pago['monto'],
                    'nro_transaccion' => $pago['nro_transaccion'],
                    'fecha_deposito' => $pago['fecha_deposito'],
                    'comprobante' => $subidos[$i],
                ];
            }

            // UNA sola llamada con todas las boletas, nunca una por depósito en un
            // foreach: partirlo gasta dos números de recibo. Ver CLAUDE.md.
            $caja->registrarDepositos($guia, $depositos);
        } catch (CobroInvalidoException $e) {
            foreach ($subidos as $ruta) {
                Archivos::borrar($ruta);
            }

            return back()->withInput()->withErrors(['pagos' => $e->getMessage()]);
        } catch (\Throwable $e) {
            foreach ($subidos as $ruta) {
                Archivos::borrar($ruta);
            }

            throw $e;
        }

        $guia->refresh();
        $cuantos = count($datos['pagos']);

        //  REGISTRAR Y ENVIAR SON UN SOLO ACTO CUANDO EL ARANCEL QUEDA CUBIERTO
        $enviada = false;

        if (($datos['enviar'] ?? false) && $guia->puedeEnviarseARevision()) {
            $revision->enviar($guia);
            $guia->refresh();
            $enviada = true;
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', match (true) {
                $enviada => sprintf(
                    '%d depósito(s) registrado(s) y enviada a revisión. Se emitió el recibo con el '.
                    'total; queda esperando la firma de quien la aprueba.',
                    $cuantos,
                ),
                $guia->saldoPendiente() <= 0.0 => sprintf(
                    '%d depósito(s) registrado(s). El arancel quedó cubierto: ya se puede enviar a revisión.',
                    $cuantos,
                ),
                default => sprintf(
                    '%d depósito(s) registrado(s). Quedan %s Bs por cobrar.',
                    $cuantos,
                    number_format($guia->saldoPendiente(), 2, ',', '.'),
                ),
            });
    }

    /**
     * ENVIAR A REVISIÓN — POST /panel/guias/{guia}/enviar
     */
    public function enviar(GuiaMovimiento $guia, RevisarGuiaService $revision): RedirectResponse
    {
        try {
            $revision->enviar($guia);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', 'Enviada a revisión. Se emitió el recibo con el total de los depósitos; '.
                'queda esperando la firma de quien la aprueba.');
    }

    /**
     * APROBAR — PATCH /panel/guias/{guia}/aprobar
     */
    public function aprobar(GuiaMovimiento $guia, RevisarGuiaService $revision): RedirectResponse
    {
        try {
            $revision->aprobar($guia);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', sprintf(
                'Guía aprobada. Ampara el traslado hasta el %s: ya se puede imprimir.',
                $guia->refresh()->fecha_vencimiento?->format('d/m/Y H:i') ?? '—',
            ));
    }

    /**
     * RECHAZAR — PATCH /panel/guias/{guia}/rechazar
     */
    public function rechazar(
        RechazarGuiaRequest $request,
        GuiaMovimiento $guia,
        RevisarGuiaService $revision,
    ): RedirectResponse {
        try {
            $revision->rechazar($guia, $request->validated()['motivo']);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', 'Guía devuelta a ventanilla con el motivo escrito. Los depósitos y el '.
                'recibo no se tocaron.');
    }

    /**
     * CERRAR — PATCH /panel/guias/{guia}/cerrar
     */
    public function cerrar(CerrarGuiaRequest $request, GuiaMovimiento $guia): RedirectResponse
    {
        $peso = $request->validated()['peso_total_kg'] ?? null;

        try {
            $this->servicio->cerrar($guia, $peso !== null ? (float) $peso : null);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['peso_total_kg' => $e->getMessage()]);
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', 'Guía cerrada. La carga llegó a destino.');
    }

    /**
     * ANULAR — PATCH /panel/guias/{guia}/anular
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

    //  Auxiliares

    /**
     * Lo que los dos formularios necesitan del servidor.
     *
     * Los catálogos y la tarifa salen de acá y NO escritos en React: el
     * descuento de piscicultura es plata, y duplicarlo deja dos verdades.
     *
     * @return array<string, mixed>
     */
    private function catalogosDelFormulario(): array
    {
        return [
            'medios' => MedioTransporte::opciones(),
            'tiposTransporte' => TipoTransporte::opciones(),
            'condiciones' => CondicionProducto::opciones(),
            'diasVigencia' => GuiaMovimiento::DIAS_VIGENCIA,
            'tarifaBase' => GuiaMovimiento::tarifaVigente(),
            'descuentoPiscicultura' => GuiaMovimiento::DESCUENTO_PISCICULTURA,
        ];
    }

    /**
     * Un carnet como lo necesita el formulario de emisión.
     *
     * @return array<string, mixed>
     */
    private function resumirCarnetParaEmitir(Carnet $carnet): array
    {
        return [
            'id' => $carnet->id,
            'codigo' => $carnet->codigo_legible,
            'tipo' => $carnet->tipoCarnet?->nombre,
            'tipo_actor' => $carnet->tipo_actor->value,
            'tipo_actor_etiqueta' => $carnet->tipo_actor->etiqueta(),
            'puede_emitir_faenas' => $carnet->puedeEmitirFaenas(),
            'puede_emitir_guias' => $carnet->puedeEmitirGuias(),
            'saldo_kg' => null,
        ];
    }

    /**
     * El recibo del trámite, sin una consulta por fila.
     *
     * `Pagable::recibos()` es una CONSULTA y no una relación, así que en un
     * listado hay que resolverlo desde los pagos ya precargados.
     */
    private function reciboDe(GuiaMovimiento $guia): ?Recibo
    {
        if ($guia->relationLoaded('pagos')) {
            return $guia->pagos->firstWhere('recibo_id', '!=', null)?->recibo;
        }

        return $guia->recibos()->first();
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
        $recibo = $this->reciboDe($guia);

        return [
            'id' => $guia->id,
            'numero_guia' => $guia->numero_guia,
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
            'estado_etiqueta' => $guia->estado->etiqueta(),
            'estado_color' => $guia->estado->color(),
            'vigente' => $guia->estaVigente(),
            'caducada' => $guia->estaCaducada(),
            'horas_restantes' => $guia->horasRestantes(),

            // Las puertas del borrador: estado PENDIENTE y sin un peso cargado.
            // Se resuelven acá para que la pantalla no las recalcule.
            'puede_editarse' => $guia->puedeEditarse(),
            'puede_eliminarse' => $guia->puedeEliminarse(),
            'puede_cerrarse' => $guia->estado === EstadoGuia::Activa,
            'puede_anularse' => $guia->estado === EstadoGuia::Activa,
            'ya_fue_aprobada' => $guia->yaFueAprobada(),
            'admite_pagos' => $guia->admitePagos(),
            // Por qué todavía no ampara. Se resuelve en el servidor: React no
            // vuelve a evaluar el estado.
            'motivo_sin_amparar' => $guia->motivoSinAmparar(),

            'monto' => $guia->montoACobrar(),
            'saldo_pendiente' => $guia->saldoPendiente(),
            'pagado' => $guia->estaPagado(),

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
