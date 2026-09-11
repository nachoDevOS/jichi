<?php

namespace Tests\Feature;

use App\Enums\CategoriaDocumento;
use App\Enums\EstadoDocumento;
use App\Enums\EstadoTramite;
use App\Enums\VigenciaTipo;
use App\Models\Documento;
use App\Models\Solicitante;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\User;
use App\Services\EmisionDocumentoService;
use Database\Seeders\AreaSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ============================================================================
 *  LAS REGLAS DEL TRÁMITE PESQUERO
 * ============================================================================
 *
 * Estas pruebas cuidan el orden que no se puede saltear:
 *
 *     solicitante → cédula de pescador vigente → faena / guía de transporte
 *
 * Son las reglas que se relevaron con el responsable del sistema, y están acá
 * porque son invisibles al leer el código: nada en un CRUD dice que un permiso
 * por faena no se le puede emitir a alguien sin cédula vigente. Si mañana
 * alguien saca esa condición «porque no hacía nada», estas pruebas lo frenan.
 *
 * La regla más fácil de romper sin darse cuenta es la de la GESTIÓN: la cédula
 * NO vale un año desde que se emite, vale hasta el 31 de diciembre. Quien la
 * saca en diciembre tiene unas semanas, no doce meses.
 */
class HabilitacionTramiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolPermisoSeeder::class, AreaSeeder::class, UsuarioSeeder::class]);
    }

    /*
    |--------------------------------------------------------------------------
    | La vigencia por gestión
    |--------------------------------------------------------------------------
    */

    /**
     * El caso que motivó todo: dos cédulas emitidas con once meses de
     * diferencia tienen que vencer EL MISMO DÍA.
     */
    public function test_la_cedula_vence_al_cerrar_la_gestion_sin_importar_el_mes(): void
    {
        $enero = VigenciaTipo::Gestion->calcularVencimiento(Carbon::parse('2026-01-05'));
        $diciembre = VigenciaTipo::Gestion->calcularVencimiento(Carbon::parse('2026-12-20'));

        $this->assertSame('2026-12-31', $enero->toDateString());
        $this->assertSame('2026-12-31', $diciembre->toDateString());
    }

    /**
     * Con la cuenta vieja —emisión + 365 días— una cédula sacada en diciembre
     * habría durado hasta diciembre del año siguiente. Casi un año de más
     * habilitando a pescar.
     */
    public function test_la_gestion_no_es_lo_mismo_que_un_año_desde_la_emision(): void
    {
        $emision = Carbon::parse('2026-12-20');

        $porGestion = VigenciaTipo::Gestion->calcularVencimiento($emision);
        $porDias = VigenciaTipo::Dias->calcularVencimiento($emision, 365);

        $this->assertSame('2026-12-31', $porGestion->toDateString());
        $this->assertSame('2027-12-20', $porDias->toDateString());
    }

    public function test_la_vigencia_por_dias_cuenta_desde_la_emision(): void
    {
        $vence = VigenciaTipo::Dias->calcularVencimiento(Carbon::parse('2026-03-10'), 30);

        $this->assertSame('2026-04-09', $vence->toDateString());
    }

    public function test_sin_vencimiento_no_devuelve_fecha(): void
    {
        $this->assertNull(
            VigenciaTipo::SinVencimiento->calcularVencimiento(Carbon::parse('2026-03-10'), 30),
        );
        $this->assertNull(
            VigenciaTipo::Dias->calcularVencimiento(Carbon::parse('2026-03-10'), null),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | El catálogo sembrado
    |--------------------------------------------------------------------------
    */

    public function test_la_cedula_de_pescador_esta_cargada_como_vigencia_por_gestion(): void
    {
        $cedula = $this->tipo('CAP');

        $this->assertSame(VigenciaTipo::Gestion, $cedula->vigencia_tipo);
        $this->assertSame(CategoriaDocumento::Credencial, $cedula->categoria_documento);

        // La cédula es la llave: no puede exigirse a sí misma.
        $this->assertFalse($cedula->requiere_credencial);
    }

    public function test_la_faena_dura_un_mes_y_es_de_un_solo_uso(): void
    {
        $faena = $this->tipo('PPF');

        $this->assertSame(VigenciaTipo::Dias, $faena->vigencia_tipo);
        $this->assertSame(30, $faena->vigencia_dias);
        $this->assertTrue($faena->uso_unico);
        $this->assertTrue($faena->requiere_credencial);
    }

    public function test_la_guia_de_transporte_tambien_exige_credencial(): void
    {
        $guia = $this->tipo('GUT');

        $this->assertTrue($guia->requiere_credencial);
        $this->assertTrue($guia->uso_unico);
    }

    /*
    |--------------------------------------------------------------------------
    | La compuerta
    |--------------------------------------------------------------------------
    */

    public function test_sin_cedula_no_se_puede_pedir_una_faena(): void
    {
        $solicitante = Solicitante::factory()->create();

        $habilitacion = $this->tipo('PPF')->habilitacionPara($solicitante);

        $this->assertFalse($habilitacion->habilitado);
        $this->assertStringContainsString('no tiene Cédula de Pescador', $habilitacion->motivo);
        $this->assertNull($habilitacion->credencial);
    }

    public function test_con_cedula_vigente_la_faena_queda_habilitada(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->emitirCedula($solicitante, vence: now()->endOfYear());

        $habilitacion = $this->tipo('PPF')->habilitacionPara($solicitante);

        $this->assertTrue($habilitacion->habilitado);
        $this->assertNotNull($habilitacion->credencial);
    }

    /**
     * «Nunca la sacó» y «se le venció» son dos conversaciones distintas en
     * ventanilla, y el operador necesita poder distinguirlas: en un caso se
     * emite desde cero, en el otro se renueva.
     */
    public function test_con_cedula_vencida_se_avisa_desde_cuando(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->emitirCedula($solicitante, vence: Carbon::parse('2025-12-31'));

        $habilitacion = $this->tipo('PPF')->habilitacionPara($solicitante);

        $this->assertFalse($habilitacion->habilitado);
        $this->assertStringContainsString('venció el 31/12/2025', $habilitacion->motivo);
        $this->assertStringContainsString('Renovar', $habilitacion->comoResolver);

        // Se devuelve la credencial vencida para poder mostrarla en pantalla.
        $this->assertNotNull($habilitacion->credencial);
    }

    /**
     * La cédula que vence HOY todavía habilita. El pescador que llega el 31 de
     * diciembre con su cédula de esa gestión tiene derecho a que se le atienda.
     */
    public function test_la_cedula_que_vence_hoy_todavia_habilita(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->emitirCedula($solicitante, vence: now());

        $this->assertTrue($solicitante->tieneCredencialVigente());
    }

    /**
     * Una cédula anulada no habilita nada, aunque su fecha no haya llegado.
     */
    public function test_una_cedula_anulada_no_habilita(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->emitirCedula($solicitante, vence: now()->endOfYear(), estado: EstadoDocumento::Anulado);

        $this->assertFalse($solicitante->tieneCredencialVigente());
        $this->assertFalse($this->tipo('PPF')->habilitacionPara($solicitante)->habilitado);
    }

    /**
     * La cédula no se exige a sí misma: si se exigiera, nadie podría sacar la
     * primera y el sistema quedaría trabado para todos.
     */
    public function test_la_cedula_se_puede_pedir_sin_tener_cedula(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->assertTrue($this->tipo('CAP')->habilitacionPara($solicitante)->habilitado);
    }

    /**
     * La cédula NO habilita al pedirla.
     *
     * El trámite entra, pasa a revisión, un supervisor lo aprueba y recién ahí
     * se emite el documento. Entre medio el pescador no puede sacar faena: si
     * pudiera, la revisión no serviría de nada.
     */
    public function test_una_cedula_en_revision_todavia_no_habilita(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->tramiteDeCedula($solicitante, EstadoTramite::EnRevision);

        $habilitacion = $this->tipo('PPF')->habilitacionPara($solicitante);

        $this->assertFalse($habilitacion->habilitado);
        $this->assertStringContainsString('está en trámite', $habilitacion->motivo);
        $this->assertStringContainsString('En Revisión', $habilitacion->motivo);

        // Se devuelve el trámite en curso para poder mostrarlo en pantalla.
        $this->assertNotNull($habilitacion->tramiteEnCurso);
    }

    /**
     * Aprobado tampoco alcanza: falta emitir el documento.
     */
    public function test_una_cedula_aprobada_pero_sin_emitir_todavia_no_habilita(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->tramiteDeCedula($solicitante, EstadoTramite::Aprobado);

        $this->assertFalse($this->tipo('PPF')->habilitacionPara($solicitante)->habilitado);
    }

    /**
     * «Nunca la sacó» y «ya la pidió» piden acciones distintas en ventanilla:
     * en el primer caso hay que cargarle la cédula, en el segundo cargarla de
     * nuevo sería duplicar el trámite.
     */
    public function test_se_distingue_no_tener_cedula_de_tenerla_en_revision(): void
    {
        $sinNada = Solicitante::factory()->create();
        $enRevision = Solicitante::factory()->create();
        $this->tramiteDeCedula($enRevision, EstadoTramite::EnRevision);

        $faena = $this->tipo('PPF');

        $this->assertStringContainsString(
            'no tiene Cédula',
            $faena->habilitacionPara($sinNada)->motivo,
        );
        $this->assertStringContainsString(
            'en trámite',
            $faena->habilitacionPara($enRevision)->motivo,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ayudantes
    |--------------------------------------------------------------------------
    */

    /**
     * Un trámite de cédula sin documento emitido: el estado intermedio.
     */
    private function tramiteDeCedula(Solicitante $solicitante, EstadoTramite $estado): Tramite
    {
        return Tramite::create([
            'solicitante_id' => $solicitante->id,
            'tipo_tramite_id' => $this->tipo('CAP')->id,
            'user_id' => User::firstOrFail()->id,
            'estado' => $estado,
            'monto_total' => 80,
        ]);
    }

    private function tipo(string $codigo): TipoTramite
    {
        return TipoTramite::where('codigo', $codigo)->firstOrFail();
    }

    /**
     * Emite una cédula de pescador para el solicitante, con su trámite.
     *
     * El documento cuelga del trámite y no del solicitante, así que hay que
     * crear los dos: es la misma cadena que va a existir en producción.
     */
    private function emitirCedula(
        Solicitante $solicitante,
        Carbon $vence,
        EstadoDocumento $estado = EstadoDocumento::Vigente,
    ): Documento {
        $tipo = $this->tipo('CAP');
        $operador = User::firstOrFail();

        $tramite = Tramite::create([
            'solicitante_id' => $solicitante->id,
            'tipo_tramite_id' => $tipo->id,
            'user_id' => $operador->id,
            'monto_total' => 80,
        ]);

        return Documento::create([
            'codigo_verificacion' => Str::upper(Str::random(EmisionDocumentoService::LARGO_CODIGO)),
            'tramite_id' => $tramite->id,
            'tipo' => CategoriaDocumento::Credencial,
            'fecha_emision' => $vence->copy()->startOfYear()->toDateString(),
            'fecha_vencimiento' => $vence->toDateString(),
            'estado' => $estado,
            'emitido_por' => $operador->id,
        ]);
    }
}
