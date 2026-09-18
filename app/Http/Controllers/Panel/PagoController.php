<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoValidacionPago;
use App\Exceptions\SolicitudInvalidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\CorregirPagoRequest;
use App\Http\Requests\Panel\RegistrarPagoRequest;
use App\Models\Faena;
use App\Models\Guia;
use App\Models\Pago;
use App\Models\Tramite;
use App\Services\PagoTramiteService;
use App\Services\ValidacionPagoService;
use App\Support\ControlDePago;
use App\Support\Paginacion;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  MÓDULO PAGOS — Regla C
 * ============================================================================
 *
 * Un trámite se cubre con uno o varios depósitos. Este controlador atiende el
 * caso de «el pescador volvió con otra boleta»; la primera boleta, si la trajo
 * el día del alta, entra por TramiteController::store().
 *
 * NO HAY ALTA SUELTA DE PAGO. Todas las rutas cuelgan de un trámite
 * (/panel/tramites/{tramite}/pagos) porque un pago sin expediente no significa
 * nada: no habría contra qué compararlo ni a quién acreditárselo.
 *
 * ----------------------------------------------------------------------------
 *  EL LIBRO DE CAJA YA NO ES SOLO DE TRÁMITES
 * ----------------------------------------------------------------------------
 *
 * Desde que `pagos` es polimórfica, esta pantalla lista también lo cobrado por
 * FAENAS y GUÍAS — y tiene que ser así, porque lo que se cuadra contra el
 * extracto del banco es TODO lo que entró, no una parte.
 *
 * El alta de esos otros dos no pasa por acá: cada permiso se cobra desde su
 * propia pantalla, por lo mismo que un pago de trámite se carga desde el
 * expediente.
 */
class PagoController extends Controller
{
    public function __construct(
        private readonly PagoTramiteService $pagos,
        private readonly ValidacionPagoService $validaciones,
    ) {}

    /**
     * LIBRO DE CAJA — GET /panel/pagos
     *
     * Todos los depósitos del sistema, para cuadrar contra el extracto del
     * banco. Es la única pantalla de pagos que no cuelga de un trámite, porque
     * justamente lo que se quiere es mirarlos todos juntos.
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
            // «Mostrame lo que falta controlar» es la primera pregunta de quien
            // revisa, así que el estado del control es un filtro y no solo una
            // columna.
            'validacion' => $request->string('validacion')->trim()->value() ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $pagos = Pago::query()
            /*
             * EL EAGER LOADING DE UNA RELACIÓN POLIMÓRFICA VA CON morphWith.
             *
             * Un `with('pagable.carnet')` a secas NO COMPILA: Eloquent no sabe
             * qué es `pagable` hasta que lee la fila, así que no puede resolver
             * lo que cuelga de él. `morphWith` le dice qué traer para cada tipo,
             * y resuelve todo en una consulta por tipo presente en la página.
             *
             * Sin esto son cuatro consultas por fila y el libro de caja de un
             * mes es un N+1 de manual.
             */
            ->with(['pagable' => fn (MorphTo $m) => $m->morphWith([
                Tramite::class => [
                    'rubro:id,nombre',
                    'carnet:id,beneficiario_id',
                    'carnet.beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                ],
                // En faenas y guías el rubro no está en la fila: se llega por el
                // carnet, que es de un solo rubro desde el modelo nuevo.
                Faena::class => [
                    'carnet:id,beneficiario_id,rubro_id',
                    'carnet.rubro:id,nombre',
                    'carnet.beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                ],
                Guia::class => [
                    'carnet:id,beneficiario_id,rubro_id',
                    'carnet.rubro:id,nombre',
                    'carnet.beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                ],
            ])])
            // Quién cargó y quién controló, para las dos columnas de la
            // derecha. Sin esto son dos consultas por fila.
            ->with(['registradoPor:id,name', 'validadoPor:id,name'])

            ->when($filtros['buscar'], fn ($q, $t) => $q->where('nro_transaccion', 'like', "%{$t}%"))
            ->when($filtros['validacion'], fn ($q, $v) => $q->where('pagos.estado_validacion', $v))
            // whereDate y no where: `fecha_pago` es un timestamp, y comparar con
            // una fecha suelta dejaría fuera todo lo cargado después de las 00:00
            // del día «hasta».
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_pago', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_pago', '<=', $h))
            ->latest('fecha_pago')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through(fn (Pago $p): array => [
                'id' => $p->id,
                'nro_transaccion' => $p->nro_transaccion,
                'monto' => (float) $p->monto,
                /*
                 * LA FECHA DEL DEPÓSITO VIAJA COMO FECHA SUELTA, NO COMO
                 * INSTANTE — y la diferencia se veía en pantalla.
                 *
                 * La columna es un timestamp, pero lo que guarda es el DÍA que
                 * dice la boleta del banco. Mandado con `toIso8601String()`
                 * salía «2026-09-17T00:00:00+00:00», y el navegador lo
                 * convertía a horario local: en Bolivia —UTC-4— eso es el 16 a
                 * las 20:00, así que un depósito del 17 se mostraba como 16/09.
                 * El operador tipeaba una fecha y la pantalla le contestaba
                 * otra.
                 *
                 * Con `toDateString()` llega «2026-09-17», que `fecha()` trata
                 * como un día del calendario y no corre de zona. Ver
                 * `aFechaLocal()` en lib/utils.ts y la trampa anotada en
                 * CLAUDE.md.
                 */
                'fecha_pago' => $p->fecha_pago?->toDateString(),
                'comprobante_url' => $p->comprobante_url,
                ...ControlDePago::resumen($p, $request->user()),
                ...$this->origen($p),
            ]);

        return Inertia::render('panel/pagos/index', [
            'pagos' => $pagos,
            'filtros' => $filtros,
            // El catálogo sale del servidor: son los valores del enum, y
            // escritos también en React algún día dirían otra cosa.
            'validaciones' => EstadoValidacionPago::opciones(),
            // El total se calcula sobre TODO lo filtrado, no sobre la página que
            // se está viendo: sumar lo de la página respondería una pregunta que
            // nadie hizo.
            'total' => (float) Pago::query()
                ->when($filtros['buscar'], fn ($q, $t) => $q->where('nro_transaccion', 'like', "%{$t}%"))
                ->when($filtros['validacion'], fn ($q, $v) => $q->where('estado_validacion', $v))
                ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_pago', '>=', $d))
                ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_pago', '<=', $h))
                ->sum('monto'),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * ========================================================================
     *  VALIDAR UN DEPÓSITO — PATCH /panel/pagos/{pago}/validar
     * ========================================================================
     *
     * Quien revisa abrió la boleta, la comparó contra el extracto del banco y
     * dice que cuadra. Queda escrito quién y cuándo.
     */
    public function validar(Request $request, Pago $pago): RedirectResponse
    {
        try {
            $this->validaciones->validar($pago, $request->user());
        } catch (SolicitudInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', "Depósito {$pago->nro_transaccion} validado.");
    }

    /**
     * ========================================================================
     *  OBSERVAR UN DEPÓSITO — PATCH /panel/pagos/{pago}/observar
     * ========================================================================
     *
     * La boleta no cuadra. El motivo es OBLIGATORIO y lo exige el servicio: es
     * lo único que le dice a ventanilla qué tiene que ir a corregir.
     *
     * No es definitivo —se corrige y se vuelve a validar— y por eso es otra cosa
     * que rechazar el trámite: acá el problema es de UN depósito.
     */
    public function observar(Request $request, Pago $pago): RedirectResponse
    {
        try {
            $this->validaciones->observar(
                $pago,
                $request->user(),
                $request->string('motivo')->trim()->value(),
            );
        } catch (SolicitudInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', "Depósito {$pago->nro_transaccion} observado.");
    }

    /**
     * ========================================================================
     *  DE QUÉ ES ESTE DEPÓSITO
     * ========================================================================
     *
     * Devuelve los campos que describen el origen del pago, ya resueltos para la
     * pantalla. El libro de caja tiene que poder decir «esto es la emisión del
     * carnet de Pérez» o «esto es la faena 002190 de Pérez», y desde que `pagos`
     * es polimórfica eso depende de a qué apunte la fila.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ EL `match` VA ACÁ Y NO EN EL MODELO
     * ------------------------------------------------------------------------
     *
     * Porque es presentación: qué rótulo lee el operador y a qué pantalla lleva
     * el enlace. Las reglas de negocio —quién puede emitir qué, cuánto falta
     * pagar— sí viven en los modelos y los servicios.
     *
     * `tramite_id` viaja aparte y puede ser null: es lo único que habilita el
     * enlace a la ficha del expediente. Faenas y guías todavía no tienen
     * pantalla propia, así que su fila se muestra sin enlace en vez de llevar a
     * una ruta que no existe.
     *
     * @return array<string, mixed>
     */
    private function origen(Pago $p): array
    {
        $pagable = $p->pagable;

        // Un pago cuyo `pagable` no se puede resolver no debería existir —la
        // fila apunta a algo borrado—, pero la base ya no tiene clave foránea
        // que lo impida. Si pasa, la pantalla lo muestra en vez de reventar:
        // un libro de caja al que le falta una línea es peor que uno con una
        // línea incompleta, porque el total ya no cuadra y nadie sabe por qué.
        if ($pagable === null) {
            return [
                'concepto' => 'Origen no encontrado',
                'tramite_id' => null,
                'carnet_registro' => null,
                'beneficiario' => null,
                'rubro' => null,
            ];
        }

        $carnet = $pagable->carnet;

        return [
            'concepto' => match (true) {
                $pagable instanceof Tramite => $pagable->tipo_tramite->etiqueta(),
                $pagable instanceof Faena => "Faena {$pagable->nro_permiso}",
                $pagable instanceof Guia => "Guía {$pagable->nro_guia}",
                default => 'Otro',
            },
            'tramite_id' => $pagable instanceof Tramite ? $pagable->id : null,
            'carnet_registro' => $carnet?->registro(),
            'beneficiario' => $carnet?->beneficiario?->nombreCompleto,
            // El trámite guarda su propio rubro —es el que se pidió—; la faena y
            // la guía lo heredan del carnet, que es de una sola actividad.
            'rubro' => $pagable instanceof Tramite
                ? $pagable->rubro?->nombre
                : $carnet?->rubro?->nombre,
        ];
    }

    /**
     * REGISTRAR UN DEPÓSITO — POST /panel/tramites/{tramite}/pagos
     *
     * El método es corto porque no decide nada: si el trámite admite pagos, si
     * el número de transacción ya existe, en qué orden se sube el archivo
     * respecto de la transacción, todo eso lo resuelve PagoTramiteService.
     */
    public function store(RegistrarPagoRequest $request, Tramite $tramite): RedirectResponse
    {
        try {
            $this->pagos->registrar(
                $tramite,
                $request->safe()->except('comprobante'),
                $request->file('comprobante'),
            );
        } catch (SolicitudInvalidaException $e) {
            // El mensaje está escrito para que lo lea el operador. Los archivos
            // no vuelven con withInput —los navegadores no dejan rellenar un
            // input file— así que hay que volver a adjuntar la boleta.
            return back()->withInput()->with('error', $e->getMessage());
        }

        $saldo = $this->pagos->saldoPendiente($tramite);

        return back()->with('exito', $saldo > 0
            // Se le dice cuánto falta en el mismo mensaje: es lo primero que
            // pregunta el pescador en el mostrador.
            ? sprintf('Pago registrado. Queda un saldo de %s.', number_format($saldo, 2, ',', '.'))
            : 'Pago registrado. El trámite quedó totalmente cubierto y ya puede aprobarse.');
    }

    /**
     * ========================================================================
     *  CORREGIR UN DEPÓSITO — PUT /panel/pagos/{pago}
     * ========================================================================
     *
     * «El monto cargado no es el de la boleta», «al número le falta un dígito».
     * Corregir es la ÚNICA salida: los pagos no se anulan ni se borran, y lo
     * que había queda registrado en `auditorias`.
     *
     * LA RUTA CUELGA DEL PAGO Y NO DEL TRÁMITE, al revés que el alta. El alta
     * necesita saber A QUÉ se le carga el depósito; la corrección no, porque el
     * pago ya sabe de qué es —y desde que `pagos` es polimórfica, ese «de qué»
     * puede ser también una faena o una guía—.
     *
     * Vuelve con `back()` y no a una ficha fija por lo mismo: se corrige desde
     * la pantalla donde se estaba trabajando, y esa pantalla depende de qué se
     * está pagando.
     */
    public function update(CorregirPagoRequest $request, Pago $pago): RedirectResponse
    {
        try {
            $this->pagos->corregir(
                $pago,
                $request->safe()->except('comprobante'),
                $request->file('comprobante'),
            );
        } catch (SolicitudInvalidaException $e) {
            // Los archivos no vuelven con withInput —los navegadores no dejan
            // rellenar un input file— así que si había que reemplazar la boleta
            // hay que volver a adjuntarla.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('exito', "Depósito {$pago->nro_transaccion} corregido.");
    }

    /**
     * ========================================================================
     *  QUITAR UN DEPÓSITO — DELETE /panel/pagos/{pago}
     * ========================================================================
     *
     * La boleta que se cargó dos veces, o la de otra persona pegada en el
     * expediente equivocado. Corregirla no alcanza porque no hay ningún dato
     * correcto que poner.
     *
     * EL MOTIVO SE VALIDA ACÁ ADEMÁS DE EN EL SERVICIO, igual que al eliminar
     * un trámite: la validación de Laravel pinta el error bajo el campo del
     * formulario, que es lo que el operador necesita ver; la del servicio es la
     * que garantiza que nadie borre sin motivo entrando por otra puerta.
     *
     * El número de transacción se guarda ANTES de la baja: después de borrar la
     * fila, el mensaje no tendría con qué nombrar lo que acaba de irse.
     */
    public function destroy(Request $request, Pago $pago): RedirectResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'motivo.required' => 'Escriba por qué se quita este depósito.',
            'motivo.min' => 'El motivo tiene que explicar qué pasó: escriba al menos 10 caracteres.',
        ]);

        $nro = $pago->nro_transaccion;

        try {
            $this->pagos->eliminar($pago, $datos['motivo']);
        } catch (SolicitudInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', "Depósito {$nro} quitado del expediente.");
    }
}
