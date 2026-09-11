<?php

namespace Tests\Feature;

use App\Enums\CategoriaDocumento;
use App\Enums\EstadoDocumento;
use App\Enums\EstadoTramite;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ============================================================================
 *  EL TRÁMITE SE GUARDA DE VERDAD
 * ============================================================================
 *
 * Estas pruebas cubren el alta completa: que se escriba en la base, que tome
 * su número correlativo, que entre en revisión y no aprobado, y que los
 * adjuntos queden en disco.
 *
 * Y sobre todo: que LA COMPUERTA no se pueda saltear mandando un POST a mano.
 * Las tarjetas del paso anterior viven en el navegador del operador; si la
 * regla no estuviera también en el servidor, no estaría en ninguna parte.
 */
class RegistroTramiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolPermisoSeeder::class, AreaSeeder::class, UsuarioSeeder::class]);

        // Disco falso: los archivos de las pruebas no ensucian storage/.
        Storage::fake('public');
    }

    /*
    |--------------------------------------------------------------------------
    | Alta de la cédula
    |--------------------------------------------------------------------------
    */

    public function test_se_registra_una_cedula_de_pescador(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'nombre' => $solicitante->nombreCompleto,
                'asociacion' => 'SOC. IBARE - MAMORÉ',
                'capacidad_kg' => '600',
                ...$this->adjuntosDeCedula(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $tramite = Tramite::firstOrFail();

        $this->assertSame($solicitante->id, $tramite->solicitante_id);
        $this->assertSame(80.0, (float) $tramite->monto_total);

        // Los campos propios del servicio van a la columna jsonb.
        $this->assertSame('SOC. IBARE - MAMORÉ', $tramite->datos_adicionales['asociacion']);
    }

    /**
     * Pedir la cédula no es tenerla: alguien tiene que revisar los papeles.
     */
    public function test_el_tramite_entra_en_revision_y_no_aprobado(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                ...$this->adjuntosDeCedula(),
            ]);

        $this->assertSame(EstadoTramite::EnRevision, Tramite::firstOrFail()->estado);
    }

    /**
     * El tramite se identifica por su id y nada mas.
     *
     * Antes habia ademas una columna `codigo` con un correlativo legible. Se
     * retiro: eran dos numeros para la misma fila, y el que se imprime en la
     * credencial es otro —el registro, que vive en datos_adicionales—.
     */
    public function test_cada_tramite_recibe_su_propio_numero(): void
    {
        $operador = $this->operador();

        foreach (Solicitante::factory(2)->create() as $solicitante) {
            $this->actingAs($operador)->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                ...$this->adjuntosDeCedula(),
            ]);
        }

        $ids = Tramite::orderBy('id')->pluck('id')->all();

        $this->assertCount(2, $ids);
        $this->assertSame($ids[0] + 1, $ids[1]);
    }

    /**
     * Los respaldos van a una carpeta con el número del trámite, para poder
     * encontrarlos sin cruzar la base de datos.
     */
    public function test_los_adjuntos_quedan_guardados_en_la_carpeta_del_tramite(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())->post(route('tramites.store'), [
            'tipo' => 'CAP',
            'solicitante' => $solicitante->id,
            ...$this->adjuntosDeCedula(),
        ]);

        $tramite = Tramite::firstOrFail();

        foreach (['certificacion_asociacion', 'copia_ci'] as $campo) {
            $ruta = $tramite->requisitos_validados[$campo] ?? null;

            $this->assertNotNull($ruta, "Falta la ruta de {$campo}");
            $this->assertStringContainsString($tramite->carpeta().'/', $ruta);
            Storage::disk('public')->assertExists($ruta);
        }

        // El comprobante ya no es un campo suelto: vive dentro de su pago.
        $comprobante = $tramite->requisitos_validados['pagos'][0]['archivo'];

        $this->assertStringContainsString($tramite->carpeta().'/', $comprobante);
        Storage::disk('public')->assertExists($comprobante);
    }

    public function test_la_cedula_no_se_registra_sin_sus_respaldos(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
            ])
            ->assertSessionHasErrors([
                'certificacion_asociacion',
                'copia_ci',
                'pagos',
            ]);

        $this->assertDatabaseCount('tramites', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | El pago, que puede venir partido en varios comprobantes
    |--------------------------------------------------------------------------
    */

    /**
     * EL CASO QUE MOTIVO TODO ESTO.
     *
     * El pescador paga la cedula en dos transferencias de dias distintos. Antes
     * entraba un solo archivo y el segundo pago no quedaba en ninguna parte.
     */
    public function test_la_cedula_se_puede_pagar_en_dos_transferencias(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                ...$this->respaldosSinPago(),
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'banco' => 'Banco Union',
                        'monto' => '50',
                        'comprobante' => UploadedFile::fake()->image('transferencia-1.jpg'),
                    ],
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '990001122',
                        'banco' => 'Banco Fassil',
                        'monto' => '30.50',
                        'comprobante' => UploadedFile::fake()->create('deposito.pdf', 60, 'application/pdf'),
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $tramite = Tramite::firstOrFail();
        $pagos = $tramite->requisitos_validados['pagos'];

        $this->assertCount(2, $pagos);

        // Cada pago se lee entero, sin cruzar dos listas paralelas.
        $this->assertSame('884512203', $pagos[0]['nro_transaccion']);
        $this->assertSame('Banco Union', $pagos[0]['banco']);
        // El monto se compara casteado porque json_encode escribe 50.0 como
        // «50» y al leerlo de vuelta vuelve como entero.
        $this->assertSame(50.0, (float) $pagos[0]['monto']);
        $this->assertSame('Banco Fassil', $pagos[1]['banco']);
        $this->assertSame(30.5, (float) $pagos[1]['monto']);

        // Los dos comprobantes quedan en disco, numerados por posicion.
        foreach ($pagos as $pago) {
            Storage::disk('public')->assertExists($pago['archivo']);
        }

        // El nombre lo sortea StorageController; lo que importa es que cada
        // pago conserve SU archivo y que sean distintos entre si.
        $this->assertStringEndsWith('.jpg', $pagos[0]['archivo']);
        $this->assertStringEndsWith('.pdf', $pagos[1]['archivo']);
        $this->assertNotSame($pagos[0]['archivo'], $pagos[1]['archivo']);

        // Lo cubierto es la suma de lo declarado, no el monto de la tasa.
        $this->assertSame(80.5, (float) $tramite->monto_pagado);
    }

    /**
     * Un pago parcial se registra igual: el pescador dejo un adelanto y vuelve.
     * Lo que no puede pasar es que el tramite figure como pagado.
     */
    public function test_un_pago_parcial_deja_saldo_pendiente(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                ...$this->respaldosSinPago(),
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'monto' => '40',
                        'comprobante' => UploadedFile::fake()->image('recibo.jpg'),
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $tramite = Tramite::firstOrFail();

        $this->assertSame(40.0, (float) $tramite->monto_pagado);
        $this->assertSame(80.0, (float) $tramite->monto_total);
        $this->assertFalse($tramite->esta_pagado);
    }

    /**
     * Sin numero de transaccion, una transferencia no se puede cruzar con el
     * extracto del banco: el pago queda sin comprobar.
     */
    public function test_una_transferencia_sin_numero_de_transaccion_se_rechaza(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                ...$this->respaldosSinPago(),
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'monto' => '80',
                        'comprobante' => UploadedFile::fake()->image('transferencia.jpg'),
                    ],
                ],
            ])
            ->assertSessionHasErrors('pagos.0.nro_transaccion');

        $this->assertDatabaseCount('tramites', 0);
    }

    /**
     * POR AHORA SOLO TRANSFERENCIA.
     *
     * El efectivo y el QR no estan habilitados: falta definir como se rinde la
     * caja del dia y quien concilia el QR. Los dos siguen existiendo en el enum
     * para poder LEER pagos viejos, asi que la prueba va contra el POST: si la
     * lista de disponibles no se usara al validar, el efectivo entraria igual.
     */
    public function test_por_ahora_el_efectivo_no_se_puede_registrar(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                ...$this->respaldosSinPago(),
                'pagos' => [
                    [
                        'forma' => 'efectivo',
                        'monto' => '80',
                        'comprobante' => UploadedFile::fake()->image('recibo.jpg'),
                    ],
                ],
            ])
            ->assertSessionHasErrors('pagos.0.forma');

        $this->assertDatabaseCount('tramites', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | La fotografia, que sale de la ficha y no del tramite
    |--------------------------------------------------------------------------
    */

    /**
     * La credencial se imprime con la cara del titular, asi que sin foto no se
     * puede emitir. Pero el formulario SI se abre: al pescador se le saca la
     * foto en ese momento, en vez de mandarlo a otra pantalla a mitad del
     * tramite.
     */
    public function test_sin_fotografia_el_formulario_de_cedula_igual_se_abre(): void
    {
        $solicitante = Solicitante::factory()->sinFoto()->create();

        $this->actingAs($this->operador())
            ->get(route('tramites.crear.cedula-pescador', ['solicitante' => $solicitante->id]))
            ->assertOk();
    }

    /** Lo que no se puede es registrar la cedula sin ninguna foto. */
    public function test_sin_fotografia_y_sin_adjuntarla_la_cedula_no_se_registra(): void
    {
        $solicitante = Solicitante::factory()->sinFoto()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                ...$this->adjuntosDeCedula(),
            ])
            ->assertSessionHasErrors('foto_solicitante');

        $this->assertDatabaseCount('tramites', 0);
    }

    /**
     * ACA ESTA EL PUNTO: la foto que se saca en ventanilla queda en la FICHA,
     * no en la carpeta del tramite. De ahi la toman esta credencial y todas las
     * que se le emitan despues.
     */
    public function test_la_foto_cargada_en_el_tramite_queda_en_la_ficha(): void
    {
        $solicitante = Solicitante::factory()->sinFoto()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'foto_solicitante' => UploadedFile::fake()->image('cara.jpg'),
                ...$this->adjuntosDeCedula(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $solicitante->refresh();
        $tramite = Tramite::firstOrFail();

        $this->assertNotNull($solicitante->foto);
        Storage::disk('public')->assertExists($solicitante->foto);

        // La carpeta es la del padron, la misma que usa el alta del
        // solicitante, y no la del tramite.
        $this->assertStringStartsWith('solicitantes/', $solicitante->foto);
        $this->assertStringNotContainsString($tramite->carpeta(), $solicitante->foto);

        // Y no queda copiada como adjunto del expediente.
        $this->assertArrayNotHasKey('foto', $tramite->requisitos_validados);
        $this->assertArrayNotHasKey('foto_solicitante', $tramite->requisitos_validados);
        $this->assertArrayNotHasKey('foto_solicitante', $tramite->datos_adicionales);
    }

    /**
     * La foto se COMPLETA, no se reemplaza. Corregir una foto ya cargada es una
     * edicion de la ficha y se hace desde la ficha: si se pudiera cambiar en
     * cada tramite, la misma persona terminaria con una cara distinta en cada
     * credencial y sin forma de saber cual es la buena.
     */
    public function test_no_se_reemplaza_la_foto_que_la_ficha_ya_tiene(): void
    {
        $solicitante = Solicitante::factory()->create();
        $original = $solicitante->foto;

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'foto_solicitante' => UploadedFile::fake()->image('otra-cara.jpg'),
                ...$this->adjuntosDeCedula(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($original, $solicitante->refresh()->foto);
    }

    /*
    |--------------------------------------------------------------------------
    | El peso de los archivos
    |--------------------------------------------------------------------------
    */

    /**
     * El limite es UNO para todo el sistema y sale de config/jichi.php.
     *
     * Las pruebas leen el config en vez de escribir 3072 a mano: si el dia de
     * manana el limite cambia, estas pruebas siguen probando el limite real y
     * no uno viejo que ya nadie usa.
     */
    public function test_ningun_adjunto_puede_pasar_del_peso_maximo(): void
    {
        $solicitante = Solicitante::factory()->create();
        $kb = (int) config('jichi.archivos.max_kb');

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'certificacion_asociacion' => UploadedFile::fake()->create('grande.pdf', $kb + 1, 'application/pdf'),
                'copia_ci' => UploadedFile::fake()->create('grande.jpg', $kb + 1, 'image/jpeg'),
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'monto' => '80',
                        'comprobante' => UploadedFile::fake()->create('grande.jpg', $kb + 1, 'image/jpeg'),
                    ],
                ],
            ])
            ->assertSessionHasErrors([
                'certificacion_asociacion',
                'copia_ci',
                'pagos.0.comprobante',
            ]);

        $this->assertDatabaseCount('tramites', 0);
    }

    /** Justo en el limite SI entra: el rechazo es a partir de pasarse. */
    public function test_un_adjunto_del_peso_maximo_exacto_se_acepta(): void
    {
        $solicitante = Solicitante::factory()->create();
        $kb = (int) config('jichi.archivos.max_kb');

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'certificacion_asociacion' => UploadedFile::fake()->create('justo.pdf', $kb, 'application/pdf'),
                'copia_ci' => UploadedFile::fake()->create('justo.jpg', $kb, 'image/jpeg'),
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'monto' => '80',
                        'comprobante' => UploadedFile::fake()->create('justo.jpg', $kb, 'image/jpeg'),
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tramites', 1);
    }

    /** La fotografia usa el mismo limite que los demas adjuntos. */
    public function test_la_fotografia_tampoco_puede_pasar_del_peso_maximo(): void
    {
        $solicitante = Solicitante::factory()->sinFoto()->create();
        $kb = (int) config('jichi.archivos.max_kb');

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'foto_solicitante' => UploadedFile::fake()->create('cara.jpg', $kb + 1, 'image/jpeg'),
                ...$this->adjuntosDeCedula(),
            ])
            ->assertSessionHasErrors('foto_solicitante');

        $this->assertNull($solicitante->refresh()->foto);
    }

    /** Un archivo de un formato que no esta en la lista se rechaza. */
    public function test_un_formato_no_permitido_se_rechaza(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'certificacion_asociacion' => UploadedFile::fake()->create('planilla.xlsx', 40),
                'copia_ci' => UploadedFile::fake()->image('carnet.jpg'),
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'monto' => '80',
                        'comprobante' => UploadedFile::fake()->image('recibo.jpg'),
                    ],
                ],
            ])
            ->assertSessionHasErrors('certificacion_asociacion');

        $this->assertDatabaseCount('tramites', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | El domicilio, que tambien sale de la ficha
    |--------------------------------------------------------------------------
    */

    /**
     * ESTA ES LA QUE IMPORTA DEL DOMICILIO.
     *
     * Ciudad, provincia y direccion se imprimen en la credencial, pero salen de
     * la ficha y no del navegador. El POST manda otros valores a proposito: si
     * se guardara lo que llega, un formulario armado a mano emitiria una
     * credencial con la direccion de otra persona.
     */
    public function test_el_domicilio_impreso_sale_de_la_ficha_y_no_del_post(): void
    {
        $solicitante = Solicitante::factory()->create([
            'ciudad' => 'Trinidad',
            'provincia' => 'Cercado',
            'direccion' => 'Puerto Almacen',
        ]);

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'ciudad' => 'Santa Cruz',
                'provincia' => 'Andres Ibanez',
                'direccion' => 'Otra direccion inventada',
                'nombre' => 'Otro Nombre Cualquiera',
                ...$this->adjuntosDeCedula(),
            ])
            ->assertSessionHasNoErrors();

        $datos = Tramite::firstOrFail()->datos_adicionales;

        $this->assertSame('Trinidad', $datos['ciudad']);
        $this->assertSame('Cercado', $datos['provincia']);
        $this->assertSame('Puerto Almacen', $datos['direccion']);
        $this->assertSame($solicitante->nombreCompleto, $datos['nombre']);
        $this->assertSame($solicitante->ci_nit, $datos['ci']);

        // Y la ficha queda intacta: el POST no la corrigio por la ventana.
        $this->assertSame('Trinidad', $solicitante->refresh()->ciudad);
    }

    /** Ficha sin direccion: no se registra hasta completarla. */
    public function test_sin_domicilio_en_la_ficha_la_cedula_no_se_registra(): void
    {
        $solicitante = Solicitante::factory()->create([
            'ciudad' => null,
            'provincia' => null,
            'direccion' => null,
        ]);

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                ...$this->adjuntosDeCedula(),
            ])
            ->assertSessionHasErrors([
                'ciudad_solicitante',
                'provincia_solicitante',
                'direccion_solicitante',
            ]);

        $this->assertDatabaseCount('tramites', 0);
    }

    /**
     * Completado desde el tramite, el domicilio termina en la FICHA: es un dato
     * de la persona, y de ahi lo toman todas sus credenciales.
     */
    public function test_el_domicilio_completado_en_el_tramite_queda_en_la_ficha(): void
    {
        $solicitante = Solicitante::factory()->create([
            'ciudad' => null,
            'provincia' => null,
            'direccion' => null,
        ]);

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'ciudad_solicitante' => 'Trinidad',
                'provincia_solicitante' => 'Cercado',
                'direccion_solicitante' => 'Puerto Varador',
                ...$this->adjuntosDeCedula(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $solicitante->refresh();

        $this->assertSame('Trinidad', $solicitante->ciudad);
        $this->assertSame('Cercado', $solicitante->provincia);
        $this->assertSame('Puerto Varador', $solicitante->direccion);

        // Y lo recien completado es lo que se imprime.
        $this->assertSame('Puerto Varador', Tramite::firstOrFail()->datos_adicionales['direccion']);
    }

    /** Se completa, nunca se pisa: corregir la ficha se hace en la ficha. */
    public function test_no_se_reemplaza_el_domicilio_que_la_ficha_ya_tiene(): void
    {
        $solicitante = Solicitante::factory()->create(['direccion' => 'Puerto Almacen']);

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'direccion_solicitante' => 'Otra direccion',
                ...$this->adjuntosDeCedula(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Puerto Almacen', $solicitante->refresh()->direccion);
    }

    /** Ningun archivo suelto entra a las columnas jsonb del tramite. */
    public function test_la_fotografia_que_llegue_en_el_post_no_se_guarda(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                'foto' => UploadedFile::fake()->image('otra-cara.jpg'),
                ...$this->adjuntosDeCedula(),
            ])
            ->assertSessionHasNoErrors();

        $tramite = Tramite::firstOrFail();

        $this->assertArrayNotHasKey('foto', $tramite->requisitos_validados);
        $this->assertArrayNotHasKey('foto', $tramite->datos_adicionales);
    }

    /** Un pago sin comprobante no es un pago: es un monto escrito a mano. */
    public function test_un_pago_sin_comprobante_se_rechaza(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'CAP',
                'solicitante' => $solicitante->id,
                ...$this->respaldosSinPago(),
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'monto' => '80',
                    ],
                ],
            ])
            ->assertSessionHasErrors('pagos.0.comprobante');

        $this->assertDatabaseCount('tramites', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | La compuerta, del lado del servidor
    |--------------------------------------------------------------------------
    */

    /**
     * ESTA ES LA PRUEBA QUE MÁS IMPORTA.
     *
     * El POST se manda directo, salteando las tarjetas del paso anterior. Si
     * la regla viviera solo en React, acá se colaría un permiso por faena
     * emitido a alguien sin cédula.
     */
    public function test_no_se_puede_registrar_una_faena_sin_cedula_vigente(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'PPF',
                'solicitante' => $solicitante->id,
                'embarcacion' => 'Doña Pancha',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('tramites', 0);
    }

    public function test_con_cedula_vigente_la_faena_se_registra(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->emitirCedula($solicitante);

        $this->actingAs($this->operador())
            ->post(route('tramites.store'), [
                'tipo' => 'PPF',
                'solicitante' => $solicitante->id,
                'embarcacion' => 'Doña Pancha',
            ])
            ->assertRedirect();

        $faena = Tramite::whereHas('tipoTramite', fn ($q) => $q->where('codigo', 'PPF'))->firstOrFail();

        $this->assertSame('Doña Pancha', $faena->datos_adicionales['embarcacion']);
        $this->assertSame(15.0, (float) $faena->monto_total);
    }

    public function test_el_formulario_de_faena_redirige_si_no_hay_cedula(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->get(route('tramites.crear.permiso-faena', ['solicitante' => $solicitante->id]))
            ->assertRedirect(route('tramites.create', ['solicitante' => $solicitante->id]));
    }

    public function test_el_formulario_de_faena_abre_con_cedula_vigente(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->emitirCedula($solicitante);

        $this->actingAs($this->operador())
            ->get(route('tramites.crear.permiso-faena', ['solicitante' => $solicitante->id]))
            ->assertOk();
    }

    /**
     * Sin solicitante no hay trámite: el trámite es de alguien.
     */
    public function test_sin_solicitante_no_se_puede_entrar_al_formulario(): void
    {
        $this->actingAs($this->operador())
            ->get(route('tramites.crear.cedula-pescador'))
            ->assertRedirect(route('tramites.create'));
    }

    /*
    |--------------------------------------------------------------------------
    | El listado
    |--------------------------------------------------------------------------
    */

    public function test_el_listado_muestra_los_tramites_registrados(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())->post(route('tramites.store'), [
            'tipo' => 'CAP',
            'solicitante' => $solicitante->id,
            ...$this->adjuntosDeCedula(),
        ]);

        $this->actingAs($this->operador())
            ->get(route('tramites.index'))
            ->assertOk()
            // Las filas ya no llegan sueltas sino dentro de `data`: el listado
            // pagina del lado del servidor y el resto del objeto son los datos
            // de navegación (total, página actual, botones).
            ->assertInertia(fn ($pagina) => $pagina
                ->has('tramites.data', 1)
                ->where('tramites.data.0.solicitante', $solicitante->nombreCompleto)
                ->where('tramites.data.0.estado', EstadoTramite::EnRevision->value));
    }

    public function test_el_listado_trae_una_pagina_y_no_la_tabla_entera(): void
    {
        $porPagina = (int) config('jichi.por_pagina');
        $this->sembrarTramites($porPagina + 3);

        $this->actingAs($this->operador())
            ->get(route('tramites.index'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('tramites.data', $porPagina)
                ->where('tramites.total', $porPagina + 3)
                ->where('tramites.current_page', 1)
                ->where('tramites.last_page', 2));

        $this->actingAs($this->operador())
            ->get(route('tramites.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->has('tramites.data', 3));
    }

    public function test_se_puede_pedir_treinta_filas_por_pagina(): void
    {
        $this->sembrarTramites(32);

        $this->actingAs($this->operador())
            ->get(route('tramites.index', ['por_pagina' => 30]))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('tramites.data', 30)
                ->where('filtros.por_pagina', 30)
                // El selector se dibuja con la lista que manda el servidor.
                ->where('opcionesPorPagina', [15, 30, 50]));
    }

    /**
     * El número de filas llega por la barra de direcciones, así que cualquiera
     * puede escribir lo que quiera. La lista cerrada del controlador es lo que
     * impide que un ?por_pagina=500000 traiga la tabla entera a memoria.
     */
    public function test_un_tamano_de_pagina_inventado_se_ignora(): void
    {
        $this->sembrarTramites(20);

        foreach ([500000, 7, -3, 0, 'muchas'] as $intento) {
            $this->actingAs($this->operador())
                ->get(route('tramites.index', ['por_pagina' => $intento]))
                ->assertOk()
                ->assertInertia(fn ($pagina) => $pagina
                    ->has('tramites.data', 15)
                    ->where('filtros.por_pagina', 15));
        }
    }

    public function test_el_tamano_de_pagina_sobrevive_a_la_busqueda(): void
    {
        foreach (range(1, 32) as $i) {
            $this->crearTramite(Solicitante::factory()->create(['apellidoPaterno' => 'Moxeño']));
        }

        $this->actingAs($this->operador())
            ->get(route('tramites.index', ['buscar' => 'moxeño', 'por_pagina' => 30]))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('tramites.data', 30)
                ->where('tramites.total', 32)
                // Los enlaces de paginación llevan las dos cosas adentro.
                ->where('tramites.links.1.url', fn (?string $url) => str_contains((string) $url, 'buscar=')
                    && str_contains((string) $url, 'por_pagina=30')));
    }

    public function test_el_listado_se_filtra_por_estado(): void
    {
        $this->sembrarTramites(3); // los tres nacen en revisión

        $aprobado = $this->crearTramite(Solicitante::factory()->create());
        $aprobado->forceFill(['estado' => EstadoTramite::Aprobado])->save();

        $this->actingAs($this->operador())
            ->get(route('tramites.index', ['estado' => 'aprobado']))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('tramites.data', 1)
                ->where('tramites.data.0.id', $aprobado->id)
                ->where('filtros.estado', 'aprobado')
                // El selector se dibuja con los estados que manda el enum.
                ->has('opcionesEstado', 4));

        // Sin filtro vuelven los cuatro.
        $this->actingAs($this->operador())
            ->get(route('tramites.index'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('tramites.data', 4)
                ->where('filtros.estado', null));
    }

    /**
     * Un ?estado=loquesea escrito a mano no filtra nada, en vez de reventar.
     * Los estados válidos los define App\Enums\EstadoTramite y nadie más.
     */
    public function test_un_estado_inventado_no_filtra_nada(): void
    {
        $this->sembrarTramites(3);

        $this->actingAs($this->operador())
            ->get(route('tramites.index', ['estado' => 'emitido']))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('tramites.data', 3)
                ->where('filtros.estado', null));
    }

    public function test_el_listado_abre_por_el_tramite_mas_nuevo(): void
    {
        $this->sembrarTramites(4);
        $ultimo = Tramite::query()->orderByDesc('id')->firstOrFail();

        $this->actingAs($this->operador())
            ->get(route('tramites.index'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('tramites.data.0.id', $ultimo->id));
    }

    public function test_el_buscador_filtra_por_el_apellido_del_solicitante(): void
    {
        $this->sembrarTramites(3);

        $buscado = Solicitante::factory()->create(['apellidoPaterno' => 'Chuvirú']);
        $this->crearTramite($buscado);

        /*
         * Se busca en minúscula y a propósito: LIKE no distingue mayúsculas de
         * minúsculas, así que quien escriba rápido igual encuentra.
         *
         * Lo que SÍ distingue es el acento —«chuviru» sin tilde no trae nada—.
         * Es una limitación conocida de LIKE/ILIKE: para ignorar acentos haría
         * falta `unaccent` en PostgreSQL, que es una extensión, y SQLite no
         * tiene equivalente. Se deja anotado acá para que quede claro que es un
         * límite del motor y no un descuido del scope.
         */
        $this->actingAs($this->operador())
            ->get(route('tramites.index', ['buscar' => 'chuvirú']))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('tramites.data', 1)
                ->where('tramites.data.0.solicitante', $buscado->nombreCompleto)
                // El término vuelve a la pantalla para que el campo no se vea
                // vacío después de recargar.
                ->where('filtros.buscar', 'chuvirú'));
    }

    /**
     * El número de trámite se compara EXACTO y no con LIKE: buscar «6» no
     * tiene por qué traer el 6, el 16, el 60 y el 260.
     *
     * Puede venir acompañado igual: esos mismos dígitos pueden estar dentro de
     * una cédula, y quien escribe un número en ventanilla muchas veces está
     * tecleando justamente un CI. Por eso lo que se comprueba es que el
     * trámite buscado ENCABECE la lista, no que sea el único resultado.
     */
    public function test_el_buscador_encuentra_por_numero_de_tramite(): void
    {
        $this->sembrarTramites(5);
        $tramite = Tramite::query()->orderByDesc('id')->firstOrFail();

        $this->actingAs($this->operador())
            ->get(route('tramites.index', ['buscar' => (string) $tramite->id]))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('tramites.data.0.id', $tramite->id));
    }

    public function test_el_buscador_encuentra_por_el_registro_de_la_credencial(): void
    {
        $this->sembrarTramites(2);
        $this->crearTramite(Solicitante::factory()->create(), ['registro' => 'PES-2026-0042']);

        $this->actingAs($this->operador())
            ->get(route('tramites.index', ['buscar' => '2026-0042']))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('tramites.data', 1)
                ->where('tramites.data.0.registro', 'PES-2026-0042'));
    }

    /**
     * La trampa que documenta CLAUDE.md: sin `->withQueryString()`, al tocar
     * «página 2» se pierde lo buscado y la búsqueda vuelve a empezar.
     */
    public function test_la_busqueda_sobrevive_al_cambio_de_pagina(): void
    {
        $porPagina = (int) config('jichi.por_pagina');

        // Todos comparten apellido para que la búsqueda deje más de una página.
        foreach (range(1, $porPagina + 2) as $i) {
            $this->crearTramite(Solicitante::factory()->create(['apellidoPaterno' => 'Yacumeño']));
        }

        $this->actingAs($this->operador())
            ->get(route('tramites.index', ['buscar' => 'yacumeño']))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('tramites.data', $porPagina)
                ->where('tramites.total', $porPagina + 2)
                // Cada enlace de paginación lleva el término adentro.
                ->where('tramites.links.2.url', fn (?string $url) => str_contains((string) $url, 'buscar=')));
    }

    /*
    |--------------------------------------------------------------------------
    | Ayudantes
    |--------------------------------------------------------------------------
    */

    private function operador(): User
    {
        return User::firstOrFail();
    }

    /**
     * Crea trámites directamente en la base, sin pasar por el formulario.
     *
     * Las pruebas del alta sí hacen el POST completo, porque lo que miran es
     * el alta. Las del listado miran otra cosa —el orden, la paginación y el
     * buscador— y para eso solo necesitan filas: cargarlas por el formulario
     * costaría una subida de archivos por cada una y volvería lentísima la
     * corrida.
     */
    private function sembrarTramites(int $cantidad): void
    {
        foreach (range(1, $cantidad) as $i) {
            $this->crearTramite(Solicitante::factory()->create());
        }
    }

    /**
     * @param  array<string, mixed>  $datosAdicionales
     */
    private function crearTramite(Solicitante $solicitante, array $datosAdicionales = []): Tramite
    {
        return Tramite::create([
            'solicitante_id' => $solicitante->id,
            'tipo_tramite_id' => TipoTramite::query()->firstOrFail()->id,
            'user_id' => $this->operador()->id,
            'estado' => EstadoTramite::EnRevision,
            'monto_total' => 80,
            'datos_adicionales' => $datosAdicionales ?: null,
        ]);
    }

    /**
     * Los respaldos de una cedula pagada de una sola vez, por transferencia.
     *
     * @return array<string, mixed>
     */
    private function adjuntosDeCedula(): array
    {
        return [
            ...$this->respaldosSinPago(),
            'pagos' => [
                [
                    'forma' => 'transferencia',
                    'nro_transaccion' => '884512203',
                    'banco' => 'Banco Union',
                    'monto' => '80',
                    'comprobante' => UploadedFile::fake()->image('recibo.jpg'),
                ],
            ],
        ];
    }

    /**
     * Los dos papeles que no son el pago, para las pruebas que arman el suyo.
     *
     * @return array<string, UploadedFile>
     */
    private function respaldosSinPago(): array
    {
        return [
            'certificacion_asociacion' => UploadedFile::fake()->create('certificacion.pdf', 120, 'application/pdf'),
            'copia_ci' => UploadedFile::fake()->image('carnet.jpg'),
        ];
    }

    private function emitirCedula(Solicitante $solicitante): Documento
    {
        $tipo = TipoTramite::where('codigo', 'CAP')->firstOrFail();
        $operador = $this->operador();

        $tramite = Tramite::create([
            'solicitante_id' => $solicitante->id,
            'tipo_tramite_id' => $tipo->id,
            'user_id' => $operador->id,
            // La credencial ya emitida: el trámite queda aprobado y el
            // documento existe. Emitir dejó de ser un estado del trámite.
            'estado' => EstadoTramite::Aprobado,
            'monto_total' => 80,
        ]);

        return Documento::create([
            'codigo_verificacion' => Str::upper(Str::random(EmisionDocumentoService::LARGO_CODIGO)),
            'tramite_id' => $tramite->id,
            'tipo' => CategoriaDocumento::Credencial,
            'fecha_emision' => now()->startOfYear()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
            'estado' => EstadoDocumento::Vigente,
            'emitido_por' => $operador->id,
        ]);
    }
}
