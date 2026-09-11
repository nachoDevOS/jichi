<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Solicitante;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas del módulo Solicitantes.
 *
 * PARA QUÉ SIRVE ESTO
 *
 * Cada prueba levanta una base de datos vacía, la siembra, hace peticiones
 * como si fuera un navegador y comprueba el resultado. Si mañana alguien toca
 * el controlador y rompe la búsqueda, `php artisan test` lo dice en segundos
 * en vez de que lo descubra ventanilla con gente esperando.
 *
 * RefreshDatabase corre todas las migraciones antes de cada prueba y deshace
 * los cambios al terminar: las pruebas nunca se ensucian entre sí.
 */
class SolicitanteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // No se usa DemoSeeder a propósito: genera datos al azar y las pruebas
        // tienen que dar siempre el mismo resultado. Cada prueba crea lo suyo.
        $this->seed([
            RolPermisoSeeder::class,
            ConfiguracionSeeder::class,
            AreaSeeder::class,
            UsuarioSeeder::class,
        ]);

        // Disco falso: las fotos de las pruebas no ensucian storage/.
        Storage::fake('public');
    }

    /** Un operador de ventanilla, que es quien usa este módulo todo el día. */
    private function operador(): User
    {
        return User::role(RolSistema::Operador->value)->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | Acceso y permisos
    |--------------------------------------------------------------------------
    */

    public function test_el_listado_exige_iniciar_sesion(): void
    {
        $this->get(route('solicitantes.index'))->assertRedirect(route('login'));
    }

    public function test_el_operador_ve_el_listado(): void
    {
        $this->actingAs($this->operador())
            ->get(route('solicitantes.index'))
            ->assertOk();
    }

    public function test_un_usuario_de_solo_lectura_no_puede_crear(): void
    {
        $consulta = User::role(RolSistema::SoloLectura->value)->firstOrFail();

        // 403 = el middleware 'permiso:solicitantes.crear' lo frenó.
        $this->actingAs($consulta)
            ->get(route('solicitantes.create'))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Listado, búsqueda y filtros
    |--------------------------------------------------------------------------
    */

    public function test_la_busqueda_filtra_por_nombre(): void
    {
        Solicitante::factory()->create([
            'primerNombre' => 'Juan',
            'segundoNombre' => null,
            'apellidoPaterno' => 'Pérez',
            'apellidoMaterno' => null,
        ]);
        Solicitante::factory()->create([
            'primerNombre' => 'María',
            'segundoNombre' => null,
            'apellidoPaterno' => 'Suárez',
            'apellidoMaterno' => null,
        ]);

        $this->actingAs($this->operador())
            ->get(route('solicitantes.index', ['buscar' => 'Pérez']))
            ->assertOk()
            // assertInertia mira las props que recibió el componente de React.
            ->assertInertia(fn ($pagina) => $pagina
                ->component('panel/solicitantes/index')
                ->has('solicitantes.data', 1)
                ->where('solicitantes.data.0.nombreCompleto', 'Juan Pérez'));
    }

    public function test_se_puede_elegir_cuantas_filas_muestra_el_padron(): void
    {
        Solicitante::factory()->count(32)->create();

        $this->actingAs($this->operador())
            ->get(route('solicitantes.index'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('solicitantes.data', 15)
                ->where('filtros.por_pagina', 15)
                // El selector se dibuja con la lista que manda el servidor.
                ->where('opcionesPorPagina', [15, 30, 50]));

        $this->actingAs($this->operador())
            ->get(route('solicitantes.index', ['por_pagina' => 30]))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('solicitantes.data', 30)
                ->where('filtros.por_pagina', 30));
    }

    /**
     * El número de filas llega por la barra de direcciones, así que cualquiera
     * puede escribir lo que quiera. La lista cerrada de App\Support\Paginacion
     * es lo que impide que un ?por_pagina=500000 traiga el padrón entero a
     * memoria.
     */
    public function test_un_tamano_de_pagina_inventado_se_ignora_en_el_padron(): void
    {
        Solicitante::factory()->count(20)->create();

        foreach ([500000, 7, -3, 0, 'muchas'] as $intento) {
            $this->actingAs($this->operador())
                ->get(route('solicitantes.index', ['por_pagina' => $intento]))
                ->assertOk()
                ->assertInertia(fn ($pagina) => $pagina
                    ->has('solicitantes.data', 15)
                    ->where('filtros.por_pagina', 15));
        }
    }

    /**
     * La trampa que documenta CLAUDE.md: sin `->withQueryString()`, al tocar
     * «página 2» se pierden los filtros y la búsqueda vuelve a empezar.
     */
    public function test_la_busqueda_y_el_tamano_sobreviven_al_cambio_de_pagina(): void
    {
        Solicitante::factory()->count(32)->create(['apellidoPaterno' => 'Moxeño']);

        $this->actingAs($this->operador())
            ->get(route('solicitantes.index', ['buscar' => 'moxeño', 'por_pagina' => 30]))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('solicitantes.data', 30)
                ->where('solicitantes.total', 32)
                ->where('solicitantes.links.1.url', fn (?string $url) => str_contains((string) $url, 'buscar=')
                    && str_contains((string) $url, 'por_pagina=30')));
    }

    /**
     * Un solicitante dado de baja no aparece en el padrón.
     *
     * No hace falta ninguna condición en la consulta: el trait SoftDeletes ya
     * excluye solo las filas con `deleted_at`. La prueba está para que quede
     * fijado, porque `deleted_at` es el ÚNICO estado que tiene un solicitante
     * —no existe una columna `activo` aparte.
     */
    public function test_un_solicitante_dado_de_baja_no_aparece_en_el_listado(): void
    {
        Solicitante::factory()->create();
        Solicitante::factory()->create()->delete();

        $this->actingAs($this->operador())
            ->get(route('solicitantes.index'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->has('solicitantes.data', 1));
    }

    /*
    |--------------------------------------------------------------------------
    | Alta
    |--------------------------------------------------------------------------
    */

    public function test_se_registra_un_solicitante_con_el_nombre_partido(): void
    {
        $operador = $this->operador();

        $this->actingAs($operador)
            ->post(route('solicitantes.store'), [
                'ci_nit' => '4821779',
                'complemento' => '1a',
                'expedido' => 'bn',
                'primerNombre' => 'Rosa',
                'segundoNombre' => 'Elena',
                'apellidoPaterno' => 'Justiniano',
                'apellidoMaterno' => 'Roca',
                'ciudad' => 'Trinidad',
                'provincia' => 'Cercado',
                'telefono' => '71234567',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('solicitantes', [
            'ci_nit' => '4821779',
            // prepareForValidation() los pasó a mayúsculas antes de validar.
            'complemento' => '1A',
            'expedido' => 'BN',
            'primerNombre' => 'Rosa',
            'apellidoPaterno' => 'Justiniano',
            'ciudad' => 'Trinidad',
            'provincia' => 'Cercado',
        ]);

        // Quién lo cargó no está en `solicitantes`: lo guarda el trait
        // Auditable en su propia tabla.
        $this->assertDatabaseHas('auditorias', [
            'user_id' => $operador->id,
            'auditable_type' => Solicitante::class,
            'evento' => 'creado',
        ]);
    }

    /**
     * El nombre completo NO es una columna: se arma cada vez que se pide.
     *
     * Esta prueba existe porque el «de» del apellido de casada se agrega al
     * concatenar y no está guardado en la base. Si alguien lo guardara adentro
     * de la columna, acá saldría «de de Áñez» y la prueba lo cazaría.
     */
    public function test_el_nombre_completo_se_arma_con_el_de_del_apellido_de_casada(): void
    {
        $solicitante = Solicitante::factory()->create([
            'primerNombre' => 'Rosa',
            'segundoNombre' => 'Elena',
            'apellidoPaterno' => 'Justiniano',
            'apellidoMaterno' => 'Roca',
            'apellidoCasada' => 'Áñez',
        ]);

        $this->assertSame('Rosa Elena Justiniano Roca de Áñez', $solicitante->nombreCompleto);
    }

    /**
     * Quien no tiene segundo nombre ni apellido materno no debe quedar con
     * espacios dobles en el medio del nombre impreso.
     */
    public function test_el_nombre_completo_omite_las_partes_vacias(): void
    {
        $solicitante = Solicitante::factory()->create([
            'primerNombre' => 'Juan',
            'segundoNombre' => null,
            'apellidoPaterno' => 'Pérez',
            'apellidoMaterno' => null,
            'apellidoCasada' => null,
        ]);

        $this->assertSame('Juan Pérez', $solicitante->nombreCompleto);
    }

    /**
     * La cédula se imprime con su complemento y su lugar de expedición:
     * «7656924-1A BN». Es el texto exacto que va en la credencial.
     */
    public function test_el_documento_de_identidad_junta_cedula_complemento_y_expedido(): void
    {
        $solicitante = Solicitante::factory()->create([
            'ci_nit' => '7656924',
            'complemento' => '1A',
            'expedido' => 'BN',
        ]);

        $this->assertSame('7656924-1A BN', $solicitante->documento_identidad);
    }

    /**
     * El lugar de expedición sale de la lista del SEGIP: son nueve códigos
     * fijos, y aceptar cualquier cosa dejaría credenciales que dicen «XX».
     */
    public function test_no_se_acepta_un_lugar_de_expedicion_inventado(): void
    {
        $this->actingAs($this->operador())
            ->post(route('solicitantes.store'), [
                'ci_nit' => '1234567',
                'expedido' => 'XX',
                'primerNombre' => 'Juan',
                'apellidoPaterno' => 'Pérez',
            ])
            ->assertSessionHasErrors('expedido');
    }

    public function test_un_solicitante_sin_primer_nombre_no_se_guarda(): void
    {
        $this->actingAs($this->operador())
            ->post(route('solicitantes.store'), [
                'ci_nit' => '1234567',
            ])
            ->assertSessionHasErrors(['primerNombre', 'apellidoPaterno']);

        $this->assertDatabaseCount('solicitantes', 0);
    }

    /**
     * Hay pescadores con un solo apellido. Pedir los dos los dejaría fuera del
     * sistema, así que la regla exige AL MENOS uno.
     */
    public function test_alcanza_con_un_solo_apellido(): void
    {
        $this->actingAs($this->operador())
            ->post(route('solicitantes.store'), [
                'ci_nit' => '9876543',
                'primerNombre' => 'Marcelo',
                'apellidoMaterno' => 'Suárez',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('solicitantes', [
            'ci_nit' => '9876543',
            'apellidoPaterno' => null,
            'apellidoMaterno' => 'Suárez',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | La fotografia
    |--------------------------------------------------------------------------
    */

    /**
     * El limite de peso es UNO para todo el sistema y sale de config/jichi.php.
     * La prueba lo lee de ahi en vez de escribir el numero a mano: si manana
     * cambia, esto sigue probando el limite real.
     */
    public function test_la_foto_no_puede_pasar_del_peso_maximo(): void
    {
        $kb = (int) config('jichi.archivos.max_kb');

        $this->actingAs($this->operador())
            ->post(route('solicitantes.store'), [
                'ci_nit' => '5550001',
                'primerNombre' => 'Marcelo',
                'apellidoPaterno' => 'Suarez',
                'foto' => UploadedFile::fake()->create('grande.jpg', $kb + 1, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertDatabaseCount('solicitantes', 0);
    }

    /** Justo en el limite si entra: el rechazo es a partir de pasarse. */
    public function test_una_foto_del_peso_maximo_exacto_se_acepta(): void
    {
        $kb = (int) config('jichi.archivos.max_kb');

        $this->actingAs($this->operador())
            ->post(route('solicitantes.store'), [
                'ci_nit' => '5550002',
                'primerNombre' => 'Marcelo',
                'apellidoPaterno' => 'Suarez',
                'foto' => UploadedFile::fake()->create('justo.jpg', $kb, 'image/jpeg'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Solicitante::firstOrFail()->foto);
    }

    /** La foto se imprime en la credencial: un PDF ahi no se puede dibujar. */
    public function test_la_foto_no_puede_ser_un_pdf(): void
    {
        $this->actingAs($this->operador())
            ->post(route('solicitantes.store'), [
                'ci_nit' => '5550003',
                'primerNombre' => 'Marcelo',
                'apellidoPaterno' => 'Suarez',
                'foto' => UploadedFile::fake()->create('carnet.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertDatabaseCount('solicitantes', 0);
    }

    public function test_no_se_puede_repetir_el_mismo_ci(): void
    {
        Solicitante::factory()->create(['ci_nit' => '4821779', 'complemento' => null]);

        $this->actingAs($this->operador())
            ->post(route('solicitantes.store'), [
                'ci_nit' => '4821779',
                'primerNombre' => 'Otro',
                'apellidoPaterno' => 'Distinto',
            ])
            ->assertSessionHasErrors('ci_nit');

        $this->assertDatabaseCount('solicitantes', 1);
    }

    /**
     * Comprueba el índice único PARCIAL de la migración 2026_09_08_100000.
     *
     * La validación del formulario ya frena los duplicados, pero un seeder, un
     * comando de importación o una consulta a mano no pasan por ahí. La base de
     * datos tiene que ser la última línea de defensa.
     *
     * Antes de esa migración este test fallaba: el índice incluía deleted_at y
     * como NULL nunca es igual a NULL, dejaba pasar el duplicado.
     */
    public function test_la_base_de_datos_rechaza_dos_solicitantes_vivos_con_el_mismo_ci(): void
    {
        Solicitante::factory()->create(['ci_nit' => '4821779', 'complemento' => null]);

        $this->expectException(QueryException::class);

        Solicitante::factory()->create(['ci_nit' => '4821779', 'complemento' => null]);
    }

    /**
     * La otra mitad de la regla: un registro dado de baja NO debe estorbar.
     * Si un solicitante se eliminó por error, se lo tiene que poder volver a
     * cargar con la misma cédula.
     */
    public function test_un_solicitante_dado_de_baja_libera_su_ci(): void
    {
        $original = Solicitante::factory()->create(['ci_nit' => '4821779', 'complemento' => null]);
        $original->delete();

        $nuevo = Solicitante::factory()->create(['ci_nit' => '4821779', 'complemento' => null]);

        $this->assertNotSame($original->id, $nuevo->id);
        $this->assertDatabaseCount('solicitantes', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Edición
    |--------------------------------------------------------------------------
    */

    public function test_se_editan_los_datos_de_un_solicitante(): void
    {
        $solicitante = Solicitante::factory()->create([
            'primerNombre' => 'Juan',
            'apellidoPaterno' => 'Perez',
            'telefono' => '70000000',
        ]);

        $this->actingAs($this->operador())
            ->put(route('solicitantes.update', $solicitante), [
                'ci_nit' => $solicitante->ci_nit,
                'complemento' => $solicitante->complemento,
                'primerNombre' => 'Juan',
                'segundoNombre' => 'Carlos',
                'apellidoPaterno' => 'Pérez',
                'apellidoMaterno' => 'Áñez',
                'telefono' => '71111111',
            ])
            ->assertRedirect(route('solicitantes.show', $solicitante));

        $this->assertDatabaseHas('solicitantes', [
            'id' => $solicitante->id,
            'segundoNombre' => 'Carlos',
            'apellidoPaterno' => 'Pérez',
            'apellidoMaterno' => 'Áñez',
            'telefono' => '71111111',
        ]);

        $this->assertSame('Juan Carlos Pérez Áñez', $solicitante->fresh()->nombreCompleto);
    }

    public function test_editar_sin_cambiar_el_ci_no_choca_con_la_regla_de_unicidad(): void
    {
        $solicitante = Solicitante::factory()->create(['ci_nit' => '4821779']);

        $this->actingAs($this->operador())
            ->put(route('solicitantes.update', $solicitante), [
                'ci_nit' => '4821779',
                'complemento' => $solicitante->complemento,
                'primerNombre' => $solicitante->primerNombre,
                'apellidoPaterno' => $solicitante->apellidoPaterno,
            ])
            ->assertSessionHasNoErrors();
    }

    /*
    |--------------------------------------------------------------------------
    | Baja
    |--------------------------------------------------------------------------
    */

    public function test_un_solicitante_sin_tramites_se_da_de_baja(): void
    {
        $solicitante = Solicitante::factory()->create();
        $admin = User::role(RolSistema::Administrador->value)->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('solicitantes.destroy', $solicitante))
            ->assertRedirect(route('solicitantes.index'));

        // Borrado lógico: la fila sigue existiendo con deleted_at puesto.
        $this->assertSoftDeleted('solicitantes', ['id' => $solicitante->id]);
    }

    /**
     * Dar de baja a alguien con trámites NO puede romperle el historial.
     *
     * El borrado es lógico, así que la fila sigue ahí. Lo que esta prueba
     * cuida de verdad es el `withTrashed()` de `Tramite::solicitante()`: sin
     * él, el titular del trámite llegaría como null y el listado, el
     * dashboard y la verificación pública se caerían al pedirle el nombre.
     */
    public function test_dar_de_baja_no_rompe_el_historial_de_tramites(): void
    {
        $admin = User::role(RolSistema::Administrador->value)->firstOrFail();
        $solicitante = Solicitante::factory()->create();

        $tramite = Tramite::create([
            'solicitante_id' => $solicitante->id,
            'tipo_tramite_id' => TipoTramite::firstOrFail()->id,
            'user_id' => $admin->id,
            'monto_total' => 100,
        ]);

        $this->actingAs($admin)
            ->delete(route('solicitantes.destroy', $solicitante))
            ->assertRedirect(route('solicitantes.index'));

        $this->assertSoftDeleted('solicitantes', ['id' => $solicitante->id]);

        // El trámite sigue sabiendo a nombre de quién está.
        $this->assertSame(
            $solicitante->nombreCompleto,
            $tramite->fresh()->solicitante->nombreCompleto,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ficha
    |--------------------------------------------------------------------------
    */

    public function test_la_ficha_muestra_el_solicitante_y_su_historial(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($this->operador())
            ->get(route('solicitantes.show', $solicitante))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('panel/solicitantes/ver')
                ->where('solicitante.id', $solicitante->id)
                ->has('tramites')
                ->has('deuda'));
    }
}
