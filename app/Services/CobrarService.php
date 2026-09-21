<?php

namespace App\Services;

use App\Exceptions\CobroInvalidoException;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\Pago;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 *  CAJA — cobrar uno o varios trámites bajo UN recibo
 */
class CobrarService
{
    /**
     * La serie del correlativo de caja.
     */
    public const SERIE = 'REC';

    /**
     * Qué se puede cobrar, y cómo lo nombra el formulario.
     *
     * @var array<string, class-string<Model>>
     */
    public const COBRABLES = [
        'carnet' => Carnet::class,
        'cupo' => AprovechamientoPesq::class,
        'guia' => GuiaMovimiento::class,
    ];

    public function __construct(private readonly CorrelativoService $correlativos) {}

    /**
     * Emite un recibo y sus abonos.
     *
     * @param  array<int, array{tipo: string, id: int, monto: float}>  $lineas
     */
    public function cobrar(
        array $lineas,
        string $nroTransaccion,
        string $fechaDeposito,
        string $comprobante,
        ?string $concepto = null,
    ): Recibo {
        if ($lineas === []) {
            throw CobroInvalidoException::sinLineas();
        }

        return DB::transaction(function () use ($lineas, $concepto, $nroTransaccion, $fechaDeposito, $comprobante): Recibo {
            $resueltas = [];

            foreach ($lineas as $linea) {
                $resueltas[] = $this->resolver($linea);
            }

            /*
             * EL RECIBO SALE A NOMBRE DEL TITULAR DE LO COBRADO, y por eso los
             * trámites tienen que ser todos de la MISMA persona: un papel a
             * nombre de dos no existe. Antes el nombre venía tipeado del
             * formulario y nada lo ataba a lo que se estaba cobrando.
             */
            $beneficiarioId = $this->titularDe($resueltas);

            /*
             * EL NÚMERO SE RESERVA DENTRO DE LA MISMA TRANSACCIÓN.
             */
            $recibo = Recibo::create([
                'beneficiario_id' => $beneficiarioId,
                'numero_recibo' => $this->correlativos->siguiente(self::SERIE),
                'concepto' => $concepto ?: $this->conceptoAutomatico($resueltas),
            ]);

            $orden = 1;

            foreach ($resueltas as ['tramite' => $tramite, 'monto' => $monto]) {
                Pago::create([
                    'recibo_id' => $recibo->id,
                    'registrado_por' => Auth::id(),
                    'pagable_type' => $tramite->getMorphClass(),
                    'pagable_id' => $tramite->getKey(),
                    'monto_parcial' => $monto,
                    'fecha_deposito' => $fechaDeposito,

                    /*
                     * LA BOLETA SE REPITE EN CADA LÍNEA DEL MISMO COBRO, y es
                     * correcto: un solo depósito puede cubrir el carnet y el
                     * cupo a la vez, y las dos filas están respaldadas por ese
                     * mismo papel.
                     */
                    'nro_transaccion' => $orden === 1 ? $nroTransaccion : $nroTransaccion.'-'.$orden,
                    'comprobante' => $comprobante,
                ]);

                $orden++;
            }

            /*
             * El total se CONGELA acá, con los abonos que acaban de entrar.
             */
            return $recibo->recalcularTotal();
        });
    }

    /**
     * Carga depósitos SIN emitir recibo — el circuito del aprovechamiento.
     *
     * @param  array<int, array{monto: float|string, nro_transaccion: string, fecha_deposito: string, comprobante: string}>  $depositos
     * @return int cuántos se cargaron
     */
    public function registrarDepositos(Model $tramite, array $depositos): int
    {
        if ($depositos === []) {
            throw CobroInvalidoException::sinLineas();
        }

        return DB::transaction(function () use ($tramite, $depositos): int {
            // Igual que en `resolver()`: dos ventanillas a la vez leerían el
            // mismo saldo y las dos pasarían el control.
            $bloqueado = $tramite->newQuery()
                ->whereKey($tramite->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nombre = $this->nombrar($bloqueado);

            // Al MODELO y no al enum: `EstadoAprovechamiento` lo llama
            // `permitePagos()`, así que un method_exists sobre el enum deja
            // pasar cualquier estado sin decir nada.
            if (method_exists($bloqueado, 'admitePagos') && ! $bloqueado->admitePagos()) {
                throw CobroInvalidoException::noAdmiteDepositos(
                    $nombre,
                    $bloqueado->estado->etiqueta(),
                );
            }

            $saldo = $bloqueado->saldoPendiente();

            if ($saldo <= 0.0) {
                throw CobroInvalidoException::yaEstaPagado($nombre);
            }

            /*
             * LOS DEPÓSITOS TIENEN QUE CUBRIR EL SALDO ENTERO, y se miran como
             * CONJUNTO: se admiten varias boletas —la persona deposita en dos
             * veces— pero entran todas juntas y en una sola carga. Un parcial
             * guardado dejaba el expediente a medio cobrar, sin recibo y sin
             * poder enviarse, y el saldo solo se descubría abriendo la ficha.
             *
             * DE MÁS SÍ SE ADMITE, al revés que en Caja: la boleta del banco
             * dice lo que dice y el excedente queda a favor de la entidad. Con
             * el tope puesto, un depósito de 170 por un trámite de 165 no se
             * podía cargar y el expediente quedaba trabado con la plata ya
             * depositada.
             */
            $suma = round(array_sum(array_map(
                static fn (array $d): float => round((float) $d['monto'], 2),
                $depositos,
            )), 2);

            if ($suma < $saldo) {
                throw CobroInvalidoException::noCubreElMonto($nombre, $suma, $saldo);
            }

            foreach ($depositos as $deposito) {
                $monto = round((float) $deposito['monto'], 2);

                Pago::create([
                    // NULL a propósito: el recibo del trámite todavía no existe.
                    'recibo_id' => null,
                    'registrado_por' => Auth::id(),
                    'pagable_type' => $bloqueado->getMorphClass(),
                    'pagable_id' => $bloqueado->getKey(),
                    'monto_parcial' => $monto,
                    'nro_transaccion' => $deposito['nro_transaccion'],
                    'fecha_deposito' => $deposito['fecha_deposito'],
                    'comprobante' => $deposito['comprobante'],
                ]);
            }

            return count($depositos);
        });
    }

    /**
     * Emite el recibo del trámite: uno solo, con todos sus depósitos sueltos.
     *
     * Devuelve NULL si no había ninguno, y eso es lo que hace que un REENVÍO no
     * emita un segundo papel: el número que la persona tiene sigue valiendo.
     */
    public function emitirRecibo(
        Model $tramite,
        ?string $concepto = null,
    ): ?Recibo {
        return DB::transaction(function () use ($tramite, $concepto): ?Recibo {
            $sueltos = Pago::query()
                ->where('pagable_type', $tramite->getMorphClass())
                ->where('pagable_id', $tramite->getKey())
                ->whereNull('recibo_id')
                ->lockForUpdate()
                ->get();

            if ($sueltos->isEmpty()) {
                return null;
            }

            $recibo = Recibo::create([
                // Sale a nombre del titular del trámite, no de quien lo tipeó.
                'beneficiario_id' => $tramite->beneficiario_id,
                'numero_recibo' => $this->correlativos->siguiente(self::SERIE),
                'concepto' => $concepto ?: $this->nombrar($tramite),
            ]);

            // De a uno y no con un update() masivo: el builder no dispara
            // eventos, así que Auditable no registraría nada.
            foreach ($sueltos as $pago) {
                $pago->update(['recibo_id' => $recibo->id]);
            }

            // El total se congela con los depósitos que acaban de quedar
            // amparados. Ver el comentario de `cobrar()`.
            return $recibo->recalcularTotal();
        });
    }

    //  Auxiliares

    /**
     * El titular de todo lo que se está cobrando, o revienta si son varios.
     *
     * @param  array<int, array{tramite: Model, monto: float}>  $resueltas
     */
    private function titularDe(array $resueltas): int
    {
        $titulares = array_unique(array_map(
            fn (array $r): int => (int) $r['tramite']->beneficiario_id,
            $resueltas,
        ));

        if (count($titulares) > 1) {
            throw CobroInvalidoException::variosTitulares();
        }

        return (int) reset($titulares);
    }

    /**
     * Convierte una línea del formulario en un trámite real, comprobado.
     *
     * @param  array{tipo: string, id: int, monto: float}  $linea
     * @return array{tramite: Model, monto: float}
     */
    private function resolver(array $linea): array
    {
        $clase = self::COBRABLES[$linea['tipo']] ?? null;

        if ($clase === null) {
            throw CobroInvalidoException::tipoDesconocido($linea['tipo']);
        }

        /** @var Model $tramite */
        $tramite = $clase::query()->whereKey($linea['id'])->lockForUpdate()->firstOrFail();

        $nombre = $this->nombrar($tramite);

        /*
         * `admitePagos()` solo lo tienen los estados que pueden decir que no
         * —hoy, la guía anulada—. Los otros no declaran el método, así que se
         * pregunta con method_exists en vez de obligar a los tres enums a
         * tenerlo por simetría.
         */
        $estado = $tramite->estado;

        if (method_exists($estado, 'admitePagos') && ! $estado->admitePagos()) {
            throw CobroInvalidoException::noAdmitePagos($nombre);
        }

        $saldo = $tramite->saldoPendiente();

        if ($saldo <= 0.0) {
            throw CobroInvalidoException::yaEstaPagado($nombre);
        }

        $monto = round((float) $linea['monto'], 2);

        if ($monto > $saldo) {
            throw CobroInvalidoException::excedeElSaldo($nombre, $monto, $saldo);
        }

        return ['tramite' => $tramite, 'monto' => $monto];
    }

    /**
     *  COBRAR YA NO ACTIVA NADA, Y ES DELIBERADO
     */

    /**
     * Cómo se nombra un trámite en el recibo y en los mensajes de error.
     */
    private function nombrar(Model $tramite): string
    {
        return match (true) {
            /*
             * «Cédula de Pescador», no «Carnet»: es como lo llama el talonario
             * —la casilla marcada en el recibo dice CÉDULAS— y es lo que la
             * persona reconoce.
             *
             * SIN EL CÓDIGO: son 16 caracteres que llenan el renglón y que el
             * pescador no tiene cómo contrastar en el mostrador. Y con la
             * CAPACIDAD cuando la lleva —la del cupo que respalda la cédula—,
             * porque es lo que distingue una de otra; el comercializador no
             * lleva volumen, así que ahí no va nada.
             */
            $tramite instanceof Carnet => 'Cédula de '.$tramite->tipo_actor->etiqueta()
                .$this->tramoDe($tramite->aprovechamiento),
            /*
             * EL NOMBRE COMPLETO Y LA CAPACIDAD. Decía «Aprovechamiento escala
             * 3»: el número del tramo es del catálogo interno y no le dice
             * nada a quien recibe el papel; lo que identifica lo cobrado es el
             * documento y cuántos kilos autoriza.
             */
            $tramite instanceof AprovechamientoPesq => 'Autorización de Pesca para Aprovechamiento '
                .'Pesquero'.$this->tramoDe($tramite),
            $tramite instanceof GuiaMovimiento => 'Guía '.$tramite->codigo_guia,
            default => 'Trámite',
        };
    }

    /**
     * El tramo de la escala, tal como se escribe en el recibo: « - 201 Kg
     * Hasta 300 Kg».
     *
     * Devuelve cadena VACÍA si no hay cupo o no tiene tramo: así el concepto
     * de un comercializador —que no lleva volumen— no queda con un guion
     * colgando al final.
     */
    private function tramoDe(?AprovechamientoPesq $cupo): string
    {
        $tramo = $cupo?->categoria?->descripcion_kg;

        return $tramo ? ' - '.$tramo : '';
    }

    /**
     * El texto que se imprime cuando el operador no escribe uno.
     *
     * @param  array<int, array{tramite: Model, monto: float}>  $resueltas
     */
    private function conceptoAutomatico(array $resueltas): string
    {
        return implode(' · ', array_map(
            fn (array $r): string => $this->nombrar($r['tramite']),
            $resueltas,
        ));
    }
}
