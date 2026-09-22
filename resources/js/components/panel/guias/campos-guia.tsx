import { Campo } from '@/components/ui/campo';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { CampoGuia, CatalogosGuia, FormularioGuia } from '@/types/guias';

/**
 * LOS BLOQUES B y C DEL PAPEL — la ubicación y el transporte.
 *
 * Vive acá y no en cada pantalla porque lo comparten emitir y corregir: escrito
 * dos veces, el día que el talonario sume un renglón se agrega en uno y el otro
 * sigue emitiendo guías incompletas.
 */
export function CamposGuia({
    datos,
    errores,
    medios,
    tiposTransporte,
    descuentoPiscicultura,
    onCambio,
}: {
    datos: FormularioGuia;
    errores: Partial<Record<string, string>>;
    medios: CatalogosGuia['medios'];
    tiposTransporte: CatalogosGuia['tiposTransporte'];
    descuentoPiscicultura: number;
    onCambio: (campo: CampoGuia, valor: string | boolean) => void;
}) {
    return (
        <div className="space-y-6">
            {/* ─────────────────────────────── B.- UBICACIÓN */}
            <section className="space-y-4">
                <h3 className="text-sm font-semibold">B. Ubicación</h3>

                <Ubicacion
                    titulo="Producción u origen"
                    prefijo="origen"
                    datos={datos}
                    errores={errores}
                    onCambio={onCambio}
                    ejemplo="Puerto Almacén"
                />

                <Ubicacion
                    titulo="Destino"
                    prefijo="destino"
                    datos={datos}
                    errores={errores}
                    onCambio={onCambio}
                    ejemplo="Santa Cruz de la Sierra"
                />
            </section>

            {/* ─────────────────────────────── C.- TRANSPORTE */}
            <section className="space-y-4">
                <h3 className="text-sm font-semibold">C. Transporte</h3>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo
                        etiqueta="Medio"
                        htmlFor="medio_transporte"
                        error={errores.medio_transporte}
                        ayuda="El casillero 10 del papel: por dónde viaja."
                    >
                        <Select
                            id="medio_transporte"
                            value={datos.medio_transporte}
                            onChange={(e) => onCambio('medio_transporte', e.target.value)}
                            aria-invalid={Boolean(errores.medio_transporte)}
                        >
                            <option value="">— sin indicar —</option>
                            {medios.map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </Select>
                    </Campo>

                    <Campo
                        etiqueta="Vehículo"
                        htmlFor="tipo_transporte"
                        error={errores.tipo_transporte}
                        ayuda="Los renglones a/b/c del bloque C: en qué se traslada."
                    >
                        <Select
                            id="tipo_transporte"
                            value={datos.tipo_transporte}
                            onChange={(e) => onCambio('tipo_transporte', e.target.value)}
                            aria-invalid={Boolean(errores.tipo_transporte)}
                        >
                            <option value="">— sin indicar —</option>
                            {tiposTransporte.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </Select>
                    </Campo>

                    <Campo
                        etiqueta="Nombre o tipo"
                        htmlFor="transporte_nombre"
                        error={errores.transporte_nombre}
                    >
                        <Input
                            id="transporte_nombre"
                            value={datos.transporte_nombre}
                            onChange={(e) => onCambio('transporte_nombre', e.target.value)}
                            aria-invalid={Boolean(errores.transporte_nombre)}
                            placeholder="Camioneta Toyota Hilux"
                        />
                    </Campo>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo
                            etiqueta="Placa"
                            htmlFor="transporte_placa"
                            error={errores.transporte_placa}
                        >
                            <Input
                                id="transporte_placa"
                                value={datos.transporte_placa}
                                onChange={(e) =>
                                    onCambio('transporte_placa', e.target.value.toUpperCase())
                                }
                                aria-invalid={Boolean(errores.transporte_placa)}
                                placeholder="1234-ABC"
                                className="font-mono"
                            />
                        </Campo>

                        <Campo
                            etiqueta="Cap. máxima (kg)"
                            htmlFor="transporte_capacidad_kg"
                            error={errores.transporte_capacidad_kg}
                        >
                            <Input
                                id="transporte_capacidad_kg"
                                type="number"
                                step="0.01"
                                min={0}
                                value={datos.transporte_capacidad_kg}
                                onChange={(e) => onCambio('transporte_capacidad_kg', e.target.value)}
                                aria-invalid={Boolean(errores.transporte_capacidad_kg)}
                            />
                        </Campo>
                    </div>
                </div>
            </section>

            {/* ─────────────────────────────── Lo que mueve el arancel */}
            <Campo
                etiqueta="Origen del producto"
                error={errores.es_piscicultura}
                ayuda="El pescado de criadero no sale del río, así que no consume el recurso que la tasa protege."
            >
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={datos.es_piscicultura}
                        onChange={(e) => onCambio('es_piscicultura', e.target.checked)}
                        className="size-4 rounded border-input"
                    />
                    Es producto de <strong>piscicultura</strong> — paga el{' '}
                    {Math.round((1 - descuentoPiscicultura) * 100)}% del arancel
                </label>
            </Campo>

            <Campo
                etiqueta="Observaciones"
                htmlFor="observaciones"
                error={errores.observaciones}
                ayuda="Se imprime en el recuadro del papel."
            >
                <Textarea
                    id="observaciones"
                    rows={3}
                    value={datos.observaciones}
                    onChange={(e) => onCambio('observaciones', e.target.value)}
                    aria-invalid={Boolean(errores.observaciones)}
                />
            </Campo>
        </div>
    );
}

/**
 * Un renglón del bloque B: lugar + departamento + provincia + distrito.
 *
 * Solo el LUGAR es obligatorio; lo demás llega incompleto del papel y frenarlo
 * dejaría al comerciante esperando por un dato que el talonario tampoco exige.
 */
function Ubicacion({
    titulo,
    prefijo,
    datos,
    errores,
    onCambio,
    ejemplo,
}: {
    titulo: string;
    prefijo: 'origen' | 'destino';
    datos: FormularioGuia;
    errores: Partial<Record<string, string>>;
    onCambio: (campo: CampoGuia, valor: string | boolean) => void;
    ejemplo: string;
}) {
    const campo = (sufijo: '' | '_departamento' | '_provincia' | '_distrito') =>
        `${prefijo}${sufijo}` as CampoGuia;

    return (
        <div className="rounded-md border border-border p-4">
            <p className="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {titulo}
            </p>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Campo
                    etiqueta="Lugar"
                    htmlFor={campo('')}
                    error={errores[campo('')]}
                    obligatorio
                >
                    <Input
                        id={campo('')}
                        value={datos[campo('')] as string}
                        onChange={(e) => onCambio(campo(''), e.target.value)}
                        aria-invalid={Boolean(errores[campo('')])}
                        placeholder={ejemplo}
                    />
                </Campo>

                <Campo etiqueta="Departamento" htmlFor={campo('_departamento')}>
                    <Input
                        id={campo('_departamento')}
                        value={datos[campo('_departamento')] as string}
                        onChange={(e) => onCambio(campo('_departamento'), e.target.value)}
                    />
                </Campo>

                <Campo etiqueta="Provincia" htmlFor={campo('_provincia')}>
                    <Input
                        id={campo('_provincia')}
                        value={datos[campo('_provincia')] as string}
                        onChange={(e) => onCambio(campo('_provincia'), e.target.value)}
                    />
                </Campo>

                <Campo etiqueta="Distrito o cuenca" htmlFor={campo('_distrito')}>
                    <Input
                        id={campo('_distrito')}
                        value={datos[campo('_distrito')] as string}
                        onChange={(e) => onCambio(campo('_distrito'), e.target.value)}
                    />
                </Campo>
            </div>
        </div>
    );
}
