<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Exceptions\CobroInvalidoException;
use App\Exceptions\CupoInvalidoException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Http\Requests\Panel\EliminarCupoRequest;
use App\Http\Requests\Panel\OtorgarCupoRequest;
use App\Http\Requests\Panel\RechazarCupoRequest;
use App\Http\Requests\Panel\RegistrarDepositosRequest;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\CategoriaAprovechamiento;
use App\Models\Pago;
use App\Models\PermisoFaena;
use App\Models\Recibo;
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
            // OJO CON PEDIR COLUMNAS SUELTAS: van las CINCO partes del nombre
            // porque `nombreCompleto` las lee todas. La que falte vuelve null y
            // el accesor contesta cualquier cosa, sin error.
            ->with([
                'beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
                'categoria',
            ])
            // Los DOS withSum evitan dos agregados POR FILA: con 30 cupos en
            // pantalla son 61 consultas sin ellos, y el listado se ve igual.
            ->withSum('faenasQueConsumen', 'kilos_extraidos')
            ->withSum('pagos', 'monto_parcial')
            // `recibos()` del trait NO es una relación sino una consulta, así
            // que llamarla por fila serían treinta. Se precargan los pagos.
            ->with(['pagos:id,pagable_type,pagable_id,recibo_id', 'pagos.recibo:id,numero_recibo'])
            ->when($filtros['buscar'], fn ($q, $termino) => $q->whereHas(
                'beneficiario',
                fn ($b) => $b->buscar($termino),
            ))
            ->when($filtros['estado'], fn ($q, $estado) => $q->where(
                'aprovechamientos_pesq.estado',
                $estado,
            ))
            // Por `created_at` y no por la fecha de solicitud, que el operador
            // declara y puede ser pasada. El `id` desempata, o el paginado
            // repite filas entre páginas.
            ->orderByDesc('aprovechamientos_pesq.created_at')
            ->orderByDesc('aprovechamientos_pesq.id')
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
                $datos['tipo_embarcacion'],
                now()->parse($datos['fecha_solicitud']),
            );
        } catch (CupoInvalidoException $e) {
            // Error DEL CAMPO y no un cartel arriba: la regla que falló es sobre
            // la persona elegida, y el texto tiene que salir al lado de ese campo.
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
                'Aprovechamiento registrado, PENDIENTE de cobro: %s kg por %s Bs. Cargue los '.
                'depósitos para poder enviarlo a revisión.',
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
            'beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'categoria',
            'codigo',
        ]);

        // El recibo del trámite: uno solo, emitido al enviar a revisión. NULL
        // mientras está pendiente.
        $recibo = $aprovechamiento->recibos()->first();

        // Boletas sin dar por buenas, observadas incluidas. Acá y no en
        // `resumir()`, que el listado corre treinta veces.
        $sinValidar = $aprovechamiento->pagos()->sinValidar()->count();

        return Inertia::render('panel/aprovechamientos/ver', [
            'cupo' => [
                ...$this->resumir($aprovechamiento),

                // La llave del QR de la autorización impresa.
                'codigo' => $aprovechamiento->codigo_legible,

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
                // A mano: `admiteControl()` le pregunta al `pagable`, y sin esto
                // cada fila lo va a buscar a la base.
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

            // Las cédulas que se apoyan en este cupo. Son varias en teoría: el
            // carnet se renueva a mitad de un cupo vigente, o se repone uno perdido.
            'carnets' => $aprovechamiento->carnets()
                ->with('tipoCarnet:id,nombre')
                ->latest('created_at')
                ->get()
                ->map(fn (Carnet $c): array => [
                    'id' => $c->id,
                    'codigo' => $c->codigo_legible,
                    'registro' => $c->registro_legible,
                    'tipo' => $c->tipoCarnet?->nombre,
                    'tipo_actor_etiqueta' => $c->tipo_actor->etiqueta(),
                    'tipo_actor_color' => $c->tipo_actor->color(),
                    'estado_etiqueta' => $c->estado->etiqueta(),
                    'estado_color' => $c->estado->color(),
                    'ya_fue_aprobado' => $c->yaFueAprobado(),
                    // Mismo corte que CarnetImpresionController: firmado y no revocado.
                    'puede_imprimirse' => $c->yaFueAprobado() && $c->estado !== EstadoCarnet::Revocado,
                    'puede_reponerse' => $c->estado->permiteRevocacion(),
                    'fecha_solicitud' => $c->fecha_solicitud?->toDateString(),
                    'fecha_emision' => $c->fecha_emision?->toDateString(),
                    'fecha_vencimiento' => $c->fecha_vencimiento?->toDateString(),
                ])
                ->all(),

            // El carnet con el que se emite la próxima faena: aprobado, vigente y con saldo.
            'carnetParaFaena' => $aprovechamiento->carnets()
                ->vigentes()
                ->with('aprovechamiento')
                ->latest('fecha_emision')
                ->get()
                ->first(fn (Carnet $c): bool => $c->puedeEmitirFaenas())?->id,

            // Las faenas de este cupo: es el detalle que explica el saldo. Sin
            // él, «le quedan 20 kg» es un número que hay que creer.
            'faenas' => $aprovechamiento->faenas()
                // Con `nro_registro` y `fecha_emision`: de esas dos columnas
                // salen los accesores del número del carnet, y sin ellas
                // devuelven null sin ningún error.
                ->with(['carnet:id,nro_registro,fecha_emision', 'carnet.codigo'])
                ->orderByDesc('numero_faena')
                ->get()
                ->map(fn (PermisoFaena $f): array => [
                    'id' => $f->id,
                    'numero_faena' => $f->numero_faena,
                    // Con los seis ceros del talonario: lo arma el modelo, no
                    // la pantalla, o cada tabla elige su propio relleno.
                    'numero_legible' => $f->numero_legible,

                    // De qué carnet cuelga: un cupo puede respaldar más de una
                    // credencial, y hay que saber cuál gastó esos kilos.
                    'carnet_id' => $f->carnet_id,
                    'carnet_registro' => $f->carnet?->registro_legible,
                    'kilos_extraidos' => (float) $f->kilos_extraidos,
                    'estado' => $f->estado->value,
                    'estado_etiqueta' => $f->estado->etiqueta(),
                    'estado_color' => $f->estado->color(),
                    // Una faena vencida LIBERA su volumen: la pantalla lo marca
                    // para que el saldo cuadre a la vista.
                    'consume_cupo' => $f->consumeCupo(),
                    'puede_imprimirse' => $f->yaFueAprobada(),
                    'fecha_salida' => $f->fecha_salida?->toDateString(),
                    'fecha_desembarque' => $f->fecha_desembarque?->toDateString(),
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
            'beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'categoria',
        ]);

        return Inertia::render('panel/aprovechamientos/editar', [
            // La persona llega FIJA: cambiar de titular no es corregir un cupo,
            // es otorgar otro.
            'cupo' => [
                'id' => $aprovechamiento->id,
                'beneficiario_id' => $aprovechamiento->beneficiario_id,
                'beneficiario' => $aprovechamiento->beneficiario?->nombreCompleto,
                'documento' => $aprovechamiento->beneficiario?->documento_identidad,
                'foto_url' => $aprovechamiento->beneficiario?->foto_url,
                'categoria_aprov_id' => $aprovechamiento->categoria_aprov_id,
                'tipo_embarcacion' => $aprovechamiento->tipo_embarcacion,
                'fecha_solicitud' => $aprovechamiento->fecha_solicitud?->toDateString(),
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
                $datos['tipo_embarcacion'],
                now()->parse($datos['fecha_solicitud']),
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
        RegistrarDepositosRequest $request,
        AprovechamientoPesq $aprovechamiento,
        CobrarService $caja,
        RevisarCupoService $revision,
    ): RedirectResponse {
        $datos = $request->validated();
        $aprovechamiento->loadMissing('beneficiario');

        // EL ESTADO SE MIRA ANTES DE SUBIR NADA: descubrirlo después de escribir
        // cinco archivos obligaría a borrarlos.
        if (! $aprovechamiento->admitePagos()) {
            return back()->withErrors([
                'pagos' => CupoInvalidoException::noAdmitePagos(
                    $aprovechamiento->estado->etiqueta(),
                )->getMessage(),
            ]);
        }

        // Y el monto igual. El control de verdad lo hace el servicio con la fila
        // bloqueada; esto solo evita subir archivos que habría que borrar.
        $suma = round(array_sum(array_map(
            static fn (array $p): float => round((float) $p['monto'], 2),
            $datos['pagos'],
        )), 2);

        $saldo = $aprovechamiento->saldoPendiente();

        if ($suma < $saldo) {
            return back()->withInput()->withErrors([
                'pagos' => CobroInvalidoException::noCubreElMonto(
                    'este aprovechamiento',
                    $suma,
                    $saldo,
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
            // El recibo lo emite el ENVÍO, y sale a nombre del titular del cupo.
            $revision->enviar($cupo);

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
     * El recibo del trámite, sin disparar una consulta por fila.
     *
     * `Pagable::recibos()` es una CONSULTA y no una relación, así que en un
     * listado hay que resolverlo desde los pagos ya precargados.
     */
    private function reciboDe(AprovechamientoPesq $cupo): ?Recibo
    {
        if ($cupo->relationLoaded('pagos')) {
            return $cupo->pagos->firstWhere('recibo_id', '!=', null)?->recibo;
        }

        return $cupo->recibos()->first();
    }

    /**
     * Los datos de un cupo que pintan el listado y la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumir(AprovechamientoPesq $cupo): array
    {
        $recibo = $this->reciboDe($cupo);

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
            // NULL y no cadena vacía: así la pantalla dice «no declarada» en vez
            // de imprimir un renglón en blanco.
            'tipo_embarcacion' => $cupo->tipo_embarcacion,
            'kilos_consumidos' => $cupo->kilosConsumidos(),
            'saldo_kg' => $cupo->saldoKg(),
            'porcentaje_usado' => $cupo->porcentajeUsado(),

            'modalidad' => $cupo->modalidad->value,
            'modalidad_etiqueta' => $cupo->modalidad->etiqueta(),
            'modalidad_color' => $cupo->modalidad->color(),

            // Cuándo se cargó, que no es la fecha de solicitud. Es un MOMENTO:
            // va con toIso8601String().
            'registrado_en' => $cupo->created_at?->toIso8601String(),

            'estado' => $cupo->estado->value,
            'estado_etiqueta' => $cupo->estado->etiqueta(),
            'estado_color' => $cupo->estado->color(),
            'vigente' => $cupo->estaVigente(),

            // Los kilos que se pasaron del cupo. En modo estricto siempre es cero.
            // `saldoKg()` no puede decirlo: se corta en cero.
            'kilos_excedidos' => $cupo->kilosExcedidos(),
            'excedido' => $cupo->estaExcedido(),
            // Se puede colgar una faena HOY: vigente, con saldo y sin agotar.
            'puede_emitir_faena' => $cupo->puedeEmitirFaena(),
            // Y si no se puede, POR QUÉ: un cupo pendiente no es uno vencido.
            'motivo_sin_faena' => $cupo->motivoSinFaena(),
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

            /*
             * EL RECIBO, para poder imprimirlo sin entrar a la ficha. Existe
             * desde el ENVÍO, así que aparece en revisión y sigue después.
             */
            'recibo_id' => $recibo?->id,
            'recibo_numero' => $recibo?->numero_recibo,

            'monto' => $cupo->montoACobrar(),
            'saldo_pendiente' => $cupo->saldoPendiente(),
            'pagado' => $cupo->estaPagado(),

            // Son DÍAS, no instantes: van con toDateString(). Mandados como
            // instante, en UTC-4 la pantalla mostraría el día anterior.
            'fecha_solicitud' => $cupo->fecha_solicitud?->toDateString(),
            // NULL hasta la firma: recién ahí hay algo otorgado.
            'fecha_emision' => $cupo->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $cupo->fecha_vencimiento?->toDateString(),
        ];
    }
}
