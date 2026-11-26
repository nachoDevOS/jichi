<?php

namespace Tests\Feature\ApiApp;

use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Recibo;
use App\Models\User;
use App\Services\CuentaPortalService;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * «Descargar PDF» del recibo digital de la app (26/11/2026): el PDF guardado al emitirse,
 * por un enlace firmado de 5 minutos (s3: el del bucket; disco local: una ruta firmada).
 */
class ReciboPdfTest extends TestCase
{
    use RefreshDatabase, SirebFalso;

    private Beneficiario $ignacio;

    private User $cuenta;

    private string $token;

    private int $siguiente = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);

        $this->ignacio = Beneficiario::create([
            'ci' => '7633685', 'primerNombre' => 'Ignacio', 'apellidoPaterno' => 'Molina', 'fechaNacimiento' => '1997-03-08',
        ]);
        $servicio = app(CuentaPortalService::class);
        $servicio->darAcceso($this->ignacio);
        $this->cuenta = $this->ignacio->cuenta()->firstOrFail();
        $servicio->cambiarClave($this->cuenta, 'pesca2026');
        $this->token = $this->cuenta->createToken('prueba')->plainTextToken;
    }

    public function test_en_s3_devuelve_el_enlace_firmado_del_bucket_para_bajarlo(): void
    {
        $this->bucketSimulado();
        $recibo = $this->recibo($this->ignacio, 's3');
        $ruta = $recibo->documentoEmitido->ruta;

        $respuesta = $this->pedir("/recibos/{$recibo->codigo_legible}/pdf")
            ->assertOk()
            ->assertJsonPath('pdf.nombre', 'recibo-000001.pdf');

        $url = $respuesta->json('pdf.url');
        $this->assertStringStartsWith("https://bucket.test/{$ruta}?", $url);
        $this->assertStringContainsString(urlencode('attachment; filename="recibo-000001.pdf"'), $url);
        // Vence a los 5 minutos, ni más ni menos.
        $this->assertStringContainsString('vence='.now()->addMinutes(5)->timestamp, $url);
        $this->assertSame(now()->addMinutes(5)->toIso8601String(), $respuesta->json('pdf.vence_en'));
        // El token no viaja en el enlace.
        $this->assertStringNotContainsString($this->token, $url);
    }

    public function test_un_recibo_ajeno_da_404_igual_que_uno_que_no_existe(): void
    {
        $this->bucketSimulado();
        $otro = Beneficiario::create([
            'ci' => '1111111', 'primerNombre' => 'Otro', 'apellidoPaterno' => 'Pescador', 'fechaNacimiento' => '1990-01-01',
        ]);
        $ajeno = $this->recibo($otro, 's3');

        $this->pedir("/recibos/{$ajeno->codigo_legible}/pdf")->assertNotFound();
        $this->pedir('/recibos/ZZZZ-ZZZZ-ZZZZ-ZZZZ/pdf')->assertNotFound();
    }

    public function test_sin_token_no_entra(): void
    {
        $this->getJson('/api/v1/portal/recibos/ZZZZ-ZZZZ-ZZZZ-ZZZZ/pdf')->assertUnauthorized();
    }

    public function test_en_disco_local_el_enlace_firmado_baja_el_pdf_y_vence(): void
    {
        Storage::fake('local');
        $recibo = $this->recibo($this->ignacio, 'local');

        $url = $this->pedir("/recibos/{$recibo->codigo_legible}/pdf")->assertOk()->json('pdf.url');

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="recibo-000001.pdf"')
            ->assertContent('%PDF-1.7 recibo de prueba');

        // Sin firma, o con la firma tocada, no se baja.
        $this->get(strtok($url, '?'))->assertForbidden();
        $this->get($url.'x')->assertForbidden();

        // Pasados los 5 minutos, tampoco.
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_el_portal_web_sigue_bajando_su_recibo_y_no_el_ajeno(): void
    {
        $this->bucketSimulado();
        $suyo = $this->recibo($this->ignacio, 's3');
        $otro = Beneficiario::create([
            'ci' => '1111111', 'primerNombre' => 'Otro', 'apellidoPaterno' => 'Pescador', 'fechaNacimiento' => '1990-01-01',
        ]);
        $ajeno = $this->recibo($otro, 's3');

        $this->actingAs($this->cuenta)
            ->get(route('portal.recibos.descargar', ['codigo' => $suyo->codigo->codigo]))
            ->assertRedirectContains("https://bucket.test/{$suyo->documentoEmitido->ruta}");
        $this->actingAs($this->cuenta)
            ->get(route('portal.recibos.descargar', ['codigo' => $ajeno->codigo->codigo]))
            ->assertNotFound();
    }

    /** s3 simulado, con enlaces firmados de mentira que dicen cuándo vencen. */
    private function bucketSimulado(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->buildTemporaryUrlsUsing(
            fn (string $ruta, DateTimeInterface $vence, array $opciones): string => "https://bucket.test/{$ruta}?vence={$vence->getTimestamp()}"
                .'&disposicion='.urlencode($opciones['ResponseContentDisposition'] ?? ''),
        );
    }

    /** Un recibo con su PDF ya guardado en [disco]: la descarga no lo vuelve a armar. */
    private function recibo(Beneficiario $de, string $disco): Recibo
    {
        $this->siguiente++;
        $recibo = Recibo::query()->create([
            'beneficiario_id' => $de->id,
            'recibible_type' => (new AprovechamientoPesq)->getMorphClass(),
            'recibible_id' => $this->siguiente,
            'numero_recibo' => str_pad((string) $this->siguiente, 6, '0', STR_PAD_LEFT),
            'monto_total' => 500,
            'concepto' => 'Autorización de Pesca',
            'fecha_pago' => '2026-11-25',
        ]);
        $recibo->asignarCodigo();

        $ruta = "documentos/recibos/November2026/prueba{$this->siguiente}.pdf";
        $bytes = '%PDF-1.7 recibo de prueba';
        Storage::disk($disco)->put($ruta, $bytes);
        $recibo->documentoEmitido()->create([
            'disco' => $disco,
            'ruta' => $ruta,
            'sha256' => hash('sha256', $bytes),
            'bytes' => strlen($bytes),
            'generado_en' => now(),
        ]);

        return $recibo->load(['codigo', 'documentoEmitido']);
    }

    private function pedir(string $ruta): TestResponse
    {
        return $this->withToken($this->token)->getJson('/api/v1/portal'.$ruta);
    }
}
