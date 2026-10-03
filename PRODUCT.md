# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Principal — funcionario de ventanilla del SEDAG** (Servicio Departamental
  Agropecuario, Gobierno Autónomo Departamental del Beni). Trabaja en el panel
  (`/panel`) desde una PC de oficina, con gente esperando en fila: registra
  beneficiarios, otorga la Autorización de Pesca para Aprovechamiento Pesquero,
  emite carnets, permisos de faena y guías únicas de transporte, verifica pagos
  en SIREB e imprime los documentos. **Cuando los intereses chocan, manda este
  usuario.**
- **Beneficiario** (pescador o comercializador) en el portal (`/mi-cuenta`):
  consulta desde el celular sus papeles vigentes, lo que tiene en trámite y sus
  recibos. Baja familiaridad digital, conexión floja.
- **Quien verifica un documento** (inspector, control en ruta o puerto): escanea
  el QR impreso y abre la vista pública sin iniciar sesión, desde un teléfono.

## Product Purpose

Digitalizar el circuito completo de habilitación pesquera del Beni: el registro
del beneficiario, la autorización con su cupo en kilos, el carnet anual por
actividad, el permiso de faena por salida y la guía por traslado, con el cobro
liquidado en SIREB (Recaudaciones) y el recibo emitido al confirmarse el pago.

Éxito: la ventanilla atiende rápido y sin errores de cupo ni de cobro; cada papel
entregado se puede verificar en la calle; el beneficiario sabe qué tiene vigente
sin ir a la oficina.

## Positioning

Es el sistema oficial del SEDAG, no un producto comercial: su valor es ser la
fuente de verdad. Cada documento impreso lleva un código y un QR que lo contrasta
contra la base en tiempo real, y el cupo de kilos se controla en el mismo
momento en que se emite cada faena.

## Operating Context

- Panel: PC de escritorio en oficina, atención presencial con fila. La carga de
  datos y la búsqueda de beneficiarios (por C.I.) tienen que ser veloces.
- Portal y verificación pública: teléfonos de gama baja, datos móviles, señal
  débil (ríos, puertos, rutas). En el portal el menú va abajo en celular.
- **El papel es el documento que circula**: carnet (plástico), recibo oficial,
  permiso de faena y guía se imprimen en PDF (DomPDF) y el talonario es
  correlativo y auditado por Contabilidad.
- El pago se hace y se valida en SIREB; Jichi solo pregunta y aprueba.

## Capabilities and Constraints

- Tres mitades que no se mezclan: panel (funcionarios), público (sin sesión) y
  portal (beneficiario). Una cuenta de beneficiario nunca entra al panel.
- La vista pública no expone datos personales completos ni pistas de la
  estructura interna.
- Todo en español (interfaz, mensajes, código). Vocabulario fijo: el documento
  se llama siempre «Autorización de Pesca para Aprovechamiento Pesquero»; nunca
  se muestra «Escala N», sino la capacidad en kilos.
- Estados de cada documento: pendiente → aprobado → vencido / revocado /
  agotado. Un papel puede quedar «sin efecto» si se revoca la autorización
  madre; el estado no se imprime, porque cambia después de impreso.
- Fechas en hora de Bolivia (America/La_Paz).
- Pendientes de producto: módulos de Reportes, Configuración y Usuarios.

## Brand Commitments

- Identidad institucional del Gobierno Autónomo Departamental del Beni:
  obligatoria.
- Logo y sellos del SEDAG ya en uso (`public/image/sedag.png`,
  `recibo-sello.png`, `faena-sello.png`, `carnet-escudo.png`,
  `recibo-escudo.png`): se mantienen.
- El nombre **Jichi** es visible: el sistema se presenta con nombre propio.
- Tono: oficial, claro, sin jerga técnica para el beneficiario.

## Evidence on Hand

- Documentos impresos ya diseñados: carnet (`carnet-fondo.png`), recibo,
  permiso de faena (`faena-peces.png`), guía.
- No hay testimonios, métricas de uso ni casos publicados: no inventarlos.

## Product Principles

1. **La ventanilla primero.** Lo que acorta la atención al público en el panel
   gana frente a la expresión visual.
2. **Lo impreso tiene que ser verdad.** Solo va al papel lo que no cambia
   después; la vigencia se confirma con el QR.
3. **Simple para quien menos sabe.** Portal y vista pública se leen sin
   conocimientos digitales, en un celular barato y con poca señal.
4. **Oficial y confiable.** Se ve como un documento de la Gobernación, no como
   una app de consumo.
5. **La pantalla no inventa reglas.** Lo que se permite o no lo decide el
   servidor; la interfaz muestra lo que el sistema ya calculó.

## Accessibility & Inclusion

- Usuarios con baja alfabetización digital: textos simples, acciones explícitas,
  sin depender de íconos solos.
- Teléfonos de gama baja y conexión débil: páginas livianas en portal y vista
  pública.
- Contraste alto en documentos impresos (se lee con poco tóner y a la
  intemperie). Estándar formal (WCAG) no definido todavía.
