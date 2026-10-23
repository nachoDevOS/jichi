<?php

namespace Tests\Feature\Documentos;

use App\Http\Controllers\Panel\AutorizacionPescaController;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\DocumentoEmitido;
use App\Models\Recibo;
use App\Services\DocumentoEmitidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El PDF de un papel se guarda al emitirse y se reimprime tal como salió.
 * S3 simulado: ninguna prueba sube nada al bucket real.
 */
class DocumentoEmitidoTest extends TestCase
{
    use RefreshDatabase;

    private DocumentoEmitidoService $documentos;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 's3']);
        Storage::fake('s3');
        $this->documentos = app(DocumentoEmitidoService::class);
    }

    public function test_guardar_sube_el_pdf_a_s3_y_anota_su_huella(): void
    {
        $emitido = $this->documentos->guardar($this->autorizacion());

        Storage::disk('s3')->assertExists($emitido->ruta);
        $bytes = Storage::disk('s3')->get($emitido->ruta);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(hash('sha256', $bytes), $emitido->sha256);
        // La visibilidad privada la aplica S3 (ACL); el disco simulado en Windows no la refleja.
        $this->assertSame('s3', $emitido->disco);
        $this->assertFalse(Storage::disk('public')->exists($emitido->ruta), 'nunca en el disco público');
    }

    public function test_reimprimir_entrega_el_mismo_archivo_aunque_cambien_los_datos(): void
    {
        $autorizacion = $this->autorizacion();
        $primero = $this->documentos->contenido($autorizacion);

        $autorizacion->beneficiario->update(['primerNombre' => 'Cambiado']);
        $segundo = $this->documentos->contenido($autorizacion->refresh());

        $this->assertSame($primero, $segundo, 'se reimprime tal como se emitió');
        $this->assertSame(1, DocumentoEmitido::withTrashed()->count(), 'no se volvió a armar');
    }

    public function test_si_falta_el_archivo_lo_vuelve_a_generar_y_deja_el_anterior_de_historial(): void
    {
        $autorizacion = $this->autorizacion();
        $viejo = $this->documentos->guardar($autorizacion);
        Storage::disk('s3')->delete($viejo->ruta);

        $this->assertStringStartsWith('%PDF', $this->documentos->contenido($autorizacion));

        $this->assertSoftDeleted($viejo);
        $this->assertSame(1, DocumentoEmitido::count());
    }

    public function test_al_aprobar_se_guardan_el_documento_y_su_recibo(): void
    {
        $autorizacion = $this->autorizacion();
        $this->recibo($autorizacion);

        $this->documentos->emitirAlAprobar($autorizacion);

        $this->assertNotNull($autorizacion->documentoEmitido()->first());
        $this->assertNotNull($autorizacion->recibo()->first()->documentoEmitido()->first());
    }

    public function test_imprimir_redirige_a_un_enlace_firmado_de_s3_al_pdf_guardado(): void
    {
        $opciones = [];
        // El S3 simulado no firma: se le da un enlace de prueba que anota lo que se pidió.
        Storage::disk('s3')->buildTemporaryUrlsUsing(function (string $ruta, $vence, array $o) use (&$opciones): string {
            $opciones[] = $o;

            return "https://s3.prueba/{$ruta}?firmado";
        });
        $autorizacion = $this->autorizacion();

        $abrir = app(AutorizacionPescaController::class)->imprimir($autorizacion);
        $bajar = app(AutorizacionPescaController::class)->imprimir($autorizacion, descarga: true);

        $ruta = $autorizacion->documentoEmitido()->first()->ruta;
        $this->assertSame("https://s3.prueba/{$ruta}?firmado", $abrir->getTargetUrl());
        $this->assertStringStartsWith('inline', $opciones[0]['ResponseContentDisposition']);
        $this->assertStringStartsWith('attachment', $opciones[1]['ResponseContentDisposition']);
        $this->assertSame(1, DocumentoEmitido::count(), 'las dos veces el mismo PDF guardado');
        $this->assertSame($abrir->getTargetUrl(), $bajar->getTargetUrl());
    }

    public function test_la_ficha_lleva_el_enlace_directo_a_s3_y_el_nombre_del_archivo(): void
    {
        $opciones = [];
        Storage::disk('s3')->buildTemporaryUrlsUsing(function (string $ruta, $vence, array $o) use (&$opciones): string {
            $opciones[] = $o;

            return "https://s3.prueba/{$ruta}?firmado";
        });
        $autorizacion = $this->autorizacion();

        $this->assertNull(DocumentoEmitidoService::resumen($autorizacion), 'sin PDF todavía: el botón usa la ruta de Jichi');

        $guardado = $this->documentos->guardar($autorizacion);
        $resumen = DocumentoEmitidoService::resumen($autorizacion);

        $this->assertSame("https://s3.prueba/{$guardado->ruta}?firmado", $resumen['enlace']['url']);
        $this->assertSame('inline; filename="autorizacion-000001.pdf"', $opciones[0]['ResponseContentDisposition']);
    }

    private function autorizacion(string $estado = 'aprobado'): AprovechamientoPesq
    {
        $autorizacion = AprovechamientoPesq::query()->create([
            'beneficiario_id' => Beneficiario::factory()->create()->id, 'nro' => 1, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => 1000, 'kilos_disponibles' => 1000, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => $estado, 'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);
        $autorizacion->asignarCodigo();

        return $autorizacion;
    }

    private function recibo(AprovechamientoPesq $autorizacion): Recibo
    {
        $recibo = Recibo::query()->create([
            'beneficiario_id' => $autorizacion->beneficiario_id,
            'recibible_type' => $autorizacion->getMorphClass(),
            'recibible_id' => $autorizacion->id,
            'numero_recibo' => '000001',
            'monto_total' => 660,
            'concepto' => 'Autorización de Pesca para Aprovechamiento Pesquero',
        ]);
        $recibo->asignarCodigo();

        return $recibo;
    }
}
