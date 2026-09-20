<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Http\Requests\Panel\GuardarBeneficiarioRequest;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Support\Archivos;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  MÓDULO BENEFICIARIOS — controlador de ejemplo
 */
class BeneficiarioController extends Controller
{
    /**
     * LISTADO — GET /panel/beneficiarios
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            // La lista de tamaños permitidos vive en App\Support\Paginacion: es
            // una regla de seguridad —el número llega por la barra de
            // direcciones— y la comparten todos los listados del panel.
            'por_pagina' => Paginacion::filas($request),
        ];

        $beneficiarios = Beneficiario::query()
            // scopeBuscar() vive en el modelo. Busca por CI, nombre, email o
            // teléfono, y ya resuelve la diferencia entre ILIKE (PostgreSQL) y
            // LIKE (SQLite). Ver app/Support/Sql.php.
            ->buscar($filtros['buscar'])

            ->ordenAlfabetico()
            ->paginate($filtros['por_pagina'])

            // Sin withQueryString(), al hacer clic en «página 2» se perderían
            // los filtros y la búsqueda volvería a empezar de cero.
            ->withQueryString()

            // through() transforma cada fila SIN romper la paginación. Se envía
            // a React solo lo que la tabla realmente pinta: mandar el modelo
            // entero expondría datos de más y haría el JSON más pesado.
            ->through(fn (Beneficiario $b): array => [
                'id' => $b->id,

                // --- Columna «Beneficiario»: foto, nombre y cédula juntos
                'foto_url' => $b->foto_url,
                'nombreCompleto' => $b->nombreCompleto,
                'documento_identidad' => $b->documento_identidad,

                // --- Columna «Datos»: género, celular y nacimiento
                'genero' => $b->genero,
                'telefono' => $b->telefono,
                'fechaNacimiento' => $b->fechaNacimiento?->toDateString(),

                /*
                 * La edad se calcula acá y NO se manda la fecha sola para que
                 * React la calcule.
                 */
                'edad' => $b->edad,
            ]);

        return Inertia::render('panel/beneficiarios/index', [
            'beneficiarios' => $beneficiarios,
            'filtros' => $filtros,
            // Las opciones del selector salen del servidor y no escritas en el
            // componente de React: si estuvieran en los dos lados, alcanzaría
            // con agregar una en la pantalla y olvidarse del servidor para que
            // el operador elija un número que después se ignora sin aviso.
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * FORMULARIO DE ALTA — GET /panel/beneficiarios/crear
     */
    public function create(): Response
    {
        return Inertia::render('panel/beneficiarios/crear', $this->catalogos());
    }

    /**
     * GUARDAR EL ALTA — POST /panel/beneficiarios
     */
    public function store(GuardarBeneficiarioRequest $request): RedirectResponse
    {
        // validated() devuelve SOLO los campos que pasaron por las reglas. Con
        // all() alguien podría mandar campos de más por el formulario.
        $beneficiario = new Beneficiario($request->validated());

        if ($request->hasFile('foto')) {
            $beneficiario->foto = $this->guardarFoto($request);
        }

        // Al guardar, el trait Auditable escribe solo la fila correspondiente en
        // `auditorias`, con el usuario de la sesión. Ahí queda el rastro de
        // quién dio el alta: la tabla `beneficiarios` no lo guarda.
        $beneficiario->save();

        return redirect()
            ->route('beneficiarios.show', $beneficiario)
            ->with('exito', "Beneficiario {$beneficiario->nombreCompleto} registrado correctamente.");
    }

    /**
     * FICHA — GET /panel/beneficiarios/{beneficiario}
     */
    public function show(Beneficiario $beneficiario): Response
    {
        $gestion = (int) now()->format('Y');

        return Inertia::render('panel/beneficiarios/ver', [
            'beneficiario' => [
                ...$beneficiario->only([
                    'id', 'ci', 'complemento', 'expedido', 'primerNombre',
                    'segundoNombre', 'apellidoPaterno', 'apellidoMaterno',
                    'apellidoCasado', 'genero', 'nacionalidad', 'direccion',
                    'ciudad', 'provincia', 'telefono', 'email',
                ]),
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,
                'fechaNacimiento' => $beneficiario->fechaNacimiento?->toDateString(),
                'registrado' => $beneficiario->created_at?->toIso8601String(),
            ],

            'gestion' => $gestion,

            // Lo que debe en total, sumando carnets, cupos y guías sin cubrir.
            'deuda' => $beneficiario->deudaTotal(),

            /*
             *  SUS CREDENCIALES — el paso 3 del flujo
             */
            'carnets' => $beneficiario->carnets()
                ->with(['asociacion:id,nombre,sigla', 'tipoCarnet', 'aprovechamiento'])
                ->withSum('pagos', 'monto_parcial')
                ->orderByDesc('fecha_emision')
                ->get()
                ->map($this->resumirCarnet(...))
                ->all(),

            /*
             *  SUS BOLSAS MADRE — el paso 2, y el que explica las faenas
             */
            'cupos' => $beneficiario->aprovechamientos()
                ->with('categoria')
                ->withSum('faenasQueConsumen', 'kilos_extraidos')
                ->withSum('pagos', 'monto_parcial')
                // Por la SOLICITUD: la emisión está en NULL hasta la firma.
                ->orderByDesc('fecha_solicitud')
                ->get()
                ->map(fn (AprovechamientoPesq $a): array => [
                    'id' => $a->id,
                    'escala' => $a->categoria?->nro_escala,
                    'descripcion' => $a->categoria?->descripcion_kg,
                    'volumen_total_kg' => (float) $a->volumen_total_kg,
                    'kilos_consumidos' => $a->kilosConsumidos(),
                    'saldo_kg' => $a->saldoKg(),
                    'porcentaje_usado' => $a->porcentajeUsado(),
                    'estado' => $a->estado->value,
                    'estado_etiqueta' => $a->estado->etiqueta(),
                    'estado_color' => $a->estado->color(),
                    'vigente' => $a->estaVigente(),
                    // Antes de la firma no hay saldo que mostrar: no se consumió.
                    'ya_fue_aprobado' => $a->yaFueAprobado(),
                    'saldo_pendiente' => $a->saldoPendiente(),
                    'fecha_solicitud' => $a->fecha_solicitud?->toDateString(),
                    'fecha_emision' => $a->fecha_emision?->toDateString(),
                    'fecha_vencimiento' => $a->fecha_vencimiento?->toDateString(),
                ])
                ->all(),
        ]);
    }

    /**
     * FORMULARIO DE EDICIÓN — GET /panel/beneficiarios/{beneficiario}/editar
     */
    public function edit(Beneficiario $beneficiario): Response
    {
        return Inertia::render('panel/beneficiarios/editar', [
            'beneficiario' => [
                ...$beneficiario->only([
                    'id', 'ci', 'complemento', 'expedido', 'primerNombre',
                    'segundoNombre', 'apellidoPaterno', 'apellidoMaterno',
                    'apellidoCasado', 'genero', 'nacionalidad', 'direccion',
                    'ciudad', 'provincia', 'telefono', 'email',
                ]),
                'fechaNacimiento' => $beneficiario->fechaNacimiento?->toDateString(),
                'foto_url' => $beneficiario->foto_url,
            ],
            ...$this->catalogos(),
        ]);
    }

    /**
     * GUARDAR LA EDICIÓN — PUT /panel/beneficiarios/{beneficiario}
     */
    public function update(GuardarBeneficiarioRequest $request, Beneficiario $beneficiario): RedirectResponse
    {
        $beneficiario->fill($request->validated());

        if ($request->hasFile('foto')) {
            // Se borra la foto anterior para no dejar archivos huérfanos
            // ocupando disco cada vez que alguien actualiza la imagen.
            Archivos::borrar($beneficiario->foto);
            $beneficiario->foto = $this->guardarFoto($request);
        } elseif ($request->boolean('quitar_foto')) {
            Archivos::borrar($beneficiario->foto);
            $beneficiario->foto = null;
        }

        $beneficiario->save();

        return redirect()
            ->route('beneficiarios.show', $beneficiario)
            ->with('exito', 'Los datos del beneficiario fueron actualizados.');
    }

    /**
     * BAJA — DELETE /panel/beneficiarios/{beneficiario}
     */
    public function destroy(Beneficiario $beneficiario): RedirectResponse
    {
        $beneficiario->delete();

        return redirect()
            ->route('beneficiarios.index')
            ->with('exito', 'Beneficiario dado de baja.');
    }

    /**
     * BUSCADOR PARA EL FORMULARIO DE SOLICITUD — GET /panel/beneficiarios/buscar
     *
     * @return array<int, array<string, mixed>>
     */
    public function buscar(Request $request): array
    {
        $termino = $request->string('q')->trim()->value();

        // Sin término no se devuelve el padrón entero: serían miles de filas
        // viajando al navegador en cada tecla.
        if (mb_strlen($termino) < 3) {
            return [];
        }

        return Beneficiario::query()
            ->buscar($termino)
            /*
             * Se traen los carnets VIGENTES con su tipo, todo en la misma tanda
             * de consultas.
             */
            /*
             * EL CUPO VIAJA CON EL CARNET, y los dos agregados con él.
             */
            ->with(['carnets' => fn ($q) => $q
                ->vigentes()
                ->with([
                    'tipoCarnet:id,nombre',
                    'aprovechamiento' => fn ($a) => $a
                        ->withSum('faenasQueConsumen', 'kilos_extraidos')
                        ->withMax('faenas', 'numero_faena'),
                ]),
            ])
            ->ordenAlfabetico()
            ->limit(10)
            ->get()
            ->map(fn (Beneficiario $b): array => [
                'id' => $b->id,
                'nombreCompleto' => $b->nombreCompleto,
                'documento_identidad' => $b->documento_identidad,
                'foto_url' => $b->foto_url,

                // Van al carnet: se imprimen debajo de la asociación. Salen de la
                // FICHA de la persona, no del formulario de trámite —son datos
                // del padrón, y se corrigen editando al beneficiario—.
                'ciudad' => $b->ciudad,
                'provincia' => $b->provincia,
                'direccion' => $b->direccion,

                /*
                 * QUÉ PUEDE EMITIR ESTA PERSONA HOY, ya resuelto.
                 */
                'carnets_vigentes' => $b->carnets->map(fn (Carnet $c): array => [
                    'id' => $c->id,
                    'codigo' => $c->codigo_legible,
                    'tipo' => $c->tipoCarnet?->nombre,
                    'tipo_actor' => $c->tipo_actor->value,
                    'tipo_actor_etiqueta' => $c->tipo_actor->etiqueta(),
                    'puede_emitir_faenas' => $c->puedeEmitirFaenas(),
                    'puede_emitir_guias' => $c->puedeEmitirGuias(),

                    /*
                     * Lo que el formulario de faena necesita para abrir con los
                     * dos campos difíciles ya resueltos: cuántos kilos quedan y
                     * qué número de talonario propone.
                     */
                    'saldo_kg' => $c->aprovechamiento?->saldoKg(),
                    'siguiente_numero_faena' => $c->aprovechamiento
                        ? (int) ($c->aprovechamiento->faenas_max_numero_faena ?? 0) + 1
                        : null,
                ])->values()->all(),
            ])
            ->all();
    }

    //  Auxiliares

    /**
     * Los datos de un carnet que pinta la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumirCarnet(Carnet $carnet): array
    {
        return [
            'id' => $carnet->id,
            // En grupos de cuatro: «PES2 6000 0017». Se guarda sin separadores.
            'codigo' => $carnet->codigo_legible,
            'tipo' => $carnet->tipoCarnet?->nombre,
            'tipo_actor' => $carnet->tipo_actor->value,
            'tipo_actor_etiqueta' => $carnet->tipo_actor->etiqueta(),
            'tipo_actor_color' => $carnet->tipo_actor->color(),
            'asociacion' => $carnet->asociacion?->sigla ?? $carnet->asociacion?->nombre,
            // Los kilos impresos en el plástico, o null si es comercializador.
            // Lo decide TipoActor::requiereAprovechamiento(), nunca el nombre
            // del tipo de carnet. Ver Carnet::cupoImpreso().
            'cupo_kg' => $carnet->cupoImpreso(),
            'estado' => $carnet->estado->value,
            'estado_etiqueta' => $carnet->estado->etiqueta(),
            'estado_color' => $carnet->estado->color(),
            'vigente' => $carnet->estaVigente(),
            'monto' => $carnet->montoACobrar(),
            'saldo_pendiente' => $carnet->saldoPendiente(),
            'fecha_emision' => $carnet->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $carnet->fecha_vencimiento?->toDateString(),
        ];
    }

    /**
     * Las listas fijas que necesitan los dos formularios de beneficiario.
     *
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'expedidos' => collect(config('jichi.expedido'))
                ->map(fn (string $nombre, string $codigo): array => [
                    'value' => $codigo,
                    'label' => $codigo.' — '.$nombre,
                ])
                ->values()
                ->all(),
            'provincias' => config('jichi.provincias'),
        ];
    }

    /**
     * Guarda la foto y devuelve su ruta.
     */
    private function guardarFoto(GuardarBeneficiarioRequest $request): string
    {
        return app(StorageController::class)->file($request->file('foto'), 'beneficiarios');
    }
}
