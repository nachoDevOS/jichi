import { Campo } from '@/components/ui/campo';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { CampoGuia, CatalogosGuia, FormularioGuia } from '@/types/guias';

// Oculta el 28/09/2026: el Art. 22 VIII del reglamento limita el 50% a ciertos
// productores, por 3 años y con registro previo, y eso todavía no se controla.
const MOSTRAR_PISCICULTURA = false;

/** Los renglones de los bloques B y C: obligatorios, igual que en EmitirGuiaRequest::reglasDelPapel(). */
export const CAMPOS_RUTA: CampoGuia[] = [
    'origen',
    'origen_departamento',
    'origen_provincia',
    'origen_distrito',
    'destino',
    'destino_departamento',
    'destino_provincia',
    'destino_distrito',
    'medio_transporte',
    'tipo_transporte',
    'transporte_nombre',
    'transporte_placa',
    'transporte_capacidad_kg',
];

/** Si la ruta y el transporte están completos; la capacidad, además, mayor que cero. */
export function rutaCompleta(datos: FormularioGuia): boolean {
    return CAMPOS_RUTA.every((c) => String(datos[c] ?? '').trim() !== '') && Number(datos.transporte_capacidad_kg) > 0;
}

/**
 * Los bloques B y C DEL PAPEL — la ubicación y el transporte.
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
        <div className="space-y-5">
            {/* B. Ubicación: origen y destino lado a lado. */}
            <section className="space-y-3">
                <Ubicacion
                    titulo="Origen"
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

            {/* C. Transporte */}
            <section className="space-y-3">
                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Transporte</h3>

                <div className="grid gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Campo etiqueta="Medio" htmlFor="medio_transporte" error={errores.medio_transporte} obligatorio>
                        <Select
                            id="medio_transporte"
                            value={datos.medio_transporte}
                            onChange={(e) => onCambio('medio_transporte', e.target.value)}
                            aria-invalid={Boolean(errores.medio_transporte)}
                        >
                            <option value="">Elija…</option>
                            {medios.map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </Select>
                    </Campo>

                    <Campo etiqueta="Vehículo" htmlFor="tipo_transporte" error={errores.tipo_transporte} obligatorio>
                        <Select
                            id="tipo_transporte"
                            value={datos.tipo_transporte}
                            onChange={(e) => onCambio('tipo_transporte', e.target.value)}
                            aria-invalid={Boolean(errores.tipo_transporte)}
                        >
                            <option value="">Elija…</option>
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
                        obligatorio
                    >
                        <Input
                            id="transporte_nombre"
                            value={datos.transporte_nombre}
                            onChange={(e) => onCambio('transporte_nombre', e.target.value)}
                            aria-invalid={Boolean(errores.transporte_nombre)}
                            placeholder="Camioneta Toyota Hilux"
                        />
                    </Campo>

                    <Campo etiqueta="Placa" htmlFor="transporte_placa" error={errores.transporte_placa} obligatorio>
                        <Input
                            id="transporte_placa"
                            value={datos.transporte_placa}
                            onChange={(e) => onCambio('transporte_placa', e.target.value.toUpperCase())}
                            aria-invalid={Boolean(errores.transporte_placa)}
                            placeholder="1234-ABC"
                            className="font-mono"
                        />
                    </Campo>

                    <Campo
                        etiqueta="Cap. máxima (kg)"
                        htmlFor="transporte_capacidad_kg"
                        error={errores.transporte_capacidad_kg}
                        obligatorio
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
            </section>

            {/* ─────────────────────────────── Lo que mueve el arancel */}
            {MOSTRAR_PISCICULTURA && (
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
            )}

            <Campo
                etiqueta="Observaciones"
                htmlFor="observaciones"
                error={errores.observaciones}
                ayuda="Se imprime en el recuadro del papel."
            >
                <Textarea
                    id="observaciones"
                    rows={2}
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
 * Los cuatro son obligatorios desde el 23/10/2026.
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
    const campo = (sufijo: '' | '_departamento' | '_provincia' | '_distrito') => `${prefijo}${sufijo}` as CampoGuia;

    return (
        <div className="space-y-2 rounded-lg border border-border bg-secondary/30 p-3">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{titulo}</p>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Campo etiqueta="Lugar" htmlFor={campo('')} error={errores[campo('')]} obligatorio>
                    <Input
                        id={campo('')}
                        value={datos[campo('')] as string}
                        onChange={(e) => onCambio(campo(''), e.target.value)}
                        aria-invalid={Boolean(errores[campo('')])}
                        placeholder={ejemplo}
                    />
                </Campo>

                <Campo
                    etiqueta="Departamento"
                    htmlFor={campo('_departamento')}
                    error={errores[campo('_departamento')]}
                    obligatorio
                >
                    <Input
                        id={campo('_departamento')}
                        value={datos[campo('_departamento')] as string}
                        onChange={(e) => onCambio(campo('_departamento'), e.target.value)}
                    />
                </Campo>

                <Campo
                    etiqueta="Provincia"
                    htmlFor={campo('_provincia')}
                    error={errores[campo('_provincia')]}
                    obligatorio
                >
                    <Input
                        id={campo('_provincia')}
                        value={datos[campo('_provincia')] as string}
                        onChange={(e) => onCambio(campo('_provincia'), e.target.value)}
                    />
                </Campo>

                <Campo
                    etiqueta="Distrito o cuenca"
                    htmlFor={campo('_distrito')}
                    error={errores[campo('_distrito')]}
                    obligatorio
                >
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
