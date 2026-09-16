<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Carnet;
use App\Models\Configuracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  VERIFICACIÓN PÚBLICA — la única pantalla sin sesión del sistema
 * ============================================================================
 *
 * Es la URL que apunta el QR impreso en cada carnet. Un pescador muestra su
 * documento, el inspector lo escanea con el teléfono y cae acá, que le dice si
 * el carnet es real, si está vigente y para qué rubros habilita.
 *
 * ----------------------------------------------------------------------------
 *  UN SOLO DATO: LA FIRMA DE VALIDACIÓN
 * ----------------------------------------------------------------------------
 *
 * El carnet no tiene número. Se identifica por su firma: dieciséis caracteres
 * alfanuméricos generados al azar, únicos, impresos en el plástico y dentro del
 * QR.
 *
 * Antes hacían falta dos datos —un código público y una firma secreta— porque el
 * código era predecible. Al retirarse el código, la firma cumple los dos papeles,
 * y eso funciona porque es IMPREDECIBLE: 16 caracteres alfanuméricos son ~8 ·
 * 10^24 combinaciones. Con el throttle de la ruta, adivinar una no es posible en
 * la práctica.
 *
 * La contrapartida, y conviene tenerla presente: quien tenga la firma puede
 * consultar ese carnet. Es el mismo nivel de acceso que tiene quien sostiene el
 * plástico en la mano, así que está bien — pero ya no hay un dato «público» que
 * se pueda compartir sin dar también la consulta.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE NO SE MUESTRA
 * ----------------------------------------------------------------------------
 *
 * Nunca la cédula completa, ni dirección, ni teléfono, ni correo, ni el id
 * interno de ninguna tabla. El inspector necesita saber si el documento vale y
 * de quién es; todo lo demás sería exponer datos personales a cualquiera que
 * levante un carnet del suelo. Ver datosPublicos().
 */
class VerificacionController extends Controller
{
    public function show(?string $firma = null): Response
    {
        $carnet = $firma ? $this->buscarCarnet($firma) : null;

        return Inertia::render('publico/verificar', [
            // Se devuelve lo que se buscó, en grupos: es lo que la pantalla
            // muestra en el acta de «no encontrado».
            'firma' => $firma ? Carnet::normalizarFirma($firma) : null,
            'carnet' => $carnet ? $this->datosPublicos($carnet) : null,

            // null = todavía no se buscó nada (se entró a /verificar a secas).
            // false = se buscó y no apareció. La pantalla dice cosas distintas
            // en cada caso, y con un solo booleano no se podrían distinguir.
            'encontrado' => $firma === null ? null : $carnet !== null,

            'institucion' => [
                'municipio' => Configuracion::obtener('municipio.nombre'),
                'sistema' => Configuracion::obtener('sistema.nombre', 'Jichi'),
                'pie_legal' => Configuracion::obtener('documentos.pie_legal'),
            ],
        ]);
    }

    /**
     * La firma escrita a mano, cuando el QR no se deja escanear.
     *
     * Se normaliza ANTES de validar, y eso no es un detalle: la firma se imprime
     * en grupos —4K7R J2MX P9TQ 3WHB— y el ciudadano la copia tal cual, con
     * espacios o guiones. Validando el texto crudo, cualquiera de esas dos formas
     * daría «no tiene 16 caracteres» cuando en realidad está perfecta.
     */
    public function buscar(Request $request): RedirectResponse
    {
        $request->merge([
            'firma' => Carnet::normalizarFirma((string) $request->input('firma')),
        ]);

        $largo = Carnet::LARGO_FIRMA;

        $validado = $request->validate([
            // alpha_num sobre el texto ya normalizado: lo que llegue con
            // símbolos raros queda corto y cae en la regla de tamaño.
            'firma' => ['required', 'string', 'alpha_num', "size:{$largo}"],
        ], [
            'firma.required' => 'Ingrese la firma de validación impresa en el carnet.',
            'firma.size' => "La firma de validación tiene {$largo} caracteres entre letras y números.",
            'firma.alpha_num' => 'La firma de validación solo lleva letras y números.',
        ]);

        return redirect()->route('verificar.show', $validado['firma']);
    }

    /**
     * Busca el carnet por su firma.
     *
     * ------------------------------------------------------------------------
     *  SE COMPARA EN LA BASE, NO EN PHP
     * ------------------------------------------------------------------------
     *
     * Cuando el carnet tenía un código público, la firma se traía y se comparaba
     * con `hash_equals()` para no filtrar información por el TIEMPO que tarda:
     * una comparación normal corta en el primer carácter distinto, y con
     * suficientes intentos cronometrados una firma se puede reconstruir letra
     * por letra.
     *
     * Ahora la firma es lo que se busca, así que la comparación la hace el índice
     * de la base —que responde en el mismo tiempo encuentre o no— y no hay nada
     * que filtrar. Lo que protege sigue siendo el tamaño del espacio: 16
     * caracteres alfanuméricos, ~8 · 10^24 combinaciones, más el throttle de 20
     * intentos por minuto de la ruta.
     */
    private function buscarCarnet(string $firma): ?Carnet
    {
        $normalizada = Carnet::normalizarFirma($firma);

        // Se corta antes de consultar si no tiene el largo exacto: una firma
        // corta no puede existir, y así una URL manipulada no llega a la base.
        if (strlen($normalizada) !== Carnet::LARGO_FIRMA) {
            return null;
        }

        return Carnet::query()
            ->with([
                'beneficiario:id,ci_nit,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                'habilitaciones.rubro:id,nombre',
            ])
            ->where('firma_validacion', $normalizada)
            ->first();
    }

    /**
     * Lo único que se le muestra a un desconocido.
     *
     * @return array<string, mixed>
     */
    private function datosPublicos(Carnet $carnet): array
    {
        $beneficiario = $carnet->beneficiario;
        $vigente = $carnet->estaVigente();

        return [
            /*
             * EL REGISTRO, NO LA FIRMA.
             *
             * Esta pantalla existe para que el inspector compare lo que ve en el
             * teléfono contra el plástico que tiene en la mano, y en el plástico
             * está impreso el registro: la firma va únicamente dentro del QR.
             * Mostrar la firma acá sería mostrar un dato que no figura en ningún
             * lado de la credencial, o sea nada que se pueda contrastar.
             *
             * Y el registro no agrega exposición: quien llegó a esta pantalla ya
             * tuvo el carnet en la mano para escanearlo.
             */
            'registro' => $carnet->registro(),
            'titular' => $beneficiario?->nombreCompleto,

            /*
             * La cédula va ENMASCARADA: solo los últimos tres dígitos.
             *
             * Alcanza para que el inspector confirme contra el documento que la
             * persona le está mostrando, y no alcanza para que alguien que
             * encuentre un carnet tirado se haga con el número completo.
             */
            'documento_titular' => $this->enmascarar((string) $beneficiario?->ci_nit),

            'gestion' => $carnet->gestion,
            'fecha_emision' => $carnet->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $carnet->fecha_vencimiento?->toDateString(),

            'estado' => $carnet->estado->value,
            'estado_etiqueta' => $carnet->estado->etiqueta(),
            'estado_color' => $carnet->estado->color(),

            /*
             * `vigente` NO es lo mismo que estado === 'vigente'.
             *
             * Carnet::estaVigente() mira además la fecha, porque el estado lo
             * escribe un comando programado y entre corrida y corrida un carnet
             * vencido ayer sigue diciendo «vigente» en la columna. Acá eso
             * importaría de verdad: sería habilitar a alguien con un documento
             * caído.
             */
            'vigente' => $vigente,

            'mensaje' => $this->mensajePublico($carnet, $vigente),

            /*
             * SOLO LOS RUBROS HABILITADOS, sin los suspendidos.
             *
             * Un rubro suspendido no autoriza a trabajar, así que mostrarlo en
             * la lista —aunque fuera marcado en rojo— arriesga que el inspector
             * lea la fila y no el color. Lo que no habilita, no aparece.
             */
            'rubros' => $vigente
                ? $carnet->rubrosHabilitados()->pluck('nombre')->all()
                : [],
        ];
    }

    private function mensajePublico(Carnet $carnet, bool $vigente): string
    {
        if ($vigente) {
            return 'Carnet auténtico y vigente, emitido por la Gobernación del Beni.';
        }

        return match ($carnet->estado->value) {
            'anulado' => 'Este carnet fue ANULADO por la autoridad competente y no habilita ninguna actividad.',
            default => sprintf(
                'Este carnet venció el %s. Corresponde tramitar el de la gestión en curso.',
                $carnet->fecha_vencimiento?->format('d/m/Y') ?? '—',
            ),
        };
    }

    private function enmascarar(string $ci): string
    {
        $visibles = 3;

        return str_repeat('•', max(0, mb_strlen($ci) - $visibles)).mb_substr($ci, -$visibles);
    }
}
