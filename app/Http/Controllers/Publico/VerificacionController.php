<?php

namespace App\Http\Controllers\Publico;

use App\Enums\EstadoCarnet;
use App\Http\Controllers\Controller;
use App\Models\Carnet;
use App\Models\Configuracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  VERIFICACIÓN PÚBLICA DE CARNETS — la única pantalla sin sesión
 * ============================================================================
 *
 * Un pescador muestra su carnet, el inspector lee el código con el teléfono y
 * cae en esta pantalla, que le dice si el documento es real, si está vigente y
 * qué actividad autoriza. Por eso NO puede pedir login.
 *
 * ----------------------------------------------------------------------------
 *  HACE FALTA UN SOLO DATO: EL CÓDIGO DEL CARNET
 * ----------------------------------------------------------------------------
 *
 * `carnets.codigo_carnet` es único GLOBAL —no por tipo— justamente para esto:
 * un control en ruta lee un código y tiene que llegar a UN documento, sin
 * preguntar antes de qué tipo es.
 *
 * LO QUE ESTO CUESTA, Y HAY QUE TENERLO PRESENTE: el código va IMPRESO en el
 * plástico, así que quien tenga el carnet en la mano —o una foto— puede
 * consultarlo. Se aceptó porque lo que se muestra acá es deliberadamente poco:
 * nombre, cédula enmascarada, actividad y vigencia. Nada que no esté ya en la
 * tarjeta que esa persona está mirando.
 *
 * Lo que sí protege del barrido automático es el `throttle` de la ruta. Un
 * código corto y predecible sería adivinable, así que al generarlo conviene que
 * lleve una parte al azar; eso es responsabilidad del módulo de carnets.
 *
 * ----------------------------------------------------------------------------
 *  REGLA DE ORO DE ESTA PARTE DEL SISTEMA
 * ----------------------------------------------------------------------------
 *
 * Acá solo puede aparecer lo mínimo para constatar que un carnet es auténtico.
 * Nunca la cédula completa, ni la dirección, ni el teléfono. Cada campo que se
 * agregue queda expuesto a cualquiera. Ver datosPublicos().
 */
class VerificacionController extends Controller
{
    /**
     * Largo mínimo aceptado antes de ir a la base.
     *
     * No es una regla de negocio sino un filtro barato: un código de dos letras
     * no puede existir, y cortando acá una URL manipulada ni siquiera llega a
     * consultar.
     */
    private const LARGO_MINIMO = 6;

    public function show(?string $codigo = null): Response
    {
        $carnet = $codigo ? $this->buscarCarnet($codigo) : null;

        return Inertia::render('publico/verificar', [
            // Se devuelve lo que se buscó: es lo que la pantalla muestra en el
            // acta de «no encontrado».
            'codigo' => $codigo ? Carnet::normalizarCodigo($codigo) : null,
            'carnet' => $carnet ? $this->datosPublicos($carnet) : null,

            // null = todavía no se buscó nada (se entró a /verificar a secas).
            // false = se buscó y no apareció. La pantalla dice cosas distintas
            // en cada caso, y con un solo booleano no se podrían distinguir.
            'encontrado' => $codigo === null ? null : $carnet !== null,

            'institucion' => [
                'municipio' => Configuracion::obtener('municipio.nombre'),
                'sistema' => Configuracion::obtener('sistema.nombre', 'Jichi'),
                'pie_legal' => Configuracion::obtener('documentos.pie_legal'),
            ],
        ]);
    }

    /**
     * El código escrito a mano, cuando el QR no se deja escanear.
     *
     * Se normaliza ANTES de validar, y eso no es un detalle: el código se
     * imprime en grupos de cuatro y la gente lo copia con los espacios. Sin
     * normalizar primero, la validación rechazaría lo que el operador ve
     * escrito en la tarjeta.
     */
    public function buscar(Request $request): RedirectResponse
    {
        $request->merge([
            'codigo' => Carnet::normalizarCodigo((string) $request->input('codigo')),
        ]);

        $validado = $request->validate([
            'codigo' => ['required', 'string', 'alpha_num', 'min:'.self::LARGO_MINIMO, 'max:40'],
        ], [
            'codigo.required' => 'Ingrese el código impreso en el carnet.',
            'codigo.min' => 'El código tiene al menos '.self::LARGO_MINIMO.' caracteres.',
            'codigo.alpha_num' => 'El código solo lleva letras y números.',
        ]);

        return redirect()->route('verificar.show', $validado['codigo']);
    }

    /**
     * Busca el carnet por su código.
     *
     * La comparación la hace el ÍNDICE ÚNICO de la base, que responde en el
     * mismo tiempo encuentre o no. Comparar en PHP obligaría a traer filas y a
     * cuidarse del ataque por tiempo —una comparación normal corta en el primer
     * carácter distinto—; acá no hay nada que filtrar.
     */
    private function buscarCarnet(string $codigo): ?Carnet
    {
        $normalizado = Carnet::normalizarCodigo($codigo);

        if (strlen($normalizado) < self::LARGO_MINIMO) {
            return null;
        }

        return Carnet::query()
            /*
             * OJO CON PEDIR COLUMNAS SUELTAS: el beneficiario va con las CINCO
             * partes del nombre porque `nombreCompleto` las lee todas, más `ci`
             * para poder enmascararla. Una columna que el modelo consulta y no
             * está en el select vuelve null y el accesor contesta cualquier
             * cosa, sin ningún error.
             */
            ->with([
                'beneficiario:id,ci,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                'tipoCarnet:id,nombre',
                'aprovechamiento',
            ])
            ->where('codigo_carnet', $normalizado)
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
            // En grupos de cuatro, igual que va impreso: es lo que el inspector
            // compara contra el plástico que tiene en la mano.
            'codigo' => $carnet->codigo_legible,
            'titular' => $beneficiario?->nombreCompleto,

            /*
             * La cédula va ENMASCARADA: solo los últimos tres dígitos.
             *
             * Alcanza para que el inspector confirme contra el documento que la
             * persona le está mostrando, y no alcanza para que alguien que
             * encuentre un carnet tirado se haga con el número completo.
             */
            'documento_titular' => $this->enmascarar((string) $beneficiario?->ci),

            // La gestión se DERIVA de la fecha de emisión y no se guarda: el
            // carnet ya no tiene columna `gestion`, y dos datos que dicen lo
            // mismo terminan contradiciéndose.
            'gestion' => (int) $carnet->fecha_emision?->format('Y'),

            'fecha_emision' => $carnet->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $carnet->fecha_vencimiento?->toDateString(),

            'estado' => $carnet->estado->value,
            'estado_etiqueta' => $carnet->estado->etiqueta(),
            'estado_color' => $carnet->estado->color(),

            /*
             * `vigente` NO es lo mismo que estado === 'activo'.
             *
             * Carnet::estaVigente() mira además la fecha, porque el estado lo
             * escribe un comando programado y entre corrida y corrida un carnet
             * vencido ayer sigue diciendo «activo» en la columna. Acá eso
             * importaría de verdad: sería habilitar a alguien con un documento
             * caído.
             */
            'vigente' => $vigente,

            'mensaje' => $this->mensajePublico($carnet, $vigente),

            /*
             * LA ACTIVIDAD QUE ESTE CARNET AUTORIZA, Y SU CUPO.
             *
             * Es UNA, no una lista: cada actividad es un carnet propio.
             *
             * SE MANDA NULL Y NO EL NOMBRE cuando el carnet no está vigente, y
             * no es un olvido: mostrar la actividad —aunque fuera marcada en
             * rojo— arriesga que el inspector lea la fila y no el color. Lo que
             * no habilita, no aparece.
             */
            'actividad' => $vigente ? $carnet->tipo_actor->etiqueta() : null,
            'tipo_carnet' => $vigente ? $carnet->tipoCarnet?->nombre : null,

            // Solo el pescador lleva cupo. Lo decide el enum, nunca el nombre
            // del tipo de carnet. Ver Carnet::cupoImpreso().
            'cupo_kg' => $vigente ? $carnet->cupoImpreso() : null,
        ];
    }

    private function mensajePublico(Carnet $carnet, bool $vigente): string
    {
        if ($vigente) {
            return 'Carnet auténtico y vigente, emitido por la Gobernación del Beni.';
        }

        return match ($carnet->estado) {
            EstadoCarnet::Revocado => 'Este carnet fue REVOCADO por la autoridad competente y no habilita ninguna actividad.',

            // Cubre `Vencido` y también `Activo` con la fecha ya pasada, que es
            // el caso en que la columna todavía no se actualizó.
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
