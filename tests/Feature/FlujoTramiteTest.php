<?php

namespace Tests\Feature;

use App\Enums\EstadoTramite;
use App\Enums\RolSistema;
use App\Models\Documento;
use App\Models\Solicitante;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ============================================================================
 *  EL CIRCUITO: REVISAR, APROBAR, EMITIR Y ENTREGAR
 * ============================================================================
 *
 * Registrar el trámite es la mitad del trabajo; la otra mitad la hace otra
 * persona en otro momento. Estas pruebas cubren esa segunda mitad, y sobre todo
 * las dos cosas que NO se pueden saltear:
 *
 *   - el orden de los estados, que decide EstadoTramite y no el navegador
 *   - los permisos, que no son los mismos para cada paso
 *
 * Todas van contra el POST directo. Si las reglas vivieran solo en React, un
 * formulario armado a mano entregaría un trámite que nunca se emitió.
 */
class FlujoTramiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolPermisoSeeder::class, AreaSeeder::class, UsuarioSeeder::class]);

        Storage::fake('public');
    }

    /*
    |--------------------------------------------------------------------------
    | La ficha
    |--------------------------------------------------------------------------
    */

    public function test_la_ficha_del_tramite_se_abre_y_trae_lo_que_hay_que_revisar(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs($this->operador())
            ->get(route('tramites.show', $tramite->id))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('panel/tramites/ver')
                ->where('tramite.id', $tramite->id)
                ->where('tramite.estado', EstadoTramite::EnRevision->value)
                // Los saltos posibles salen del enum y viajan a la pantalla.
                ->where('tramite.siguientes', ['aprobado', 'rechazado'])
                ->has('tramite.requisitos', 2)
                ->has('tramite.pagos', 1));
    }

    /*
    |--------------------------------------------------------------------------
    | Corregir el expediente mientras esta en curso
    |--------------------------------------------------------------------------
    */

    public function test_el_formulario_de_correccion_se_abre_en_revision(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs($this->operador())
            ->get(route('tramites.edit', $tramite->id))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('panel/tramites/editar')
                ->where('tramite.puede_editarse', true)
                // El comprobante ya cargado viaja con su ruta: sin eso, corregir
                // un monto obligaria a volver a adjuntarlo.
                ->where('tramite.pagos.0.archivo', fn ($v) => filled($v)));
    }

    /**
     * ESTA ES LA QUE MAS IMPORTA DE LA CORRECCION.
     *
     * Desde aprobado ya no se toca: alguien firmo mirando esos papeles, y
     * cambiarlos despues dejaria una aprobacion que no corresponde a lo que hay
     * en el expediente.
     */
    public function test_un_tramite_aprobado_ya_no_se_puede_corregir(): void
    {
        $tramite = $this->tramiteAprobado();

        $this->actingAs($this->operador())
            ->get(route('tramites.edit', $tramite->id))
            ->assertRedirect(route('tramites.show', $tramite->id));

        $this->actingAs($this->operador())
            ->put(route('tramites.update', $tramite->id), [
                'asociacion' => 'OTRA ASOCIACION',
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '111',
                        'monto' => '80',
                        'archivo_actual' => 'tramites/x/y.jpg',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            'SOC. IBARE - MAMORÉ',
            $tramite->refresh()->datos_adicionales['asociacion'],
        );
    }

    public function test_se_corrige_el_monto_de_un_pago_sin_volver_a_adjuntarlo(): void
    {
        $tramite = $this->tramiteEnRevision();
        $comprobante = $tramite->requisitos_validados['pagos'][0]['archivo'];

        $this->actingAs($this->operador())
            ->put(route('tramites.update', $tramite->id), [
                'asociacion' => 'SOC. IBARE - MAMORÉ',
                'capacidad_kg' => '700',
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'banco' => 'Banco Unión',
                        'monto' => '60',
                        'archivo_actual' => $comprobante,
                    ],
                ],
            ])
            ->assertRedirect(route('tramites.show', $tramite->id))
            ->assertSessionHasNoErrors();

        $tramite->refresh();

        // El comprobante sigue siendo el mismo: no se volvio a subir nada.
        $this->assertSame($comprobante, $tramite->requisitos_validados['pagos'][0]['archivo']);
        $this->assertSame(60.0, (float) $tramite->requisitos_validados['pagos'][0]['monto']);

        // Y lo cubierto se recalcula solo.
        $this->assertSame(60.0, (float) $tramite->monto_pagado);
        $this->assertSame('700', $tramite->datos_adicionales['capacidad_kg']);
    }

    /**
     * Lo que identifica a la persona no se toca desde el tramite, tampoco al
     * corregir: un PUT armado a mano no puede cambiar el nombre ni el registro.
     */
    public function test_la_correccion_no_puede_tocar_el_registro_ni_los_datos_de_la_ficha(): void
    {
        $tramite = $this->tramiteEnRevision();
        $registro = $tramite->datos_adicionales['registro'];

        $this->actingAs($this->operador())
            ->put(route('tramites.update', $tramite->id), [
                'registro' => 'AAAA-BBBB-CCCC',
                'nombre' => 'Otro Nombre',
                'direccion' => 'Otra dirección',
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'monto' => '80',
                        'archivo_actual' => $tramite->requisitos_validados['pagos'][0]['archivo'],
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $datos = $tramite->refresh()->datos_adicionales;

        $this->assertSame($registro, $datos['registro']);
        $this->assertSame($tramite->solicitante->nombreCompleto, $datos['nombre']);
        $this->assertSame('Puerto Almacén', $datos['direccion']);
    }

    /** Un papel nuevo reemplaza al anterior, y el viejo se va del disco. */
    public function test_al_reemplazar_un_papel_se_borra_el_anterior(): void
    {
        $tramite = $this->tramiteEnRevision();

        // El de la prueba no existe en el disco falso: se crea para poder
        // comprobar que despues se borra.
        $anterior = $tramite->requisitos_validados['copia_ci'];
        Storage::disk('public')->put($anterior, 'contenido viejo');

        $this->actingAs($this->operador())
            ->put(route('tramites.update', $tramite->id), [
                'copia_ci' => UploadedFile::fake()->image('carnet-nuevo.jpg'),
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'monto' => '80',
                        'archivo_actual' => $tramite->requisitos_validados['pagos'][0]['archivo'],
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $nuevo = $tramite->refresh()->requisitos_validados['copia_ci'];

        $this->assertNotSame($anterior, $nuevo);
        Storage::disk('public')->assertExists($nuevo);
        Storage::disk('public')->assertMissing($anterior);
    }

    /** Un pago que se agrega en la correccion tiene que traer su comprobante. */
    public function test_un_pago_nuevo_sin_comprobante_se_rechaza(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs($this->operador())
            ->put(route('tramites.update', $tramite->id), [
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'monto' => '40',
                        'archivo_actual' => $tramite->requisitos_validados['pagos'][0]['archivo'],
                    ],
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '990001122',
                        'monto' => '40',
                    ],
                ],
            ])
            ->assertSessionHasErrors('pagos.1.comprobante');
    }

    /** Un usuario de solo lectura no corrige nada. */
    public function test_solo_lectura_no_puede_corregir(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs(User::role(RolSistema::SoloLectura->value)->firstOrFail())
            ->get(route('tramites.edit', $tramite->id))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | El orden de los estados
    |--------------------------------------------------------------------------
    */

    public function test_el_supervisor_aprueba_un_tramite_en_revision(): void
    {
        $tramite = $this->tramiteEnRevision();
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)
            ->post(route('tramites.aprobar', $tramite->id))
            ->assertRedirect();

        $tramite->refresh();

        $this->assertSame(EstadoTramite::Aprobado, $tramite->estado);
        $this->assertSame($supervisor->id, $tramite->aprobado_por);
        $this->assertNotNull($tramite->fecha_aprobacion);

        // Aunque nadie lo hubiera tomado formalmente, queda el rastro de quién
        // lo revisó: fue esta misma persona.
        $this->assertSame($supervisor->id, $tramite->revisado_por);
    }

    /**
     * ESTA ES LA PRUEBA QUE MÁS IMPORTA DEL ORDEN.
     *
     * Un trámite en revisión no se puede entregar: no se emitió, no existe el
     * documento. Quien decide qué salto vale es EstadoTramite, no la pantalla.
     */
    public function test_no_se_puede_entregar_un_tramite_que_no_se_emitio(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs($this->operador())
            ->post(route('tramites.entregar', $tramite->id), ['modo_entrega' => 'fisica'])
            ->assertRedirect();

        $this->assertSame(EstadoTramite::EnRevision, $tramite->refresh()->estado);
        $this->assertNull($tramite->fecha_entrega);
    }

    public function test_no_se_puede_emitir_un_tramite_sin_aprobar(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs($this->supervisor())
            ->post(route('tramites.emitir', $tramite->id))
            ->assertRedirect();

        $this->assertDatabaseCount('documentos', 0);
        $this->assertSame(EstadoTramite::EnRevision, $tramite->refresh()->estado);
    }

    public function test_un_tramite_rechazado_no_admite_mas_cambios(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs($this->supervisor())
            ->post(route('tramites.rechazar', $tramite->id), [
                'motivo_rechazo' => 'La certificación de la asociación está vencida.',
            ])
            ->assertRedirect();

        $this->assertSame(EstadoTramite::Rechazado, $tramite->refresh()->estado);

        $this->actingAs($this->supervisor())
            ->post(route('tramites.aprobar', $tramite->id))
            ->assertRedirect();

        $this->assertSame(EstadoTramite::Rechazado, $tramite->refresh()->estado);
    }

    /**
     * El motivo es obligatorio: el pescador vuelve a preguntar por qué, y quien
     * lo atiende casi nunca es el mismo que rechazó.
     */
    public function test_no_se_puede_rechazar_sin_explicar_por_que(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs($this->supervisor())
            ->post(route('tramites.rechazar', $tramite->id), ['motivo_rechazo' => 'no'])
            ->assertSessionHasErrors('motivo_rechazo');

        $this->assertSame(EstadoTramite::EnRevision, $tramite->refresh()->estado);
    }

    /*
    |--------------------------------------------------------------------------
    | Los permisos, que no son los mismos en cada paso
    |--------------------------------------------------------------------------
    */

    /**
     * Quien atiende en ventanilla recepciona y entrega, pero NO aprueba. Sin
     * esa separación, un operador se aprueba a sí mismo el trámite que acaba
     * de cargar.
     */
    public function test_el_operador_no_puede_aprobar(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs($this->operador())
            ->post(route('tramites.aprobar', $tramite->id))
            ->assertForbidden();

        $this->assertSame(EstadoTramite::EnRevision, $tramite->refresh()->estado);
    }

    public function test_un_usuario_de_solo_lectura_no_puede_emitir(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs(User::role(RolSistema::SoloLectura->value)->firstOrFail())
            ->post(route('tramites.emitir', $tramite->id))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | La emisión
    |--------------------------------------------------------------------------
    */

    /**
     * Emitir dejó de ser un cambio de estado, así que el orden ya no lo
     * garantiza el enum: lo comprueba el controlador a mano. Estas tres
     * pruebas son las que cuidan esa parte.
     */
    public function test_un_tramite_en_revision_no_puede_emitir_documento(): void
    {
        $tramite = $this->tramiteEnRevision();

        $this->actingAs($this->supervisor())
            ->post(route('tramites.emitir', $tramite->id))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('documentos', 0);
    }

    /**
     * Dos clics seguidos —o dos operadores a la vez— no pueden quemar dos
     * números de la serie para el mismo trámite: serían dos credenciales
     * válidas de la misma persona en la calle.
     */
    public function test_no_se_emite_dos_veces_el_documento_del_mismo_tramite(): void
    {
        $tramite = $this->tramiteAprobado();
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)->post(route('tramites.emitir', $tramite->id));

        $this->actingAs($supervisor)
            ->post(route('tramites.emitir', $tramite->id))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('documentos', 1);
    }

    public function test_no_se_entrega_un_tramite_sin_documento_emitido(): void
    {
        $tramite = $this->tramiteAprobado();

        $this->actingAs($this->operador())
            ->post(route('tramites.entregar', $tramite->id), ['modo_entrega' => 'fisica'])
            ->assertSessionHas('error');

        $this->assertSame(EstadoTramite::Aprobado, $tramite->refresh()->estado);
    }

    public function test_al_emitir_nace_el_documento_con_su_codigo_de_verificacion(): void
    {
        $tramite = $this->tramiteAprobado();
        $supervisor = $this->supervisor();
        $anio = now()->format('Y');

        $this->actingAs($supervisor)
            ->post(route('tramites.emitir', $tramite->id))
            ->assertRedirect();

        $documento = Documento::firstOrFail();

        // Emitir ya no mueve el estado: el trámite sigue aprobado hasta que se
        // entrega. Lo que cambia es que ahora existe el documento.
        $this->assertSame(EstadoTramite::Aprobado, $tramite->refresh()->estado);
        $this->assertSame($tramite->id, $documento->tramite_id);
        $this->assertSame($supervisor->id, $documento->emitido_por);

        // Dieciséis caracteres sin I, O, 0 ni 1: el código se dicta por
        // teléfono y se tipea a mano cuando el QR está rayado.
        $this->assertMatchesRegularExpression(
            '/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{16}$/',
            $documento->codigo_verificacion,
        );

        // La cédula vale la gestión: vence el 31 de diciembre del año en que se
        // emitió, no a los 365 días. Ver App\Enums\VigenciaTipo.
        $this->assertSame("{$anio}-12-31", $documento->fecha_vencimiento->toDateString());
    }

    /**
     * El documento se lleva una copia congelada de lo impreso.
     *
     * Si el pescador se muda y corrige su ficha, la credencial que tiene en la
     * mano sigue diciendo la dirección vieja: la verificación pública tiene que
     * mostrar lo que dice el papel, no lo que dice la base hoy.
     */
    public function test_el_documento_guarda_una_copia_de_lo_que_quedo_impreso(): void
    {
        $tramite = $this->tramiteAprobado();
        $solicitante = $tramite->solicitante;

        $this->actingAs($this->supervisor())->post(route('tramites.emitir', $tramite->id));

        $snapshot = Documento::firstOrFail()->datos_snapshot;

        $this->assertSame($solicitante->nombreCompleto, $snapshot['solicitante']['nombre']);
        $this->assertSame($tramite->id, $snapshot['tramite']['numero']);

        // Se cambia la ficha DESPUÉS de emitir: la copia no se mueve.
        $solicitante->update(['direccion' => 'Otra dirección nueva']);

        $this->assertSame(
            'Puerto Almacén',
            Documento::firstOrFail()->datos_snapshot['solicitante']['direccion'],
        );
    }

    /**
     * Una credencial emitida ya está en la calle y el saldo se vuelve
     * incobrable. Por eso se cobra primero y se emite después.
     */
    public function test_no_se_emite_un_documento_con_saldo_pendiente(): void
    {
        $tramite = $this->tramiteAprobado();
        $tramite->forceFill(['monto_pagado' => 40])->save();

        $this->actingAs($this->supervisor())
            ->post(route('tramites.emitir', $tramite->id))
            ->assertRedirect();

        $this->assertDatabaseCount('documentos', 0);
        $this->assertSame(EstadoTramite::Aprobado, $tramite->refresh()->estado);
    }

    /**
     * Emitir la cédula es lo que habilita al pescador para todo lo demás: hasta
     * que el documento existe, el permiso por faena no se puede registrar.
     */
    public function test_recien_con_la_cedula_emitida_el_pescador_queda_habilitado(): void
    {
        $tramite = $this->tramiteAprobado();
        $solicitante = $tramite->solicitante;

        $this->assertFalse(
            TipoTramite::where('codigo', 'PPF')->firstOrFail()
                ->habilitacionPara($solicitante)->habilitado,
        );

        $this->actingAs($this->supervisor())->post(route('tramites.emitir', $tramite->id));

        $this->assertTrue(
            TipoTramite::where('codigo', 'PPF')->firstOrFail()
                ->habilitacionPara($solicitante->refresh())->habilitado,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | La entrega
    |--------------------------------------------------------------------------
    */

    public function test_el_operador_entrega_el_documento_emitido(): void
    {
        $tramite = $this->tramiteAprobado();
        $this->actingAs($this->supervisor())->post(route('tramites.emitir', $tramite->id));

        $operador = $this->operador();

        $this->actingAs($operador)
            ->post(route('tramites.entregar', $tramite->id), ['modo_entrega' => 'fisica'])
            ->assertRedirect();

        $tramite->refresh();

        $this->assertSame(EstadoTramite::Entregado, $tramite->estado);
        $this->assertSame('fisica', $tramite->modo_entrega);
        $this->assertSame($operador->id, $tramite->entregado_por);
        $this->assertNotNull($tramite->fecha_entrega);
    }

    public function test_hay_que_indicar_como_se_entrego(): void
    {
        $tramite = $this->tramiteAprobado();
        $this->actingAs($this->supervisor())->post(route('tramites.emitir', $tramite->id));

        $this->actingAs($this->operador())
            ->post(route('tramites.entregar', $tramite->id))
            ->assertSessionHasErrors('modo_entrega');

        // Emitir ya no mueve el estado: el trámite sigue aprobado hasta que se
        // entrega. Lo que cambia es que ahora existe el documento.
        $this->assertSame(EstadoTramite::Aprobado, $tramite->refresh()->estado);
    }

    /*
    |--------------------------------------------------------------------------
    | La verificación pública, que es el final del circuito
    |--------------------------------------------------------------------------
    */

    public function test_el_documento_emitido_se_verifica_desde_la_calle(): void
    {
        $tramite = $this->tramiteAprobado();
        $this->actingAs($this->supervisor())->post(route('tramites.emitir', $tramite->id));

        $codigo = Documento::firstOrFail()->codigo_verificacion;

        // Sin sesión: es la URL que está dentro del QR.
        $this->get(route('verificar.show', $codigo))->assertOk();
    }

    /**
     * El código se tipea a mano cuando el QR está rayado, y la gente lo escribe
     * como puede: en minúscula, con guiones, con espacios cada cuatro letras.
     */
    public function test_el_codigo_se_encuentra_aunque_se_escriba_con_separadores(): void
    {
        $tramite = $this->tramiteAprobado();
        $this->actingAs($this->supervisor())->post(route('tramites.emitir', $tramite->id));

        $codigo = Documento::firstOrFail()->codigo_verificacion;
        $conGuiones = strtolower(implode('-', str_split($codigo, 4)));

        $this->get(route('verificar.show', $conGuiones))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('documento.codigo_verificacion', $codigo));
    }

    /*
    |--------------------------------------------------------------------------
    | El formulario público, cuando el QR no se puede escanear
    |--------------------------------------------------------------------------
    */

    public function test_el_codigo_escrito_a_mano_tiene_que_tener_dieciseis_caracteres(): void
    {
        $this->post(route('verificar.buscar'), ['codigo' => 'ABC123'])
            ->assertSessionHasErrors('codigo');

        $this->post(route('verificar.buscar'), ['codigo' => ''])
            ->assertSessionHasErrors('codigo');
    }

    /**
     * El código se imprime en grupos —4K7R J2MX P9TQ 3WHB— y el ciudadano lo
     * copia tal cual. Los separadores se limpian ANTES de validar el largo: si
     * no, el código escrito correctamente sería rechazado por tener 19
     * caracteres.
     */
    public function test_los_separadores_no_hacen_fallar_la_validacion(): void
    {
        $tramite = $this->tramiteAprobado();
        $this->actingAs($this->supervisor())->post(route('tramites.emitir', $tramite->id));

        $codigo = Documento::firstOrFail()->codigo_verificacion;

        $this->post(route('verificar.buscar'), [
            'codigo' => strtolower(implode(' ', str_split($codigo, 4))),
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('verificar.show', $codigo));
    }

    public function test_el_codigo_no_acepta_simbolos_que_no_sean_letras_ni_numeros(): void
    {
        // Diecisiete caracteres con símbolos: al limpiarlos quedan menos de 16
        // y la validación lo rechaza en vez de ir a buscar cualquier cosa.
        $this->post(route('verificar.buscar'), ['codigo' => '<script>alert(1)</'])
            ->assertSessionHasErrors('codigo');
    }

    /*
    |--------------------------------------------------------------------------
    | Ayudantes
    |--------------------------------------------------------------------------
    */

    private function operador(): User
    {
        return User::role(RolSistema::Operador->value)->firstOrFail();
    }

    private function supervisor(): User
    {
        return User::role(RolSistema::Supervisor->value)->firstOrFail();
    }

    /** Una cédula de pescador recién registrada, con sus papeles y su pago. */
    private function tramiteEnRevision(): Tramite
    {
        $solicitante = Solicitante::factory()->create(['direccion' => 'Puerto Almacén']);
        $tipo = TipoTramite::where('codigo', 'CAP')->firstOrFail();

        $tramite = Tramite::create([
            'solicitante_id' => $solicitante->id,
            'tipo_tramite_id' => $tipo->id,
            'user_id' => $this->operador()->id,
            'estado' => EstadoTramite::EnRevision,
            'monto_total' => 80,
            'monto_pagado' => 80,
            'fecha_recepcion' => now(),
            'datos_adicionales' => [
                'asociacion' => 'SOC. IBARE - MAMORÉ',
                'capacidad_kg' => '600',
                // Los mismos que escribe el alta: el registro lo pone el
                // sistema y el nombre y el domicilio salen de la ficha.
                'registro' => '78T3-8K9T-789P',
                'nombre' => $solicitante->nombreCompleto,
                'direccion' => 'Puerto Almacén',
            ],
        ]);

        /*
         * Los adjuntos se escriben DESPUÉS de crear la fila, igual que en el
         * alta real: la carpeta lleva el id, y el id lo asigna la base al
         * insertar.
         */
        $tramite->forceFill([
            'requisitos_validados' => [
                'certificacion_asociacion' => $tramite->carpeta().'/certificacion-asociacion.pdf',
                'copia_ci' => $tramite->carpeta().'/copia-ci.jpg',
                'pagos' => [
                    [
                        'forma' => 'transferencia',
                        'nro_transaccion' => '884512203',
                        'banco' => 'Banco Unión',
                        'monto' => 80.0,
                        'archivo' => $tramite->carpeta().'/comprobante-pago-1.jpg',
                    ],
                ],
            ],
        ])->save();

        return $tramite;
    }

    private function tramiteAprobado(): Tramite
    {
        $tramite = $this->tramiteEnRevision();

        $tramite->forceFill([
            'estado' => EstadoTramite::Aprobado,
            'fecha_aprobacion' => now(),
            'aprobado_por' => $this->supervisor()->id,
        ])->save();

        return $tramite;
    }
}
