<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoCarnet;
use App\Http\Controllers\Controller;
use App\Models\Carnet;
use App\Models\Rubro;
use App\Support\Paginacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta y administración de carnets ya emitidos.
 *
 * NO HAY create() NI store(). Un carnet no se crea a mano: nace dentro de
 * SolicitudCarnetService::registrar() cuando la Regla A determina que la
 * persona no tenía uno de esta gestión. Un botón de «crear carnet» suelto
 * permitiría emitir documentos sin expediente que los respalde —y sin cobrar—.
 *
 * Lo que sí se hace acá es lo que pasa DESPUÉS de emitido: consultarlo,
 * suspender el carnet y anularlo.
 */
class CarnetController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'gestion' => $request->integer('gestion') ?: null,
            // Con un carnet por actividad, «mostrame los de Pescador» pasó a ser
            // una pregunta corriente de ventanilla.
            'rubro' => $request->integer('rubro') ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $carnets = Carnet::query()
            ->with([
                'beneficiario:id,ci_nit,complemento,expedido,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                // El rubro ES el carnet desde el modelo nuevo: sin esto el
                // listado no puede decir de qué actividad es cada documento,
                // que es lo primero que se busca con dos carnets de la misma
                // persona en pantalla.
                'rubro:id,nombre',
            ])

            // Se califica la columna con el nombre de la tabla porque
            // `carnets`, `beneficiarios` y `rubros` tienen todas una columna
            // `estado` y esta consulta las cruza: sin calificar, PostgreSQL
            // responde «column reference "estado" is ambiguous».
            ->when($filtros['estado'], fn ($q, $e) => $q->where('carnets.estado', $e))
            ->when($filtros['gestion'], fn ($q, $g) => $q->where('gestion', $g))
            ->when($filtros['rubro'], fn ($q, $r) => $q->where('carnets.rubro_id', $r))
            /*
             * La búsqueda acepta tres cosas, porque son las tres por las que se
             * pregunta en ventanilla:
             *
             *   - el NÚMERO DE REGISTRO impreso en el carnet («el 13»), que es
             *     el id. Se compara solo si lo escrito es numérico, y se le
             *     quitan los ceros de relleno: «000013» y «13» son el mismo.
             *   - la FIRMA, para quien la copió del panel o la tiene del QR.
             *   - el nombre o la cédula del titular.
             */
            ->when($filtros['buscar'], fn ($q, $t) => $q->where(function ($sub) use ($t) {
                $sub->whereHas('beneficiario', fn ($b) => $b->buscar($t));

                if (ctype_digit(ltrim($t, '0')) && ltrim($t, '0') !== '') {
                    $sub->orWhere('id', (int) ltrim($t, '0'));
                }

                $firma = Carnet::normalizarFirma($t);

                if ($firma !== '') {
                    $sub->orWhere('firma_validacion', 'like', $firma.'%');
                }
            }))

            ->orderByDesc('gestion')
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through(fn (Carnet $c): array => [
                'id' => $c->id,
                // El número impreso en el plástico: es por el que se pregunta.
                'registro' => $c->registro(),
                'gestion' => $c->gestion,
                'beneficiario_id' => $c->beneficiario_id,
                'beneficiario' => $c->beneficiario?->nombreCompleto,
                'documento_identidad' => $c->beneficiario?->documento_identidad,
                'estado' => $c->estado->value,
                'estado_etiqueta' => $c->estado->etiqueta(),
                'estado_color' => $c->estado->color(),
                'vigente' => $c->estaVigente(),
                'rubro' => $c->rubro?->nombre,
                'capacidad' => $c->capacidadLegible(),
                'fecha_emision' => $c->fecha_emision?->toDateString(),
                'fecha_vencimiento' => $c->fecha_vencimiento?->toDateString(),
            ]);

        return Inertia::render('panel/carnets/index', [
            'carnets' => $carnets,
            'filtros' => $filtros,
            'estados' => EstadoCarnet::opciones(),
            'gestiones' => Carnet::query()->distinct()->orderByDesc('gestion')->pluck('gestion')->all(),
            // Para el selector del filtro. Se listan TODOS y no solo los
            // activos: un rubro dado de baja sigue teniendo carnets emitidos
            // que alguien va a querer buscar.
            'rubros' => Rubro::query()->orderBy('nombre')->get(['id', 'nombre'])->all(),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * ========================================================================
     *  AUTOCOMPLETADO DE CARNETS — GET /panel/carnets/buscar
     * ========================================================================
     *
     * Devuelve JSON, no una pantalla: lo consulta el formulario de faenas y el
     * de guías mientras el operador escribe, y ahí no se quiere navegar a ningún
     * lado.
     *
     * ------------------------------------------------------------------------
     *  FILTRA POR LO QUE EL CARNET PUEDE EMITIR
     * ------------------------------------------------------------------------
     *
     * `?permiso=faenas` devuelve solo carnets de rubros con `emite_faenas`, y
     * VIGENTES. Es deliberado que el filtro esté acá y no en el navegador: si la
     * lista trajera todos y React escondiera los que no sirven, el formulario de
     * faenas mostraría carnets de Comercializador a medio filtrar cada vez que
     * alguien agregue un rubro nuevo.
     *
     * Con la lista ya filtrada, el operador NO PUEDE elegir mal —que es el error
     * más probable del módulo, porque la misma persona tiene los dos carnets—.
     * El servicio igual lo vuelve a comprobar: esto es comodidad, no seguridad.
     *
     * El límite de 15 no es cosmético: sin él, teclear una letra sola traería el
     * padrón entero a memoria.
     */
    public function buscar(Request $request): JsonResponse
    {
        $termino = $request->string('q')->trim()->value();

        // Menos de dos caracteres no acota nada y devolvería medio padrón.
        if (mb_strlen($termino) < 2) {
            return response()->json([]);
        }

        $permiso = $request->string('permiso')->trim()->value();

        $carnets = Carnet::query()
            ->with([
                'beneficiario:id,ci_nit,complemento,expedido,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                // Las dos banderas van en el select aunque el filtro ya use
                // whereHas: si mañana la fila las muestra, leerlas sin pedirlas
                // devolvería null en silencio.
                'rubro:id,nombre,emite_faenas,emite_guias',
            ])
            ->vigentes()
            ->when($permiso === 'faenas', fn ($q) => $q->whereHas('rubro', fn ($r) => $r->where('emite_faenas', true)))
            ->when($permiso === 'guias', fn ($q) => $q->whereHas('rubro', fn ($r) => $r->where('emite_guias', true)))
            ->where(function ($sub) use ($termino) {
                $sub->whereHas('beneficiario', fn ($b) => $b->buscar($termino));

                // «000013» y «13» son el mismo carnet: es el número impreso,
                // y el operador lo dicta con o sin los ceros.
                $limpio = ltrim($termino, '0');

                if ($limpio !== '' && ctype_digit($limpio)) {
                    $sub->orWhere('carnets.id', (int) $limpio);
                }
            })
            ->orderByDesc('gestion')
            ->limit(15)
            ->get();

        return response()->json($carnets->map(fn (Carnet $c): array => [
            'id' => $c->id,
            'registro' => $c->registro(),
            'gestion' => $c->gestion,
            'rubro' => $c->rubro?->nombre,
            'beneficiario' => $c->beneficiario?->nombreCompleto,
            'documento_identidad' => $c->beneficiario?->documento_identidad,
            'capacidad' => $c->capacidadLegible(),
        ])->all());
    }

    public function show(Carnet $carnet): Response
    {
        $carnet->load([
            'beneficiario',
            'rubro:id,nombre,descripcion,requiere_capacidad,emite_faenas,emite_guias',
            'tramites.rubro:id,nombre',
            /*
             * LOS PERMISOS OPERATIVOS DEL CARNET.
             *
             * Se cargan los dos aunque un carnet dado solo use uno: limitarlo por
             * rubro obligaría a preguntar antes de poder consultar, y una colección
             * vacía dice exactamente lo mismo. Ver Carnet::faenas() y guias().
             *
             * `limit(10)` porque la ficha muestra los últimos, no el historial
             * entero: un carnet de Comercializador activo puede tener cientos de
             * guías, y traerlas todas para pintar una tabla de diez es tirar
             * memoria. El listado completo está a un clic.
             */
            'faenas' => fn ($q) => $q->limit(10),
            'guias' => fn ($q) => $q->limit(10),
            'guias.detalles',
        ]);

        // Los totales van aparte del `limit` de arriba: la ficha dice «mostrando
        // 10 de 143», y ese 143 no se puede sacar de una colección recortada.
        $carnet->loadCount(['faenas', 'guias']);

        return Inertia::render('panel/carnets/ver', [
            'carnet' => [
                'id' => $carnet->id,
                'gestion' => $carnet->gestion,
                'estado' => $carnet->estado->value,
                'estado_etiqueta' => $carnet->estado->etiqueta(),
                'estado_color' => $carnet->estado->color(),
                'vigente' => $carnet->estaVigente(),
                'admite_tramites' => $carnet->admiteTramites(),

                /*
                 * LA ACTIVIDAD DEL CARNET Y SU CUPO.
                 *
                 * Ocupan el lugar que tenía la lista `habilitaciones`: con un
                 * carnet por rubro no hay lista que mostrar, hay UN rubro y UN
                 * cupo, y los dos van impresos en el plástico.
                 */
                'rubro' => $carnet->rubro?->nombre,
                'rubro_descripcion' => $carnet->rubro?->descripcion,
                'capacidad_kg' => $carnet->capacidad_kg !== null ? (float) $carnet->capacidad_kg : null,
                'capacidad' => $carnet->capacidadLegible(),

                /*
                 * Si esta actividad se autoriza por volumen. La ficha esconde el
                 * bloque del cupo cuando no: mostrar «Cupo autorizado: sin
                 * definir» en un carnet de Comercializador no informa nada, y
                 * sugiere que falta cargar un dato que no existe.
                 */
                'requiere_capacidad' => $carnet->rubro?->requiereCapacidad() ?? false,

                /*
                 * QUÉ PERMISO OPERATIVO PUEDE EMITIR ESTE CARNET.
                 *
                 * Lo decide el servidor —Carnet::puedeEmitirFaenas()— y no React,
                 * por lo mismo que los `puede_*` del trámite: escrita otra vez en
                 * el frontend, la regla terminaría diciendo algo distinto. Incluye
                 * la vigencia, así que un carnet suspendido devuelve false y el
                 * botón no aparece.
                 */
                'puede_emitir_faenas' => $carnet->puedeEmitirFaenas(),
                'puede_emitir_guias' => $carnet->puedeEmitirGuias(),

                // Para los rótulos de las pestañas, aunque no se pueda emitir:
                // un carnet vencido sigue mostrando lo que emitió en su momento.
                'emite_faenas' => $carnet->rubro?->emiteFaenas() ?? false,
                'emite_guias' => $carnet->rubro?->emiteGuias() ?? false,
                'total_faenas' => (int) ($carnet->faenas_count ?? 0),
                'total_guias' => (int) ($carnet->guias_count ?? 0),

                // Si se puede suspender o levantar la suspensión desde la ficha.
                'puede_suspenderse' => $carnet->estado === EstadoCarnet::Vigente,
                'puede_rehabilitarse' => $carnet->estado->esReversible(),
                'fecha_emision' => $carnet->fecha_emision?->toDateString(),
                'fecha_vencimiento' => $carnet->fecha_vencimiento?->toDateString(),

                // El número IMPRESO en el carnet: 000013. Es el id rellenado
                // con ceros, y es por el que se pregunta en ventanilla.
                'registro' => $carnet->registro(),

                /*
                 * LA FIRMA NO VA IMPRESA EN EL PLÁSTICO: viaja solo dentro del
                 * QR. Se muestra ACÁ, en el panel, por un motivo concreto: si el
                 * QR de alguien queda ilegible, esta es la única forma de
                 * recuperarla y dictársela para que pueda verificar. Por eso la
                 * ficha del carnet la trae y ninguna otra pantalla del sistema.
                 */
                'firma' => $carnet->firmaLegible(),

                // Si el plástico se puede sacar o no lo decide el modelo, no la
                // pantalla. Ver Carnet::puedeImprimirse().
                'puede_imprimirse' => $carnet->puedeImprimirse(),
                'url_verificacion' => $carnet->urlVerificacion(),
            ],

            'beneficiario' => [
                'id' => $carnet->beneficiario?->id,
                'nombreCompleto' => $carnet->beneficiario?->nombreCompleto,
                'documento_identidad' => $carnet->beneficiario?->documento_identidad,
                'foto_url' => $carnet->beneficiario?->foto_url,
                'fechaNacimiento' => $carnet->beneficiario?->fechaNacimiento?->toDateString(),
            ],

            'tramites' => $carnet->tramites->map(fn ($t): array => [
                'id' => $t->id,
                'rubro' => $t->rubro?->nombre,
                'tipo_etiqueta' => $t->tipo_tramite->etiqueta(),
                'estado' => $t->estado->value,
                'estado_etiqueta' => $t->estado->etiqueta(),
                'estado_color' => $t->estado->color(),
                'monto_requerido' => (float) $t->monto_requerido,
                'fecha_solicitud' => $t->fecha_solicitud?->toIso8601String(),
            ])->all(),

            'faenas' => $carnet->faenas->map(fn ($f): array => [
                'id' => $f->id,
                'nro_permiso' => $f->nro_permiso,
                'estado_etiqueta' => $f->estado->etiqueta(),
                'estado_color' => $f->estado->color(),
                'embarcacion' => $f->embarcacion,
                'fecha_salida' => $f->fecha_salida?->toDateString(),
                'fecha_desembarque' => $f->fecha_desembarque?->toDateString(),
                'cantidad' => $f->cantidadLegible(),
            ])->all(),

            'guias' => $carnet->guias->map(fn ($g): array => [
                'id' => $g->id,
                'nro_guia' => $g->nro_guia,
                'estado_etiqueta' => $g->estado->etiqueta(),
                'estado_color' => $g->estado->color(),
                'transporte_etiqueta' => $g->tipo_transporte->etiqueta(),
                'destino_lugar' => $g->destino_lugar,
                // El detalle ya vino con el eager loading, así que totalKg() lo
                // suma en memoria y no dispara una consulta por fila.
                'total_kg' => $g->totalKg(),
                'fecha' => $g->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * ========================================================================
     *  SUSPENDER O LEVANTAR UN CARNET — POST /panel/carnets/{carnet}/suspender
     * ========================================================================
     *
     * LA MEDIDA SIGUE SIENDO POR ACTIVIDAD, aunque ahora se aplique al carnet.
     * A un pescador se le puede cortar el transporte sin quitarle la pesca: son
     * dos carnets distintos y se suspende uno. Antes esto se hacía sobre la fila
     * de `carnet_rubro`; con un carnet por rubro, suspender el carnet ES
     * suspender esa actividad, y los demás carnets de la persona no se enteran.
     *
     * EL CARNET NO SE BORRA NI SE ANULA AL SUSPENDER. Borrarlo dejaría al
     * sistema como si esa actividad nunca se hubiera habilitado, y se perdería
     * el dato de que estuvo autorizada hasta tal fecha —que es lo que un
     * inspector necesita saber cuando revisa una infracción del mes pasado—.
     * Anularlo sería definitivo, y una suspensión por definición no lo es.
     *
     * ------------------------------------------------------------------------
     *  ES UN INTERRUPTOR, PERO NO SE PUEDE ENCENDER DESDE CUALQUIER LADO
     * ------------------------------------------------------------------------
     *
     * Solo van y vuelven VIGENTE y SUSPENDIDO. Un carnet anulado o vencido no se
     * suspende —ya no habilita— ni se «levanta» a vigente, porque eso
     * resucitaría un documento que caducó o que se dio de baja por una sanción.
     * Quién puede pasar a qué lo dice `EstadoCarnet`, no este método.
     */
    public function suspender(Request $request, Carnet $carnet): RedirectResponse
    {
        $suspender = $carnet->estado === EstadoCarnet::Vigente;

        if (! $suspender && ! $carnet->estado->esReversible()) {
            return back()->with(
                'error',
                "El carnet está {$carnet->estado->etiqueta()} y ese estado no se revierte desde acá.",
            );
        }

        $carnet->update([
            'estado' => $suspender ? EstadoCarnet::Suspendido : EstadoCarnet::Vigente,
        ]);

        // El trait Auditable ya registró el cambio con el usuario y la IP. Se le
        // agrega el motivo como descripción porque el «por qué» de una sanción
        // no está en los valores antes/después.
        if (filled($request->input('motivo'))) {
            $carnet->registrarAuditoria(
                $suspender ? 'suspendido' : 'rehabilitado',
                null,
                trim((string) $request->input('motivo')),
            );
        }

        $rubro = $carnet->rubro?->nombre ?? 'el rubro';

        return back()->with('exito', $suspender
            ? "Carnet de «{$rubro}» de la gestión {$carnet->gestion} suspendido."
            : "Carnet de «{$rubro}» rehabilitado.");
    }

    /**
     * ANULAR EL CARNET — POST /panel/carnets/{carnet}/anular
     *
     * EL CARNET NO SE BORRA NI SE REEMPLAZA. Su número de registro es el `id`
     * de la fila —ver Carnet::registro()— y la secuencia no lo devuelve al
     * borrar; un registro que desaparece deja un hueco en la serie que nadie
     * puede explicar después.
     *
     * Anulado sigue ocupando su lugar, así que la persona TAMPOCO puede sacar
     * otro carnet DEL MISMO RUBRO este año: el índice único
     * (beneficiario, rubro, gestión) no lo permitiría. Es lo correcto —anular es
     * una sanción, no un trámite de reposición— pero conviene tenerlo presente
     * antes de apretar el botón.
     *
     * Sus OTRAS actividades no se tocan: anular el carnet de Comercializador
     * deja intacto el de Pescador. Para cortar todo hay que anular cada uno.
     */
    public function anular(Request $request, Carnet $carnet): RedirectResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'motivo.required' => 'Escriba el motivo de la anulación.',
            'motivo.min' => 'El motivo tiene que explicar la causa: escriba al menos 10 caracteres.',
        ]);

        if ($carnet->estado === EstadoCarnet::Anulado) {
            return back()->with('error', 'El carnet ya estaba anulado.');
        }

        $carnet->update(['estado' => EstadoCarnet::Anulado]);

        $carnet->registrarAuditoria('anulado', null, trim($datos['motivo']));

        return back()->with('exito', "Carnet de la gestión {$carnet->gestion} anulado.");
    }
}
