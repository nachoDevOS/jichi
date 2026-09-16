<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoCarnet;
use App\Enums\EstadoHabilitacion;
use App\Http\Controllers\Controller;
use App\Models\Carnet;
use App\Models\CarnetRubro;
use App\Support\Paginacion;
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
 * suspender un rubro y anular el documento entero.
 */
class CarnetController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'gestion' => $request->integer('gestion') ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $carnets = Carnet::query()
            ->with(['beneficiario:id,ci_nit,complemento,expedido,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado'])
            ->withCount('habilitaciones')

            // Se califica la columna con el nombre de la tabla porque
            // `carnets`, `beneficiarios` y `rubros` tienen todas una columna
            // `estado` y esta consulta las cruza: sin calificar, PostgreSQL
            // responde «column reference "estado" is ambiguous».
            ->when($filtros['estado'], fn ($q, $e) => $q->where('carnets.estado', $e))
            ->when($filtros['gestion'], fn ($q, $g) => $q->where('gestion', $g))
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
                'rubros_count' => $c->habilitaciones_count,
                'fecha_emision' => $c->fecha_emision?->toDateString(),
                'fecha_vencimiento' => $c->fecha_vencimiento?->toDateString(),
            ]);

        return Inertia::render('panel/carnets/index', [
            'carnets' => $carnets,
            'filtros' => $filtros,
            'estados' => EstadoCarnet::opciones(),
            'gestiones' => Carnet::query()->distinct()->orderByDesc('gestion')->pluck('gestion')->all(),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    public function show(Carnet $carnet): Response
    {
        $carnet->load([
            'beneficiario',
            'habilitaciones.rubro:id,nombre,descripcion',
            'tramites.rubro:id,nombre',
        ]);

        return Inertia::render('panel/carnets/ver', [
            'carnet' => [
                'id' => $carnet->id,
                'gestion' => $carnet->gestion,
                'estado' => $carnet->estado->value,
                'estado_etiqueta' => $carnet->estado->etiqueta(),
                'estado_color' => $carnet->estado->color(),
                'vigente' => $carnet->estaVigente(),
                'admite_adiciones' => $carnet->admiteAdiciones(),
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

            'habilitaciones' => $carnet->habilitaciones->map(fn (CarnetRubro $h): array => [
                'id' => $h->id,
                'rubro' => $h->rubro?->nombre,
                'descripcion' => $h->rubro?->descripcion,
                'estado' => $h->estado->value,
                'estado_etiqueta' => $h->estado->etiqueta(),
                'estado_color' => $h->estado->color(),
                'fecha_habilitacion' => $h->fecha_habilitacion?->toDateString(),

                // El cupo autorizado para ESTE rubro. No sale en el plástico
                // —se decidió no imprimirlo— pero sí acá, que es donde la
                // unidad lo consulta y lo contrasta contra las guías.
                'capacidad_kg' => $h->capacidad_kg !== null
                    ? (float) $h->capacidad_kg
                    : null,
            ])->all(),

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
        ]);
    }

    /**
     * SUSPENDER O REHABILITAR UN RUBRO — POST /panel/habilitaciones/{habilitacion}/alternar
     *
     * La medida es por rubro y no por carnet: a un pescador se le puede cortar
     * el transporte sin quitarle la pesca, y el carnet sigue valiendo para lo
     * demás.
     *
     * La fila NO se borra al suspender. Borrarla dejaría al carnet como si ese
     * rubro nunca se hubiera habilitado, y se perdería el dato de que estuvo
     * autorizado hasta tal fecha —que es lo que un inspector necesita saber
     * cuando revisa una infracción del mes pasado—.
     */
    public function alternarHabilitacion(Request $request, CarnetRubro $habilitacion): RedirectResponse
    {
        $suspender = $habilitacion->estaHabilitado();

        $habilitacion->update([
            'estado' => $suspender ? EstadoHabilitacion::Suspendido : EstadoHabilitacion::Habilitado,
        ]);

        // El trait Auditable ya registró el cambio con el usuario y la IP. Se le
        // agrega el motivo como descripción porque el «por qué» de una sanción
        // no está en los valores antes/después.
        if (filled($request->input('motivo'))) {
            $habilitacion->registrarAuditoria(
                $suspender ? 'suspendido' : 'rehabilitado',
                null,
                trim((string) $request->input('motivo')),
            );
        }

        return back()->with('exito', $suspender
            ? "Rubro «{$habilitacion->rubro?->nombre}» suspendido en el carnet de {$habilitacion->carnet?->gestion}."
            : "Rubro «{$habilitacion->rubro?->nombre}» rehabilitado.");
    }

    /**
     * ANULAR EL CARNET — POST /panel/carnets/{carnet}/anular
     *
     * EL CARNET NO SE BORRA NI SE REEMPLAZA. Su número de registro es el `id`
     * de la fila —ver Carnet::registro()— y la secuencia no lo devuelve al
     * borrar; un registro que desaparece deja un hueco en la serie que nadie
     * puede explicar después.
     *
     * Anulado sigue ocupando su lugar en la gestión, así que la persona TAMPOCO
     * puede sacar otro este año: el índice único (beneficiario, gestión) no lo
     * permitiría. Es lo correcto —anular es una sanción, no un trámite de
     * reposición— pero conviene tenerlo presente antes de apretar el botón.
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
