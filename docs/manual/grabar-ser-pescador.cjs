// Graba el tutorial «Ser pescador»: autorización → carnet → faena del beneficiario CI_DEMO,
// con el pago de cada uno cargado desde Jichi y VALIDADO A MANO en SIREB (Paititi).
// Mientras se espera la validación la grabación se pausa y ese tiempo no entra al video.
// Uso: ver docs/manual/README.md.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const SALIDA = process.env.SALIDA || path.join(require('os').tmpdir(), 'jichi-manual-pescador');
const DIR = path.join(SALIDA, 'frames');
const W = 1920, H = 1080;
fs.rmSync(SALIDA, { recursive: true, force: true });
fs.mkdirSync(DIR, { recursive: true });

// Tiene que estar en el padrón y sin autorización en curso.
const CI = process.env.CI_DEMO || '7633685';
const ESCALA = process.env.ESCALA || '201 kg hasta 400 kg';
// DESDE=carnet CARNET_ID=n: segunda parte, desde la ficha de un carnet ya registrado (ver README).
const DESDE = process.env.DESDE || '';
const frames = [];
const eventos = [];
const capitulos = [];
let pasoPrevio = null;
let inicio = 0;
// Segundos en pausa: se restan a frames y eventos para que la espera no exista en el video.
let pausado = 0;
let browser, cdp, grabando = false;
const ahora = () => Date.now() / 1000 - pausado;
const LENTO = Number(process.env.LENTO || 5);
const espera = (ms) => new Promise((r) => setTimeout(r, ms * LENTO));
const dormir = (ms) => new Promise((r) => setTimeout(r, ms));

const CSS_NARRA = `
  #demo-narra{position:fixed;left:50%;bottom:44px;transform:translateX(-50%);max-width:1250px;width:max-content;
    background:rgba(15,23,42,.94);color:#fff;font:500 27px/1.4 'Instrument Sans','DejaVu Sans',system-ui,sans-serif;
    padding:20px 34px;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.35);border-left:6px solid #22c55e}
  #demo-narra.arriba{bottom:auto;top:44px}
  #demo-narra b{color:#86efac}
  #demo-paso{display:block;font-size:17px;letter-spacing:.08em;text-transform:uppercase;color:#86efac;margin-bottom:4px}`;

const CURSOR = '<svg width="28" height="28" viewBox="0 0 24 24" style="filter:drop-shadow(0 2px 3px rgba(0,0,0,.45));margin:8px">'
  + '<path d="M4 2l16 9.5-7 1.6-3.6 6.9z" fill="#fff" stroke="#0f172a" stroke-width="1.6" stroke-linejoin="round"/></svg>';

// Los respaldos del carnet: papeles de MUESTRA, que digan que lo son.
const papel = (titulo, cuerpo) => `<html><body style="margin:0;width:900px;height:560px;background:#f8fafc;font-family:system-ui">
  <div style="margin:30px;height:500px;border:3px solid #0f766e;border-radius:18px;background:#ecfdf5;padding:30px;box-sizing:border-box">
  <div style="font:700 30px system-ui;color:#0f766e">${titulo}</div><div style="margin-top:24px;font:24px/1.6 system-ui;color:#134e4a">${cuerpo}</div>
  <div style="margin-top:40px;font:700 46px system-ui;color:#dc2626;opacity:.55;transform:rotate(-12deg)">DOCUMENTO DE MUESTRA</div></div></body></html>`;

(async () => {
  browser = await chromium.launch({
    args: ['--disable-features=LocalNetworkAccessChecks,PrivateNetworkAccessChecks', '--hide-scrollbars', '--force-device-scale-factor=2'],
  });
  const ctx = await browser.newContext({ viewport: { width: W, height: H }, deviceScaleFactor: 2, locale: 'es-BO' });
  const page = await ctx.newPage();
  cdp = await ctx.newCDPSession(page);

  const hoja = await browser.newPage({ viewport: { width: 900, height: 560 } });
  const archivoCi = path.join(SALIDA, 'cedula-muestra.png');
  const archivoAval = path.join(SALIDA, 'aval-asociacion-muestra.png');
  await hoja.setContent(papel('Cédula de identidad', `C.I. ${CI} BN<br>Ignacio Molina Guzmán`));
  await hoja.screenshot({ path: archivoCi });
  await hoja.setContent(papel('Asociación de Pescadores de Trinidad', 'Certifica que Ignacio Molina Guzmán<br>es socio activo de la asociación.'));
  await hoja.screenshot({ path: archivoAval });
  await hoja.close();

  const narrar = async (paso, txt, ms = 3200) => {
    if (paso && paso !== pasoPrevio) { capitulos.push({ paso, texto: txt.replace(/<[^>]+>/g, ''), t: (ahora() - inicio) / LENTO }); pasoPrevio = paso; }
    eventos.push({ tipo: 'narra', t: ahora(), paso, html: txt });
    await espera(ms);
  };
  const caja = async (loc) => { await loc.scrollIntoViewIfNeeded(); await espera(250); return loc.boundingBox(); };
  const foco = async (loc, ms = 0) => { const r = loc ? await caja(loc) : null; eventos.push({ tipo: 'foco', t: ahora(), r }); if (ms) await espera(ms); };
  const mover = async (loc) => { const r = await caja(loc); const x = r.x + r.width / 2, y = r.y + r.height / 2; eventos.push({ tipo: 'cursor', t: ahora(), x, y }); await espera(800); return [x, y]; };
  const clic = async (loc) => { const [x, y] = await mover(loc); eventos.push({ tipo: 'clic', t: ahora(), x, y }); await espera(250); await loc.click(); };
  const escribir = async (loc, txt, ms = 75) => { await foco(loc); await clic(loc); await loc.fill(''); await loc.pressSequentially(txt, { delay: ms * LENTO }); await espera(350); };
  const listo = async () => { await page.waitForLoadState('networkidle'); await espera(700); };

  // Al pausar se dejan de guardar cuadros; al volver, el tiempo esperado se descuenta.
  const esperarValidacion = async (modelo, id, que) => {
    const ini = Date.now();
    grabando = false;
    console.log(`\n### VALIDAR: ${que} — ${modelo} #${id}. Valide el pago en Paititi; la grabación sigue sola.`);
    for (;;) {
      let estado = '';
      try { estado = execFileSync('php', [path.join(__dirname, 'pago-validado.php'), modelo, String(id)], { encoding: 'utf8' }).trim(); } catch (e) { estado = 'error'; }
      if (estado === 'pagada') break;
      process.stdout.write(`  ${new Date().toLocaleTimeString()} ${que}: ${estado}\n`);
      await dormir(8000);
    }
    console.log(`### VALIDADO: ${que}. Retomando la grabación.`);
    await page.reload();
    await page.waitForLoadState('networkidle');
    await dormir(1500);
    pausado += (Date.now() - ini) / 1000;
    grabando = true;
    await espera(300);
  };

  const idDeUrl = () => Number(page.url().match(/\/(\d+)(?:\?.*)?$/)[1]);

  // Cargar la boleta en la tarjeta de Recaudaciones de la ficha abierta.
  const cargarPago = async (paso, que) => {
    const opcion = page.getByRole('button', { name: /Ya depositó/ });
    await foco(page.getByText('¿Cómo va a pagar el titular?').locator('..'));
    await narrar(paso, `Queda <b>pendiente de pago</b>. Se paga en Recaudaciones: por <b>QR</b> o depositando en el banco.`, 3800);
    await narrar(paso, `Si el titular ya depositó, presione <b>Ya depositó</b> y copie los datos de la boleta.`, 1200);
    await clic(opcion);
    const nro = page.locator('#numero_transaccion');
    await nro.waitFor();
    await espera(500);
    await foco(page.getByRole('dialog').first());
    await narrar(paso, `Revise que la boleta sea por el <b>monto</b> y con el <b>código de pago</b> indicados.`, 3400);
    await escribir(nro, String(Date.now()).slice(-8), 90);
    await clic(page.getByRole('button', { name: 'Banco Unión', exact: true }));
    await espera(400);
    const enviar = page.getByRole('button', { name: 'Cargar boleta' });
    await foco(enviar);
    await clic(enviar); await foco(null);
    await page.getByText('Boleta cargada').first().waitFor({ timeout: 180000 });
    await listo();
    await foco(page.getByText('Boleta cargada').first().locator('..').locator('..'));
    await narrar(paso, `La boleta quedó <b>por validar</b>. Recaudaciones (Paititi) la revisa contra el banco.`, 4200);
    await foco(null);
    console.log(`  boleta cargada: ${que}`);
  };

  // Al volver de la pausa: «Revisar si ya lo validaron» → aprobado y recibo.
  const verAprobado = async (paso, que) => {
    await narrar(paso, `Cuando Recaudaciones <b>valida el pago</b>, el sistema lo aprueba solo (revisa cada 10 minutos).`, 3800);
    const revisar = page.getByRole('button', { name: /Revisar si ya lo validaron/ });
    if (await revisar.isVisible().catch(() => false)) {
      await foco(revisar);
      await narrar(paso, 'Para no esperar, presione <b>Revisar si ya lo validaron</b>.', 2400);
      await clic(revisar); await foco(null);
      await page.getByText(/Imprimir recibo/).first().waitFor({ timeout: 180000 });
      await listo();
    }
    const recibo = page.getByText(/Imprimir recibo/).first();
    await foco(recibo.locator('..').locator('..'));
    await narrar(paso, `${que} quedó <b>aprobado</b> y salió el <b>recibo</b>, listo para imprimir.`, 4200);
    await foco(null);
  };

  // Login (sin grabar), con el acceso de emergencia del administrador.
  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('networkidle');
  const emergencia = page.getByText('Acceso de emergencia');
  if (await emergencia.isVisible().catch(() => false)) await emergencia.click();
  await page.getByPlaceholder('admin@admin.com').fill(process.env.USUARIO || 'admin@admin.com');
  await page.locator('input[type=password]').fill(process.env.CLAVE || 'password');
  await page.locator('form button[type=submit]').click();
  await page.waitForURL('**/panel/**');
  await page.goto(DESDE === 'carnet' ? `${BASE}/panel/carnets/${process.env.CARNET_ID}` : `${BASE}/panel/dashboard`);
  await page.waitForLoadState('networkidle');
  await espera(800);

  grabando = true;
  cdp.on('Page.screencastFrame', ({ data, metadata, sessionId }) => {
    cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
    if (!grabando) return;
    const archivo = path.join(DIR, `f${String(frames.length).padStart(6, '0')}.jpg`);
    fs.writeFileSync(archivo, Buffer.from(data, 'base64'));
    frames.push({ archivo, t: metadata.timestamp - pausado });
  });
  await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 95, maxWidth: W * 2, maxHeight: H * 2 });
  await espera(300);
  inicio = frames[0]?.t ?? ahora();
  eventos.push({ tipo: 'cursor', t: ahora(), x: 1500, y: 760, salto: true });

  let idAprov = null, idCarnet = Number(process.env.CARNET_ID) || null;
  if (DESDE !== 'carnet') {
  // ── Presentación ────────────────────────────────────────────────
  await narrar('Manual de usuario', 'Cómo habilitar a un <b>pescador</b>: autorización, carnet y permiso de faena.', 4200);
  await narrar('Manual de usuario', 'Son <b>tres trámites en orden</b>, y cada uno se aprueba cuando se paga en Recaudaciones.', 4200);

  // ── 1. Autorización ─────────────────────────────────────────────
  const menuAprov = page.getByRole('link', { name: 'Aprov. Pesquero', exact: true }).first();
  await foco(menuAprov);
  await narrar('Paso 1 · Autorización', 'Primero, la <b>Autorización de Pesca</b>: los kilos que puede pescar en la gestión. Entre a <b>Aprov. Pesquero</b>.', 3800);
  await clic(menuAprov); await foco(null);
  await page.waitForURL('**/panel/aprovechamientos**');
  await listo();
  const nuevaAprov = page.getByRole('button', { name: /Registrar aprovechamiento/ }).first();
  await foco(nuevaAprov);
  await narrar('Paso 1 · Autorización', 'Presione <b>Registrar aprovechamiento</b>.', 2400);
  await clic(nuevaAprov); await foco(null);
  await page.waitForURL('**/aprovechamientos/crear**');
  await listo();

  const buscador = page.getByPlaceholder('Buscar por cédula o nombre…');
  await foco(buscador);
  await narrar('Paso 1 · Autorización', 'Busque al pescador por su <b>cédula</b>. Tiene que estar registrado en el padrón.', 1800);
  await clic(buscador);
  await buscador.pressSequentially(CI, { delay: 110 * LENTO });
  const sugerido = page.locator('ul li button').filter({ hasText: CI }).first();
  await sugerido.waitFor({ timeout: 60000 });
  await espera(600);
  await foco(sugerido);
  await clic(sugerido);
  await espera(600);

  const escala = page.getByRole('radiogroup', { name: 'Escala' });
  await foco(escala);
  await narrar('Paso 1 · Autorización', 'Elija la <b>escala</b>: cada una dice los kilos autorizados y lo que se cobra. Los precios vienen de Recaudaciones.', 4200);
  await clic(escala.getByRole('radio').filter({ hasText: ESCALA }).first());
  await espera(800);

  const embarcacion = page.getByRole('button', { name: 'Peque-peque', exact: true });
  await foco(embarcacion.locator('..').locator('..'));
  await narrar('Paso 1 · Autorización', 'Indique el <b>tipo de embarcación</b>; va impreso en la autorización.', 1800);
  await clic(embarcacion);
  await espera(700);

  const regAprov = page.getByRole('button', { name: 'Registrar autorización' });
  await foco(page.getByText('Lo que se va a registrar').locator('..').locator('..'));
  await narrar('Paso 1 · Autorización', 'A la derecha está el <b>resumen</b>: volumen autorizado y monto a cobrar.', 3600);
  await foco(regAprov);
  await narrar('Paso 1 · Autorización', 'Presione <b>Registrar autorización</b>.', 2200);
  await clic(regAprov); await foco(null);
  await page.waitForURL(/\/aprovechamientos\/\d+$/, { timeout: 180000 });
  await listo();
  idAprov = idDeUrl();
  await narrar('Paso 1 · Autorización', 'Se abre la <b>ficha de la autorización</b>.', 2600);

  await cargarPago('Paso 2 · Pago de la autorización', 'autorización');
  await esperarValidacion('AprovechamientoPesq', idAprov, 'Autorización de pesca');
  await verAprobado('Paso 2 · Pago de la autorización', 'La autorización');

  // ── 2. Carnet ───────────────────────────────────────────────────
  const emitirCarnet = page.getByRole('button', { name: /Emitir carnet/ }).first();
  await foco(emitirCarnet);
  await narrar('Paso 3 · Carnet', 'Con la autorización aprobada ya se puede sacar el <b>carnet de pescador</b>: presione <b>Emitir carnet</b>.', 3800);
  await clic(emitirCarnet); await foco(null);
  await page.waitForURL('**/carnets/crear**');
  await listo();
  await narrar('Paso 3 · Carnet', 'El titular y su autorización ya vienen elegidos desde la ficha.', 3000);

  const pescador = page.getByRole('radiogroup', { name: 'Actividad' }).getByRole('radio').filter({ hasText: 'Pescador' });
  await foco(pescador);
  await narrar('Paso 3 · Carnet', 'Elija la actividad <b>Pescador</b>: es la que habilita los permisos de faena.', 1800);
  await clic(pescador);
  await espera(700);
  const autoriz = page.getByRole('radiogroup', { name: 'Autorización' });
  await foco(autoriz);
  await narrar('Paso 3 · Carnet', 'Abajo aparece la <b>autorización aprobada</b> que lo respalda; su volumen se imprime en el carnet.', 3800);

  const asoc = page.locator('#asociacion_id');
  await foco(asoc);
  await narrar('Paso 3 · Carnet', 'Elija la <b>asociación</b> que lo certifica.', 1500);
  await clic(asoc);
  const valorAsoc = await asoc.locator('option').evaluateAll((os) => (os.find((o) => /Trinidad/.test(o.textContent)) || os[1]).value);
  await asoc.selectOption(valorAsoc);
  await espera(700);

  const respaldos = page.locator('#archivo_ci').locator('..').locator('..').locator('..');
  await foco(respaldos);
  await narrar('Paso 3 · Carnet', 'Adjunte los <b>respaldos</b>: la cédula y el documento de la asociación (foto o PDF, hasta 3 MB).', 2200);
  await page.locator('#archivo_ci').setInputFiles(archivoCi);
  await espera(900);
  await page.locator('#archivo_asociacion').setInputFiles(archivoAval);
  await espera(1200);

  const regCarnet = page.getByRole('button', { name: 'Registrar carnet' });
  await foco(regCarnet);
  await narrar('Paso 3 · Carnet', 'Revise el resumen y presione <b>Registrar carnet</b>.', 2400);
  await clic(regCarnet); await foco(null);
  await page.waitForURL(/\/carnets\/\d+$/, { timeout: 180000 });
  await listo();
  idCarnet = idDeUrl();
  }
  await narrar('Paso 3 · Carnet', 'Se abre la <b>ficha del carnet</b>, pendiente de pago.', 2600);

  await cargarPago('Paso 4 · Pago del carnet', 'carnet');
  await esperarValidacion('Carnet', idCarnet, 'Carnet de pescador');
  await verAprobado('Paso 4 · Pago del carnet', 'El carnet');
  const impCarnet = page.getByRole('link', { name: /^Carnet$/ }).first();
  if (await impCarnet.isVisible().catch(() => false)) {
    await foco(impCarnet);
    await narrar('Paso 4 · Pago del carnet', 'Con el botón <b>Carnet</b> se imprime la credencial para entregarla.', 3400);
    await foco(null);
  }

  // ── 3. Faena ────────────────────────────────────────────────────
  const emitirFaena = page.getByRole('button', { name: /Emitir faena/ }).first();
  await foco(emitirFaena);
  await narrar('Paso 5 · Permiso de faena', 'El carnet es la llave del año; para <b>salir a pescar</b> hace falta un <b>permiso de faena</b> por salida. Presione <b>Emitir faena</b>.', 4600);
  await clic(emitirFaena); await foco(null);
  await page.waitForURL('**/faenas/crear**');
  await listo();

  const carnets = page.getByRole('radiogroup', { name: 'Carnet' });
  await foco(carnets);
  await narrar('Paso 5 · Permiso de faena', 'Elija el <b>carnet</b> del pescador. El permiso sale con los <b>kilos disponibles</b> de su autorización.', 2000);
  await clic(carnets.locator('button:not([disabled])').first());
  await espera(800);

  await narrar('Paso 5 · Permiso de faena', 'Copie los <b>datos del talonario</b>: embarcación, propietario, comandante, matrícula y kardex.', 1200);
  await escribir(page.locator('#embarcacion'), 'Peque-peque «San Ignacio»', 55);
  const propietario = page.locator('#propietario');
  if (!(await propietario.inputValue()).trim()) await escribir(propietario, 'Ignacio Molina Guzmán', 55);
  else await foco(propietario, 1500);
  await escribir(page.locator('#comandante_barco'), 'Ignacio Molina Guzmán', 55);
  await escribir(page.locator('#matricula_naval'), 'BN-0157', 90);
  await escribir(page.locator('#nro_kardex'), '04512', 90);
  await narrar('Paso 5 · Permiso de faena', 'Y la <b>región</b> donde va a pescar.', 1000);
  await escribir(page.locator('#region_desde'), 'Puerto Ballivián', 60);
  await escribir(page.locator('#region_hasta'), 'Loma Suárez', 60);

  await foco(page.getByText('La salida es el día').first());
  await narrar('Paso 5 · Permiso de faena', 'Las <b>fechas</b> no se escriben: la salida es el día en que se aprueba y vale 30 días.', 3800);
  const regFaena = page.getByRole('button', { name: 'Registrar faena' });
  await foco(regFaena);
  await narrar('Paso 5 · Permiso de faena', 'Presione <b>Registrar faena</b>.', 2200);
  await clic(regFaena); await foco(null);
  await page.waitForURL(/\/faenas\/\d+$/, { timeout: 180000 });
  await listo();
  const idFaena = idDeUrl();

  await cargarPago('Paso 6 · Pago de la faena', 'faena');
  await esperarValidacion('PermisoFaena', idFaena, 'Permiso de faena');
  await verAprobado('Paso 6 · Pago de la faena', 'El permiso de faena');
  const impFaena = page.getByRole('link', { name: /^Faena$/ }).first();
  if (await impFaena.isVisible().catch(() => false)) {
    await foco(impFaena);
    await narrar('Paso 6 · Pago de la faena', 'Con <b>Faena</b> se imprime el permiso: el pescador lo lleva en cada salida.', 3800);
    await foco(null);
  }
  await narrar('Listo', '¡Listo! El pescador tiene su <b>autorización</b>, su <b>carnet</b> y su <b>permiso de faena</b>: ya puede salir a pescar.', 5600);
  await narrar('', '', 600);

  await cerrar();
  console.log('### TERMINADO frames', frames.length, 'autorización', idAprov, 'carnet', idCarnet, 'faena', idFaena);
})().catch(async (e) => {
  console.error('### ERROR', e);
  // Lo grabado hasta el error se guarda igual: se puede componer y ver dónde se cortó.
  await cerrar().catch((e2) => console.error('### ERROR al guardar', e2));
  process.exit(1);
});

async function cerrar() {
  grabando = false;
  await cdp.send('Page.stopScreencast').catch(() => {});
  const lienzo = await browser.newPage({ viewport: { width: W, height: H }, deviceScaleFactor: 1 });
  const subtitulos = eventos.filter((e) => e.tipo === 'narra' && e.html);
  for (let i = 0; i < subtitulos.length; i++) {
    for (const lado of ['abajo', 'arriba']) {
      await lienzo.setContent(`<html><body style="margin:0;background:transparent"><style>${CSS_NARRA}</style>
        <div id="demo-narra" class="${lado}">${subtitulos[i].paso ? `<span id="demo-paso">${subtitulos[i].paso}</span>` : ''}${subtitulos[i].html}</div></body></html>`);
      await lienzo.screenshot({ path: path.join(SALIDA, `sub${i}-${lado}.png`), omitBackground: true });
    }
    subtitulos[i].png = `sub${i}`;
  }
  const lienzo4 = await browser.newPage({ viewport: { width: 44, height: 44 }, deviceScaleFactor: 4 });
  await lienzo4.setContent(`<html><body style="margin:0;background:transparent">${CURSOR}</body></html>`);
  await lienzo4.screenshot({ path: path.join(SALIDA, 'cursor.png'), omitBackground: true });

  fs.writeFileSync(path.join(SALIDA, 'eventos.json'), JSON.stringify({ lento: LENTO, frames, eventos }, null, 1));
  fs.writeFileSync(path.join(SALIDA, 'capitulos.json'), JSON.stringify(capitulos, null, 1));
  await browser.close();
}
