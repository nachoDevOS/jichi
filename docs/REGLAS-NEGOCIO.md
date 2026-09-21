# Reglas de negocio y funcionales — Sistema Jichi

> **Definidas por el responsable del proyecto el 20/09/2026.** Es la
> especificación: cuando el código y este documento no coincidan, **manda este
> documento** y lo que falta es trabajo por hacer —anotado al final—.
>
> El esquema que las sostiene está en [MER.md](MER.md).

---

## Paso 1 — El registro base (Beneficiarios)

- Todo trámite del sistema nace a partir de una persona registrada en la tabla
  **`beneficiarios`**.
- Se identifica de manera única por su número de carnet de identidad (`ci`), que
  incluye de forma opcional su **complemento** y el departamento de expedición
  (**`expedido`**).
- Almacena de forma rigurosa los datos personales —nombres, apellidos, fecha de
  nacimiento, género y nacionalidad— y los datos de contacto o ubicación
  —dirección, ciudad, provincia, teléfono y correo—.
- Cuenta con **borrado lógico** (`deleted_at`) para preservar la integridad
  histórica de los registros ante auditorías o trámites pasados.

## Paso 2 — La extracción y la «Bolsa Madre» (Aprovechamiento Pesquero)

- Este módulo aplica **exclusivamente para pescadores**.
- Está regulado por escalas predefinidas en **`categorias_aprovechamiento`**, que
  establecen un número de escala, un rango de kilos mínimos y un **techo máximo
  de extracción** (`kilos_max`), además del costo oficial en bolivianos
  (`valor_bs`).
- Cuando un pescador solicita un cupo se genera un registro en
  **`aprovechamientos_pesq`** que **hereda** la modalidad y el volumen total de
  kilos de la escala elegida.
- Su ciclo de vida pasa por los estados `pendiente`, `en_revision`, `aprobado`,
  `vencido` o `agotado`, dependiendo de la vigencia de la gestión.

## Paso 3 — La identificación oficial (Carnets)

- El carnet es el **documento habilitante** que define el rol del actor mediante
  el campo **`tipo_actor`**: `pescador` o `comercializador`.
- **Regla para el pescador:** su registro debe estar vinculado
  **obligatoriamente** a un `aprovechamiento_id`. A partir de una misma «Bolsa
  Madre» el sistema permite gestionar y emitir los carnets necesarios.
- **Regla para el comercializador:** su `aprovechamiento_id` se registra en
  **NULL**, porque no realiza actividades extractivas.
- Todo carnet se clasifica según su `tipo_carnet_id` y cuenta con el **aval
  obligatorio** de una asociación (`asociacion_id`) y un **código único global**
  (`codigo_carnet`).

## Paso 4 — La operativa del pescador (Permisos de Faena)

- Depende **estrictamente del carnet** del pescador: cada permiso se vincula de
  forma directa **únicamente a un `carnet_id`**.
- Controla la salida mediante un **número de faena correlativo** y registra los
  `kilos_extraidos` de cada salida.
- Esos kilos impactan **indirectamente** en el volumen total del aprovechamiento
  raíz al que está asociado el carnet.
- Vigencia **máxima de 30 días** por salida (`fecha_salida` y `fecha_limite`),
  con los estados `activo`, `completado` o `vencido`.

## Paso 5 — La operativa del comercializador (Guías Únicas de Transporte)

- Aplica **exclusivamente** al traslado de producto por parte de los
  comercializadores.
- La guía (**`guias_movimiento`**) se vincula de forma **directa al carnet** del
  comercializador (`carnet_id`) y a su respectiva asociación (`asociacion_id`).
- Controla las rutas especificando `origen`, `destino`, `peso_total_kg` y un
  indicador booleano de si proviene de **piscicultura**.
- Vigencia de transporte de **máximo 5 días**, con los estados `activa`,
  `cerrada` o `anulada`.

## Paso 6 — El ciclo financiero y contable (Recibos y Pagos)

- **Emisión de recibos.** Se emite un recibo a nombre del beneficiario con un
  número único (`numero_recibo`), **congelando de forma definitiva** el monto
  total y el concepto tarifario al momento de generarse.
- **Pagos polimórficos.** La tabla `pagos` usa una estructura polimórfica
  (`pagable_type` + `pagable_id`) que permite amortizar o cancelar los costos
  asociados indistintamente a un **Aprovechamiento**, a un **Carnet** o a una
  **Guía de Movimiento**.
- **Validación en ventanilla.** Cada transacción financiera registra el
  comprobante de depósito, la fecha, el número de transacción **único a nivel
  global**, el usuario de ventanilla que registró (`registrado_por`), el
  supervisor que validó (`validado_por`) y el estado de la verificación
  (`pendiente`, `validado` u `observado`).

---

## El flujo, en un vistazo

```
                    ┌──────────────────────────────────────────────┐
                    │              BENEFICIARIO                    │
                    └───────────────┬──────────────┬───────────────┘
                     pescador       │              │   comercializador
                                    ▼              ▼
              APROVECHAMIENTO PESQUERO        CARNET (aprovechamiento_id = NULL)
              (la bolsa madre, en kilos)               │
                          │                            ▼
                          ▼                     GUÍA DE MOVIMIENTO
                  CARNET (con cupo)             (máx. 5 días, por traslado)
                          │
                          ▼
                  PERMISO DE FAENA
              (máx. 30 días, por salida;
               descuenta kilos del cupo
               a través del carnet)

   Los tres documentos se cobran con la MISMA tabla `pagos`, y el papel que
   se entrega es el RECIBO.
```

---

## Lo que el código todavía no cumple

| Regla | Estado | Qué falta |
| --- | --- | --- |
| Paso 5 — la guía se vincula **al carnet** | ⚠️ **No cumplida** | `guias_movimiento` guarda `beneficiario_com_id` + `asociacion_id`, no `carnet_id`. El servicio ya recibe el carnet y copia los dos datos de él, así que el cambio es la misma operación que se hizo con `permisos_faena` el 20/09/2026: quitar la columna vieja, poner `carnet_id` y ajustar modelo, servicio, controlador y pantallas |

Los pasos 1, 2, 3, 4 y 6 están implementados tal como se describen arriba.
