# Pagos — se pagan y se validan en SIREB

> **Desde el 02/10/2026 el pago vive en Recaudaciones (SIREB).** Se retiraron la
> tabla `pagos`, la Caja, el control de boletas en Jichi (validar / observar /
> corregir) y el estado `en_revision`; están en el historial de git. Desde el
> 03/10/2026 Jichi puede **cargar** un pago en SIREB, pero **nunca validarlo**.

## Cómo se paga hoy

```
Jichi registra el documento ──▶ registra su LIQUIDACIÓN en SIREB (código de pago, 5 días para pagar)
                                        │
        el titular paga; el pago se CARGA en SIREB —allá, o desde la ficha con «Cargar pago»—
                                        │
                    el encargado de SIREB VALIDA el pago (Jichi no)
                                        │
Jichi pregunta (botón «Verificar pago» / jichi:verificar-pagos cada 10 min)
          ├─ pendiente, pago en revisión ─▶ la ficha muestra «Pago informado · Por validar»
          ├─ pagada, pago confirmado     ─▶ APROBADO + RECIBO
          └─ vencida                     ─▶ sigue PENDIENTE (08/10/2026)
```

| Pieza | Qué hace |
| --- | --- |
| Trait `LiquidableSireb` + columnas `sireb_*` | La liquidación de cada documento: clave, id, código público, estado, y en `sireb_envio` lo enviado, la respuesta y el último `pago` informado |
| `LiquidarSirebService` | Prepara, envía y anula la liquidación. La `Idempotency-Key` se guarda antes de llamar. **Antes de anular consulta**: con un pago cargado no anula |
| `CargarPagoService` | «Cargar pago»: N° de transacción y banco, solo si la liquidación está pendiente y sin pago |
| `ConfirmarPagoService` | Consulta `GET /liquidaciones/{id}`: aprueba y emite el recibo, y guarda el pago informado |
| `VerificarPagosCommand` | `jichi:verificar-pagos`, cada 10 minutos (necesita el cron de Laravel) |
| `TarjetaRecaudaciones` | La tarjeta de las cuatro fichas: estado de la liquidación, código de pago copiable, pago informado o «Pago cargado: No / Sin verificar», «Cargar pago», «Verificar pago» y el recibo |

## Lo que se puede hacer según SIREB

| SIREB dice | Cargar pago | Eliminar el trámite | Verificar pago |
| --- | :-: | --- | --- |
| Pendiente, sin pago | ✔ | ✔ se anula la liquidación | Sigue pendiente |
| Pendiente, pago en revisión | ✘ | ✘ | Sigue pendiente, muestra el pago |
| Pagada | ✘ | ✘ se aprueba en su lugar | Aprueba + recibo |
| Vencida (nunca tiene pago: vence porque no se pagó) | ✘ | ✔ sin pedir anular | Se ofrece junto a «Generar nueva liquidación»; el comando no la consulta (08/10/2026) |
| Anulada | ✘ | ✔ sin pedir anular | Igual que vencida |

El detalle —contrato con SIREB, reintentos, idempotencia— está en
[SIREB.md](SIREB.md). El recibo, en [RECIBOS.md](RECIBOS.md).

## Lo que NO hace Jichi

- **No valida pagos**: lo aprobado es lo que SIREB dio por pagado, con las reglas
  propias de cada documento, que siguen valiendo al aprobar.
- No guarda la imagen del comprobante: la API de SIREB no la expone.
- No cobra en efectivo ni por QR.
