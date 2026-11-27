<?php

namespace Tests\Concerns;

use App\Enums\EstadoCarnet;
use App\Enums\OrigenTramite;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\User;
use App\Services\CuentaPortalService;

/**
 * Un pescador con su cuenta del portal (clave ya cambiada), su Autorización de Pesca
 * y su carnet, como los deja ventanilla. Para las pruebas de los trámites en línea.
 */
trait PescadorDePrueba
{
    /** Los siete renglones del talonario «PERMISO POR FAENA». */
    protected const TALONARIO = [
        'embarcacion' => 'Canoa San José',
        'propietario' => 'Ignacio Molina Guzman',
        'comandante_barco' => 'Ignacio Molina Guzman',
        'matricula_naval' => 'MN-4585',
        'nro_kardex' => '456',
        'region_desde' => 'Loma Suárez',
        'region_hasta' => 'Boca del Mamoré',
    ];

    protected function beneficiarioConCuenta(string $ci = '7633685', string $nombre = 'Ignacio'): User
    {
        $persona = Beneficiario::create([
            'ci' => $ci,
            'primerNombre' => $nombre,
            'apellidoPaterno' => 'Molina',
            'apellidoMaterno' => 'Guzman',
            'fechaNacimiento' => '1997-03-08',
        ]);
        $servicio = app(CuentaPortalService::class);
        $servicio->darAcceso($persona);
        $cuenta = $persona->cuenta()->firstOrFail();
        $servicio->cambiarClave($cuenta, 'pesca2026');

        return $cuenta;
    }

    protected function autorizacionDe(Beneficiario $b, string $estado = 'aprobado', float $kilos = 1000): AprovechamientoPesq
    {
        static $nro = 0;

        return AprovechamientoPesq::query()->create([
            'origen_tramite' => OrigenTramite::Ventanilla,
            'beneficiario_id' => $b->id, 'nro' => ++$nro, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => $kilos, 'kilos_disponibles' => $kilos, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => $estado, 'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);
    }

    protected function carnetDe(Beneficiario $b, string $actor, ?AprovechamientoPesq $cupo = null): Carnet
    {
        static $nro = 0;
        $asociacion = Asociacion::query()->firstOrCreate(['nombre' => 'Asociación de prueba'], ['sigla' => 'ADP']);

        return Carnet::query()->create([
            'origen_tramite' => OrigenTramite::Ventanilla,
            'beneficiario_id' => $b->id, 'asociacion_id' => $asociacion->id, 'aprovechamiento_id' => $cupo?->id,
            'tipo_actor' => $actor, 'monto' => 15, 'estado' => EstadoCarnet::Aprobado, 'nro' => ++$nro,
            'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);
    }

    /** Pescador listo para pedir faenas: autorización aprobada y carnet de pescador colgado de ella. */
    protected function pescadorListo(Beneficiario $b, float $kilos = 1000): Carnet
    {
        return $this->carnetDe($b, 'pescador', $this->autorizacionDe($b, kilos: $kilos));
    }
}
