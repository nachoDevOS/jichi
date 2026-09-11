<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Http\Requests\Panel\GuardarSolicitanteRequest;
use App\Models\Solicitante;
use App\Support\Archivos;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  MÓDULO SOLICITANTES — controlador de ejemplo
 * ============================================================================
 *
 * Este archivo es la PLANTILLA del sistema. Los otros cuatro módulos que faltan
 * (Trámites, Documentos, Reportes, Configuración) siguen exactamente
 * este mismo patrón. Está comentado paso a paso a propósito.
 *
 * ----------------------------------------------------------------------------
 *  CÓMO VIAJA LA INFORMACIÓN DE LARAVEL A REACT
 * ----------------------------------------------------------------------------
 *
 *   1. El navegador pide  GET /panel/solicitantes
 *   2. routes/panel.php decide que ese pedido lo atiende el método index()
 *   3. index() consulta la base de datos con Eloquent
 *   4. index() devuelve  Inertia::render('panel/solicitantes/index', [...datos...])
 *   5. Inertia busca  resources/js/pages/panel/solicitantes/index.tsx
 *   6. Ese componente de React recibe los [...datos...] como PROPS
 *
 * Lo importante: NO hay una API REST de por medio, no se escribe fetch() ni
 * axios en ninguna parte. El array que se pasa como segundo argumento de
 * Inertia::render() ES el objeto de props que llega a React. Si acá se agrega
 * una clave 'total', en React aparece como `total`. Nada más.
 *
 * ----------------------------------------------------------------------------
 *  Y DE REACT DE VUELTA A LARAVEL
 * ----------------------------------------------------------------------------
 *
 * En React se usa router.post() / useForm().post() de Inertia. Eso hace un
 * POST normal, con token CSRF, a una ruta normal de Laravel. El controlador
 * responde con un redirect() (nunca con JSON), e Inertia se encarga de pintar
 * la página destino sin recargar el navegador.
 */
class SolicitanteController extends Controller
{
    /**
     * LISTADO — GET /panel/solicitantes
     *
     * Muestra la tabla con buscador, filtros y paginación.
     */
    public function index(Request $request): Response
    {
        /*
         * Se lee el buscador de la barra de direcciones (?buscar=perez). Va
         * dentro de un array igual, para poder devolvérselo a React y que el
         * campo aparezca con lo que el usuario ya había escrito.
         */
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            // Cuántas filas mostrar, elegido desde la pantalla. La lista de
            // tamaños permitidos vive en App\Support\Paginacion: es una regla
            // de seguridad —el número llega por la barra de direcciones— y la
            // comparten todos los listados del panel.
            'por_pagina' => Paginacion::filas($request),
        ];

        $solicitantes = Solicitante::query()
            // scopeBuscar() vive en el modelo. Busca por CI, nombre, email
            // o teléfono, y ya resuelve solo la diferencia entre ILIKE
            // (PostgreSQL) y LIKE (SQLite). Ver app/Support/Sql.php.
            ->buscar($filtros['buscar'])

            // Cuenta los trámites de cada solicitante en la MISMA consulta.
            // Sin esto habría que preguntar uno por uno dentro del bucle de la
            // vista: ese es el clásico problema "N+1" y hace lentísima la tabla.
            ->withCount('tramites')

            ->ordenAlfabetico()
            ->paginate($filtros['por_pagina'])

            // Sin withQueryString(), al hacer clic en "página 2" se perderían
            // los filtros y la búsqueda volvería a empezar de cero.
            ->withQueryString()

            // through() transforma cada fila SIN romper la paginación.
            // Se envía a React solo lo que la tabla realmente pinta: mandar el
            // modelo entero expondría datos de más y haría el JSON más pesado.
            ->through(fn (Solicitante $s): array => [
                'id' => $s->id,
                'ci_nit' => $s->ci_nit,
                'documento_identidad' => $s->documento_identidad,
                'nombreCompleto' => $s->nombreCompleto,
                'telefono' => $s->telefono,
                'email' => $s->email,
                'tramites_count' => $s->tramites_count,
            ]);

        return Inertia::render('panel/solicitantes/index', [
            'solicitantes' => $solicitantes,
            'filtros' => $filtros,
            // Las opciones del selector salen del servidor y no escritas en el
            // componente de React: si estuvieran en los dos lados, alcanzaría
            // con agregar una en la pantalla y olvidarse del servidor para que
            // el operador elija un número que después se ignora sin aviso.
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * FORMULARIO DE ALTA — GET /panel/solicitantes/crear
     *
     * Solo pinta el formulario vacío. No toca la base de datos.
     */
    public function create(): Response
    {
        return Inertia::render('panel/solicitantes/crear', $this->catalogos());
    }

    /**
     * GUARDAR EL ALTA — POST /panel/solicitantes
     *
     * Fíjate en el tipo del parámetro: GuardarSolicitanteRequest, no Request.
     * Con eso Laravel valida ANTES de entrar acá. Si la validación falla, este
     * método nunca llega a ejecutarse.
     */
    public function store(GuardarSolicitanteRequest $request): RedirectResponse
    {
        // validated() devuelve SOLO los campos que pasaron por las reglas.
        // Usarlo en lugar de all() evita que alguien mande campos de más por
        // el formulario (por ejemplo un 'id' que no corresponde).
        $solicitante = new Solicitante($request->validated());

        if ($request->hasFile('foto')) {
            $solicitante->foto = $this->guardarFoto($request);
        }

        // Al guardar, el trait Auditable escribe solo la fila correspondiente
        // en la tabla `auditorias`, con el usuario de la sesión. Ahí queda el
        // rastro de quién dio el alta: la tabla `solicitantes` no lo guarda.
        $solicitante->save();

        // Se responde con redirect, NUNCA con JSON. El mensaje flash lo levanta
        // el hook useFlash() de React y lo muestra como toast.
        return redirect()
            ->route('solicitantes.show', $solicitante)
            ->with('exito', "Solicitante {$solicitante->nombreCompleto} registrado correctamente.");
    }

    /**
     * FICHA — GET /panel/solicitantes/{solicitante}
     *
     * El parámetro se declara como Solicitante y Laravel busca el registro por
     * id automáticamente. Si no existe, responde 404 sin ejecutar el método.
     * Eso se llama "route model binding".
     */
    public function show(Solicitante $solicitante): Response
    {
        return Inertia::render('panel/solicitantes/ver', [
            'solicitante' => [
                ...$solicitante->only([
                    'id', 'ci_nit', 'complemento', 'expedido', 'primerNombre',
                    'segundoNombre', 'apellidoPaterno', 'apellidoMaterno',
                    'apellidoCasada', 'nombreCompleto', 'genero', 'nacionalidad',
                    'direccion', 'ciudad', 'provincia', 'telefono', 'email',
                ]),
                'documento_identidad' => $solicitante->documento_identidad,
                'foto_url' => $solicitante->foto_url,
                'fechaNacimiento' => $solicitante->fechaNacimiento?->toDateString(),
                'registrado' => $solicitante->created_at?->toIso8601String(),
            ],

            // Deuda acumulada de todos sus trámites con pago incompleto.
            'deuda' => $solicitante->deudaTotal(),

            /*
             * Los trámites se cargan con with() para traer en pocas consultas
             * el tipo de trámite y su área. Sin with(), pintar 10 filas serían
             * 21 consultas a la base de datos.
             */
            'tramites' => $solicitante->tramites()
                ->with('tipoTramite:id,nombre,area_id', 'tipoTramite.area:id,nombre,icono')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($t): array => [
                    'id' => $t->id,
                    'codigo' => $t->codigo,
                    'tipo' => $t->tipoTramite->nombre,
                    'area' => $t->tipoTramite->area->nombre,
                    'icono' => $t->tipoTramite->area->icono,
                    'estado' => $t->estado->value,
                    'estado_etiqueta' => $t->estado->etiqueta(),
                    'estado_color' => $t->estado->color(),
                    'monto_total' => (float) $t->monto_total,
                    'saldo_pendiente' => $t->saldo_pendiente,
                    'creado' => $t->created_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    /**
     * FORMULARIO DE EDICIÓN — GET /panel/solicitantes/{solicitante}/editar
     */
    public function edit(Solicitante $solicitante): Response
    {
        return Inertia::render('panel/solicitantes/editar', [
            'solicitante' => [
                ...$solicitante->only([
                    'id', 'ci_nit', 'complemento', 'expedido', 'primerNombre',
                    'segundoNombre', 'apellidoPaterno', 'apellidoMaterno',
                    'apellidoCasada', 'genero', 'nacionalidad', 'direccion',
                    'ciudad', 'provincia', 'telefono', 'email',
                ]),
                'fechaNacimiento' => $solicitante->fechaNacimiento?->toDateString(),
                'foto_url' => $solicitante->foto_url,
            ],
            ...$this->catalogos(),
        ]);
    }

    /**
     * GUARDAR LA EDICIÓN — PUT /panel/solicitantes/{solicitante}
     */
    public function update(GuardarSolicitanteRequest $request, Solicitante $solicitante): RedirectResponse
    {
        $solicitante->fill($request->validated());

        if ($request->hasFile('foto')) {
            // Se borra la foto anterior para no dejar archivos huérfanos
            // ocupando disco cada vez que alguien actualiza la imagen.
            $this->borrarFoto($solicitante);
            $solicitante->foto = $this->guardarFoto($request);
        } elseif ($request->boolean('quitar_foto')) {
            $this->borrarFoto($solicitante);
            $solicitante->foto = null;
        }

        $solicitante->save();

        return redirect()
            ->route('solicitantes.show', $solicitante)
            ->with('exito', 'Los datos del solicitante fueron actualizados.');
    }

    /**
     * BAJA — DELETE /panel/solicitantes/{solicitante}
     *
     * Es un borrado LÓGICO: el modelo usa el trait SoftDeletes, así que la fila
     * no desaparece, solo se le pone fecha en `deleted_at`.
     *
     * ¿POR QUÉ NO SE BORRA DE VERDAD?
     *
     * Porque los trámites, pagos y documentos históricos siguen apuntando a
     * este solicitante y no pueden quedar huérfanos. `deleted_at` es el único
     * estado que tiene un solicitante: o está en el padrón, o está dado de
     * baja. No hay una columna `activo` aparte —dos formas de decir lo mismo
     * terminan contradiciéndose.
     *
     * Que el historial no se rompa depende de `Tramite::solicitante()`, que
     * lleva `withTrashed()` justamente para esto.
     */
    public function destroy(Solicitante $solicitante): RedirectResponse
    {
        $solicitante->delete();

        return redirect()
            ->route('solicitantes.index')
            ->with('exito', 'Solicitante dado de baja.');
    }

    /**
     * Guarda la foto en storage/app/public/solicitantes y devuelve su ruta.
     *
     * Para que la imagen sea visible desde el navegador tiene que existir el
     * enlace simbólico que crea `php artisan storage:link`.
     */
    /**
     * Las listas fijas que necesitan los dos formularios de solicitante.
     *
     * Salen de config/jichi.php y no de acá porque la cédula de pescador pide
     * las mismas provincias: escritas dos veces, tarde o temprano una se queda
     * sin actualizar.
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

    private function guardarFoto(GuardarSolicitanteRequest $request): string
    {
        // Todo archivo que sube al sistema pasa por StorageController, que es
        // el único que decide en qué disco se escribe.
        return app(StorageController::class)->file($request->file('foto'), 'solicitantes');
    }

    private function borrarFoto(Solicitante $solicitante): void
    {
        Archivos::borrar($solicitante->foto);
    }
}
