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
 *  VERIFICACIÓN PÚBLICA DE CARNETS — la única pantalla sin sesión
 */
class VerificacionController extends Controller
{
    /**
     * Largo mínimo aceptado antes de ir a la base.
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
             */
            'vigente' => $vigente,

            'mensaje' => $this->mensajePublico($carnet, $vigente),

            /*
             * LA ACTIVIDAD QUE ESTE CARNET AUTORIZA, Y SU CUPO.
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
