<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\Codigo;
use App\Models\Configuracion;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  VERIFICACIÓN PÚBLICA DE DOCUMENTOS — la única pantalla sin sesión
 *
 *  Desde el 22/09/2026 atiende los CINCO documentos que se entregan, no solo el
 *  carnet: el código sale de la tabla `codigos` y de ahí se resuelve qué es.
 */
class VerificacionController extends Controller
{
    /**
     * Largo mínimo aceptado antes de ir a la base.
     */
    private const LARGO_MINIMO = 6;

    public function show(?string $codigo = null): Response
    {
        $normalizado = $codigo === null ? null : Carnet::normalizarCodigo($codigo);
        $documento = $normalizado === null ? null : $this->buscar($normalizado);

        return Inertia::render('publico/verificar', [
            // Se devuelve lo que se buscó: es lo que la pantalla muestra en el
            // acta de «no encontrado».
            'codigo' => $normalizado,
            'documento' => $documento === null ? null : $this->datosPublicos($documento),

            // null = todavía no se buscó nada (se entró a /verificar a secas).
            // false = se buscó y no apareció. La pantalla dice cosas distintas
            // en cada caso, y con un solo booleano no se podrían distinguir.
            'encontrado' => $normalizado === null ? null : $documento !== null,

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
    public function buscarPorFormulario(Request $request): RedirectResponse
    {
        $request->merge([
            'codigo' => Carnet::normalizarCodigo((string) $request->input('codigo')),
        ]);

        $validado = $request->validate([
            'codigo' => ['required', 'string', 'alpha_num', 'min:'.self::LARGO_MINIMO, 'max:40'],
        ], [
            'codigo.required' => 'Ingrese el código impreso en el documento.',
            'codigo.min' => 'El código tiene al menos '.self::LARGO_MINIMO.' caracteres.',
            'codigo.alpha_num' => 'El código solo lleva letras y números.',
        ]);

        return redirect()->route('verificar.show', $validado['codigo']);
    }

    /**
     * UNA sola consulta: el código identifica al documento, sea del tipo que
     * sea. Es lo que hace la tabla polimórfica, y por eso el código no lleva
     * prefijo.
     */
    private function buscar(string $codigo): ?Model
    {
        if (strlen($codigo) < self::LARGO_MINIMO) {
            return null;
        }

        $fila = Codigo::query()
            /*
             * `codigable` va con `withTrashed()` —ver App\Models\Codigo—: un
             * documento anulado tiene que contestar «fue anulado», no
             * «no existe».
             */
            ->with('codigable')
            ->where('codigo', $codigo)
            ->first();

        return $fila?->codigable;
    }

    /**
     *  LO ÚNICO QUE SE LE MUESTRA A UN DESCONOCIDO
     *
     * Misma forma para los cinco: el acta pública dibuja renglones, no campos
     * con nombre propio. Así sumar un documento nuevo no toca React.
     *
     * @return array<string, mixed>
     */
    private function datosPublicos(Model $documento): array
    {
        $beneficiario = $this->titularDe($documento);

        return [
            'tipo_etiqueta' => $this->etiquetaDe($documento),

            // En grupos de cuatro, igual que va impreso: es lo que se compara
            // contra el papel que se tiene en la mano.
            'codigo' => $documento->codigo_legible,

            'titular' => $beneficiario?->nombreCompleto,

            // La cédula va ENMASCARADA: solo los últimos tres dígitos.
            'documento_titular' => $this->enmascarar((string) $beneficiario?->ci),

            'estado_etiqueta' => $documento->estado?->etiqueta() ?? 'Emitido',
            'estado_color' => $documento->estado?->color() ?? 'neutro',

            'vigente' => $this->estaVigente($documento),
            'mensaje' => $this->mensaje($documento),
            'renglones' => $this->renglones($documento),
        ];
    }

    /**
     * Qué documento es, dicho para alguien de afuera.
     */
    private function etiquetaDe(Model $documento): string
    {
        return match (true) {
            $documento instanceof Carnet => 'Carnet de '.$documento->tipo_actor->etiqueta(),
            $documento instanceof AprovechamientoPesq => 'Autorización de Aprovechamiento Pesquero',
            $documento instanceof PermisoFaena => 'Permiso de Faena',
            $documento instanceof GuiaMovimiento => 'Guía Única de Transporte',
            $documento instanceof Recibo => 'Recibo Oficial',
            default => 'Documento',
        };
    }

    /**
     * De quién es. La faena y la guía cuelgan del carnet, no del beneficiario.
     */
    private function titularDe(Model $documento): mixed
    {
        return match (true) {
            $documento instanceof PermisoFaena,
            $documento instanceof GuiaMovimiento => $documento->carnet?->beneficiario,
            default => $documento->beneficiario ?? null,
        };
    }

    /**
     * ¿Habilita algo HOY?
     *
     * Se pregunta al modelo y no al estado guardado: `vencido` lo escribe un
     * comando que corre una vez al día, así que la columna puede mentir.
     */
    private function estaVigente(Model $documento): bool
    {
        return match (true) {
            $documento instanceof Carnet,
            $documento instanceof AprovechamientoPesq,
            $documento instanceof PermisoFaena,
            $documento instanceof GuiaMovimiento => $documento->estaVigente(),

            // El recibo no habilita nada: es el comprobante de que se cobró, y
            // eso no vence.
            default => true,
        };
    }

    /**
     * La frase que lee el inspector.
     */
    private function mensaje(Model $documento): string
    {
        $que = $this->etiquetaDe($documento);

        if ($documento instanceof Recibo) {
            return "Este {$que} fue emitido por la Gobernación del Beni y consta en el registro electrónico.";
        }

        if ($this->estaVigente($documento)) {
            return "Documento auténtico y vigente: {$que} emitido por la Gobernación del Beni.";
        }

        return 'Este documento existe en el registro, pero NO está vigente: figura como '.
            mb_strtolower((string) $documento->estado?->etiqueta()).'. No habilita ninguna actividad.';
    }

    /**
     * Los renglones del acta, distintos para cada documento.
     *
     * Criterio para sumar uno: **que el inspector pueda contrastarlo contra el
     * papel que tiene en la mano**. Nada de ids internos ni de datos
     * personales completos. Ver la regla 3 de CLAUDE.md.
     *
     * @return array<int, array<string, mixed>>
     */
    private function renglones(Model $documento): array
    {
        $vigente = $this->estaVigente($documento);

        return match (true) {
            $documento instanceof Carnet => array_values(array_filter([
                $this->renglon('Actividad', $vigente ? $documento->tipo_actor->etiqueta() : null),
                $this->renglon('Registro', $documento->registro_legible),
                $this->renglon('Gestión', $documento->gestion),
                $this->renglon('Emitido', $documento->fecha_emision?->format('d/m/Y')),
                $this->renglon('Vence', $documento->fecha_vencimiento?->format('d/m/Y')),
                $this->renglon('Cupo autorizado', $vigente && $documento->cupoImpreso()
                    ? $documento->cupoImpreso().' kg' : null),
            ])),

            $documento instanceof AprovechamientoPesq => array_values(array_filter([
                $this->renglon('Volumen autorizado', $documento->volumen_total_kg.' kg'),
                $this->renglon('Modalidad', $documento->modalidad?->etiqueta()),
                $this->renglon('Emitido', $documento->fecha_emision?->format('d/m/Y')),
                $this->renglon('Vence', $documento->fecha_vencimiento?->format('d/m/Y')),
            ])),

            $documento instanceof PermisoFaena => array_values(array_filter([
                $this->renglon('Nº de faena', str_pad((string) $documento->numero_faena, 6, '0', STR_PAD_LEFT), true),
                $this->renglon('Kilos autorizados', $documento->kilos_extraidos.' kg'),
                $this->renglon('Salida', $documento->fecha_salida?->format('d/m/Y')),
                $this->renglon('Válido hasta', $documento->fecha_limite?->format('d/m/Y')),
            ])),

            $documento instanceof GuiaMovimiento => array_values(array_filter([
                $this->renglon('Nº de guía', $documento->numero_legible, true),
                $this->renglon('Origen', $documento->origen),
                $this->renglon('Destino', $documento->destino),
                $this->renglon('Peso amparado', $documento->peso_total_kg.' kg'),
                $this->renglon('Válida hasta', $documento->fecha_vencimiento?->format('d/m/Y H:i')),
            ])),

            $documento instanceof Recibo => array_values(array_filter([
                $this->renglon('Nº de recibo', $documento->numero_recibo, true),
                $this->renglon('Concepto', $documento->concepto),
                $this->renglon('Emitido', $documento->created_at?->format('d/m/Y')),
            ])),

            default => [],
        };
    }

    /**
     * Un renglón, o null si no hay qué mostrar — el `array_filter` lo saca.
     *
     * @return array<string, mixed>|null
     */
    private function renglon(string $etiqueta, mixed $valor, bool $mono = false): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return ['etiqueta' => $etiqueta, 'valor' => (string) $valor, 'mono' => $mono];
    }

    private function enmascarar(string $ci): string
    {
        $visibles = 3;

        return str_repeat('•', max(0, mb_strlen($ci) - $visibles)).mb_substr($ci, -$visibles);
    }
}
