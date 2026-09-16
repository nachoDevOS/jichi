<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Http\Requests\Panel\GuardarBeneficiarioRequest;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Support\Archivos;
use App\Support\Paginacion;
use App\Support\SituacionCarnet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  MÓDULO BENEFICIARIOS — controlador de ejemplo
 * ============================================================================
 *
 * Este archivo es la PLANTILLA del sistema: los demás módulos siguen el mismo
 * patrón. Está comentado paso a paso a propósito.
 *
 * ----------------------------------------------------------------------------
 *  CÓMO VIAJA LA INFORMACIÓN DE LARAVEL A REACT
 * ----------------------------------------------------------------------------
 *
 *   1. El navegador pide  GET /panel/beneficiarios
 *   2. routes/panel.php decide que ese pedido lo atiende el método index()
 *   3. index() consulta la base de datos con Eloquent
 *   4. index() devuelve  Inertia::render('panel/beneficiarios/index', [...datos...])
 *   5. Inertia busca  resources/js/pages/panel/beneficiarios/index.tsx
 *   6. Ese componente de React recibe los [...datos...] como PROPS
 *
 * Lo importante: NO hay una API REST de por medio, no se escribe fetch() ni
 * axios en ninguna parte. El array que se pasa como segundo argumento de
 * Inertia::render() ES el objeto de props que llega a React.
 *
 * ----------------------------------------------------------------------------
 *  Y DE REACT DE VUELTA A LARAVEL
 * ----------------------------------------------------------------------------
 *
 * En React se usa router.post() / useForm().post() de Inertia. Eso hace un POST
 * normal, con token CSRF, a una ruta normal. El controlador responde con un
 * redirect() (nunca con JSON), e Inertia pinta la página destino sin recargar.
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
                 *
                 * Podría hacerse en el navegador, pero entonces habría dos
                 * definiciones de «edad» en el sistema —la de PHP y la de
                 * JavaScript— y tarde o temprano difieren por un día en los
                 * bordes: el cumpleaños de hoy, los años bisiestos, la zona
                 * horaria del teléfono del operador. El servidor ya sabe la
                 * respuesta; que la mande.
                 *
                 * Ver Beneficiario::edad(), y por qué no hay columna `edad`.
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
     *
     * Fíjate en el tipo del parámetro: GuardarBeneficiarioRequest, no Request.
     * Con eso Laravel valida ANTES de entrar acá; si la validación falla, este
     * método nunca llega a ejecutarse.
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
     *
     * El parámetro se declara como Beneficiario y Laravel busca el registro por
     * id automáticamente. Si no existe, responde 404 sin ejecutar el método. Eso
     * se llama «route model binding».
     */
    public function show(Beneficiario $beneficiario): Response
    {
        $gestion = (int) now()->format('Y');

        return Inertia::render('panel/beneficiarios/ver', [
            'beneficiario' => [
                ...$beneficiario->only([
                    'id', 'ci_nit', 'complemento', 'expedido', 'primerNombre',
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

            // Lo que debe en total, sumando los trámites no rechazados.
            'deuda' => $beneficiario->deudaTotal(),

            /*
             * EL DATO QUE DECIDE EL PRÓXIMO TRÁMITE — Regla A.
             *
             * La pantalla necesita saber si esta persona ya tiene carnet de la
             * gestión, porque de eso depende el cartel que muestra: «emitir
             * carnet» o «agregar rubro». El sistema lo vuelve a calcular al
             * registrar, con la fila bloqueada; esto es solo para la vista.
             */
            'carnetGestion' => $this->resumirCarnet($beneficiario->carnetDeGestion($gestion)),

            /*
             * Los carnets se cargan con with() para traer sus rubros en pocas
             * consultas. Sin eso, pintar 5 carnets serían 6 consultas.
             */
            'carnets' => $beneficiario->carnets()
                ->with('rubros:id,nombre')
                ->withCount('tramites')
                ->orderByDesc('gestion')
                ->get()
                ->map(fn ($c): array => [
                    ...$this->resumirCarnet($c),
                    'tramites_count' => $c->tramites_count,
                    'rubros' => $c->rubros->pluck('nombre')->all(),
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
                    'id', 'ci_nit', 'complemento', 'expedido', 'primerNombre',
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
     *
     * Es un borrado LÓGICO: el modelo usa SoftDeletes, así que la fila no
     * desaparece, solo se le pone fecha en `deleted_at`.
     *
     * ¿POR QUÉ NO SE BORRA DE VERDAD? Porque los carnets, trámites y pagos
     * históricos siguen apuntando a esta persona y no pueden quedar huérfanos.
     * `deleted_at` es el único estado que tiene una ficha: o está en el padrón,
     * o está dada de baja. No hay una columna `activo` aparte —dos formas de
     * decir lo mismo terminan contradiciéndose—.
     *
     * Que el historial no se rompa depende de `Carnet::beneficiario()`, que
     * lleva withTrashed() justamente para esto.
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
     * Devuelve JSON y no una pantalla de Inertia: lo consume el autocompletado
     * del formulario de trámite mientras el operador escribe, y ahí no se quiere
     * navegar a ningún lado.
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

        $gestion = (int) now()->format('Y');

        return Beneficiario::query()
            ->buscar($termino)
            /*
             * Se traen los carnets de la gestión CON sus habilitaciones y el
             * nombre de cada rubro, todo en la misma tanda de consultas.
             *
             * Sin esto, armar la situación de diez personas serían veintiuna
             * consultas —el clásico N+1— y encima disparadas en cada tecleada
             * del operador.
             */
            ->with([
                'carnets' => fn ($q) => $q->where('gestion', $gestion),
                'carnets.habilitaciones.rubro:id,nombre',
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
                 * TODO lo que la pantalla necesita para decidir qué ofrecer:
                 * si tiene carnet, cuál, si sigue vigente, qué rubros ya tiene y
                 * cuáles quedan bloqueados.
                 *
                 * Va en el mismo payload que la búsqueda y no en una segunda
                 * petición al elegir a la persona: son diez filas ya cargadas, y
                 * un viaje más al servidor justo cuando el operador acaba de
                 * hacer clic se nota.
                 */
                'situacion' => SituacionCarnet::para($b, $gestion),
            ])
            ->all();
    }

    // ------------------------------------------------------------------
    //  Auxiliares
    // ------------------------------------------------------------------

    /**
     * Los datos de un carnet que pinta la ficha. NULL si no hay carnet.
     *
     * MANDA EL REGISTRO, NO LA FIRMA. El registro es el número corto impreso
     * en el plástico —el id rellenado con ceros—: es por el que pregunta la
     * gente y no abre nada. La firma sí abre la verificación pública, así que
     * viaja únicamente dentro del QR y se muestra en una sola pantalla del
     * panel, la ficha del carnet, para poder dictarla si el QR queda ilegible.
     *
     * @return array<string, mixed>|null
     */
    private function resumirCarnet(?Carnet $carnet): ?array
    {
        if ($carnet === null) {
            return null;
        }

        return [
            'id' => $carnet->id,
            // El número impreso en el carnet: 000013. Ver Carnet::registro().
            'registro' => $carnet->registro(),
            'gestion' => $carnet->gestion,
            'estado' => $carnet->estado->value,
            'estado_etiqueta' => $carnet->estado->etiqueta(),
            'estado_color' => $carnet->estado->color(),
            'vigente' => $carnet->estaVigente(),
            'fecha_emision' => $carnet->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $carnet->fecha_vencimiento?->toDateString(),
        ];
    }

    /**
     * Las listas fijas que necesitan los dos formularios de beneficiario.
     *
     * Salen de config/jichi.php y no de acá porque las mismas provincias las
     * pide más de una pantalla: escritas dos veces, tarde o temprano una se
     * queda sin actualizar.
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
     *
     * Todo archivo que sube al sistema pasa por StorageController, que es el
     * único que decide en qué disco se escribe. Para que la imagen sea visible
     * desde el navegador tiene que existir el enlace simbólico que crea
     * `php artisan storage:link`.
     */
    private function guardarFoto(GuardarBeneficiarioRequest $request): string
    {
        return app(StorageController::class)->file($request->file('foto'), 'beneficiarios');
    }
}
