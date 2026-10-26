// Graba el tutorial «Ser comercializador»: carnet de comercializador → guía de transporte,
// comprándole al pescador PESCADOR_CI. Cada pago se VALIDA A MANO en SIREB (Paititi) y la
// grabación se pausa mientras tanto. Muestra el papel impreso de los dos (visor de PDF).
// Uso: ver docs/manual/README.md.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const SALIDA = process.env.SALIDA || path.join(require('os').tmpdir(), 'jichi-manual-comercializador');
const DIR = path.join(SALIDA, 'frames');
const W = 1920, H = 1080;
fs.rmSync(SALIDA, { recursive: true, force: true });
fs.mkdirSync(DIR, { recursive: true });

// El comercializador, sin carnet vigente; el pescador, con una autorización aprobada y kilos libres.
const CI = process.env.CI_DEMO || '5489008';
const CODIGO_AUTORIZACION = process.env.CODIGO_AUTORIZACION || '6NHM-8G3Y-WBMB-NX7Y';
const NOMBRE = process.env.NOMBRE_DEMO || 'Paola Delafuente Márquez';
// La del pescador que vende: su ficha cierra el video con los kilos ya descontados.
const AUTORIZACION_ID = process.env.AUTORIZACION_ID || 2;
// DESDE=guia CARNET_ID=n: desde la ficha de su carnet ya aprobado; DESDE=pago-guia GUIA_ID=n:
// desde la ficha de la guía con el pago ya validado en SIREB; DESDE=cupo: solo el cierre (ver README).
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
  await hoja.setContent(papel('Cédula de identidad', `C.I. ${CI} BN<br>${NOMBRE}`));
  await hoja.screenshot({ path: archivoCi });
  await hoja.setContent(papel('Asociación de Comercializadores de Pescado del Beni', `Certifica que ${NOMBRE}<br>es socia activa de la asociación.`));
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
    await narrar(paso, `${que} y salió el <b>recibo</b>, listo para imprimir.`, 4200);
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
  await page.goto(DESDE === 'guia' ? `${BASE}/panel/carnets/${process.env.CARNET_ID}`
    : DESDE === 'pago-guia' ? `${BASE}/panel/guias/${process.env.GUIA_ID}`
      : DESDE === 'cupo' ? `${BASE}/panel/aprovechamientos/${AUTORIZACION_ID}` : `${BASE}/panel/dashboard`);
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

  // El papel impreso: el PDF real, pasado a PNG con la grabación en pausa, en un visor dibujado.
  const mostrarPdf = async (paso, enlace, nombre, frases) => {
    await foco(enlace);
    await narrar(paso, frases.antes, 1400);
    const [x, y] = await mover(enlace);
    eventos.push({ tipo: 'clic', t: ahora(), x, y });
    await espera(400);
    await foco(null);
    const ini = Date.now();
    grabando = false;
    const resp = await page.request.get(await enlace.getAttribute('href'));
    const pdf = path.join(SALIDA, `${nombre}.pdf`);
    fs.writeFileSync(pdf, await resp.body());
    const pngs = JSON.parse(execFileSync('python', [path.join(__dirname, 'pdf-a-png.py'), pdf, SALIDA], { encoding: 'utf8' }));
    const imagenes = pngs.map(({ ruta, vertical }) => ({ src: `data:image/png;base64,${fs.readFileSync(ruta).toString('base64')}`, vertical }));
    pausado += (Date.now() - ini) / 1000;
    grabando = true;
    await page.evaluate(({ imagenes, nombre }) => {
      const v = document.createElement('div');
      v.id = 'demo-visor';
      v.style.cssText = 'position:fixed;inset:0;z-index:9999;background:#525659;overflow:auto';
      v.innerHTML = `<div style="position:sticky;top:0;height:56px;background:#323639;color:#f1f3f4;display:flex;align-items:center;
        padding:0 24px;font:500 17px system-ui;box-shadow:0 2px 6px rgba(0,0,0,.4);z-index:1">${nombre} — PDF</div>`
        + imagenes.map(({ src, vertical }, i) => `<div style="position:relative;margin:28px auto;width:${vertical ? 1500 : 1100}px">
            <img id="demo-hoja-${i}" src="${src}" style="display:block;width:100%;box-shadow:0 4px 16px rgba(0,0,0,.5);background:#fff">
            <div id="demo-arriba-${i}" style="position:absolute;left:0;right:0;top:0;height:40%"></div>
            <div id="demo-abajo-${i}" style="position:absolute;left:0;right:0;bottom:0;height:28%"></div></div>`).join('');
      document.body.appendChild(v);
    }, { imagenes, nombre });
    await page.locator('#demo-hoja-0').waitFor();
    await espera(800);
    for (let i = 0; i < imagenes.length; i++) {
      if (imagenes[i].vertical) {
        await foco(page.locator(`#demo-arriba-${i}`));
        await narrar(paso, frases.papel[0], 4800);
        await foco(page.locator(`#demo-abajo-${i}`));
        await narrar(paso, frases.papel[1], 4600);
      } else {
        await foco(page.locator(`#demo-hoja-${i}`));
        await narrar(paso, frases.papel[Math.min(i, frases.papel.length - 1)], 4600);
      }
    }
    await foco(null);
    await espera(500);
    await page.evaluate(() => document.getElementById('demo-visor').remove());
    await espera(600);
  };

  // El cupo del pescador después de la venta: tarjetas y el gráfico de cómo bajó el disponible.
  const mostrarCupo = async () => {
    const tarjetas = page.getByText('Vendido en guías', { exact: true }).locator('xpath=ancestor::*[contains(@class,"grid")][1]');
    await foco(tarjetas);
    await narrar('Paso 8 · Kilos del pescador', 'Tenía <b>400 kg</b>: la guía le descontó <b>230 kg</b> y le quedan <b>170 kg disponibles</b> para vender.', 5200);
    await foco(page.getByText('Cómo bajó el disponible', { exact: true }).locator('xpath=ancestor::*[contains(@class,"rounded-xl")][1]'));
    await narrar('Paso 8 · Kilos del pescador', 'El gráfico muestra <b>cómo baja su cupo</b> con cada guía que le compran.', 4800);
    await foco(null);
  };

  let idCarnet = Number(process.env.CARNET_ID) || null, idGuia = Number(process.env.GUIA_ID) || null;
  if (DESDE === '') {
  // ── Presentación ────────────────────────────────────────────────
  await narrar('Manual de usuario', 'Cómo habilitar a un <b>comercializador</b> y sacar su <b>guía de transporte</b>.', 4200);
  await narrar('Manual de usuario', 'El comercializador <b>no lleva autorización</b>: saca su carnet y, con él, una guía por cada traslado.', 4600);

  // ── 1. Carnet ───────────────────────────────────────────────────
  const menuCarnets = page.getByRole('link', { name: 'Carnets', exact: true }).first();
  await foco(menuCarnets);
  await narrar('Paso 1 · Carnet', 'Primero, el <b>carnet de comercializador</b>. Entre a <b>Carnets</b>.', 3000);
  await clic(menuCarnets); await foco(null);
  await page.waitForURL('**/panel/carnets**');
  await listo();
  const nuevoCarnet = page.getByRole('button', { name: /Registrar carnet|Nuevo carnet/ }).first();
  await foco(nuevoCarnet);
  await narrar('Paso 1 · Carnet', 'Presione el botón para <b>registrar un carnet</b>.', 2400);
  await clic(nuevoCarnet); await foco(null);
  await page.waitForURL('**/carnets/crear**');
  await listo();

  const buscador = page.getByPlaceholder('Buscar por cédula o nombre…');
  await foco(buscador);
  await narrar('Paso 1 · Carnet', 'Busque a la persona por su <b>cédula</b>. Tiene que estar en el padrón.', 1800);
  await clic(buscador);
  await buscador.pressSequentially(CI, { delay: 110 * LENTO });
  const sugerido = page.locator('ul li button').filter({ hasText: CI }).first();
  await sugerido.waitFor({ timeout: 60000 });
  await espera(600);
  await foco(sugerido);
  await clic(sugerido);
  await espera(600);

  const comercializador = page.getByRole('radiogroup', { name: 'Actividad' }).getByRole('radio').filter({ hasText: 'Comercializador' });
  await foco(comercializador);
  await narrar('Paso 1 · Carnet', 'Elija <b>Comercializador</b>: habilita las guías de transporte y no pide autorización de pesca.', 2000);
  await clic(comercializador);
  await espera(700);

  const asoc = page.locator('#asociacion_id');
  await foco(asoc);
  await narrar('Paso 1 · Carnet', 'Elija la <b>asociación</b> que la certifica.', 1500);
  await clic(asoc);
  const valorAsoc = await asoc.locator('option').evaluateAll((os) => (os.find((o) => /Comercializadores/.test(o.textContent)) || os[1]).value);
  await asoc.selectOption(valorAsoc);
  await espera(700);

  const respaldos = page.locator('#archivo_ci').locator('..').locator('..').locator('..');
  await foco(respaldos);
  await narrar('Paso 1 · Carnet', 'Adjunte los <b>respaldos</b>: la cédula y el documento de la asociación.', 2200);
  await page.locator('#archivo_ci').setInputFiles(archivoCi);
  await espera(900);
  await page.locator('#archivo_asociacion').setInputFiles(archivoAval);
  await espera(1200);

  const regCarnet = page.getByRole('button', { name: 'Registrar carnet' });
  await foco(page.getByText('Lo que se va a registrar').locator('..').locator('..'));
  await narrar('Paso 1 · Carnet', 'El resumen dice qué habilita: <b>guías de transporte</b>, y cuánto se cobra.', 3400);
  await foco(regCarnet);
  await narrar('Paso 1 · Carnet', 'Presione <b>Registrar carnet</b>.', 2200);
  await clic(regCarnet); await foco(null);
  await page.waitForURL(/\/carnets\/\d+$/, { timeout: 180000 });
  await listo();
  idCarnet = idDeUrl();
  await narrar('Paso 1 · Carnet', 'Se abre la <b>ficha del carnet</b>, pendiente de pago.', 2600);

  await cargarPago('Paso 2 · Pago del carnet', 'carnet de comercializador');
  await esperarValidacion('Carnet', idCarnet, 'Carnet de comercializador');
  await verAprobado('Paso 2 · Pago del carnet', 'El carnet quedó <b>aprobado</b>');
  await mostrarPdf('Paso 2 · Imprimir el carnet', page.getByRole('link', { name: /^Carnet$/ }).first(), 'Carnet', {
    antes: 'Con el botón <b>Carnet</b> se imprime la credencial.',
    papel: ['El <b>carnet de comercializador</b>: sus datos, la asociación, hasta cuándo vale y el QR.',
      'Atrás va el <b>reglamento</b> y el recuadro de la firma.'],
  });
  }

  if (DESDE === '' || DESDE === 'guia') {
  // ── 2. Guía ─────────────────────────────────────────────────────
  const emitirGuia = page.getByRole('button', { name: /Emitir guía/ }).first();
  await foco(emitirGuia);
  await narrar('Paso 3 · Guía · Comercializador', 'Para <b>trasladar pescado</b> hace falta una <b>guía de transporte</b> por viaje. Presione <b>Emitir guía</b>.', 4200);
  await clic(emitirGuia); await foco(null);
  await page.waitForURL('**/guias/crear**');
  await listo();
  await foco(page.getByRole('navigation', { name: 'Pasos de la guía' }));
  await narrar('Paso 3 · Guía · Comercializador', 'La guía se llena en <b>cuatro pasos</b>. El comercializador y su carnet ya vienen elegidos.', 3800);

  const buscaPescador = page.getByPlaceholder('Buscar por cédula o nombre…');
  await foco(buscaPescador.locator('..').locator('..').locator('..'));
  await narrar('Paso 4 · Guía · Pescado de', '¿A qué <b>pescador</b> le compró? Sus kilos se <b>descuentan</b> de su autorización.', 3600);
  await narrar('Paso 4 · Guía · Pescado de', 'Se lo busca por nombre, cédula o el <b>código de su autorización</b> (o escaneando su QR).', 1600);
  await clic(buscaPescador);
  await buscaPescador.pressSequentially(CODIGO_AUTORIZACION, { delay: 90 * LENTO });
  const vendedor = page.locator('ul li button').first();
  await vendedor.waitFor({ timeout: 60000 });
  await espera(600);
  await foco(vendedor);
  await clic(vendedor);
  await espera(900);
  const autorizacion = page.getByRole('radiogroup', { name: 'Autorización' });
  await foco(autorizacion);
  await narrar('Paso 4 · Guía · Pescado de', 'Aparece su <b>autorización aprobada</b> con los kilos que todavía puede vender.', 4000);
  await clic(page.getByRole('button', { name: /Siguiente/ }));
  await espera(800);

  await narrar('Paso 5 · Guía · Productos', 'Cargue el <b>cuadro D</b>: cada especie, cómo va y cuántos kilos. Se cobra por kilo.', 1200);
  const renglon = async (i, producto, condicion, kilos) => {
    const prod = page.getByLabel(`Producto del renglón ${i}`);
    await foco(prod.locator('xpath=ancestor::tr'));
    await clic(prod);
    await prod.selectOption(await prod.locator('option').evaluateAll((os, p) => os.find((o) => o.textContent.trim().startsWith(p)).value, producto));
    await espera(500);
    const cond = page.getByLabel(`Condición del renglón ${i}`);
    await clic(cond);
    await cond.selectOption(condicion);
    await espera(500);
    await escribir(page.getByLabel(`Kilos del renglón ${i}`), kilos, 120);
  };
  await renglon(1, 'Surubí', 'fresco_entero', '150');
  await clic(page.getByRole('button', { name: /Agregar especie/ }));
  await espera(600);
  await renglon(2, 'Pacú', 'fresco_eviscerado', '80');
  await foco(page.getByText('Lo que se va a registrar').locator('..').locator('..'));
  await narrar('Paso 5 · Guía · Productos', 'A la derecha: el <b>importe</b> y cuántos kilos le quedan al pescador después de esta guía.', 4400);
  await clic(page.getByRole('button', { name: /Siguiente/ }));
  await espera(800);

  await narrar('Paso 6 · Guía · Ruta', 'Por último, la <b>ruta</b>: de dónde sale, adónde va y en qué.', 1200);
  for (const [id, valor] of [['origen', 'Puerto Ballivián'], ['origen_departamento', 'Beni'], ['origen_provincia', 'Cercado'], ['origen_distrito', 'Río Mamoré'],
    ['destino', 'Mercado Campesino'], ['destino_departamento', 'Beni'], ['destino_provincia', 'Cercado'], ['destino_distrito', 'Trinidad']]) {
    await escribir(page.locator(`#${id}`), valor, 45);
  }
  const medio = page.locator('#medio_transporte');
  await foco(medio); await clic(medio); await medio.selectOption('terrestre'); await espera(500);
  const tipo = page.locator('#tipo_transporte');
  await clic(tipo); await tipo.selectOption('automotriz'); await espera(500);
  await escribir(page.locator('#transporte_nombre'), 'Camioneta Toyota Hilux', 45);
  await escribir(page.locator('#transporte_placa'), '2468-KTR', 90);
  await escribir(page.locator('#transporte_capacidad_kg'), '1000', 110);

  const regGuia = page.getByRole('button', { name: 'Registrar guía' });
  await foco(regGuia);
  await narrar('Paso 6 · Guía · Ruta', 'Con los cuatro pasos completos, presione <b>Registrar guía</b>.', 2600);
  await clic(regGuia); await foco(null);
  await page.waitForURL(/\/guias\/\d+$/, { timeout: 180000 });
  await listo();
  idGuia = idDeUrl();
  await narrar('Paso 6 · Guía · Ruta', 'Se abre la <b>ficha de la guía</b>. Los kilos ya se descontaron al pescador.', 3400);

  await cargarPago('Paso 7 · Pago de la guía', 'guía');
  await esperarValidacion('GuiaMovimiento', idGuia, 'Guía de transporte');
  }
  if (DESDE !== 'cupo') {
  await verAprobado('Paso 7 · Pago de la guía', 'La guía quedó <b>aprobada</b>');
  await mostrarPdf('Paso 7 · Imprimir la guía', page.getByRole('link', { name: /^Guía$/ }).first(), 'Guía', {
    antes: 'Con el botón <b>Guía</b> se imprime la guía para el viaje.',
    papel: ['La <b>guía única de transporte</b>: quién traslada, el pescado de quién es, la ruta y el transporte.',
      'Abajo el <b>cuadro D</b> con cada producto y el <b>QR</b> para el control en el camino.'],
  });

  // ── 3. El pescador ve lo que le compraron ───────────────────────
  const menuAprov = page.getByRole('link', { name: 'Aprov. Pesquero', exact: true }).first();
  await foco(menuAprov);
  await narrar('Paso 8 · Kilos del pescador', 'En la <b>autorización del pescador</b> queda registrado lo que se le compró.', 2400);
  await clic(menuAprov); await foco(null);
  await page.waitForURL('**/panel/aprovechamientos**');
  await listo();
  await page.goto(`${BASE}/panel/aprovechamientos/${AUTORIZACION_ID}`);
  await listo();
  }
  await mostrarCupo();
  await narrar('Listo', '¡Listo! La comercializadora tiene su <b>carnet</b> y su <b>guía</b>: ya puede trasladar el pescado.', 5600);
  await narrar('', '', 600);

  await cerrar();
  console.log('### TERMINADO frames', frames.length, 'carnet', idCarnet, 'guia', idGuia);
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
