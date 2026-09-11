<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Documento;
use App\Services\EmisionDocumentoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Verificación pública de autenticidad. Es la URL que apunta el QR impreso
 * en cada documento emitido, accesible sin iniciar sesión.
 *
 * Solo se expone lo mínimo para constatar la validez del documento: nunca
 * el CI completo, dirección ni teléfono del titular.
 */
class VerificacionController extends Controller
{
    public function show(Request $request, ?string $codigo = null): Response
    {
        $documento = $codigo ? $this->buscarPorCodigo($codigo) : null;

        // Se cuenta el escaneo sin disparar eventos de modelo. El porqué está
        // explicado en Documento::registrarVerificacion().
        $documento?->registrarVerificacion();

        return Inertia::render('publico/verificar', [
            'codigo' => $codigo,
            'documento' => $documento ? $this->datosPublicos($documento) : null,
            'encontrado' => $codigo === null ? null : $documento !== null,
            'institucion' => [
                'municipio' => Configuracion::obtener('municipio.nombre'),
                'sistema' => Configuracion::obtener('sistema.nombre', 'Jichi'),
                'pie_legal' => Configuracion::obtener('documentos.pie_legal'),
            ],
        ]);
    }

    /**
     * El código escrito a mano, cuando el QR no se puede escanear.
     *
     * SE NORMALIZA ANTES DE VALIDAR, y eso no es un detalle: el código se
     * imprime en grupos para poder leerlo —4K7R J2MX P9TQ 3WHB— y el ciudadano
     * lo copia tal cual, con espacios o guiones. Validando el texto crudo,
     * cualquiera de esas dos formas daría «el código no tiene 16 caracteres»
     * cuando en realidad está perfecto.
     *
     * El largo exacto sale de EmisionDocumentoService: dieciséis caracteres,
     * letras y números. Escribirlo acá a mano sería tener el mismo número en
     * dos lados, y el día que cambie el generador esta pantalla rechazaría
     * códigos que el sistema acaba de emitir.
     */
    public function buscar(Request $request): RedirectResponse
    {
        $request->merge(['codigo' => $this->normalizar((string) $request->input('codigo'))]);

        $largo = EmisionDocumentoService::LARGO_CODIGO;

        $validado = $request->validate([
            // alpha_num sobre el texto ya normalizado: lo que llegue con
            // símbolos raros queda vacío o corto y cae en las otras reglas.
            'codigo' => ['required', 'string', 'alpha_num', "size:{$largo}"],
        ], [
            'codigo.required' => 'Ingrese el código de verificación impreso en el documento.',
            'codigo.size' => "El código de verificación tiene {$largo} caracteres entre letras y números.",
            'codigo.alpha_num' => 'El código de verificación solo lleva letras y números.',
        ]);

        return redirect()->route('verificar.show', $validado['codigo']);
    }

    private function buscarPorCodigo(string $codigo): ?Documento
    {
        return Documento::query()
            ->with([
                'tramite.solicitante:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasada,ci_nit',
                'tramite.tipoTramite:id,nombre,area_id',
                'tramite.tipoTramite.area:id,nombre,icono',
            ])
            ->where('codigo_verificacion', $this->normalizar($codigo))
            ->first();
    }

    /**
     * Deja el código como está guardado: mayúsculas y sin separadores.
     *
     * El código se dicta por teléfono y se tipea a mano cuando el QR está
     * rayado o el celular no lo lee, y ahí la gente lo escribe como puede —con
     * guiones, con espacios cada cuatro letras, en minúscula—. Guardado sin
     * separadores, cualquiera de esas formas tenía que fallar. Limpiar lo que
     * llega cuesta una línea; explicarle a un inspector en la orilla del río
     * por qué «su documento no existe», no.
     */
    private function normalizar(string $codigo): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($codigo)));
    }

    /**
     * @return array<string, mixed>
     */
    private function datosPublicos(Documento $documento): array
    {
        $estado = $documento->estadoEfectivo();
        $solicitante = $documento->tramite->solicitante;

        return [
            'codigo_verificacion' => $documento->codigo_verificacion,
            'titular' => $solicitante->nombreCompleto,
            // Se enmascara el documento de identidad: solo los últimos 3 dígitos.
            'documento_titular' => str_repeat('•', max(0, strlen($solicitante->ci_nit) - 3))
                .substr($solicitante->ci_nit, -3),
            'tipo_documento' => $documento->tramite->tipoTramite->nombre,
            'categoria' => $documento->tipo->etiqueta(),
            'area' => $documento->tramite->tipoTramite->area->nombre,
            'icono_area' => $documento->tramite->tipoTramite->area->icono,
            'fecha_emision' => $documento->fecha_emision->toDateString(),
            'fecha_vencimiento' => $documento->fecha_vencimiento?->toDateString(),
            'estado' => $estado->value,
            'estado_etiqueta' => $estado->etiqueta(),
            'estado_color' => $estado->color(),
            'mensaje' => $estado->mensajePublico(),
            'es_valido' => $estado->esValido(),
        ];
    }
}
