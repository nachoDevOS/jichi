<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoTramite;
use App\Enums\FormaPago;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Models\Documento;
use App\Models\Solicitante;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Services\EmisionDocumentoService;
use App\Services\RegistroPescadorService;
use App\Support\Archivos;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  MÓDULO TRÁMITES
 * ============================================================================
 *
 * El trámite YA SE GUARDA en la base de datos, con su número correlativo, su
 * monto, sus adjuntos y su estado inicial.
 *
 * Lo que todavía NO se hace es emitir el documento: la credencial y los
 * permisos en PDF, con su código de verificación, son el paso siguiente.
 *
 * Todo esto sale de la base de datos:
 *
 *   - El catálogo de servicios (tabla `tipos_tramite`).
 *   - La vigencia de cada uno y su forma de vencer.
 *   - LA COMPUERTA DE LA CREDENCIAL, que se comprueba en el servidor.
 *
 * ----------------------------------------------------------------------------
 *  EL ORDEN QUE NO SE PUEDE SALTEAR
 * ----------------------------------------------------------------------------
 *
 *     solicitante registrado
 *            ↓
 *     Cédula de Pescador VIGENTE
 *            ↓
 *     Permiso por Faena  /  Guía Única de Transporte
 *
 * Por eso el alta empieza eligiendo al SOLICITANTE y no al servicio: hasta no
 * saber de quién se trata, el sistema no puede decir qué puede pedir. Con el
 * solicitante elegido, cada tarjeta del catálogo ya sabe si está habilitada o
 * qué le falta.
 *
 * La comprobación se repite al entrar a cada formulario y otra vez al guardar.
 * No es desconfianza del propio código: las tarjetas viven en el navegador del
 * operador y una dirección escrita a mano las saltea enteras. Es la misma
 * regla 5 de la guía del proyecto, la de que la seguridad va del lado del
 * servidor y esconder un botón es solo comodidad.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ QUEDA UN CATÁLOGO ESCRITO A MANO
 * ----------------------------------------------------------------------------
 *
 * Los membretes, el encabezado legal, las tres copias del talonario y las
 * leyendas al pie NO están en la base: son textos impresos en los talonarios
 * del SEDAG y solo se usan para dibujar la vista previa del documento. Viven
 * en presentacionImpresa(), separados de los datos que sí son del negocio.
 */
class TramiteController extends Controller
{
    /**
     * LISTADO — GET /panel/tramites
     *
     * Los trámites realmente registrados, el último primero.
     */
    public function index(Request $request): Response
    {
        /*
         * El buscador viaja en la barra de direcciones (?buscar=perez) y no en
         * el estado de React. Así una búsqueda se puede guardar en favoritos,
         * mandar por WhatsApp a otra ventanilla o recargar sin perderla, y el
         * botón «atrás» del navegador hace lo que uno espera.
         *
         * Se devuelve dentro de un array para que React pueda dejar el campo
         * con lo que ya se había escrito.
         */
        /*
         * El estado pedido, si es uno de los que existen.
         *
         * `tryFrom` devuelve null cuando el texto no es ninguno de los cuatro
         * casos del enum, y ese null significa «todos»: un ?estado=cualquiera
         * escrito a mano no filtra nada en vez de reventar. Los estados
         * válidos los define App\Enums\EstadoTramite y nadie más.
         */
        $estado = EstadoTramite::tryFrom((string) $request->string('estado')->trim());

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $estado?->value,
            // La lista de tamaños permitidos vive en App\Support\Paginacion:
            // es una regla de seguridad y la comparten todos los listados.
            'por_pagina' => Paginacion::filas($request),
        ];

        $tramites = Tramite::query()
            // Sin with() serían tres consultas por fila: el clásico N+1.
            ->with('solicitante', 'tipoTramite.area')
            /*
             * Lo más nuevo primero, ordenado por id y no por created_at.
             *
             * Antes era ->latest(), que ordena por fecha de creación. El
             * problema es el empate: varios trámites recepcionados dentro del
             * mismo segundo —una carga seguida en ventanilla, o el sembrado de
             * datos— comparten created_at, y ahí el orden entre ellos lo
             * decide la base de datos, que puede devolverlos distinto en cada
             * consulta. El operador veía la lista bailar sin haber tocado
             * nada.
             *
             * El id es un contador que nunca empata, así que «el último que
             * entró» es siempre el de arriba.
             */
            ->orderByDesc('id')

            // scopeBuscar() vive en el modelo: busca por número de trámite,
            // registro de la credencial, embarcación, servicio y datos del
            // solicitante. Ver App\Models\Tramite.
            ->buscar($filtros['buscar'])

            // scopeEnEstado() califica su columna: `tramites`, `pagos` y
            // `documentos` tienen las tres una columna `estado`.
            ->when($estado, fn ($consulta) => $consulta->enEstado($estado))

            /*
             * PAGINACIÓN DEL LADO DEL SERVIDOR.
             *
             * Antes se traían las 50 filas más nuevas y nada más: pasadas esas,
             * un trámite viejo no había forma de verlo desde el listado.
             *
             * `paginate()` trae solo la página pedida y le agrega a la
             * respuesta el total, la página actual y los botones ya
             * calculados. Cuántas filas, lo decide el operador desde la
             * pantalla, dentro de la lista cerrada de arriba.
             */
            ->paginate($filtros['por_pagina'])

            // Sin withQueryString(), al tocar «página 2» se perdería el
            // término buscado y la búsqueda volvería a empezar de cero.
            ->withQueryString()

            // through() transforma cada fila SIN romper la paginación: si se
            // usara map() se perderían los datos de navegación y quedaría un
            // array pelado.
            ->through(fn (Tramite $t): array => [
                'id' => $t->id,
                // El registro impreso, cuando el servicio lo lleva. Es lo que
                // el pescador tiene en la mano y por lo que va a preguntar.
                'registro' => (string) ($t->datos_adicionales['registro'] ?? ''),
                'solicitante' => $t->solicitante->nombreCompleto,
                'ci_nit' => $t->solicitante->ci_nit,
                // Solo la tiene el permiso por faena; en los demás va vacío.
                'embarcacion' => (string) ($t->datos_adicionales['embarcacion'] ?? ''),
                'tipo' => $t->tipoTramite->nombre,
                'area' => $t->tipoTramite->area->nombre,
                'icono' => (string) $t->tipoTramite->area->icono,
                'estado' => $t->estado->value,
                'estado_etiqueta' => $t->estado->etiqueta(),
                'estado_color' => $t->estado->color(),
                'monto_total' => (float) $t->monto_total,
                'creado' => $t->created_at?->toIso8601String(),
            ]);

        return Inertia::render('panel/tramites/index', [
            'esMaqueta' => false,
            'tramites' => $tramites,
            'filtros' => $filtros,
            // Las opciones del selector salen del servidor y no escritas en el
            // componente de React: si estuvieran en los dos lados, alcanzaría
            // con agregar una en la pantalla y olvidarse del servidor para que
            // el operador elija un número que después se ignora sin aviso.
            'opcionesPorPagina' => Paginacion::OPCIONES,
            // Los estados del selector salen del enum, no escritos a mano en
            // React: agregar un estado mañana tiene que alcanzar con tocar
            // App\Enums\EstadoTramite y nada más.
            'opcionesEstado' => EstadoTramite::opciones(),
        ]);
    }

    /**
     * PASO 1 DEL ALTA — GET /panel/tramites/crear
     *
     * Dos pantallas en una, según haya solicitante elegido o no:
     *
     *   sin ?solicitante  → buscador de solicitantes
     *   con ?solicitante  → catálogo de servicios, cada uno con su habilitación
     *
     * Se hizo así y no en dos rutas separadas porque es UN solo paso mental
     * para el operador: «a quién atiendo y qué necesita». Partirlo en dos
     * direcciones distintas obligaría a volver atrás cada vez que se equivoca
     * de persona.
     */
    public function create(Request $request): Response
    {
        $solicitante = $this->solicitanteDe($request);

        if ($solicitante === null) {
            return Inertia::render('panel/tramites/crear', [
                'esMaqueta' => false,
                'solicitante' => null,
                'busqueda' => $request->string('buscar')->trim()->value() ?: null,
                'solicitantes' => $this->buscarSolicitantes($request),
                'tipos' => [],
            ]);
        }

        return Inertia::render('panel/tramites/crear', [
            'esMaqueta' => false,
            'solicitante' => $this->presentarSolicitante($solicitante),
            'busqueda' => null,
            'solicitantes' => [],
            'tipos' => $this->catalogo($solicitante),
        ]);
    }

    /**
     * PASO 2 — GET /panel/tramites/crear/permiso-faena
     */
    public function crearPermisoFaena(Request $request): Response|RedirectResponse
    {
        return $this->pantallaDeServicio($request, 'PPF', 'panel/tramites/crear-permiso-faena');
    }

    /**
     * PASO 2 — GET /panel/tramites/crear/guia-transporte
     */
    public function crearGuiaTransporte(Request $request): Response|RedirectResponse
    {
        return $this->pantallaDeServicio($request, 'GUT', 'panel/tramites/crear-guia-transporte', [
            'catalogos' => $this->catalogosGuiaTransporte(),
        ]);
    }

    /**
     * PASO 2 — GET /panel/tramites/crear/cedula-pescador
     */
    public function crearCedulaPescador(Request $request): Response|RedirectResponse
    {
        return $this->pantallaDeServicio($request, 'CAP', 'panel/tramites/crear-cedula-pescador', [
            'catalogos' => $this->catalogosCedulaPescador(),
        ]);
    }

    /**
     * El tronco común de las tres pantallas de servicio.
     *
     * Las tres hacen exactamente lo mismo antes de dibujar nada: buscar al
     * solicitante y comprobar que pueda pedir ese servicio. Escrito tres veces
     * bastaría con olvidarse una sola para que ese servicio se pueda emitir
     * sin cédula vigente, que es justo el agujero que hay que evitar.
     *
     * @param  array<string, mixed>  $extra
     */
    private function pantallaDeServicio(
        Request $request,
        string $codigo,
        string $pantalla,
        array $extra = [],
    ): Response|RedirectResponse {
        $tipo = TipoTramite::with('area')->where('codigo', $codigo)->firstOrFail();
        $solicitante = $this->solicitanteDe($request);

        // Sin solicitante no se puede ni empezar: el trámite es de alguien.
        if ($solicitante === null) {
            return redirect()
                ->route('tramites.create')
                ->with('info', 'Elegí primero al solicitante que hace el trámite.');
        }

        $habilitacion = $tipo->habilitacionPara($solicitante);

        // LA COMPUERTA, del lado del servidor. Un enlace escrito a mano
        // saltearía las tarjetas del paso 1; acá no.
        if (! $habilitacion->habilitado) {
            return redirect()
                ->route('tramites.create', ['solicitante' => $solicitante->id])
                ->with('error', trim($habilitacion->motivo.' '.$habilitacion->comoResolver));
        }

        return Inertia::render($pantalla, [
            'esMaqueta' => false,
            'tipo' => $this->presentarTipo($tipo, $solicitante),
            'solicitante' => $this->presentarSolicitante($solicitante),
            ...$extra,
        ]);
    }

    /**
     * El solicitante que viene en la petición, o NULL.
     */
    private function solicitanteDe(Request $request): ?Solicitante
    {
        $id = $request->integer('solicitante');

        return $id > 0 ? Solicitante::find($id) : null;
    }

    /**
     * El catálogo de servicios para un solicitante concreto.
     *
     * Sale de la tabla `tipos_tramite`. Cada tipo viaja con dos cosas que no
     * son columnas: si tiene formulario construido, y su habilitación para
     * ESTE solicitante.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalogo(Solicitante $solicitante): array
    {
        return TipoTramite::activos()
            ->with('area')
            ->get()
            // Primero los que se pueden usar hoy: en ventanilla no sirve tener
            // que buscar entre diez tarjetas cuál está construida.
            ->sortBy(fn (TipoTramite $t): array => [
                $this->rutaDelFormulario($t->codigo) === null ? 1 : 0,
                $t->nombre,
            ])
            ->map(fn (TipoTramite $t): array => $this->presentarTipo($t, $solicitante))
            ->values()
            ->all();
    }

    /**
     * Un tipo de trámite, tal como lo necesita React.
     *
     * @return array<string, mixed>
     */
    private function presentarTipo(TipoTramite $tipo, ?Solicitante $solicitante = null): array
    {
        $ruta = $this->rutaDelFormulario($tipo->codigo);

        return [
            'codigo' => $tipo->codigo,
            'nombre' => $tipo->nombre,
            'area' => $tipo->area->nombre,
            'icono' => $tipo->area->icono,
            'resumen' => (string) ($tipo->descripcion ?? ''),
            'categoria_documento' => $tipo->categoria_documento->value,

            // El precio del servicio. En cero significa que no tiene monto
            // fijo y se liquida según lo que se declare en ventanilla.
            'monto' => (float) $tipo->monto ?: null,

            'vigencia' => $tipo->vigenciaLegible(),
            'uso_unico' => $tipo->uso_unico,
            'requiere_credencial' => $tipo->requiere_credencial,
            'requisitos' => $tipo->requisitos ?? [],

            // false = se muestra apagado: el formulario todavía no existe.
            'habilitado' => $ruta !== null,
            'ruta' => $ruta,

            'habilitacion' => $solicitante
                ? $tipo->habilitacionPara($solicitante)->paraVista()
                : null,

            ...$this->presentacionImpresa($tipo->codigo),
        ];
    }

    /**
     * Qué pantalla atiende cada servicio. NULL = todavía no está construida.
     */
    private function rutaDelFormulario(string $codigo): ?string
    {
        return match ($codigo) {
            'CAP' => 'tramites.crear.cedula-pescador',
            'PPF' => 'tramites.crear.permiso-faena',
            'GUT' => 'tramites.crear.guia-transporte',
            default => null,
        };
    }

    /**
     * Los datos del solicitante que necesitan las pantallas de trámite.
     *
     * Se manda también su credencial, porque es lo primero que el operador
     * mira antes de decidir qué se le puede emitir.
     *
     * @return array<string, mixed>
     */
    private function presentarSolicitante(Solicitante $solicitante): array
    {
        $credencial = $solicitante->credencialVigente() ?? $solicitante->ultimaCredencial();

        // Solo se busca el trámite en curso si NO tiene credencial emitida: si
        // ya la tiene, lo que esté en curso es una renovación y no cambia lo
        // que se le puede emitir hoy.
        $enTramite = $credencial === null ? $solicitante->credencialEnTramite() : null;

        /*
         * Se manda el documento partido Y armado.
         *
         * El armado («7656924-1A») es para mostrarlo; las partes sueltas son
         * para que los formularios de trámite se completen solos con lo que ya
         * está en la ficha, en vez de hacer que ventanilla vuelva a tipear
         * datos que el sistema ya tiene —y que al tipearlos de nuevo pueden
         * salir distintos de la ficha.
         */
        return [
            'id' => $solicitante->id,
            'nombreCompleto' => $solicitante->nombreCompleto,
            'documento_identidad' => $solicitante->documento_identidad,
            'ci_nit' => $solicitante->ci_nit,
            'complemento' => $solicitante->complemento,
            'expedido' => $solicitante->expedido,
            'direccion' => $solicitante->direccion,
            'ciudad' => $solicitante->ciudad,
            'provincia' => $solicitante->provincia,
            'telefono' => $solicitante->telefono,
            'foto_url' => $solicitante->foto_url,
            'credencial' => $credencial ? [
                'codigo_verificacion' => $credencial->codigo_verificacion,
                'fecha_emision' => $credencial->fecha_emision?->toDateString(),
                'fecha_vencimiento' => $credencial->fecha_vencimiento?->toDateString(),
                'estado' => $credencial->estadoEfectivo()->value,
                'estado_etiqueta' => $credencial->estadoEfectivo()->etiqueta(),
                'estado_color' => $credencial->estadoEfectivo()->color(),
                'vigente' => $credencial->estadoEfectivo()->esValido(),
            ] : null,
            'credencial_en_tramite' => $enTramite ? [
                'id' => $enTramite->id,
                'estado' => $enTramite->estado->value,
                'estado_etiqueta' => $enTramite->estado->etiqueta(),
                'estado_color' => $enTramite->estado->color(),
            ] : null,
        ];
    }

    /**
     * Buscador del paso 1.
     *
     * Devuelve pocos resultados a propósito: es un buscador de ventanilla, no
     * un listado. Si el operador ve veinte filas es porque buscó mal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buscarSolicitantes(Request $request): array
    {
        $termino = $request->string('buscar')->trim()->value();

        if ($termino === '') {
            return [];
        }

        return Solicitante::query()
            ->buscar($termino)
            ->ordenAlfabetico()
            ->limit(8)
            ->get()
            ->map(fn (Solicitante $s): array => $this->presentarSolicitante($s))
            ->all();
    }

    /**
     * Textos impresos en los talonarios del SEDAG.
     *
     * NO están en la base y no tienen por qué estarlo: son el membrete, el
     * encabezado legal y las leyendas al pie de cada formulario en papel, y
     * solo se usan para dibujar la vista previa del documento en pantalla.
     *
     * OJO con el membrete: el Permiso por Faena y la Guía dependen de
     * SECRETARÍAS DISTINTAS. No es un error de tipeo, así está impreso en los
     * dos talonarios.
     *
     * @return array<string, mixed>
     */
    private function presentacionImpresa(string $codigo): array
    {
        return match ($codigo) {
            'PPF' => [
                'membrete' => [
                    'institucion' => 'Gobierno Autónomo Departamental del Beni',
                    'secretaria' => 'Secretaría de Desarrollo Productivo, Recursos Naturales y Medio Ambiente',
                    'programa' => 'Programa: Fomento a la Actividad Piscícola y Pesquera Dpto. del Beni',
                    'unidad' => 'SEDAG - BENI',
                ],
                'encabezado_legal' => 'En cumplimiento a disposiciones contenidas en el Reglamento de Pesca y Comercialización de Especies Piscícolas en el Dpto. del Beni:',
                'autoriza' => 'El Área de Fiscalización y Control de la Actividad Pesquera Autoriza a:',
                'nota' => 'La presente autorización tiene validez para una sola faena de pesca. Sin este documento no se otorgará el derecho de Zarpe por la capitanía del Puerto.',
                'responsable' => 'RESP. PMFRPCRMBIG',
                'copias' => ['Original: Cliente', 'Copia Amarilla: Contabilidad', 'Copia Verde: Archivo'],
            ],

            'GUT' => [
                'membrete' => [
                    'institucion' => 'Gobierno Autónomo Departamental del Beni',
                    'secretaria' => 'Secretaría de Desarrollo Productivo y Economía Plural',
                    'programa' => 'Programa: Fomento a la Actividad Piscícola y Pesquera Dpto. del Beni',
                    'unidad' => 'SEDAG - BENI',
                ],
                'nota' => 'Los firmantes dan fe de los datos de su competencia y se responsabilizan de los mismos.',
                'firmas' => ['Firma Cliente', 'Sello Firma Encargado'],
            ],

            'CAP' => [
                'membrete' => [
                    'institucion' => 'Gobernación — Gobierno Autónomo Departamental del Beni',
                    'secretaria' => 'Secretaría Dptal. de Desarrollo Productivo, Recursos Naturales y Medio Ambiente',
                    'programa' => '',
                    'unidad' => 'SEDAG - BENI',
                ],
            ],

            default => [],
        };
    }

    /**
     * Listas fijas de la Cédula de Pescador.
     *
     * Las asociaciones y provincias salen de las que figuran en las cédulas ya
     * emitidas. Falta la lista oficial del SEDAG: estas son las que se pudieron
     * leer de los carnets físicos.
     *
     * @return array<string, mixed>
     */
    private function catalogosCedulaPescador(): array
    {
        return [
            'asociaciones' => [
                'SOC. IBARE - MAMORÉ',
                'ASOC. PESCADORES DEL MAMORÉ',
                'ASOC. PESCADORES PUERTO ALMACÉN',
                'ASOC. PESCADORES PUERTO VARADOR',
            ],
            // Las mismas que usa la ficha del solicitante. Ver config/jichi.php.
            'provincias' => config('jichi.provincias'),

            // Salen del enum y no de una lista escrita en React: si mañana se
            // agrega una forma de pago, la pantalla la muestra sola y el
            // servidor ya la acepta, sin tocar dos archivos que se olvidan.
            'formas_pago' => FormaPago::opciones(),
        ];
    }

    /**
     * Listas fijas de la Guía de Transporte.
     *
     * Las presentaciones son las columnas de la tabla "D.- PRODUCTOS
     * HIDROBIOLÓGICOS" del formulario en papel. En el talonario se marca con
     * una cruz debajo de la columna; acá se eligen de una lista, que captura
     * exactamente el mismo dato y entra en una pantalla de celular.
     *
     * @return array<string, mixed>
     */
    private function catalogosGuiaTransporte(): array
    {
        return [
            'vias' => [
                ['value' => 'fluvial', 'label' => 'Fluvial'],
                ['value' => 'aerea', 'label' => 'Aérea'],
                ['value' => 'terrestre', 'label' => 'Terrestre'],
            ],
            'medios' => [
                ['value' => 'embarcacion', 'label' => 'Embarcación'],
                ['value' => 'chata_absorbente', 'label' => 'Chata absorbente'],
                ['value' => 'automotriz', 'label' => 'Automotriz'],
            ],
            'presentaciones' => [
                ['value' => 'fresco_entero', 'label' => 'Fresco/refrigerado — entero', 'grupo' => 'Fresco o refrigerado'],
                ['value' => 'fresco_eviscerado', 'label' => 'Fresco/refrigerado — eviscerado', 'grupo' => 'Fresco o refrigerado'],
                ['value' => 'congelado_entero', 'label' => 'Congelado — entero', 'grupo' => 'Congelado'],
                ['value' => 'congelado_eviscerado', 'label' => 'Congelado — eviscerado', 'grupo' => 'Congelado'],
                ['value' => 'congelado_fileteado', 'label' => 'Congelado — fileteado', 'grupo' => 'Congelado'],
                ['value' => 'seco', 'label' => 'Seco', 'grupo' => 'Otros'],
                ['value' => 'sal_preso', 'label' => 'Sal preso', 'grupo' => 'Otros'],
                ['value' => 'vivos', 'label' => 'Vivos', 'grupo' => 'Otros'],
                ['value' => 'a_granel', 'label' => 'A granel', 'grupo' => 'Otros'],
                ['value' => 'otros', 'label' => 'Otros', 'grupo' => 'Otros'],
            ],
            // Especies del Mamoré y el Ibare, para que el operador no tenga que
            // escribirlas a mano. La lista definitiva la confirma el SEDAG.
            'especies' => [
                'Surubí', 'Pacú', 'Tambaquí', 'Sábalo', 'Blanquillo',
                'Bagre', 'Dorado', 'Piraña', 'Yayú', 'Paiche',
            ],
        ];
    }

    /**
     * LA FICHA DEL TRÁMITE — GET /panel/tramites/{tramite}
     *
     * ------------------------------------------------------------------------
     *  PARA QUÉ EXISTE ESTA PANTALLA
     * ------------------------------------------------------------------------
     *
     * Registrar el trámite en ventanilla es la mitad del trabajo. La otra mitad
     * la hace otra persona, en otro momento: alguien tiene que ABRIR los
     * papeles que se adjuntaron, comprobar que el pago está, y recién entonces
     * aprobar y emitir la credencial.
     *
     * Sin esta pantalla, un trámite quedaba «En Revisión» para siempre: no
     * había dónde mirarlo ni cómo aprobarlo, y por lo tanto tampoco se emitía
     * el documento —que es lo que después habilita el permiso por faena y la
     * guía de transporte—. El circuito quedaba cortado justo en el medio.
     *
     * Por eso acá va TODO junto: quién es, qué pidió, qué adjuntó, cuánto pagó
     * y por dónde va. El revisor no tiene que abrir cuatro pantallas para
     * decidir.
     */
    public function show(Tramite $tramite): Response
    {
        $tramite->load([
            'solicitante',
            'tipoTramite.area',
            'operador',
            'revisadoPor',
            'aprobadoPor',
            'entregadoPor',
            'documento.emitidoPor',
        ]);

        return Inertia::render('panel/tramites/ver', [
            'tramite' => $this->presentarTramite($tramite),
        ]);
    }

    /**
     * FORMULARIO DE CORRECCIÓN — GET /panel/tramites/{tramite}/editar
     *
     * ------------------------------------------------------------------------
     *  QUÉ SE PUEDE CORREGIR Y QUÉ NO
     * ------------------------------------------------------------------------
     *
     * Solo mientras el expediente está en curso —recibido o en revisión—, y
     * solo lo que en ventanilla se equivoca de verdad:
     *
     *   SÍ  los pagos: un monto mal anotado, un número de transacción cambiado,
     *       un comprobante que se olvidó de adjuntar
     *   SÍ  los papeles: la certificación que salió ilegible del escáner
     *   SÍ  la asociación y el cupo, que se tipean a mano
     *   SÍ  las observaciones
     *
     *   NO  el solicitante ni el servicio: eso no es corregir un trámite, es
     *       otro trámite
     *   NO  nombre, cédula ni domicilio: salen de la ficha, y se corrigen ahí
     *   NO  el N° de registro: lo asigna el sistema
     *
     * La lista de lo que NO se toca no es una restricción arbitraria: es la
     * misma de siempre —lo que identifica a la persona vive en su ficha— y acá
     * se sostiene igual que en el alta.
     */
    public function edit(Tramite $tramite): Response|RedirectResponse
    {
        if (! $tramite->estado->permiteEdicion()) {
            return redirect()
                ->route('tramites.show', $tramite->id)
                ->with('error', "Un trámite {$tramite->estado->etiqueta()} ya no se puede corregir. Si hay un error, corresponde rechazarlo y volver a cargarlo.");
        }

        $tramite->load('solicitante', 'tipoTramite.area');

        return Inertia::render('panel/tramites/editar', [
            'tramite' => $this->presentarTramite($tramite),
            'catalogos' => $this->catalogosCedulaPescador(),
        ]);
    }

    /**
     * GUARDAR LA CORRECCIÓN — PUT /panel/tramites/{tramite}
     *
     * ------------------------------------------------------------------------
     *  LOS ADJUNTOS SE REEMPLAZAN, NO SE ACUMULAN
     * ------------------------------------------------------------------------
     *
     * Cada papel es opcional acá: si no se manda nada, queda el que ya estaba.
     * Si se manda uno nuevo, entra en su lugar y el viejo se borra del disco.
     *
     * Borrar el anterior importa: si no, cada corrección deja un archivo
     * huérfano ocupando espacio que nadie va a mirar nunca, y en una carpeta
     * con tres versiones del mismo carnet ya no se sabe cuál es la buena.
     *
     * Los PAGOS son distintos: se manda la lista entera y reemplaza a la
     * anterior. Es la única forma de poder quitar un pago cargado de más — con
     * una edición campo por campo, una fila sobrante no habría manera de
     * sacarla.
     */
    public function update(Request $request, Tramite $tramite, StorageController $almacen): RedirectResponse
    {
        if (! $tramite->estado->permiteEdicion()) {
            return back()->with('error', "Un trámite {$tramite->estado->etiqueta()} ya no se puede corregir.");
        }

        $this->validarCorreccion($request, $tramite);

        DB::transaction(function () use ($request, $tramite, $almacen): void {
            $requisitos = $tramite->requisitos_validados ?? [];

            // Los papeles: solo los que vinieron.
            foreach (self::ADJUNTOS as $campo => $nombre) {
                if (! $request->hasFile($campo)) {
                    continue;
                }

                $anterior = $requisitos[$campo] ?? null;

                $requisitos[$campo] = $almacen->file(
                    $request->file($campo),
                    $tramite->carpeta(),
                );

                Archivos::borrar($anterior);
            }

            $requisitos['pagos'] = $this->corregirPagos($request, $tramite, $almacen);

            $tramite->forceFill([
                'datos_adicionales' => [
                    ...($tramite->datos_adicionales ?? []),
                    ...$this->datosCorregidos($request, $tramite),
                ],
                'requisitos_validados' => $requisitos,
                'monto_pagado' => array_sum(array_column($requisitos['pagos'], 'monto')),
                'observaciones' => $request->string('observaciones')->trim()->value() ?: null,
            ])->save();
        });

        return redirect()
            ->route('tramites.show', $tramite->id)
            ->with('exito', "Trámite N° {$tramite->id} corregido.");
    }

    /**
     * Las reglas de la corrección.
     *
     * Casi las mismas que las del alta, con una diferencia: los papeles son
     * OPCIONALES. En el alta son obligatorios porque no existe nada; acá, no
     * mandarlos significa «dejá el que ya está», que es el caso normal —se
     * corrige un monto y no se vuelven a escanear los tres papeles—.
     */
    private function validarCorreccion(Request $request, Tramite $tramite): void
    {
        $maxKb = (int) config('jichi.archivos.max_kb');
        $maxMb = round($maxKb / 1024, 1);

        $opcionales = [
            'nullable',
            'file',
            'mimes:'.implode(',', config('jichi.archivos.extensiones')),
            'max:'.$maxKb,
        ];

        $request->validate(
            [
                'certificacion_asociacion' => $opcionales,
                'copia_ci' => $opcionales,

                'observaciones' => ['nullable', 'string', 'max:2000'],

                // Los campos propios del servicio que sí se tipean a mano.
                'asociacion' => ['nullable', 'string', 'max:120'],
                'capacidad_kg' => ['nullable', 'numeric', 'min:0', 'max:999999'],

                /*
                 * Los pagos viajan enteros y reemplazan a los anteriores, así
                 * que las reglas son las mismas del alta. El comprobante, en
                 * cambio, es opcional: un pago que ya estaba conserva el suyo
                 * —viaja su ruta en `archivo_actual`— y solo se pide archivo
                 * para los que se agreguen ahora.
                 */
                'pagos' => ['required', 'array', 'min:1', 'max:5'],
                'pagos.*.forma' => [
                    'required',
                    Rule::in(array_column(FormaPago::disponibles(), 'value')),
                ],
                'pagos.*.nro_transaccion' => [
                    'required_unless:pagos.*.forma,efectivo',
                    'nullable', 'string', 'max:60',
                ],
                'pagos.*.banco' => ['nullable', 'string', 'max:80'],
                'pagos.*.monto' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
                'pagos.*.archivo_actual' => ['nullable', 'string', 'max:255'],
                'pagos.*.comprobante' => [
                    'nullable',
                    'file',
                    'mimes:'.implode(',', config('jichi.archivos.extensiones')),
                    'max:'.$maxKb,
                ],
            ],
            [
                'certificacion_asociacion.mimes' => 'La certificación tiene que ser PDF, JPG, PNG o WEBP.',
                'certificacion_asociacion.max' => "La certificación no puede pesar más de {$maxMb} MB.",
                'copia_ci.mimes' => 'La fotocopia del carnet tiene que ser PDF, JPG, PNG o WEBP.',
                'copia_ci.max' => "La fotocopia del carnet no puede pesar más de {$maxMb} MB.",
                'observaciones.max' => 'Las observaciones no pueden pasar de 2000 caracteres.',
                'asociacion.max' => 'El nombre de la asociación no puede pasar de 120 caracteres.',
                'capacidad_kg.numeric' => 'El cupo autorizado tiene que ser un número.',
                'pagos.required' => 'El trámite tiene que quedar con al menos un pago.',
                'pagos.min' => 'El trámite tiene que quedar con al menos un pago.',
                'pagos.max' => 'No se pueden registrar más de 5 pagos en un mismo trámite.',
                'pagos.*.forma.in' => 'Por ahora la cédula solo se puede pagar por transferencia bancaria.',
                'pagos.*.nro_transaccion.required_unless' => 'Anote el número de transacción del comprobante.',
                'pagos.*.monto.required' => 'Anote el monto de este pago.',
                'pagos.*.monto.min' => 'El monto del pago tiene que ser mayor que cero.',
                'pagos.*.comprobante.mimes' => 'El comprobante de pago tiene que ser PDF, JPG, PNG o WEBP.',
                'pagos.*.comprobante.max' => "El comprobante de pago no puede pesar más de {$maxMb} MB.",
            ]
        );

        /*
         * Un pago tiene que tener comprobante: el que ya estaba conserva el
         * suyo, el nuevo tiene que traer uno. No se puede expresar con las
         * reglas de arriba —depende de dos campos a la vez— así que se
         * comprueba acá, después.
         */
        foreach ((array) $request->input('pagos', []) as $i => $pago) {
            $tiene = $request->hasFile("pagos.{$i}.comprobante")
                || filled($pago['archivo_actual'] ?? null);

            if (! $tiene) {
                throw ValidationException::withMessages([
                    "pagos.{$i}.comprobante" => 'Adjunte el comprobante de este pago.',
                ]);
            }
        }
    }

    /**
     * Los pagos después de la corrección.
     *
     * Cada fila conserva su comprobante salvo que se mande uno nuevo. La ruta
     * del que ya estaba viaja en `archivo_actual`, escondida en el formulario:
     * sin eso, corregir un monto obligaría a volver a adjuntar el comprobante
     * que ya estaba cargado.
     *
     * Los comprobantes que quedaron fuera de la lista se borran del disco: si
     * el operador quitó un pago cargado de más, su archivo no tiene por qué
     * seguir ocupando lugar en la carpeta del expediente.
     *
     * @return array<int, array<string, mixed>>
     */
    private function corregirPagos(Request $request, Tramite $tramite, StorageController $almacen): array
    {
        $anteriores = array_column($tramite->requisitos_validados['pagos'] ?? [], 'archivo');
        $pagos = [];

        foreach ((array) $request->input('pagos', []) as $i => $pago) {
            $nuevo = $request->file("pagos.{$i}.comprobante");
            $actual = $pago['archivo_actual'] ?? null;

            if ($nuevo !== null) {
                $ruta = $almacen->file($nuevo, $tramite->carpeta());

                // El que se reemplaza no queda dando vueltas.
                Archivos::borrar($actual);
            } else {
                $ruta = $actual;
            }

            $pagos[] = [
                'forma' => (string) ($pago['forma'] ?? FormaPago::disponibles()[0]->value),
                'nro_transaccion' => trim((string) ($pago['nro_transaccion'] ?? '')) ?: null,
                'banco' => trim((string) ($pago['banco'] ?? '')) ?: null,
                'monto' => round((float) ($pago['monto'] ?? 0), 2),
                'archivo' => $ruta,
            ];
        }

        // Lo que ya no está en la lista se va del disco.
        foreach (array_diff($anteriores, array_column($pagos, 'archivo')) as $huerfano) {
            Archivos::borrar($huerfano);
        }

        return $pagos;
    }

    /**
     * Los campos del servicio que la corrección puede tocar.
     *
     * Solo los que se tipean a mano en la cédula. Todo lo demás de
     * `datos_adicionales` —el registro, el nombre, el domicilio— se conserva
     * tal como estaba: el spread del llamador escribe estos encima y deja el
     * resto intacto.
     *
     * @return array<string, string>
     */
    private function datosCorregidos(Request $request, Tramite $tramite): array
    {
        if ($tramite->tipoTramite->codigo !== 'CAP') {
            return [];
        }

        return [
            'asociacion' => $request->string('asociacion')->trim()->value(),
            'capacidad_kg' => $request->string('capacidad_kg')->trim()->value(),
        ];
    }

    /**
     * APROBAR — POST /panel/tramites/{tramite}/aprobar
     *
     * Aprobar NO emite el documento: son dos pasos y dos permisos distintos.
     * Aprobar es decir «los papeles están bien»; emitir es quemar un número de
     * la serie y sacar una credencial a la calle. En el talonario de papel eso
     * también son dos momentos: el jefe firma, y después alguien plastifica.
     */
    public function aprobar(Request $request, Tramite $tramite): RedirectResponse
    {
        return $this->cambiarEstado(
            $tramite,
            EstadoTramite::Aprobado,
            fn (): array => [
                'fecha_aprobacion' => now(),
                'aprobado_por' => $request->user()->id,

                // Ya no existe un paso aparte de «tomar para revisar», así que
                // la aprobación es la que deja el rastro de quién miró los
                // papeles: fue esta misma persona.
                'fecha_revision' => $tramite->fecha_revision ?? now(),
                'revisado_por' => $tramite->revisado_por ?? $request->user()->id,
            ],
            "Trámite N° {$tramite->id} aprobado. Ya se puede emitir el documento.",
        );
    }

    /**
     * RECHAZAR — POST /panel/tramites/{tramite}/rechazar
     *
     * El motivo es obligatorio y no es burocracia: el pescador vuelve a
     * ventanilla a preguntar por qué, y quien lo atiende casi nunca es el mismo
     * que rechazó. Sin el motivo escrito, la respuesta es «no sé, vuelva otro
     * día».
     */
    public function rechazar(Request $request, Tramite $tramite): RedirectResponse
    {
        $request->validate(
            ['motivo_rechazo' => ['required', 'string', 'min:10', 'max:1000']],
            [
                'motivo_rechazo.required' => 'Escriba por qué se rechaza el trámite.',
                'motivo_rechazo.min' => 'El motivo tiene que explicar qué falta: al menos 10 caracteres.',
                'motivo_rechazo.max' => 'El motivo no puede pasar de 1000 caracteres.',
            ]
        );

        return $this->cambiarEstado(
            $tramite,
            EstadoTramite::Rechazado,
            fn (): array => [
                'motivo_rechazo' => $request->string('motivo_rechazo')->trim()->value(),
                'fecha_revision' => $tramite->fecha_revision ?? now(),
                'revisado_por' => $tramite->revisado_por ?? $request->user()->id,
            ],
            "Trámite N° {$tramite->id} rechazado.",
        );
    }

    /**
     * EMITIR EL DOCUMENTO — POST /panel/tramites/{tramite}/emitir
     *
     * Acá el trámite se convierte en credencial: toma su número de serie, su
     * código de verificación y su fecha de vencimiento. Desde este momento el
     * pescador aparece como habilitado y puede sacar permisos por faena.
     *
     * El trabajo de verdad lo hace EmisionDocumentoService; acá solo se
     * comprueba que corresponda y se mueve el estado.
     */
    public function emitir(
        Request $request,
        Tramite $tramite,
        EmisionDocumentoService $emision,
    ): RedirectResponse {
        /*
         * EMITIR YA NO ES UN CAMBIO DE ESTADO.
         *
         * El trámite se queda APROBADO: que el documento esté impreso se sabe
         * mirando si existe la fila en `documentos`, no por una palabra en la
         * columna `estado`. Por eso las dos condiciones se comprueban acá a
         * mano en vez de preguntarle al enum.
         */
        if ($tramite->estado !== EstadoTramite::Aprobado) {
            return back()->with(
                'error',
                "Solo se emite el documento de un trámite aprobado. Este está {$tramite->estado->etiqueta()}.",
            );
        }

        // Sin esto, dos clics seguidos —o dos operadores a la vez— queman dos
        // números de la serie para el mismo trámite y dejan dos credenciales
        // válidas de la misma persona en la calle.
        if ($tramite->documento !== null) {
            return back()->with(
                'error',
                "El trámite ya tiene emitido su documento, con código de verificación {$tramite->documento->codigo_verificacion}.",
            );
        }

        /*
         * NO SE EMITE UN DOCUMENTO SIN COBRAR.
         *
         * Es la única regla del circuito que no está en el enum de estados,
         * porque no es una cuestión de orden sino de plata: una credencial
         * emitida es una credencial que ya está en la calle, y el saldo se
         * vuelve incobrable. Cobrar primero y emitir después es el orden que ya
         * usa el talonario de papel.
         */
        if (! $tramite->esta_pagado) {
            return back()->with(
                'error',
                "El trámite tiene un saldo pendiente de Bs {$tramite->saldo_pendiente}. No se puede emitir el documento hasta que esté cancelado.",
            );
        }

        $documento = DB::transaction(function () use ($request, $tramite, $emision): Documento {
            $doc = $emision->emitir($tramite, $request->user());

            // Solo la fecha: el estado no se toca, el trámite sigue aprobado
            // hasta que se entregue.
            $tramite->forceFill(['fecha_emision' => now()])->save();

            return $doc;
        });

        return back()->with(
            'exito',
            "Documento emitido. Código de verificación: {$documento->codigo_verificacion}.",
        );
    }

    /**
     * ENTREGAR — POST /panel/tramites/{tramite}/entregar
     *
     * El último paso, y el que cierra el expediente. Se anota CÓMO se entregó
     * porque no es lo mismo haber dado la tarjeta en mano que haber mandado el
     * PDF: cuando alguien reclama que nunca la recibió, esa diferencia es toda
     * la respuesta que hay.
     */
    public function entregar(Request $request, Tramite $tramite): RedirectResponse
    {
        $request->validate(
            ['modo_entrega' => ['required', Rule::in(['fisica', 'digital'])]],
            ['modo_entrega.required' => 'Indique cómo se entregó el documento.']
        );

        /*
         * NO SE ENTREGA LO QUE NO SE EMITIÓ.
         *
         * Antes lo garantizaba el orden de los estados —a «entregado» solo se
         * llegaba desde «emitido»—. Al retirar ese estado, la comprobación
         * pasa a ser esta: tiene que existir el documento. Sin ella, un POST
         * hecho a mano cerraría el expediente sin que exista la credencial que
         * supuestamente se entregó.
         */
        if ($tramite->documento === null) {
            return back()->with(
                'error',
                'Primero hay que emitir el documento: todavía no existe nada para entregar.',
            );
        }

        return $this->cambiarEstado(
            $tramite,
            EstadoTramite::Entregado,
            fn (): array => [
                'modo_entrega' => $request->string('modo_entrega')->value(),
                'fecha_entrega' => now(),
                'entregado_por' => $request->user()->id,
            ],
            "Trámite N° {$tramite->id} entregado.",
        );
    }

    /**
     * El tronco común de los cambios de estado.
     *
     * Los cinco pasos hacen lo mismo antes de escribir: comprobar que el salto
     * esté permitido. Escrito cinco veces, bastaría con olvidarse una para que
     * un POST hecho a mano entregara un trámite que nunca se emitió.
     *
     * Quién decide qué salto vale es el enum, no este controlador. Ver
     * `EstadoTramite::siguientes()`.
     *
     * @param  callable(): array<string, mixed>  $campos
     */
    private function cambiarEstado(
        Tramite $tramite,
        EstadoTramite $destino,
        callable $campos,
        string $mensaje,
    ): RedirectResponse {
        if (! $tramite->estado->puedePasarA($destino)) {
            return back()->with('error', $this->porQueNo($tramite, $destino));
        }

        // forceFill y no update(): los campos del circuito no están en
        // #[Fillable] a propósito, para que no se puedan tocar desde un
        // formulario. El trait Auditable igual deja la fila de quién lo hizo.
        $tramite->forceFill(['estado' => $destino, ...$campos()])->save();

        return back()->with('exito', $mensaje);
    }

    /**
     * El «no» explicado, que es lo único que le sirve a quien está en pantalla.
     */
    private function porQueNo(Tramite $tramite, EstadoTramite $destino): string
    {
        if ($tramite->estado->esFinal()) {
            return "El trámite ya está {$tramite->estado->etiqueta()} y no admite más cambios.";
        }

        $puede = array_map(
            fn (EstadoTramite $e): string => $e->etiqueta(),
            $tramite->estado->siguientes(),
        );

        return "Un trámite {$tramite->estado->etiqueta()} no puede pasar a {$destino->etiqueta()}. "
            .'Desde acá solo se puede ir a: '.implode(', ', $puede).'.';
    }

    /**
     * El trámite entero, tal como lo necesita la ficha de React.
     *
     * @return array<string, mixed>
     */
    private function presentarTramite(Tramite $tramite): array
    {
        $solicitante = $tramite->solicitante;
        $documento = $tramite->documento;

        return [
            'id' => $tramite->id,
            'estado' => $tramite->estado->value,
            'estado_etiqueta' => $tramite->estado->etiqueta(),
            'estado_color' => $tramite->estado->color(),

            // Los saltos permitidos salen del enum y viajan a React: así los
            // botones que se dibujan son exactamente los que el servidor va a
            // aceptar, sin repetir la regla en dos lugares.
            'siguientes' => array_map(
                fn (EstadoTramite $e): string => $e->value,
                $tramite->estado->siguientes(),
            ),

            // Igual que los saltos: la regla vive en el enum y la pantalla solo
            // la recibe, para no tener dos copias que se contradigan.
            'puede_editarse' => $tramite->estado->permiteEdicion(),

            /*
             * ¿Corresponde dibujar el botón de emitir?
             *
             * Emitir dejó de ser un cambio de estado, así que la pantalla no
             * lo puede deducir de `siguientes` como hace con los demás
             * botones. La respuesta se calcula acá, en el mismo lugar donde
             * emitir() la comprueba antes de aceptar, para que el botón que se
             * ve y lo que el servidor permite no puedan discrepar.
             *
             * El saldo pendiente NO entra en esta cuenta: ahí el botón se ve
             * pero apagado, con el monto que falta al lado. Esconderlo dejaría
             * al operador sin entender por qué no puede emitir.
             */
            'puede_emitirse' => $tramite->estado === EstadoTramite::Aprobado && $documento === null,

            'tipo' => [
                'codigo' => $tramite->tipoTramite->codigo,
                'nombre' => $tramite->tipoTramite->nombre,
                'area' => $tramite->tipoTramite->area->nombre,
                'icono' => (string) $tramite->tipoTramite->area->icono,
                'vigencia' => $tramite->tipoTramite->vigenciaLegible(),
                'categoria_documento' => $tramite->tipoTramite->categoria_documento->value,
            ],

            'solicitante' => [
                'id' => $solicitante->id,
                'nombreCompleto' => $solicitante->nombreCompleto,
                'documento_identidad' => $solicitante->documento_identidad,
                'telefono' => $solicitante->telefono,
                'ciudad' => $solicitante->ciudad,
                'provincia' => $solicitante->provincia,
                'direccion' => $solicitante->direccion,
                'foto_url' => $solicitante->foto_url,
            ],

            'monto_total' => (float) $tramite->monto_total,
            'monto_pagado' => (float) $tramite->monto_pagado,
            'saldo_pendiente' => $tramite->saldo_pendiente,
            'esta_pagado' => $tramite->esta_pagado,

            'datos_adicionales' => $tramite->datos_adicionales ?? [],
            'observaciones' => $tramite->observaciones,
            'motivo_rechazo' => $tramite->motivo_rechazo,
            'modo_entrega' => $tramite->modo_entrega,

            'requisitos' => $this->presentarRequisitos($tramite),
            'pagos' => $this->presentarPagos($tramite),

            'documento' => $documento ? [
                'codigo_verificacion' => $documento->codigo_verificacion,
                'url_verificacion' => $documento->url_verificacion,
                'fecha_emision' => $documento->fecha_emision?->toDateString(),
                'fecha_vencimiento' => $documento->fecha_vencimiento?->toDateString(),
                'dias_para_vencer' => $documento->dias_para_vencer,
                'estado' => $documento->estadoEfectivo()->value,
                'estado_etiqueta' => $documento->estadoEfectivo()->etiqueta(),
                'estado_color' => $documento->estadoEfectivo()->color(),
                'emitido_por' => $documento->emitidoPor?->name,
            ] : null,

            // La línea de tiempo: quién hizo qué y cuándo. Es lo primero que se
            // mira cuando alguien pregunta «¿en qué quedó este trámite?».
            'hitos' => [
                'recepcion' => $this->hito($tramite->fecha_recepcion, $tramite->operador?->name),
                'revision' => $this->hito($tramite->fecha_revision, $tramite->revisadoPor?->name),
                'aprobacion' => $this->hito($tramite->fecha_aprobacion, $tramite->aprobadoPor?->name),
                'emision' => $this->hito($tramite->fecha_emision, $documento?->emitidoPor?->name),
                'entrega' => $this->hito($tramite->fecha_entrega, $tramite->entregadoPor?->name),
            ],
        ];
    }

    /**
     * @return array{fecha: string|null, quien: string|null}|null
     */
    private function hito(?Carbon $fecha, ?string $quien): ?array
    {
        return $fecha === null ? null : [
            'fecha' => $fecha->toIso8601String(),
            'quien' => $quien,
        ];
    }

    /**
     * Los papeles adjuntos, con su enlace para abrirlos.
     *
     * Se manda la URL y no la ruta del disco porque el revisor tiene que poder
     * ABRIR la certificación y leerla: aprobar mirando solo el nombre del
     * archivo no es revisar nada.
     *
     * @return array<int, array<string, string>>
     */
    private function presentarRequisitos(Tramite $tramite): array
    {
        $etiquetas = [
            'certificacion_asociacion' => 'Certificación de la asociación',
            'copia_ci' => 'Fotocopia del carnet',
        ];

        $requisitos = [];

        foreach ($etiquetas as $campo => $etiqueta) {
            $ruta = $tramite->requisitos_validados[$campo] ?? null;

            if ($ruta === null) {
                continue;
            }

            $requisitos[] = [
                'campo' => $campo,
                'etiqueta' => $etiqueta,
                // Archivos::url y no Storage directo: con el disco en s3, lo
                // guardado ya es una direccion completa. Ver App\Support\Archivos.
                'url' => (string) Archivos::url($ruta),
                'archivo' => basename($ruta),
            ];
        }

        return $requisitos;
    }

    /**
     * Los pagos declarados, con su comprobante abrible.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentarPagos(Tramite $tramite): array
    {
        $pagos = $tramite->requisitos_validados['pagos'] ?? [];

        return array_map(function (array $pago): array {
            $forma = FormaPago::tryFrom((string) ($pago['forma'] ?? ''));

            return [
                'forma' => $pago['forma'] ?? null,
                'forma_etiqueta' => $forma?->etiqueta() ?? 'Sin indicar',
                'nro_transaccion' => $pago['nro_transaccion'] ?? null,
                'banco' => $pago['banco'] ?? null,
                'monto' => (float) ($pago['monto'] ?? 0),

                // La ruta va además de la URL: el formulario de corrección la
                // manda de vuelta para que el pago conserve su comprobante.
                'archivo' => $pago['archivo'] ?? null,

                'url' => Archivos::url($pago['archivo'] ?? null),
            ];
        }, $pagos);
    }

    /**
     * GUARDAR — POST /panel/tramites
     *
     * Acá el trámite se registra de verdad.
     *
     * ------------------------------------------------------------------------
     *  LO QUE SE COMPRUEBA ANTES DE ESCRIBIR NADA
     * ------------------------------------------------------------------------
     *
     * 1. LA COMPUERTA. El servicio no se registra si el solicitante no tiene la
     *    cédula de pescador vigente. Se comprueba acá aunque las tarjetas del
     *    paso anterior ya lo hayan hecho: esas viven en el navegador del
     *    operador y un POST hecho a mano las saltea enteras.
     *
     * 2. LOS TRES ADJUNTOS DE LA CÉDULA: certificación de la asociación,
     *    fotocopia del carnet y comprobante de pago.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ TODO VA DENTRO DE UNA TRANSACCIÓN
     * ------------------------------------------------------------------------
     *
     * Porque el número correlativo se reserva ANTES de insertar el trámite. Si
     * el insert fallara después de reservarlo, ese número quedaría quemado: el
     * talonario saltaría del 0007 al 0009 sin que exista un 0008, y en una
     * institución eso hay que explicarlo en una auditoría.
     *
     * Los archivos se guardan dentro de la transacción a propósito. No se
     * borran solos si algo falla —el disco no entiende de transacciones—, pero
     * quedan huérfanos en una carpeta con el código del trámite, que es
     * recuperable; al revés, un trámite sin sus respaldos, no.
     */
    public function store(
        Request $request,
        RegistroPescadorService $registros,
        StorageController $almacen,
    ): RedirectResponse {
        $tipo = TipoTramite::with('area')
            ->where('codigo', (string) $request->input('tipo'))
            ->first();

        $solicitante = $this->solicitanteDe($request);

        if ($tipo === null || $solicitante === null) {
            return back()->with('error', 'Falta indicar el servicio o el solicitante del trámite.');
        }

        $habilitacion = $tipo->habilitacionPara($solicitante);

        if (! $habilitacion->habilitado) {
            return back()->with('error', trim($habilitacion->motivo.' '.$habilitacion->comoResolver));
        }

        $this->validarAdjuntos($request, $tipo, $solicitante);

        $tramite = DB::transaction(function () use ($request, $tipo, $solicitante, $registros, $almacen): Tramite {
            // Antes que nada la ficha, porque la foto y el domicilio no son
            // del trámite sino de la persona: si el alta falla, lo que sí tiene
            // que quedar guardado es que a este pescador ya se le sacó la
            // fotografía y se le anotó dónde vive.
            $this->completarFichaDelSolicitante($request, $solicitante, $almacen);

            /*
             * EL TRÁMITE SE CREA PRIMERO, Y LOS ARCHIVOS DESPUÉS.
             *
             * Los adjuntos van a una carpeta con el número del trámite, y ese
             * número es el id, que la base recién asigna al insertar. Antes se
             * podía guardar todo de una porque el código se reservaba aparte;
             * ahora hay que esperar a que la fila exista.
             *
             * Los dos pasos están en la misma transacción, así que un fallo al
             * guardar los archivos deshace también el trámite: no queda un
             * expediente sin sus respaldos.
             */
            $tramite = Tramite::create([
                'solicitante_id' => $solicitante->id,
                'tipo_tramite_id' => $tipo->id,
                'user_id' => $request->user()->id,

                /*
                 * TODO trámite entra EN REVISIÓN, sin importar el servicio.
                 *
                 * Pedir la cédula no es tenerla: alguien tiene que mirar los
                 * papeles y aprobarla antes de que se emita el documento y el
                 * pescador quede habilitado.
                 *
                 * Antes los servicios sin aprobación entraban como «recibido»
                 * y había que pasarlos a revisión con un botón. Eran dos
                 * estados para decir lo mismo —el expediente está sobre el
                 * escritorio y nadie lo resolvió— y el paso intermedio no
                 * agregaba ninguna información.
                 */
                'estado' => EstadoTramite::EnRevision,

                'monto_total' => $tipo->monto,

                /*
                 * Lo que el pescador ya cubrió es la SUMA de lo declarado en
                 * cada comprobante, no el monto de la tasa. Cuando paga en dos
                 * veces y trae solo la primera transferencia, el trámite tiene
                 * que verse como parcialmente pagado: `saldo_pendiente` y
                 * `esta_pagado` del modelo se calculan con esta columna.
                 */
                // Se completa al guardar los adjuntos, unas líneas más abajo:
                // los montos salen de los pagos, y los pagos necesitan el id.
                'monto_pagado' => 0,

                'fecha_recepcion' => now(),

                // Los campos propios de cada servicio no son columnas: van en
                // la columna jsonb que la migración dejó preparada para esto.
                'datos_adicionales' => [
                    ...$this->datosPropios($request),
                    // Los renglones que salen de la ficha se escriben DESPUÉS,
                    // así pisan lo que haya mandado el navegador. Ver el método.
                    ...$this->datosDeLaFicha($tipo, $solicitante),

                    // Y el registro al final, por lo mismo: lo asigna el
                    // sistema, no el formulario. Ver RegistroPescadorService.
                    ...$this->registroAsignado($tipo, $registros),
                ],

                'observaciones' => $request->string('observaciones')->trim()->value() ?: null,
            ]);

            $pagos = $this->registrarPagos($request, $tramite->carpeta(), $almacen);

            /*
             * Los pagos van con los requisitos y no en `datos_adicionales`
             * porque son respaldo del expediente, igual que la certificación de
             * la asociación: no se imprimen en la tarjeta.
             */
            $tramite->forceFill([
                'requisitos_validados' => [
                    ...$this->guardarAdjuntos($request, $tramite->carpeta(), $almacen),
                    'pagos' => $pagos,
                ],
                'monto_pagado' => array_sum(array_column($pagos, 'monto')),
            ])->save();

            return $tramite;
        });

        return redirect()
            ->route('tramites.show', $tramite->id)
            ->with('exito', "Trámite N° {$tramite->id} registrado. Queda en revisión para su aprobación.");
    }

    /**
     * Los adjuntos obligatorios de cada servicio.
     *
     * Hoy solo la cédula exige archivos. Cuando otro servicio los pida, se
     * agrega su rama acá y no en tres pantallas distintas.
     */
    private function validarAdjuntos(Request $request, TipoTramite $tipo, Solicitante $solicitante): void
    {
        if ($tipo->codigo !== 'CAP') {
            return;
        }

        /*
         * Formatos y peso salen de config/jichi.php, que es donde vive el
         * límite único del sistema. Escrito acá a mano, este formulario
         * quedaría aceptando 4 MB el día que el resto pase a 3.
         *
         * Se acepta PDF además de imagen porque la certificación suele llegar
         * escaneada desde una fotocopiadora, y esas máquinas devuelven PDF.
         */
        $maxKb = (int) config('jichi.archivos.max_kb');
        $maxMb = round($maxKb / 1024, 1);

        $formatos = [
            'required',
            'file',
            'mimes:'.implode(',', config('jichi.archivos.extensiones')),
            'max:'.$maxKb,
        ];

        $request->validate(
            [
                'certificacion_asociacion' => $formatos,
                'copia_ci' => $formatos,

                /*
                 * LA FOTO SOLO SE PIDE SI LA FICHA NO LA TIENE.
                 *
                 * La credencial es una tarjeta plastificada con la cara del
                 * titular: sin foto no hay nada que imprimir. Pero la foto es
                 * un DATO PERSONAL del padrón, no un papel de este trámite —por
                 * eso el campo se llama `foto_solicitante` y termina en
                 * `solicitantes.foto`, no en la carpeta del trámite—.
                 *
                 * Cuando la ficha ya tiene foto, el campo ni se muestra y lo
                 * que llegue se ignora: reemplazarla es una corrección de la
                 * ficha y se hace desde la ficha. Si se pudiera cambiar en cada
                 * trámite, la misma persona terminaría con una cara distinta en
                 * cada credencial y sin forma de saber cuál es la buena.
                 */
                'foto_solicitante' => [
                    $solicitante->foto === null ? 'required' : 'nullable',
                    'image',
                    'mimes:'.implode(',', config('jichi.archivos.extensiones_imagen')),
                    'max:'.$maxKb,
                ],

                /*
                 * EL DOMICILIO TAMBIÉN ES DE LA PERSONA, NO DEL TRÁMITE.
                 *
                 * Ciudad, provincia y dirección son renglones impresos en la
                 * credencial, pero salen de la ficha del solicitante: son datos
                 * suyos y no de este expediente. En el formulario se muestran
                 * de solo lectura, y estos campos aparecen únicamente cuando la
                 * ficha viene incompleta —el padrón viejo tiene fichas sin
                 * dirección—, para completarla sin salir del trámite.
                 *
                 * Mismo criterio que la foto: se COMPLETA, nunca se pisa. Ver
                 * completarFichaDelSolicitante().
                 */
                'ciudad_solicitante' => [
                    $solicitante->ciudad === null ? 'required' : 'nullable',
                    'string', 'max:120',
                ],
                'provincia_solicitante' => [
                    $solicitante->provincia === null ? 'required' : 'nullable',
                    'string', 'max:120',
                ],
                'direccion_solicitante' => [
                    $solicitante->direccion === null ? 'required' : 'nullable',
                    'string', 'max:255',
                ],

                /*
                 * EL PAGO NO ES UN SOLO PAPEL.
                 *
                 * La cédula se puede cancelar en varias veces —dos
                 * transferencias hechas en días distintos, o un depósito más un
                 * saldo en efectivo—, y cada una llega con SU comprobante y SU
                 * número de transacción. Con un único archivo, ventanilla tenía
                 * que elegir cuál de los dos adjuntaba y el otro quedaba sin
                 * rastro en el expediente; después, al cruzar la caja con el
                 * extracto del banco, ese pago no aparecía en ninguna parte.
                 *
                 * El tope de 5 no es una regla del SEDAG: es un freno para que
                 * un formulario armado a mano no suba doscientos archivos.
                 */
                'pagos' => ['required', 'array', 'min:1', 'max:5'],
                /*
                 * La lista sale de FormaPago::disponibles() y no de cases():
                 * hoy solo se acepta transferencia bancaria. El efectivo y el
                 * QR siguen existiendo en el enum para poder LEER pagos
                 * viejos, pero no se pueden registrar.
                 *
                 * Rule::in y no Rule::enum porque el sistema no tiene
                 * traducidos los mensajes de Laravel, y el mensaje propio se
                 * engancha por el NOMBRE de la regla («pagos.*.forma.in»). Una
                 * regla objeto no tiene ese nombre, y el operador terminaría
                 * leyendo «The selected pagos.0.forma is invalid.».
                 */
                'pagos.*.forma' => [
                    'required',
                    Rule::in(array_column(FormaPago::disponibles(), 'value')),
                ],

                /*
                 * La transferencia solo se puede cruzar con el extracto del
                 * banco por su número de transacción; sin él el pago queda sin
                 * comprobar. La condición se escribe contra el efectivo —que
                 * hoy no está habilitado— para que el día que vuelva siga
                 * valiendo: el efectivo se cuenta en ventanilla contra el
                 * recibo y no tiene número que anotar.
                 * Ver FormaPago::requiereReferencia().
                 */
                'pagos.*.nro_transaccion' => [
                    'required_unless:pagos.*.forma,efectivo',
                    'nullable', 'string', 'max:60',
                ],
                'pagos.*.banco' => ['nullable', 'string', 'max:80'],

                // El mínimo es 0.01 y no 0: un pago de cero no es un pago, es
                // una fila que alguien agregó de más y se olvidó de sacar.
                'pagos.*.monto' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
                'pagos.*.comprobante' => $formatos,
            ],
            // Los mensajes van escritos acá porque el sistema no tiene
            // traducidos los de Laravel: sin esto, el operador leería
            // «The certificacion asociacion field is required.».
            [
                'certificacion_asociacion.required' => 'Adjunte la certificación emitida por su asociación.',
                'certificacion_asociacion.mimes' => 'La certificación tiene que ser PDF, JPG, PNG o WEBP.',
                'certificacion_asociacion.max' => "La certificación no puede pesar más de {$maxMb} MB.",
                'copia_ci.required' => 'Adjunte la fotocopia del carnet simple.',
                'copia_ci.mimes' => 'La fotocopia del carnet tiene que ser PDF, JPG, PNG o WEBP.',
                'copia_ci.max' => "La fotocopia del carnet no puede pesar más de {$maxMb} MB.",
                'foto_solicitante.required' => 'La ficha del solicitante no tiene fotografía: adjunte la foto del titular para poder emitir la credencial.',
                'foto_solicitante.image' => 'La fotografía tiene que ser una imagen.',
                'foto_solicitante.mimes' => 'La fotografía tiene que ser JPG, PNG o WEBP.',
                'foto_solicitante.max' => "La fotografía no puede pesar más de {$maxMb} MB.",
                'ciudad_solicitante.required' => 'La ficha del solicitante no tiene ciudad: complétela para poder emitir la credencial.',
                'provincia_solicitante.required' => 'La ficha del solicitante no tiene provincia: complétela para poder emitir la credencial.',
                'direccion_solicitante.required' => 'La ficha del solicitante no tiene dirección: complétela para poder emitir la credencial.',
                'ciudad_solicitante.max' => 'La ciudad no puede pasar de 120 caracteres.',
                'provincia_solicitante.max' => 'La provincia no puede pasar de 120 caracteres.',
                'direccion_solicitante.max' => 'La dirección no puede pasar de 255 caracteres.',

                'pagos.required' => 'Registre al menos un pago de la cédula.',
                'pagos.min' => 'Registre al menos un pago de la cédula.',
                'pagos.max' => 'No se pueden registrar más de 5 pagos en un mismo trámite.',
                'pagos.*.forma.required' => 'Indique cómo se hizo el pago.',
                'pagos.*.forma.in' => 'Por ahora la cédula solo se puede pagar por transferencia bancaria.',
                'pagos.*.nro_transaccion.required_unless' => 'Anote el número de transacción del comprobante.',
                'pagos.*.nro_transaccion.max' => 'El número de transacción no puede pasar de 60 caracteres.',
                'pagos.*.banco.max' => 'El nombre del banco no puede pasar de 80 caracteres.',
                'pagos.*.monto.required' => 'Anote el monto de este pago.',
                'pagos.*.monto.numeric' => 'El monto del pago tiene que ser un número.',
                'pagos.*.monto.min' => 'El monto del pago tiene que ser mayor que cero.',
                'pagos.*.comprobante.required' => 'Adjunte el comprobante de este pago.',
                'pagos.*.comprobante.mimes' => 'El comprobante de pago tiene que ser PDF, JPG, PNG o WEBP.',
                'pagos.*.comprobante.max' => "El comprobante de pago no puede pesar más de {$maxMb} MB.",
            ]
        );
    }

    /**
     * Los campos propios del servicio, para la columna jsonb.
     *
     * Se sacan los que NO son datos del trámite: el tipo y el solicitante son
     * columnas propias, los archivos se guardan aparte, y `_token` y `_method`
     * son de Laravel.
     *
     * @return array<string, mixed>
     */
    private function datosPropios(Request $request): array
    {
        $datos = $request->except([
            '_token',
            '_method',
            'tipo',
            'solicitante',
            'observaciones',
            // Los pagos NO son un campo del servicio: van normalizados por
            // registrarPagos(), con la ruta de su comprobante ya resuelta.
            'pagos',
            ...array_keys(self::ADJUNTOS),
        ]);

        /*
         * NINGÚN ARCHIVO ENTRA A LA COLUMNA JSONB.
         *
         * La lista de arriba nombra los adjuntos que el sistema espera, pero un
         * POST armado a mano puede traer cualquier otro —una `foto`, por
         * ejemplo, que desde que sale de la ficha del solicitante ya no es un
         * campo de este formulario—. Un UploadedFile no se puede serializar a
         * JSON, así que ahí el alta reventaría con un error que no dice nada.
         *
         * Se descartan por TIPO y no por nombre para no depender de acordarse
         * de agregar cada campo nuevo a la lista.
         */
        return array_filter($datos, fn ($valor): bool => ! $valor instanceof UploadedFile);
    }

    /**
     * Los archivos que puede traer un trámite y cómo se llaman al guardarlos.
     *
     * La fotografía NO está en esta lista y no se sube desde acá: es un dato
     * personal del padrón y sale de la ficha del solicitante. Ver la compuerta
     * de TipoTramite::habilitacionPara().
     */
    private const ADJUNTOS = [
        'certificacion_asociacion' => 'certificacion-asociacion',
        'copia_ci' => 'copia-ci',
    ];

    /**
     * Completa en la FICHA los datos personales que el trámite necesitaba.
     *
     * ------------------------------------------------------------------------
     *  SE COMPLETA, NUNCA SE PISA
     * ------------------------------------------------------------------------
     *
     * La fotografía y el domicilio son datos de la PERSONA, no de este
     * expediente: viven en `solicitantes` y de ahí los toma la credencial de
     * hoy y todas las que se le emitan después. El formulario del trámite los
     * pide únicamente cuando la ficha viene sin ellos —el padrón viejo tiene
     * fichas sin dirección y sin foto—, para no mandar al operador a otra
     * pantalla con el pescador esperando en la ventanilla.
     *
     * Lo que ya está cargado no se toca, ni siquiera cuando llega un valor:
     * corregir un dato de la ficha es una edición de la ficha y se hace desde
     * la ficha —donde, en el caso de la foto, además se borra la anterior para
     * no dejar archivos huérfanos ocupando disco—. Sin esta regla, la misma
     * persona podría terminar con una dirección distinta en cada credencial.
     */
    private function completarFichaDelSolicitante(
        Request $request,
        Solicitante $solicitante,
        StorageController $almacen,
    ): void {
        if ($solicitante->foto === null && $request->hasFile('foto_solicitante')) {
            // Misma carpeta y mismo agrupado por fecha que
            // SolicitanteController::guardarFoto(): las fotos del padrón viven
            // juntas, sin importar desde qué pantalla entraron.
            $solicitante->foto = $almacen->file($request->file('foto_solicitante'), 'solicitantes');
        }

        foreach (['ciudad', 'provincia', 'direccion'] as $campo) {
            if ($solicitante->{$campo} !== null) {
                continue;
            }

            $valor = $request->string($campo.'_solicitante')->trim()->value();

            if ($valor !== '') {
                $solicitante->{$campo} = $valor;
            }
        }

        if (! $solicitante->isDirty()) {
            return;
        }

        // save() y no update() para que el trait Auditable deje la fila con el
        // usuario de la sesión: quién completó qué dato de qué ficha es parte
        // del rastro del padrón, igual que cualquier otra corrección.
        $solicitante->save();
    }

    /**
     * El código de registro que va impreso en la credencial.
     *
     * Solo lo lleva la Cédula de Pescador: es el renglón «REGISTRO» de la
     * tarjeta. Se asigna acá y no en el formulario porque tiene que ser único
     * en todo el padrón, y eso el navegador no lo puede garantizar —ni debería
     * intentarlo: un código que el cliente elige es un código que el cliente
     * puede repetir a propósito—.
     *
     * @return array<string, string>
     */
    private function registroAsignado(TipoTramite $tipo, RegistroPescadorService $registros): array
    {
        return $tipo->codigo === 'CAP'
            ? ['registro' => $registros->siguiente()]
            : [];
    }

    /**
     * Los renglones de la credencial que NO los decide el navegador.
     *
     * Nombre, cédula, domicilio: son datos del padrón, se muestran de solo
     * lectura en el formulario y se imprimen tal como figuran en la ficha. Si
     * se guardara lo que llega en la petición, un POST armado a mano emitiría
     * una credencial a nombre de otra persona, y en papel plastificado eso no
     * se corrige editando un registro.
     *
     * Solo aplica a la Cédula de Pescador: los otros dos servicios no imprimen
     * datos personales del titular.
     *
     * @return array<string, string>
     */
    private function datosDeLaFicha(TipoTramite $tipo, Solicitante $solicitante): array
    {
        if ($tipo->codigo !== 'CAP') {
            return [];
        }

        return [
            'ci' => (string) $solicitante->ci_nit,
            'expedido' => (string) ($solicitante->expedido ?? ''),
            'nombre' => $solicitante->nombreCompleto,
            'ciudad' => (string) ($solicitante->ciudad ?? ''),
            'provincia' => (string) ($solicitante->provincia ?? ''),
            'direccion' => (string) ($solicitante->direccion ?? ''),
        ];
    }

    /**
     * Los pagos declarados, normalizados y con su comprobante ya guardado.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ CADA PAGO ES UN OBJETO CERRADO Y NO DOS LISTAS PARALELAS
     * ------------------------------------------------------------------------
     *
     * Queda así, una entrada por pago:
     *
     *     [
     *       {"forma": "transferencia", "nro_transaccion": "884512203",
     *        "banco": "Banco Unión", "monto": 50.0,
     *        "archivo": "tramites/TRA-PESCA-2026-0001/comprobante-pago-1.jpg"},
     *       {"forma": "efectivo", "nro_transaccion": null, "banco": null,
     *        "monto": 30.0,
     *        "archivo": "tramites/TRA-PESCA-2026-0001/comprobante-pago-2.pdf"}
     *     ]
     *
     * Guardar los montos por un lado y los archivos por otro parece más simple
     * hasta que alguien borra un comprobante: ahí las dos listas se corren de
     * posición y cada monto queda apuntando a la transferencia equivocada. Con
     * el pago cerrado sobre sí mismo, el expediente se lee de corrido.
     *
     * Los archivos se numeran por POSICIÓN (`comprobante-pago-1.jpg`) y no por
     * número de transacción: los códigos del banco traen barras y espacios, y
     * eso en un nombre de archivo parte la ruta en dos carpetas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function registrarPagos(
        Request $request,
        string $codigo,
        StorageController $almacen,
    ): array {
        $pagos = [];

        foreach ((array) $request->input('pagos', []) as $i => $pago) {
            $archivo = $request->file("pagos.{$i}.comprobante");

            $pagos[] = [
                // El respaldo es la primera forma habilitada y no un valor
                // escrito a mano: si mañana se deshabilita la transferencia,
                // esto no queda apuntando a una opción que ya no se acepta.
                'forma' => (string) ($pago['forma'] ?? FormaPago::disponibles()[0]->value),

                // El vacío se guarda como NULL y no como cadena vacía: en el
                // efectivo no hay número que anotar, y `""` haría creer al que
                // lea el expediente que se perdió el dato.
                'nro_transaccion' => trim((string) ($pago['nro_transaccion'] ?? '')) ?: null,
                'banco' => trim((string) ($pago['banco'] ?? '')) ?: null,

                // Se guarda como número y no como el texto del input: si entra
                // "50" como cadena, la suma de los pagos concatena en vez de
                // sumar y `monto_pagado` sale cualquier cosa.
                'monto' => round((float) ($pago['monto'] ?? 0), 2),

                'archivo' => $archivo === null
                    ? null
                    : $almacen->file($archivo, 'tramites/'.$codigo),
            ];
        }

        return $pagos;
    }

    /**
     * Guarda los adjuntos y devuelve dónde quedó cada uno.
     *
     * Van a una carpeta por trámite —`tramites/TRA-PESCA-2026-0001/`— y no
     * todos mezclados en una sola. Con una carpeta por trámite, encontrar los
     * respaldos de un expediente es entrar a la carpeta con su número; sin
     * eso, hay que cruzar la base de datos para saber qué archivo es de quién.
     *
     * El nombre se fuerza a algo legible en vez de la cadena al azar que pone
     * Laravel, porque estos archivos se abren a mano cuando alguien reclama.
     *
     * @return array<string, string>
     */
    private function guardarAdjuntos(
        Request $request,
        string $codigo,
        StorageController $almacen,
    ): array {
        $guardados = [];

        foreach (self::ADJUNTOS as $campo => $nombre) {
            if (! $request->hasFile($campo)) {
                continue;
            }

            $guardados[$campo] = $almacen->file($request->file($campo), 'tramites/'.$codigo);
        }

        return $guardados;
    }
}
