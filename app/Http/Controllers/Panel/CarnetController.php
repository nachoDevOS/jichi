<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoCarnet;
use App\Enums\TipoActor;
use App\Exceptions\CarnetInvalidoException;
use App\Exceptions\CobroInvalidoException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Http\Requests\Panel\EditarCarnetRequest;
use App\Http\Requests\Panel\EliminarCarnetRequest;
use App\Http\Requests\Panel\EmitirCarnetRequest;
use App\Http\Requests\Panel\RechazarCarnetRequest;
use App\Http\Requests\Panel\RegistrarDepositosRequest;
use App\Http\Requests\Panel\RevocarCarnetRequest;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\Pago;
use App\Models\Recibo;
use App\Models\TipoCarnet;
use App\Services\CobrarService;
use App\Services\EmitirCarnetService;
use App\Services\RevisarCarnetService;
use App\Support\Archivos;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  CARNETS — la credencial anual (paso 3 del flujo)
 */
class CarnetController extends Controller
{
    public function __construct(private readonly EmitirCarnetService $servicio) {}

    /**
     * LISTADO — GET /panel/carnets
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'actor' => $request->string('actor')->trim()->value() ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $carnets = Carnet::query()
            /*
             * OJO CON PEDIR COLUMNAS SUELTAS: el beneficiario va con las CINCO
             * partes del nombre porque `nombreCompleto` las lee todas, y
             * `tipoCarnet` va ENTERO porque `montoACobrar()` lee `precio_bs`.
             * Una columna que un método consulta y no está en el select vuelve
             * null y el método contesta cualquier cosa, sin ningún error.
             */
            ->with([
                // `codigo_legible` va en #[Appends]: sin precargar la relación,
                // serializar el listado dispara una consulta por fila.
                'codigo',
                'beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
                'asociacion:id,nombre,sigla',
                'tipoCarnet',
                'aprovechamiento',
            ])
            // Evita una consulta agregada POR FILA al calcular el saldo.
            ->withSum('pagos', 'monto_parcial')
            /*
             * Y los dos conteos que `puedeEliminarse()` necesita: sin ellos son
             * dos consultas más POR FILA, invisibles, solo para decidir si se
             * dibuja un botón. Ver Carnet::sinPermisosEmitidos().
             */
            ->withCount(['faenas', 'guias'])
            /*
             * EL RECIBO PARA EL BOTÓN DE IMPRIMIR. `Pagable::recibos()` es una
             * CONSULTA y no una relación, así que llamarla por fila serían
             * treinta consultas: se resuelve desde los pagos precargados.
             */
            ->with(['pagos:id,pagable_type,pagable_id,recibo_id', 'pagos.recibo:id,numero_recibo'])
            ->when($filtros['buscar'], fn ($q, $termino) => $q->where(
                fn ($s) => $s
                    ->whereHas('beneficiario', fn ($b) => $b->buscar($termino))
                    // El código se busca NORMALIZADO —la gente lo copia del
                    // papel con los guiones de los grupos de cuatro— y por
                    // la RELACIÓN: desde el 22/09/2026 vive en `codigos`.
                    ->orWhereHas('codigo', fn ($c) => $c->where(
                        'codigo', 'like', '%'.Carnet::normalizarCodigo($termino).'%',
                    )),
            ))
            ->when($filtros['estado'], fn ($q, $estado) => $q->where('carnets.estado', $estado))
            ->when($filtros['actor'], fn ($q, $actor) => $q->where('carnets.tipo_actor', $actor))
            /*
             * LO ÚLTIMO CARGADO, ARRIBA — y por `created_at`, no por la fecha
             * de solicitud: esa la declara el operador y puede ser pasada, así
             * que un expediente cargado hoy con fecha vieja se iba al fondo.
             * El `id` desempata, o el paginado repite filas entre páginas.
             */
            ->orderByDesc('carnets.created_at')
            ->orderByDesc('carnets.id')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through($this->resumir(...));

        return Inertia::render('panel/carnets/index', [
            'carnets' => $carnets,
            'filtros' => $filtros,
            'estados' => EstadoCarnet::opciones(),
            'actores' => TipoActor::opciones(),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * FORMULARIO — GET /panel/carnets/crear
     *
     * Acepta `?beneficiario=7` para llegar desde la ficha de la persona con el
     * buscador ya resuelto.
     */
    public function create(Request $request): Response
    {
        $beneficiario = $request->integer('beneficiario')
            ? Beneficiario::query()->find($request->integer('beneficiario'))
            : null;

        return Inertia::render('panel/carnets/crear', [
            'beneficiario' => $beneficiario ? [
                'id' => $beneficiario->id,
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,

                /*
                 * SUS BOLSAS MADRE, con el MISMO shape que devuelve el
                 * buscador: la pantalla muestra de cuál va a colgar el carnet
                 * —y deja elegir si hubiera más de una—, y tiene que decir lo
                 * mismo venga la persona preseleccionada o elegida a mano.
                 */
                'cupos_elegibles' => BeneficiarioController::cuposElegibles($beneficiario),
            ] : null,

            'asociaciones' => Asociacion::query()
                ->activas()
                ->ordenAlfabetico()
                ->get(['id', 'nombre', 'sigla'])
                ->map(fn (Asociacion $a): array => [
                    'id' => $a->id,
                    'nombre' => $a->nombre,
                    'sigla' => $a->sigla,
                ])
                ->all(),

            'tipos' => TipoCarnet::query()
                ->vigentes()
                ->orderBy('nombre')
                ->get()
                ->map(fn (TipoCarnet $t): array => [
                    'id' => $t->id,
                    'nombre' => $t->nombre,
                    // Con qué actividad es coherente. La pantalla filtra la
                    // lista con esto, y el servidor lo exige igual.
                    'tipo_actor' => $t->tipo_actor->value,
                    'precio_bs' => (float) $t->precio_bs,
                ])
                ->all(),
        ]);
    }

    /**
     * EMITIR — POST /panel/carnets
     */
    public function store(EmitirCarnetRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $tipo = TipoCarnet::query()->findOrFail($datos['tipo_carnet_id']);

        /*
         * LOS ADJUNTOS SE SUBEN ANTES DE ABRIR LA TRANSACCIÓN, y el `catch` los
         * borra: una transacción deshace filas, no escrituras en disco. Sin
         * esto, cada emisión fallida dejaba dos archivos huérfanos para
         * siempre. Van por StorageController, el único que escribe archivos.
         */
        $subidos = [
            'archivo_ci' => app(StorageController::class)->file($request->file('archivo_ci'), 'carnets'),
            'archivo_asociacion' => app(StorageController::class)->file($request->file('archivo_asociacion'), 'carnets'),
        ];

        try {
            $carnet = $this->servicio->emitir(
                Beneficiario::query()->findOrFail($datos['beneficiario_id']),
                Asociacion::query()->findOrFail($datos['asociacion_id']),
                $tipo,
                /*
                 * LA ACTIVIDAD SALE DEL TIPO y no de un campo aparte: eran dos
                 * preguntas con una sola respuesta posible. Se sigue GUARDANDO
                 * en el carnet, que es la copia congelada de la regla.
                 */
                $tipo->tipo_actor,
                now()->parse($datos['fecha_solicitud']),
                // `?? null`: `validated()` devuelve SOLO las claves que vinieron,
                // así que un nullable ausente revienta si se lee directo.
                isset($datos['aprovechamiento_id'])
                    ? AprovechamientoPesq::query()->find($datos['aprovechamiento_id'])
                    : null,
                $subidos['archivo_ci'],
                $subidos['archivo_asociacion'],
            );
        } catch (CarnetInvalidoException $e) {
            foreach ($subidos as $ruta) {
                Archivos::borrar($ruta);
            }

            /*
             * El mensaje vuelve como error DEL CAMPO de la persona y no como un
             * cartel suelto: las tres reglas que puede romper —ya tiene carnet,
             * no tiene cupo— son sobre la persona elegida, así que el texto
             * tiene que aparecer al lado de ese campo.
             */
            return back()
                ->withInput()
                ->withErrors(['beneficiario_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('carnets.show', $carnet)
            ->with('exito', sprintf(
                'Carnet %s registrado, PENDIENTE de cobro. Cargue los depósitos para poder enviarlo '.
                'a revisión: el plástico se imprime recién cuando esté aprobado.',
                $carnet->codigo_legible,
            ));
    }

    /**
     * FICHA — GET /panel/carnets/{carnet}
     */
    public function show(Carnet $carnet): Response
    {
        $carnet->load([
            // `foto` va en el select: la ficha muestra el retrato del titular,
            // y `foto_url` es un accesor que lee esa columna. Sin ella vuelve
            // null y la foto no aparece, sin ningún error.
            'beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'asociacion:id,nombre,sigla',
            'tipoCarnet',
            'aprovechamiento.categoria',
        ]);

        // El recibo del trámite —uno solo— y cuántas boletas faltan controlar.
        $recibo = $carnet->recibos()->first();
        $sinValidar = $carnet->pagos()->sinValidar()->count();

        return Inertia::render('panel/carnets/ver', [
            'carnet' => [
                ...$this->resumir($carnet),

                // Para la vista previa del plástico y para la ficha.
                'foto_url' => $carnet->beneficiario?->foto_url,
                'documento_identidad' => $carnet->beneficiario?->documento_identidad,
                'ciudad' => $carnet->beneficiario?->ciudad,
                'provincia' => $carnet->beneficiario?->provincia,
                'asociacion_nombre' => $carnet->asociacion?->nombre,

                /*
                 * El cupo con su saldo: en la ficha del carnet interesa saber
                 * cuánto le queda, porque es lo que decide si se le puede emitir
                 * una faena hoy.
                 */
                'cupo' => $carnet->aprovechamiento ? [
                    'id' => $carnet->aprovechamiento->id,
                    'escala' => $carnet->aprovechamiento->categoria?->nro_escala,
                    'descripcion' => $carnet->aprovechamiento->categoria?->descripcion_kg,
                    'volumen_total_kg' => (float) $carnet->aprovechamiento->volumen_total_kg,
                    'kilos_consumidos' => $carnet->aprovechamiento->kilosConsumidos(),
                    'saldo_kg' => $carnet->aprovechamiento->saldoKg(),
                    'porcentaje_usado' => $carnet->aprovechamiento->porcentajeUsado(),
                    'vigente' => $carnet->aprovechamiento->estaVigente(),

                    // Las tres FECHAS y el estado: sin ellas, la tarjeta decía
                    // los kilos y nada más, y la pregunta de un control es
                    // hasta cuándo vale esa autorización.
                    'estado_etiqueta' => $carnet->aprovechamiento->estado->etiqueta(),
                    'estado_color' => $carnet->aprovechamiento->estado->color(),
                    'ya_fue_aprobado' => $carnet->aprovechamiento->yaFueAprobado(),
                    'fecha_solicitud' => $carnet->aprovechamiento->fecha_solicitud?->toDateString(),
                    'fecha_emision' => $carnet->aprovechamiento->fecha_emision?->toDateString(),
                    'fecha_vencimiento' => $carnet->aprovechamiento->fecha_vencimiento?->toDateString(),
                ] : null,

                // Aprobar no es «estar en revisión»: es eso Y que no quede
                // ninguna boleta sin validar. El número dice cuántas.
                'pagos_sin_validar' => $sinValidar,
                'puede_aprobarse' => $carnet->puedeRevisarse() && $sinValidar === 0,
            ],

            /*
             * EL DETALLE DE LO COBRADO, igual que en la ficha del cupo: una
             * fila por boleta, con su control.
             */
            'pagos' => $carnet->pagos()
                // Precargados: si no, cinco depósitos son diez consultas.
                ->with(['registradoPor:id,name', 'validadoPor:id,name'])
                ->latest('created_at')
                ->get()
                // El trámite se le pone a mano: `admiteControl()` le pregunta al
                // `pagable`, y sin esto cada fila lo va a buscar a la base.
                ->each(fn (Pago $p) => $p->setRelation('pagable', $carnet))
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
     * REVOCAR — PATCH /panel/carnets/{carnet}/revocar
     */
    public function revocar(RevocarCarnetRequest $request, Carnet $carnet): RedirectResponse
    {
        try {
            $this->servicio->revocar($carnet, $request->validated()['motivo']);
        } catch (CarnetInvalidoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('carnets.show', $carnet)
            ->with('exito', 'Carnet revocado. La verificación pública ya lo informa como revocado.');
    }

    /**
     * FORMULARIO DE CORRECCIÓN — GET /panel/carnets/{carnet}/editar
     */
    public function edit(Carnet $carnet): Response
    {
        $carnet->load(['codigo', 'beneficiario', 'asociacion', 'tipoCarnet', 'aprovechamiento.categoria']);

        return Inertia::render('panel/carnets/editar', [
            'carnet' => [
                'id' => $carnet->id,
                'codigo' => $carnet->codigo_legible,
                // El titular y la actividad NO se corrigen: van solo para
                // mostrarse. Cambiarlos sería otro carnet, no una corrección.
                'beneficiario' => $carnet->beneficiario?->nombreCompleto,
                'documento' => $carnet->beneficiario?->documento_identidad,
                'foto_url' => $carnet->beneficiario?->foto_url,
                'tipo_actor' => $carnet->tipo_actor->value,
                'tipo_actor_etiqueta' => $carnet->tipo_actor->etiqueta(),

                'asociacion_id' => $carnet->asociacion_id,
                'tipo_carnet_id' => $carnet->tipo_carnet_id,
                'aprovechamiento_id' => $carnet->aprovechamiento_id,
                'fecha_solicitud' => $carnet->fecha_solicitud?->toDateString(),

                // Para decir «ya hay uno cargado» sin obligar a resubirlo.
                'archivo_ci_url' => Archivos::url($carnet->archivo_ci),
                'archivo_asociacion_url' => Archivos::url($carnet->archivo_asociacion),

                'cupos_elegibles' => $carnet->beneficiario
                    ? BeneficiarioController::cuposElegibles($carnet->beneficiario)
                    : [],
            ],

            'asociaciones' => Asociacion::query()
                ->activas()
                ->ordenAlfabetico()
                ->get(['id', 'nombre', 'sigla'])
                ->map(fn (Asociacion $a): array => [
                    'id' => $a->id,
                    'nombre' => $a->nombre,
                    'sigla' => $a->sigla,
                ])
                ->all(),

            'tipos' => TipoCarnet::query()
                ->vigentes()
                ->orderBy('nombre')
                ->get()
                ->map(fn (TipoCarnet $t): array => [
                    'id' => $t->id,
                    'nombre' => $t->nombre,
                    'tipo_actor' => $t->tipo_actor->value,
                    'precio_bs' => (float) $t->precio_bs,
                ])
                ->all(),
        ]);
    }

    /**
     * GUARDAR LA CORRECCIÓN — PUT /panel/carnets/{carnet}
     */
    public function update(EditarCarnetRequest $request, Carnet $carnet): RedirectResponse
    {
        $datos = $request->validated();

        /*
         * Los adjuntos NUEVOS se suben antes de la transacción, como en la
         * emisión, y el `catch` los borra. Los viejos se quedan donde están:
         * hoy nada los borra, y ese es el precio de no dejar un carnet sin
         * respaldo por una corrección a medias.
         */
        $subidos = [];

        foreach (['archivo_ci', 'archivo_asociacion'] as $campo) {
            $subidos[$campo] = $request->hasFile($campo)
                ? app(StorageController::class)->file($request->file($campo), 'carnets')
                : null;
        }

        try {
            $this->servicio->editar(
                $carnet,
                Asociacion::query()->findOrFail($datos['asociacion_id']),
                TipoCarnet::query()->findOrFail($datos['tipo_carnet_id']),
                now()->parse($datos['fecha_solicitud']),
                isset($datos['aprovechamiento_id'])
                    ? AprovechamientoPesq::query()->find($datos['aprovechamiento_id'])
                    : null,
                $subidos['archivo_ci'],
                $subidos['archivo_asociacion'],
            );
        } catch (CarnetInvalidoException $e) {
            foreach (array_filter($subidos) as $ruta) {
                Archivos::borrar($ruta);
            }

            return back()->withInput()->withErrors(['tipo_carnet_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('carnets.show', $carnet)
            ->with('exito', 'Carnet corregido.');
    }

    /**
     * ELIMINAR — DELETE /panel/carnets/{carnet}
     */
    public function destroy(EliminarCarnetRequest $request, Carnet $carnet): RedirectResponse
    {
        $codigo = $carnet->codigo_legible;

        try {
            $this->servicio->eliminar($carnet, $request->validated()['motivo']);
        } catch (CarnetInvalidoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('carnets.index')
            ->with('exito', "Carnet {$codigo} eliminado. El motivo quedó en la auditoría.");
    }

    /**
     *  CARGAR LOS DEPÓSITOS — POST /panel/carnets/{carnet}/pagos
     *
     * Espejo de `AprovechamientoController::pagar()`: el carnet se cobra igual
     * que el cupo, con una sección por boleta y cubriendo el arancel entero.
     */
    public function pagar(
        RegistrarDepositosRequest $request,
        Carnet $carnet,
        CobrarService $caja,
        RevisarCarnetService $revision,
    ): RedirectResponse {
        $datos = $request->validated();

        /*
         * EL ESTADO Y EL MONTO SE MIRAN ANTES DE SUBIR NADA: descubrirlo
         * adentro obligaría a borrar los archivos ya escritos, porque una
         * transacción no deshace lo que se escribió en disco.
         */
        if (! $carnet->admitePagos()) {
            return back()->withErrors([
                'pagos' => CobroInvalidoException::noAdmiteDepositos(
                    'El carnet '.$carnet->codigo_legible,
                    $carnet->estado->etiqueta(),
                )->getMessage(),
            ]);
        }

        $suma = round(array_sum(array_map(
            static fn (array $p): float => round((float) $p['monto'], 2),
            $datos['pagos'],
        )), 2);

        $saldo = $carnet->saldoPendiente();

        if ($suma < $saldo) {
            return back()->withInput()->withErrors([
                'pagos' => CobroInvalidoException::noCubreElMonto(
                    'este carnet',
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

            $caja->registrarDepositos($carnet, $depositos);
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

        $carnet->refresh();
        $cuantos = count($datos['pagos']);

        //  REGISTRAR Y ENVIAR SON UN SOLO ACTO CUANDO EL ARANCEL QUEDA CUBIERTO
        $enviado = false;

        if (($datos['enviar'] ?? false) && $carnet->puedeEnviarseARevision()) {
            $revision->enviar($carnet);
            $carnet->refresh();
            $enviado = true;
        }

        return redirect()
            ->route('carnets.show', $carnet)
            ->with('exito', match (true) {
                $enviado => sprintf(
                    '%d depósito(s) registrado(s) y enviado a revisión. Se emitió el recibo con el '.
                    'total; queda esperando la firma de quien lo aprueba.',
                    $cuantos,
                ),
                $carnet->saldoPendiente() <= 0.0 => sprintf(
                    '%d depósito(s) registrado(s). El arancel quedó cubierto: ya se puede enviar a revisión.',
                    $cuantos,
                ),
                default => sprintf(
                    '%d depósito(s) registrado(s). Quedan %s Bs por cobrar.',
                    $cuantos,
                    number_format($carnet->saldoPendiente(), 2, ',', '.'),
                ),
            });
    }

    /**
     * ENVIAR A REVISIÓN — POST /panel/carnets/{carnet}/enviar
     */
    public function enviar(Carnet $carnet, RevisarCarnetService $revision): RedirectResponse
    {
        try {
            $revision->enviar($carnet);
        } catch (CarnetInvalidoException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()
            ->route('carnets.show', $carnet)
            ->with('exito', 'Enviado a revisión. Se emitió el recibo con el total de los depósitos; '.
                'queda esperando la firma de quien lo aprueba.');
    }

    /**
     * APROBAR — PATCH /panel/carnets/{carnet}/aprobar
     *
     * Recién acá la credencial habilita a trabajar y se puede imprimir.
     */
    public function aprobar(Carnet $carnet, RevisarCarnetService $revision): RedirectResponse
    {
        try {
            $revision->aprobar($carnet);
        } catch (CarnetInvalidoException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()
            ->route('carnets.show', $carnet)
            ->with('exito', "Carnet {$carnet->codigo_legible} aprobado. Ya se puede imprimir.");
    }

    /**
     * RECHAZAR — PATCH /panel/carnets/{carnet}/rechazar
     */
    public function rechazar(RechazarCarnetRequest $request, Carnet $carnet, RevisarCarnetService $revision): RedirectResponse
    {
        try {
            $revision->rechazar($carnet, $request->validated()['motivo']);
        } catch (CarnetInvalidoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('carnets.show', $carnet)
            ->with('exito', 'Carnet devuelto a ventanilla. Los depósitos quedan intactos: se corrige '.
                'lo observado y se vuelve a presentar.');
    }

    //  Auxiliares

    /**
     * El recibo del trámite, sin disparar una consulta por fila.
     *
     * Igual que en aprovechamientos: `Pagable::recibos()` es una consulta y no
     * una relación, así que en un listado hay que resolverlo desde los pagos
     * ya precargados.
     */
    private function reciboDe(Carnet $carnet): ?Recibo
    {
        if ($carnet->relationLoaded('pagos')) {
            return $carnet->pagos->firstWhere('recibo_id', '!=', null)?->recibo;
        }

        return $carnet->recibos()->first();
    }

    /**
     * Los datos de un carnet que pintan el listado y la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumir(Carnet $carnet): array
    {
        $recibo = $this->reciboDe($carnet);

        return [
            'id' => $carnet->id,
            'beneficiario_id' => $carnet->beneficiario_id,
            'beneficiario' => $carnet->beneficiario?->nombreCompleto,

            // En grupos de cuatro, igual que va impreso: es lo que se compara
            // contra el plástico y lo que se dicta por teléfono.
            'codigo' => $carnet->codigo_legible,

            // El número del libro, «00001». Vacío hasta que lo firman: se
            // asigna al aprobar, para no gastar uno en un carnet que se rechaza.
            'registro' => $carnet->registro_legible,
            'gestion' => $carnet->gestion,

            'tipo' => $carnet->tipoCarnet?->nombre,
            'tipo_actor' => $carnet->tipo_actor->value,
            'tipo_actor_etiqueta' => $carnet->tipo_actor->etiqueta(),
            'tipo_actor_color' => $carnet->tipo_actor->color(),
            /*
             * EL NOMBRE COMPLETO, no la sigla. La columna del listado tiene
             * lugar, y «ASOPESTRI» obliga a saberse el gremio de memoria; la
             * sigla sigue yendo donde el espacio es de verdad angosto: la tira
             * del carnet impreso.
             */
            'asociacion' => $carnet->asociacion?->nombre,
            'asociacion_sigla' => $carnet->asociacion?->sigla,

            // La foto y la cédula van en la misma celda que el nombre, igual
            // que en el listado de aprovechamientos.
            'foto_url' => $carnet->beneficiario?->foto_url,
            'documento' => $carnet->beneficiario?->documento_identidad,

            // Null en un comercializador, y es la regla: lo decide el enum.
            'cupo_kg' => $carnet->cupoImpreso(),

            /*
             * CUÁNDO SE CARGÓ LA FILA, que no es lo mismo que la fecha de
             * solicitud: esa la declara el operador y puede ser pasada. Es un
             * MOMENTO, así que va con toIso8601String() y la pantalla lo
             * muestra con la hora y el «hace…».
             */
            'registrado_en' => $carnet->created_at?->toIso8601String(),

            /*
             * EL RECIBO, para poder imprimirlo sin entrar a la ficha. Existe
             * desde el ENVÍO, así que aparece en revisión y sigue después.
             */
            'recibo_id' => $recibo?->id,
            'recibo_numero' => $recibo?->numero_recibo,

            'estado' => $carnet->estado->value,
            'estado_etiqueta' => $carnet->estado->etiqueta(),
            'estado_color' => $carnet->estado->color(),
            'vigente' => $carnet->estaVigente(),
            'dias_para_vencer' => $carnet->diasParaVencer(),

            'puede_emitir_faenas' => $carnet->puedeEmitirFaenas(),
            'puede_emitir_guias' => $carnet->puedeEmitirGuias(),
            // Y si no puede, POR QUÉ: un carnet pendiente no es uno vencido.
            'motivo_sin_permisos' => $carnet->motivoSinPermisos(),

            // Corregir y eliminar llegan RESUELTAS, y no se deducen del estado
            // en React: las dos miran además si entró plata.
            'puede_editarse' => $carnet->puedeEditarse(),
            'puede_eliminarse' => $carnet->puedeEliminarse(),

            'monto' => $carnet->montoACobrar(),
            'saldo_pendiente' => $carnet->saldoPendiente(),
            'pagado' => $carnet->estaPagado(),

            /*
             * LAS DEL CIRCUITO DE REVISIÓN, resueltas en el servidor. React no
             * vuelve a evaluar el estado: pregunta por estas.
             */
            'admite_pagos' => $carnet->admitePagos(),
            'puede_enviarse' => $carnet->puedeEnviarseARevision(),
            'puede_revisarse' => $carnet->puedeRevisarse(),
            'ya_fue_aprobado' => $carnet->yaFueAprobado(),

            // Los dos respaldos de la emisión, listos para abrir.
            'archivo_ci_url' => Archivos::url($carnet->archivo_ci),
            'archivo_asociacion_url' => Archivos::url($carnet->archivo_asociacion),

            // Son DÍAS, no instantes: con toDateString(). Mandados como instante,
            // en UTC-4 la pantalla mostraría el día anterior.
            'fecha_solicitud' => $carnet->fecha_solicitud?->toDateString(),
            // NULL hasta la firma: recién ahí hay un carnet emitido.
            'fecha_emision' => $carnet->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $carnet->fecha_vencimiento?->toDateString(),
        ];
    }
}
