<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoAprovechamiento;
use App\Exceptions\CobroInvalidoException;
use App\Exceptions\CupoInvalidoException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Http\Requests\Panel\EliminarCupoRequest;
use App\Http\Requests\Panel\OtorgarCupoRequest;
use App\Http\Requests\Panel\RechazarCupoRequest;
use App\Http\Requests\Panel\RegistrarPagoCupoRequest;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\CategoriaAprovechamiento;
use App\Models\Pago;
use App\Models\PermisoFaena;
use App\Services\CobrarService;
use App\Services\OtorgarCupoService;
use App\Services\RevisarCupoService;
use App\Support\Archivos;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  APROVECHAMIENTOS — la BOLSA MADRE del pescador (paso 2 del flujo)
 */
class AprovechamientoController extends Controller
{
    public function __construct(private readonly OtorgarCupoService $servicio) {}

    /**
     * LISTADO — GET /panel/aprovechamientos
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $cupos = AprovechamientoPesq::query()
            /*
             * OJO CON PEDIR COLUMNAS SUELTAS: el beneficiario va con las CINCO
             * partes del nombre porque `nombreCompleto` las lee todas. Una
             * columna que el modelo consulta y no está en el select vuelve null
             * y el accesor contesta cualquier cosa, sin ningún error.
             */
            ->with([
                'beneficiario:id,ci,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
                'categoria',
            ])
            /*
             * Los DOS withSum son lo que evita dos consultas agregadas POR FILA:
             * una para los kilos consumidos y otra para lo cobrado. Con 30 cupos
             * en pantalla son 61 consultas sin ellos, y el listado se ve igual.
             */
            ->withSum('faenasQueConsumen', 'kilos_extraidos')
            ->withSum('pagos', 'monto_parcial')
            ->when($filtros['buscar'], fn ($q, $termino) => $q->whereHas(
                'beneficiario',
                fn ($b) => $b->buscar($termino),
            ))
            ->when($filtros['estado'], fn ($q, $estado) => $q->where(
                'aprovechamientos_pesq.estado',
                $estado,
            ))
            ->latest('fecha_emision')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through($this->resumir(...));

        return Inertia::render('panel/aprovechamientos/index', [
            'cupos' => $cupos,
            'filtros' => $filtros,
            'estados' => EstadoAprovechamiento::opciones(),
            'opcionesPorPagina' => Paginacion::OPCIONES,

            /*
             * EL MODO SE MANDA A LA PANTALLA, y no es un detalle informativo.
             */
            'modoEstricto' => AprovechamientoPesq::modoEstricto(),
        ]);
    }

    /**
     * FORMULARIO — GET /panel/aprovechamientos/crear
     */
    public function create(Request $request): Response
    {
        $beneficiario = $request->integer('beneficiario')
            ? Beneficiario::query()->find($request->integer('beneficiario'))
            : null;

        return Inertia::render('panel/aprovechamientos/crear', [
            'beneficiario' => $beneficiario ? [
                'id' => $beneficiario->id,
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,
            ] : null,

            'escala' => $this->tramosElegibles(),
        ]);
    }

    /**
     * OTORGAR — POST /panel/aprovechamientos
     */
    public function store(OtorgarCupoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $cupo = $this->servicio->otorgar(
                Beneficiario::query()->findOrFail($datos['beneficiario_id']),
                CategoriaAprovechamiento::query()->findOrFail($datos['categoria_aprov_id']),
                now()->parse($datos['fecha_emision']),
                $datos['tipo_embarcacion'] ?? null,
            );
        } catch (CupoInvalidoException $e) {
            /*
             * El mensaje del servicio se devuelve como error DEL CAMPO y no como
             * un aviso suelto arriba de la pantalla. La regla que falló es sobre
             * la persona elegida, así que el texto tiene que aparecer al lado de
             * ese campo: un cartel arriba obliga al operador a adivinar qué
             * corregir.
             */
            return back()
                ->withInput()
                ->withErrors(['beneficiario_id' => $e->getMessage()]);
        }

        /*
         *  OTORGAR TERMINA EN LA FICHA DEL CUPO
         */
        return redirect()
            ->route('aprovechamientos.show', $cupo)
            ->with('exito', sprintf(
                'Cupo otorgado: %s kg por %s Bs. Cargue los depósitos para poder enviarlo a revisión.',
                number_format((float) $cupo->volumen_total_kg, 2, ',', '.'),
                number_format($cupo->montoACobrar(), 2, ',', '.'),
            ));
    }

    /**
     * FICHA — GET /panel/aprovechamientos/{aprovechamiento}
     */
    public function show(AprovechamientoPesq $aprovechamiento): Response
    {
        $aprovechamiento->load([
            'beneficiario:id,ci,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'categoria',
        ]);

        /*
         * El recibo del trámite: uno solo, emitido al enviar a revisión.
         * `recibos()` devuelve colección porque el trait sirve también al carnet
         * y a la guía; acá el primero es el único. NULL mientras está pendiente.
         */
        $recibo = $aprovechamiento->recibos()->first();

        /*
         * Cuántas boletas quedan sin dar por buenas, observadas incluidas. Acá y
         * no en `resumir()`: el listado lo usa para treinta filas.
         */
        $sinValidar = $aprovechamiento->pagos()->sinValidar()->count();

        return Inertia::render('panel/aprovechamientos/ver', [
            'cupo' => [
                ...$this->resumir($aprovechamiento),

                // La ficha sí muestra el detalle del tramo: es donde alguien va
                // a mirar bajo qué resolución se otorgó.
                'escala_descripcion' => $aprovechamiento->categoria?->descripcion_kg,
                'escala_rango' => $aprovechamiento->categoria
                    ? [(float) $aprovechamiento->categoria->kilos_min, (float) $aprovechamiento->categoria->kilos_max]
                    : null,

                // Aprobar no es «estar en revisión»: es eso Y que no falte
                // ninguna boleta por validar. El número va para decir cuántas.
                'pagos_sin_validar' => $sinValidar,
                'puede_aprobarse' => $aprovechamiento->puedeRevisarse() && $sinValidar === 0,
            ],

            'modoEstricto' => AprovechamientoPesq::modoEstricto(),

            /*
             *  LOS DEPÓSITOS QUE PAGARON ESTE CUPO, CON SU BOLETA
             */
            'pagos' => $aprovechamiento->pagos()
                // Precargados: si no, cinco depósitos son diez consultas.
                ->with(['registradoPor:id,name', 'validadoPor:id,name'])
                ->latest('created_at')
                ->get()
                /*
                 * El trámite se le pone a mano: `admiteControl()` le pregunta al
                 * `pagable`, y sin esto cada fila lo va a buscar a la base — es
                 * el mismo cupo que ya está en la mano.
                 */
                ->each(fn (Pago $p) => $p->setRelation('pagable', $aprovechamiento))
                ->map(fn (Pago $p): array => [
                    'id' => $p->id,
                    'monto_parcial' => (float) $p->monto_parcial,
                    'nro_transaccion' => $p->nro_transaccion,
                    'fecha_deposito' => $p->fecha_deposito?->toDateString(),
                    'comprobante_url' => $p->comprobante_url,
                    // Un MOMENTO: cuándo entró la plata. Va con toIso8601String().
                    'cobrado_en' => $p->created_at?->toIso8601String(),

                    // El control de la boleta. `puede_*` llegan resueltas: la
                    // regla mira el estado del TRÁMITE, no solo el del pago.
                    'estado_validacion' => $p->estado_validacion->value,
                    'estado_validacion_etiqueta' => $p->estado_validacion->etiqueta(),
                    'estado_validacion_color' => $p->estado_validacion->color(),
                    'observacion' => $p->observacion,
                    'registrado_por' => $p->registradoPor?->name,
                    'validado_por' => $p->validadoPor?->name,
                    // Otro MOMENTO: cuándo se miró la boleta.
                    'validado_en' => $p->validado_en?->toIso8601String(),
                    'puede_validarse' => $p->admiteControl(),
                    'puede_corregirse' => $p->admiteCorreccion(),
                ])
                ->all(),

            // Para la cabecera de la tarjeta de Pagos. El número no va repetido
            // en cada fila: repetirlo hacía leer «un recibo por depósito».
            'recibo' => $recibo ? [
                'id' => $recibo->id,
                'numero_recibo' => $recibo->numero_recibo,
                'monto_total' => (float) $recibo->monto_total,
                'emitido_en' => $recibo->created_at?->toIso8601String(),
            ] : null,

            /*
             * Las faenas que colgaron de este cupo, de la más nueva a la más
             * vieja. Es el detalle que explica el saldo: sin él, «le quedan 20
             * kg» es un número que hay que creer.
             */
            'faenas' => $aprovechamiento->faenas()
                ->with('carnet:id,codigo_carnet')
                ->orderByDesc('numero_faena')
                ->get()
                ->map(fn (PermisoFaena $f): array => [
                    'id' => $f->id,
                    'numero_faena' => $f->numero_faena,
                    'kilos_extraidos' => (float) $f->kilos_extraidos,
                    'estado' => $f->estado->value,
                    'estado_etiqueta' => $f->estado->etiqueta(),
                    'estado_color' => $f->estado->color(),
                    // Una faena vencida LIBERA su volumen: la pantalla lo marca
                    // para que el saldo cuadre a la vista.
                    'consume_cupo' => $f->consumeCupo(),
                    'fecha_salida' => $f->fecha_salida?->toDateString(),
                    'fecha_limite' => $f->fecha_limite?->toDateString(),
                ])
                ->all(),
        ]);
    }

    /**
     * FORMULARIO DE CORRECCIÓN — GET /panel/aprovechamientos/{id}/editar
     */
    public function edit(AprovechamientoPesq $aprovechamiento): Response|RedirectResponse
    {
        if (! $aprovechamiento->puedeEditarse()) {
            return redirect()
                ->route('aprovechamientos.show', $aprovechamiento)
                ->withErrors(['general' => CupoInvalidoException::noSePuedeEditar(
                    $aprovechamiento->estado->etiqueta(),
                )->getMessage()]);
        }

        $aprovechamiento->load([
            'beneficiario:id,ci,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'categoria',
        ]);

        return Inertia::render('panel/aprovechamientos/editar', [
            /*
             * La persona llega con la MISMA forma que usa el autocompletado, y
             * el formulario la muestra fija: cambiar de titular no es corregir
             * un cupo, es otorgar otro. Dejarlo elegible abriría la puerta a
             * mover una autorización de una persona a otra sin ningún rastro.
             */
            'cupo' => [
                'id' => $aprovechamiento->id,
                'beneficiario_id' => $aprovechamiento->beneficiario_id,
                'beneficiario' => $aprovechamiento->beneficiario?->nombreCompleto,
                'documento' => $aprovechamiento->beneficiario?->documento_identidad,
                'foto_url' => $aprovechamiento->beneficiario?->foto_url,
                'categoria_aprov_id' => $aprovechamiento->categoria_aprov_id,
                'tipo_embarcacion' => $aprovechamiento->tipo_embarcacion,
                'fecha_emision' => $aprovechamiento->fecha_emision?->toDateString(),
            ],

            'escala' => $this->tramosElegibles(),
        ]);
    }

    /**
     * GUARDAR LA CORRECCIÓN — PUT /panel/aprovechamientos/{id}
     */
    public function update(OtorgarCupoRequest $request, AprovechamientoPesq $aprovechamiento): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $this->servicio->editar(
                $aprovechamiento,
                CategoriaAprovechamiento::query()->findOrFail($datos['categoria_aprov_id']),
                now()->parse($datos['fecha_emision']),
                $datos['tipo_embarcacion'] ?? null,
            );
        } catch (CupoInvalidoException $e) {
            // Cuelga del tramo y no de la persona: al corregir, el titular no se
            // toca, así que el único campo con el que el operador puede
            // reaccionar es la escala.
            return back()->withInput()->withErrors(['categoria_aprov_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('aprovechamientos.show', $aprovechamiento)
            ->with('exito', 'Aprovechamiento corregido.');
    }

    /**
     * ELIMINAR — DELETE /panel/aprovechamientos/{id}
     */
    public function destroy(EliminarCupoRequest $request, AprovechamientoPesq $aprovechamiento): RedirectResponse
    {
        $persona = $aprovechamiento->beneficiario?->nombreCompleto ?? 'el pescador';

        try {
            $this->servicio->eliminar($aprovechamiento, $request->validated()['motivo']);
        } catch (CupoInvalidoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('aprovechamientos.index')
            ->with('exito', "Aprovechamiento de {$persona} eliminado. El motivo quedó en la auditoría.");
    }

    /**
     *  CARGAR LOS DEPÓSITOS — POST /panel/aprovechamientos/{id}/pagos
     */
    public function pagar(
        RegistrarPagoCupoRequest $request,
        AprovechamientoPesq $aprovechamiento,
        CobrarService $caja,
        RevisarCupoService $revision,
    ): RedirectResponse {
        $datos = $request->validated();
        $aprovechamiento->loadMissing('beneficiario');

        /*
         * SE COMPRUEBA EL ESTADO ANTES DE SUBIR NADA. Un cupo en revisión o ya
         * aprobado no admite pagos, y descubrirlo después de escribir cinco
         * archivos obligaría a borrarlos.
         */
        if (! $aprovechamiento->admitePagos()) {
            return back()->withErrors([
                'pagos' => CupoInvalidoException::noAdmitePagos(
                    $aprovechamiento->estado->etiqueta(),
                )->getMessage(),
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

            $caja->registrarDepositos($aprovechamiento, $depositos);
        } catch (CobroInvalidoException $e) {
            foreach ($subidos as $ruta) {
                Archivos::borrar($ruta);
            }

            // Cuelga de `pagos` porque lo que falla es cuánto se está cobrando,
            // y el operador corrige los montos de las secciones.
            return back()->withInput()->withErrors(['pagos' => $e->getMessage()]);
        } catch (\Throwable $e) {
            foreach ($subidos as $ruta) {
                Archivos::borrar($ruta);
            }

            throw $e;
        }

        $cupo = $aprovechamiento->refresh();
        $cuantos = count($datos['pagos']);

        /*
         *  REGISTRAR Y ENVIAR SON UN SOLO ACTO CUANDO EL MONTO QUEDA CUBIERTO
         */
        $enviado = false;

        if (($datos['enviar'] ?? false) && $cupo->puedeEnviarseARevision()) {
            // Viajan hasta acá porque el recibo lo emite el ENVÍO. Pueden ser
            // los de un tercero: la empresa que paga por el pescador.
            $revision->enviar(
                $cupo,
                $datos['nit_ci_factura'] ?? null,
                $datos['nombre_factura'] ?? null,
            );

            $cupo->refresh();
            $enviado = true;
        }

        return redirect()
            ->route('aprovechamientos.show', $aprovechamiento)
            ->with('exito', match (true) {
                $enviado => sprintf(
                    '%d depósito(s) registrado(s) y enviado a revisión. Se emitió el recibo del trámite '.
                    'con el total; queda esperando la firma de quien lo aprueba.',
                    $cuantos,
                ),
                $cupo->saldoPendiente() <= 0.0 => sprintf(
                    '%d depósito(s) registrado(s). El monto quedó cubierto: ya se puede enviar a revisión.',
                    $cuantos,
                ),
                default => sprintf(
                    '%d depósito(s) registrado(s). Quedan %s Bs por cobrar.',
                    $cuantos,
                    number_format($cupo->saldoPendiente(), 2, ',', '.'),
                ),
            });
    }

    /**
     * ENVIAR A REVISIÓN — POST /panel/aprovechamientos/{id}/enviar
     */
    public function enviar(AprovechamientoPesq $aprovechamiento, RevisarCupoService $revision): RedirectResponse
    {
        try {
            $revision->enviar($aprovechamiento);
        } catch (CupoInvalidoException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()
            ->route('aprovechamientos.show', $aprovechamiento)
            ->with('exito', 'Enviado a revisión. Se emitió el recibo del trámite con el total de los '.
                'depósitos; queda esperando la firma de quien lo aprueba.');
    }

    /**
     * APROBAR — PATCH /panel/aprovechamientos/{id}/aprobar
     *
     * Recién acá el cupo autoriza faenas.
     */
    public function aprobar(AprovechamientoPesq $aprovechamiento, RevisarCupoService $revision): RedirectResponse
    {
        try {
            $revision->aprobar($aprovechamiento);
        } catch (CupoInvalidoException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()
            ->route('aprovechamientos.show', $aprovechamiento)
            ->with('exito', 'Aprobado. El aprovechamiento quedó activo y ya autoriza faenas.');
    }

    /**
     * RECHAZAR — PATCH /panel/aprovechamientos/{id}/rechazar
     *
     * Vuelve a PENDIENTE con el motivo escrito. Los pagos ya cargados siguen
     * ahí: ventanilla corrige y lo vuelve a presentar sin recargar nada.
     */
    public function rechazar(RechazarCupoRequest $request, AprovechamientoPesq $aprovechamiento, RevisarCupoService $revision): RedirectResponse
    {
        try {
            $revision->rechazar($aprovechamiento, $request->validated()['motivo']);
        } catch (CupoInvalidoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('aprovechamientos.show', $aprovechamiento)
            ->with('exito', 'Rechazado y devuelto a ventanilla. El motivo quedó en la auditoría.');
    }

    //  Auxiliares

    /**
     * Los tramos que el operador puede elegir, con sus consecuencias.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tramosElegibles(): array
    {
        return CategoriaAprovechamiento::query()
            ->vigentes()
            ->enOrdenDeEscala()
            ->get()
            ->map(fn (CategoriaAprovechamiento $c): array => [
                'id' => $c->id,
                'nro_escala' => $c->nro_escala,
                'descripcion_kg' => $c->descripcion_kg,
                'kilos_min' => (float) $c->kilos_min,
                'kilos_max' => (float) $c->kilos_max,
                'valor_bs' => (float) $c->valor_bs,
                // El régimen del tramo: la escala progresiva o la cuota de una
                // especie con tasación fija. Se muestra ANTES de otorgarlo.
                'modalidad' => $c->modalidad->value,
                'modalidad_etiqueta' => $c->modalidad->etiqueta(),
                'modalidad_descripcion' => $c->modalidad->descripcion(),
            ])
            ->all();
    }

    /**
     * Los datos de un cupo que pintan el listado y la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumir(AprovechamientoPesq $cupo): array
    {
        return [
            'id' => $cupo->id,
            'beneficiario_id' => $cupo->beneficiario_id,
            'beneficiario' => $cupo->beneficiario?->nombreCompleto,
            'documento' => $cupo->beneficiario?->documento_identidad,
            // La foto va con el nombre y la cédula en la misma celda del
            // listado. Sale de un accesor que lee la columna `foto`, así que esa
            // columna TIENE que estar en el select de arriba.
            'foto_url' => $cupo->beneficiario?->foto_url,

            'escala' => $cupo->categoria?->nro_escala,
            'descripcion' => $cupo->categoria?->descripcion_kg,

            'volumen_total_kg' => (float) $cupo->volumen_total_kg,
            /*
             * Lo que el pescador declaró que navega. Va NULL y no una cadena
             * vacía cuando no se declaró, para que la pantalla pueda decir «no
             * declarada» en vez de imprimir un renglón en blanco.
             */
            'tipo_embarcacion' => $cupo->tipo_embarcacion,
            'kilos_consumidos' => $cupo->kilosConsumidos(),
            'saldo_kg' => $cupo->saldoKg(),
            'porcentaje_usado' => $cupo->porcentajeUsado(),

            'modalidad' => $cupo->modalidad->value,
            'modalidad_etiqueta' => $cupo->modalidad->etiqueta(),
            'modalidad_color' => $cupo->modalidad->color(),

            'estado' => $cupo->estado->value,
            'estado_etiqueta' => $cupo->estado->etiqueta(),
            'estado_color' => $cupo->estado->color(),
            'vigente' => $cupo->estaVigente(),

            /*
             * LOS KILOS QUE SE PASARON DEL CUPO. En modo estricto siempre es
             * cero —la emisión no deja pasar una faena que no entre— y por eso
             * el número solo aparece en las pantallas cuando hay algo que
             * mostrar. `saldoKg()` no puede decirlo: se corta en cero.
             */
            'kilos_excedidos' => $cupo->kilosExcedidos(),
            'excedido' => $cupo->estaExcedido(),
            // Se puede colgar una faena HOY: vigente, con saldo y sin agotar.
            'puede_emitir_faena' => $cupo->puedeEmitirFaena(),
            /*
             * EDITAR Y ELIMINAR LLEGAN RESUELTAS, y no se deducen de `estado`
             * en React.
             */
            'puede_editarse' => $cupo->puedeEditarse(),
            'puede_eliminarse' => $cupo->puedeEliminarse(),

            /*
             * LAS TRES DEL CIRCUITO DE REVISIÓN, resueltas en el servidor.
             */
            'admite_pagos' => $cupo->admitePagos(),
            'puede_enviarse' => $cupo->puedeEnviarseARevision(),
            'puede_revisarse' => $cupo->puedeRevisarse(),
            // La autorización en papel sale recién con el cupo firmado.
            'ya_fue_aprobado' => $cupo->yaFueAprobado(),

            'monto' => $cupo->montoACobrar(),
            'saldo_pendiente' => $cupo->saldoPendiente(),
            'pagado' => $cupo->estaPagado(),

            // Son DÍAS, no instantes: van con toDateString(). Mandados como
            // instante, en UTC-4 la pantalla mostraría el día anterior.
            'fecha_emision' => $cupo->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $cupo->fecha_vencimiento?->toDateString(),
        ];
    }
}
