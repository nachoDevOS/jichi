# Reportes por tipo de servicio

> 24/11/2026. Administración › Reportes (`/panel/reportes`).

Se elige el **tipo de servicio** (autorización de pesca, carnet, permiso de faena o guía de
transporte, los de `ConceptoArancel`), el **rango de fechas** y, si se quiere, el **estado** y
el **origen** (Ventanilla SEDAG, Portal web o App BeniDigital; 26/11/2026).
Sale el detalle de cada trámite de ese servicio, con sus totales, en pantalla, en **Excel** y
en **PDF**. Los tres usan los mismos filtros, que van en la URL.

## Qué muestra

- **La fecha es la de solicitud** (`fecha_solicitud`). Orden: del más viejo al más nuevo.
- **Columnas comunes:** fecha, N°, titular (en la guía, el comercializador), C.I., monto,
  **origen** (desde dónde se registró: `origen_tramite`, 26/11/2026), estado y N° de recibo si
  ya se cobró.
- **Columnas propias:**

| Servicio | Columnas propias |
|---|---|
| Autorización de pesca | Escala, kg autorizados |
| Carnet | Actividad, asociación |
| Permiso de faena | Embarcación, kg |
| Guía de transporte | Origen → destino, kg |

- **Totales:** trámites, monto liquidado, **cobrado** (la suma de sus recibos) y **por
  cobrar** (el monto de los pendientes).

## Detalles

- **El estado se valida contra los de ese servicio**: uno que no corresponde se ignora; no
  llega texto libre a la consulta. Al cambiar de servicio, el estado elegido se borra.
- **El origen** se valida contra `OrigenTramite` (`Rule::enum`). El PDF lo dice en el
  subtítulo («· Origen: App BeniDigital») cuando se filtró.
- **El último día del rango entra** (corregido el 26/11/2026): SQLite guarda un `date` como
  «AAAA-MM-DD 00:00:00», así que `BETWEEN '…-01' AND '…-26'` dejaba afuera lo de ese día —el
  rango por defecto termina HOY—. El límite superior va con `23:59:59`, que en PostgreSQL
  también sirve. La prueba vieja usaba fechas a mitad de mes y no lo veía.
- **Excel** = CSV para Excel en español (`App\Support\ExportadorCsv`): UTF-8 con BOM, `;` y
  coma decimal. Un texto que empieza con `=`, `+`, `-` o `@` va con un apóstrofo adelante,
  para que Excel no lo ejecute como fórmula. Va con `lazy()` y no con `lazyById()`: la
  consulta ordena por fecha, y `lazyById()` pagina por id y se saltearía filas.
- **PDF** = A4 apaisado con membrete, totales, detalle (DomPDF repite el encabezado en cada
  hoja) y «Página X de Y». Vista: `resources/views/documentos/reporte-servicio.blade.php`.
  Trae todas las filas del rango: para un rango muy largo, conviene el Excel.
- **Sin permiso propio, a pedido del responsable**: lo ve todo funcionario. El grupo del
  panel exige `funcionario`, así que el público y el portal no entran (hay prueba). Se aparta
  de la regla 5. Para restringirlo: `permiso:reportes.ver` en las tres rutas y
  `permiso: "reportes.ver"` en `navegacion.ts` (el permiso ya existe en `RolSistema`).

## Las piezas

| Pieza | Qué hace |
|---|---|
| `app/Services/ReportePorServicioService.php` | Modelo, columnas, fila y totales de cada servicio |
| `app/Http/Controllers/Panel/ReporteController.php` | `index()`, `excel()` y `pdf()` |
| `app/Support/ExportadorCsv.php` | El CSV para Excel |
| `resources/js/pages/panel/reportes/index.tsx` | La pantalla |
| `tests/Feature/Reportes/ReportePorServicioTest.php` | 5 pruebas |
| `tests/Feature/Tramites/OrigenTramiteTest.php` | El filtro por origen (`test_el_reporte_filtra_por_origen`) |
