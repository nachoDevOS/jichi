# Pagos — se hacen en SIREB

> **Desde el 02/10/2026 Jichi no carga ni controla pagos.** Se retiraron la tabla
> `pagos`, la Caja, el control de boletas (validar / observar / corregir) y el
> estado `en_revision`. El módulo anterior está en el historial de git.

## Cómo se paga hoy

```
Jichi registra el documento ──▶ registra su LIQUIDACIÓN en SIREB (código de pago)
                                        │
          el titular paga en Recaudaciones; el encargado de SIREB valida la boleta
                                        │
Jichi pregunta (botón «Verificar pago» / jichi:verificar-pagos cada 10 min)
          └─ «pagada» ──▶ APROBADO + RECIBO con la boleta de SIREB
```

| Pieza | Qué hace |
| --- | --- |
| Trait `LiquidableSireb` + columnas `sireb_*` | La liquidación de cada documento: clave, id, código público, estado y el JSON de lo enviado |
| `LiquidarSirebService` | Prepara, envía y anula la liquidación. La `Idempotency-Key` se guarda antes de llamar |
| `ConfirmarPagoService` | Consulta `GET /liquidaciones/{id}`; con `pagada` aprueba y emite el recibo |
| `VerificarPagosCommand` | `jichi:verificar-pagos`, programado cada 10 minutos (necesita el cron de Laravel) |
| `TarjetaRecaudaciones` | La tarjeta de las cuatro fichas: estado, código de pago, recibo y «Verificar pago» |

El detalle —contrato con SIREB, reintentos, anulación al corregir o eliminar—
está en [SIREB.md](SIREB.md). El recibo, en [RECIBOS.md](RECIBOS.md).

## Lo que NO hace Jichi

- No guarda boletas, ni montos parciales, ni quién validó: eso vive en SIREB.
- No firma: lo aprobado es lo que SIREB dio por pagado (con las reglas propias
  de cada documento, que siguen valiendo al aprobar).
- No cobra en efectivo ni por QR.
