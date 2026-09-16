<?php

namespace Database\Seeders;

use App\Models\Beneficiario;
use Illuminate\Database\Seeder;

/**
 * ============================================================================
 *  DATOS DE PRUEBA — no debe correr en producción
 * ============================================================================
 *
 * Siembra ÚNICAMENTE el padrón: fichas de beneficiarios. Nada de carnets,
 * trámites, pagos ni recibos.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ SOLO PERSONAS
 * ----------------------------------------------------------------------------
 *
 * Este seeder llegó a fabricar también doce carnets con sus trámites, pagos y
 * habilitaciones, siguiendo un guion de casos. Se sacó todo eso y conviene
 * entender por qué, para no volver a agregarlo sin pensarlo:
 *
 *   1. ESCRIBÍA EL RESULTADO DE LAS REGLAS A MANO. No pasaba por
 *      SolicitudCarnetService —que exige `UploadedFile` de verdad—, así que
 *      copiaba lo que el servicio hace: qué tipo de trámite corresponde, cuándo
 *      nace la fila en `carnet_rubro`, cómo se suman los pagos. Una copia de
 *      reglas de negocio en un archivo que nadie mira al cambiarlas, y las
 *      copias se quedan viejas.
 *
 *   2. FABRICABA DINERO Y DOCUMENTOS QUE NO EXISTEN. Los pagos venían con
 *      números de transacción inventados y rutas de boletas que no apuntan a
 *      ningún archivo —`pagos/demo/boleta-{uuid}.pdf`—, así que cada enlace
 *      «Ver boleta» daba 404. Cuarenta y siete rutas así había en la base.
 *
 *   3. EL CIRCUITO SE PRUEBA MEJOR RECORRIÉNDOLO. Cargar una solicitud desde la
 *      pantalla, cobrarla, tomarla para revisión y aprobarla toma dos minutos, y
 *      pasa por las reglas de verdad —incluida la que exige los papeles
 *      completos antes de revisar—. Un expediente sembrado a mano se salta
 *      justamente lo que hay que probar.
 *
 * Lo que sí hace falta sembrado es el PADRÓN: tipear treinta personas a mano
 * para probar el buscador, la paginación o el orden alfabético no prueba nada y
 * cuesta una tarde.
 *
 * ----------------------------------------------------------------------------
 *  LAS FICHAS NO TRAEN FOTOGRAFÍA
 * ----------------------------------------------------------------------------
 *
 * `BeneficiarioFactory` deja `foto` en NULL, y es correcto: una foto de prueba
 * sería un archivo falso más en `storage/app/public` que nadie limpia.
 *
 * Consecuencia a tener presente: un trámite cargado sobre una de estas fichas
 * **no va a poder pasar a EN REVISIÓN** hasta que se le suba una fotografía
 * desde la ficha del beneficiario. No es una falla del seeder — es la regla de
 * `Tramite::faltantesParaRevision()` haciendo su trabajo, y probarla es parte de
 * recorrer el circuito.
 */
class DemoSeeder extends Seeder
{
    /**
     * Cuántas fichas se siembran.
     *
     * Treinta alcanza para ver el padrón con varias páginas —el listado muestra
     * quince por defecto— sin que buscar un caso concreto se vuelva incómodo.
     */
    private const FICHAS = 30;

    /**
     * Cuántas de esas llevan apellido de casada.
     *
     * No se dejan al azar: el apellido de casada cambia cómo se arma
     * `nombreCompleto` —le agrega el «de»— y hay que poder ver esa variante en
     * pantalla sin depender de la suerte. Ver Beneficiario::nombreCompleto().
     */
    private const CASADAS = 4;

    public function run(): void
    {
        Beneficiario::factory(self::FICHAS - self::CASADAS)->create();
        Beneficiario::factory(self::CASADAS)->casada()->create();

        $this->command?->info(sprintf(
            'Demo: %d beneficiarios sembrados. Los carnets y trámites se cargan desde el panel.',
            Beneficiario::count(),
        ));
    }
}
