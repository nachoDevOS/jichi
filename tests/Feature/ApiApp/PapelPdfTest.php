<?php

namespace Tests\Feature\ApiApp;

use App\Enums\EstadoFaena;
use App\Jobs\VerificarPagoJob;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\PermisoFaena;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * «Descargar» de «Vigente hoy» en la app (27/11/2026): el PDF guardado de su
 * autorización, faena o guía vigente, por un enlace firmado de 5 minutos. Las reglas
 * de «Descargar» del portal web: solo lo suyo, solo lo vigente hoy, nunca el carnet.
 */
class PapelPdfTest extends TestCase
{
    use PescadorDePrueba, RefreshDatabase, SirebFalso;

    private User $cuenta;

    private Carnet $carnet;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);
        $this->bucketSimulado();

        $this->cuenta = $this->beneficiarioConCuenta();
        $this->carnet = $this->pescadorListo($this->cuenta->beneficiario);
        $this->carnet->asignarCodigo();
        $this->carnet->aprovechamiento->asignarCodigo();
        $this->token = $this->cuenta->createToken('prueba')->plainTextToken;
    }

    public function test_baja_su_autorizacion_vigente_por_un_enlace_firmado(): void
    {
        $autorizacion = $this->conPdf($this->carnet->aprovechamiento);

        $respuesta = $this->pedir("/papeles/{$autorizacion->codigo_legible}/pdf")
            ->assertOk()
            ->assertJsonPath('pdf.nombre', "autorizacion-{$autorizacion->numeroLegible()}.pdf");

        $url = $respuesta->json('pdf.url');
        $this->assertStringStartsWith("https://bucket.test/{$autorizacion->documentoEmitido->ruta}?", $url);
        $this->assertStringContainsString('vence='.now()->addMinutes(5)->timestamp, $url);
        // El token no viaja en el enlace.
        $this->assertStringNotContainsString($this->token, $url);
    }

    public function test_la_faena_se_baja_aprobada_y_vigente_no_pendiente_ni_vencida(): void
    {
        $this->pedir('/faenas', 'post', self::TALONARIO)->assertCreated();
        $faena = $this->conPdf(PermisoFaena::query()->sole());
        $ruta = "/papeles/{$faena->codigo_legible}/pdf";

        // Pendiente de pago: todavía no es un papel válido.
        $this->pedir($ruta)->assertNotFound();

        $faena->update([
            'estado' => EstadoFaena::Aprobado,
            'fecha_salida' => now()->toDateString(),
            'fecha_desembarque' => now()->addDays(20)->toDateString(),
        ]);
        $this->pedir($ruta)->assertOk()->assertJsonPath('pdf.nombre', "permiso-faena-{$faena->numero_legible}.pdf");

        $faena->update(['fecha_desembarque' => now()->subDay()->toDateString()]);
        $this->pedir($ruta)->assertNotFound();
    }

    public function test_el_carnet_nunca_se_baja(): void
    {
        $carnet = $this->conPdf($this->carnet);

        $this->pedir("/papeles/{$carnet->codigo_legible}/pdf")->assertNotFound();
    }

    public function test_lo_ajeno_da_404_igual_que_lo_que_no_existe(): void
    {
        $otro = Beneficiario::create([
            'ci' => '1111111', 'primerNombre' => 'Otro', 'apellidoPaterno' => 'Pescador', 'fechaNacimiento' => '1990-01-01',
        ]);
        $ajena = $this->autorizacionDe($otro);
        $ajena->asignarCodigo();
        $this->conPdf($ajena);

        $this->pedir("/papeles/{$ajena->codigo_legible}/pdf")->assertNotFound();
        $this->pedir('/papeles/ZZZZ-ZZZZ-ZZZZ-ZZZZ/pdf')->assertNotFound();
    }

    public function test_sin_token_no_entra(): void
    {
        $this->getJson('/api/v1/portal/papeles/ZZZZ-ZZZZ-ZZZZ-ZZZZ/pdf')->assertUnauthorized();
    }

    public function test_el_inicio_dice_que_se_baja_la_autorizacion_y_no_el_carnet(): void
    {
        $vigentes = collect($this->pedir('/inicio')->assertOk()->json('inicio.vigentes'))->keyBy('clase');

        $this->assertTrue($vigentes['aprovechamiento']['puede_descargar']);
        $this->assertFalse($vigentes['carnet']['puede_descargar']);
    }

    /** El PDF ya guardado en el bucket: la descarga no lo vuelve a armar. */
    private function conPdf(Model $papel): Model
    {
        $ruta = 'documentos/prueba/'.Str::random(8).'.pdf';
        $bytes = '%PDF-1.7 papel de prueba';
        Storage::disk('s3')->put($ruta, $bytes);
        $papel->documentoEmitido()->create([
            'disco' => 's3',
            'ruta' => $ruta,
            'sha256' => hash('sha256', $bytes),
            'bytes' => strlen($bytes),
            'generado_en' => now(),
        ]);

        return $papel->load(['codigo', 'documentoEmitido']);
    }

    /** s3 simulado, con enlaces firmados de mentira que dicen cuándo vencen. */
    private function bucketSimulado(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->buildTemporaryUrlsUsing(
            fn (string $ruta, DateTimeInterface $vence): string => "https://bucket.test/{$ruta}?vence={$vence->getTimestamp()}",
        );
    }

    /** @param  array<string, mixed>  $datos */
    private function pedir(string $ruta, string $metodo = 'get', array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $encabezados = $metodo === 'post' ? ['Idempotency-Key' => (string) Str::uuid()] : [];

        return $this->withToken($this->token)->json(strtoupper($metodo), '/api/v1/portal'.$ruta, $datos, $encabezados);
    }
}
