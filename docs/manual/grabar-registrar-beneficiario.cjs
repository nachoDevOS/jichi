// Graba el tutorial «Registrar beneficiario»: cuadros a 2x (3840x2160) y un registro
// de eventos. Cámara, foco, cursor y subtítulos los dibuja componer.py a 30 fps.
// Uso: ver docs/manual/README.md.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const SALIDA = process.env.SALIDA || path.join(require('os').tmpdir(), 'jichi-manual');
const DIR = path.join(SALIDA, 'frames');
const W = 1920, H = 1080;
fs.rmSync(SALIDA, { recursive: true, force: true });
fs.mkdirSync(DIR, { recursive: true });

// La cédula es única: antes de regrabar, dar de baja al beneficiario de la grabación anterior.
const CI = process.env.CI_DEMO || '7633685';
const frames = [];
const eventos = [];
const capitulos = [];
let pasoPrevio = null;
let inicio = 0;
const ahora = () => Date.now() / 1000;
// A 4K el screencast da ~3 cuadros/s: se graba en cámara lenta y componer.py comprime el tiempo.
const LENTO = Number(process.env.LENTO || 5);
const espera = (ms) => new Promise((r) => setTimeout(r, ms * LENTO));

const CSS_NARRA = `
  #demo-narra{position:fixed;left:50%;bottom:44px;transform:translateX(-50%);max-width:1250px;width:max-content;
    background:rgba(15,23,42,.94);color:#fff;font:500 27px/1.4 'Instrument Sans','DejaVu Sans',system-ui,sans-serif;
    padding:20px 34px;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.35);border-left:6px solid #22c55e}
  #demo-narra.arriba{bottom:auto;top:44px}
  #demo-narra b{color:#86efac}
  #demo-paso{display:block;font-size:17px;letter-spacing:.08em;text-transform:uppercase;color:#86efac;margin-bottom:4px}`;

const CURSOR = '<svg width="28" height="28" viewBox="0 0 24 24" style="filter:drop-shadow(0 2px 3px rgba(0,0,0,.45));margin:8px">'
  + '<path d="M4 2l16 9.5-7 1.6-3.6 6.9z" fill="#fff" stroke="#0f172a" stroke-width="1.6" stroke-linejoin="round"/></svg>';

(async () => {
  const browser = await chromium.launch({
    // Sin la bandera, deviceScaleFactor 2 se emula y la captura sale a 1x.
    args: ['--disable-features=LocalNetworkAccessChecks,PrivateNetworkAccessChecks', '--hide-scrollbars', '--force-device-scale-factor=2'],
  });
  const ctx = await browser.newContext({ viewport: { width: W, height: H }, deviceScaleFactor: 2, locale: 'es-BO' });
  const page = await ctx.newPage();
  const cdp = await ctx.newCDPSession(page);

  const narrar = async (paso, txt, ms = 3200) => {
    if (paso && paso !== pasoPrevio) { capitulos.push({ paso, texto: txt.replace(/<[^>]+>/g, ''), t: (ahora() - inicio) / LENTO }); pasoPrevio = paso; }
    eventos.push({ tipo: 'narra', t: ahora(), paso, html: txt });
    await espera(ms);
  };
  const caja = async (loc) => { await loc.scrollIntoViewIfNeeded(); await espera(250); return loc.boundingBox(); };
  const foco = async (loc, ms = 0) => { const r = loc ? await caja(loc) : null; eventos.push({ tipo: 'foco', t: ahora(), r }); if (ms) await espera(ms); };
  const mover = async (loc) => { const r = await caja(loc); const x = r.x + r.width / 2, y = r.y + r.height / 2; eventos.push({ tipo: 'cursor', t: ahora(), x, y }); await espera(800); return [x, y]; };
  const clic = async (loc) => { const [x, y] = await mover(loc); eventos.push({ tipo: 'clic', t: ahora(), x, y }); await espera(250); await loc.click(); };
  const escribir = async (loc, txt, ms = 75) => { await foco(loc); await clic(loc); await loc.pressSequentially(txt, { delay: ms * LENTO }); await espera(350); };
  const elegir = async (loc, valor) => { await foco(loc); await clic(loc); await espera(300); await loc.selectOption(valor); await espera(600); };

  // Login (sin grabar)
  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('networkidle');
  const emergencia = page.getByText('Acceso de emergencia');
  if (await emergencia.isVisible().catch(() => false)) await emergencia.click();
  await page.getByPlaceholder('admin@admin.com').fill(process.env.USUARIO || 'admin@admin.com');
  await page.locator('input[type=password]').fill(process.env.CLAVE || 'password');
  await page.locator('form button[type=submit]').click();
  await page.waitForURL('**/panel/**');
  await page.goto(`${BASE}/panel/dashboard`);
  await page.waitForLoadState('networkidle');
  await espera(800);

  let grabando = true;
  cdp.on('Page.screencastFrame', ({ data, metadata, sessionId }) => {
    cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
    if (!grabando) return;
    const archivo = path.join(DIR, `f${String(frames.length).padStart(6, '0')}.jpg`);
    fs.writeFileSync(archivo, Buffer.from(data, 'base64'));
    frames.push({ archivo, t: metadata.timestamp });
  });
  await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 95, maxWidth: W * 2, maxHeight: H * 2 });
  console.log('viewport CSS', await page.evaluate(() => [innerWidth, innerHeight, devicePixelRatio]));
  await espera(300);
  inicio = frames[0]?.t ?? ahora();
  eventos.push({ tipo: 'cursor', t: ahora(), x: 1500, y: 760, salto: true });

  await narrar('Manual de usuario', 'Cómo <b>buscar</b> y <b>registrar un beneficiario</b> en Jichi.', 3800);
  await narrar('Paso 1', 'Partimos del <b>Tablero</b>, la pantalla de inicio del panel.', 3200);

  const menu = page.getByRole('link', { name: 'Beneficiarios', exact: true }).first();
  await foco(menu);
  await narrar('Paso 1', 'En el menú de la izquierda, entre a <b>Beneficiarios</b>.', 2600);
  await clic(menu); await foco(null);
  await page.waitForURL('**/panel/beneficiarios**');
  await page.waitForLoadState('networkidle');
  await foco(null);
  await narrar('Paso 2', 'Aquí está el <b>padrón</b>: todas las personas registradas.', 3200);

  const buscar = page.getByPlaceholder('Buscar…');
  await foco(buscar);
  await narrar('Paso 2', 'Antes de registrar, <b>busque por cédula o nombre</b> para no duplicar a la persona.', 3600);
  await clic(buscar);
  await buscar.pressSequentially(CI, { delay: 110 * LENTO });
  await buscar.press('Enter');
  await page.waitForLoadState('networkidle');
  await espera(900);
  await foco(page.getByText('Sin coincidencias').first());
  await narrar('Paso 2', 'No aparece nadie con esa cédula: <b>corresponde registrarlo</b>.', 3400);

  const nuevo = page.getByRole('button', { name: /Nuevo beneficiario/ }).first();
  await foco(nuevo);
  await narrar('Paso 3', 'Presione <b>Nuevo beneficiario</b>.', 2400);
  await clic(nuevo); await foco(null);
  await page.waitForURL('**/beneficiarios/crear');
  await page.waitForLoadState('networkidle');
  await foco(null);
  await narrar('Paso 3', 'Se abre el formulario. Los campos con <b style="color:#fca5a5">*</b> son obligatorios.', 3400);

  await narrar('Paso 4 · Documento', 'Escriba el <b>número de cédula</b>, el complemento si tiene y dónde fue expedida.', 1200);
  await escribir(page.locator('#ci'), CI, 110);
  const exp = page.locator('#departamento_id');
  await exp.locator('option').nth(1).waitFor({ state: 'attached' });
  const opcion = await exp.locator('option').evaluateAll((os) => (os.find((o) => /beni|bn/i.test(o.textContent)) || os[1]).value);
  await elegir(exp, opcion);
  await espera(600);

  await narrar('Paso 5 · Datos personales', 'Cargue <b>nombres y apellidos</b> tal como figuran en la cédula.', 1200);
  await escribir(page.locator('#primerNombre'), 'Ignacio');
  await escribir(page.locator('#apellidoPaterno'), 'Molina');
  await escribir(page.locator('#apellidoMaterno'), 'Guzmán');

  await narrar('Paso 5 · Datos personales', 'La <b>fecha de nacimiento</b> es obligatoria. Luego el género y la nacionalidad.', 1200);
  const fn = page.locator('#fechaNacimiento');
  await foco(fn); await clic(fn); await fn.fill('1997-03-08'); await espera(700);
  await elegir(page.locator('#genero'), 'masculino');
  const nac = page.locator('#nacionalidad');
  if ((await nac.evaluate((e) => e.tagName)) === 'SELECT') { await foco(nac, 900); } else if (!(await nac.inputValue())) await escribir(nac, 'Boliviana');

  await narrar('Paso 6 · Contacto', 'Agregue <b>teléfono, correo y dirección</b>: sirven para avisarle del trámite.', 1200);
  await escribir(page.locator('#telefono'), '67285914', 90);
  await escribir(page.locator('#email'), 'ignacio.molina@correo.com', 45);
  await escribir(page.locator('#ciudad'), 'Trinidad');
  await escribir(page.locator('#provincia'), 'Cercado');
  await escribir(page.locator('#direccion'), 'Barrio Pompeya, calle Nicolás Suárez Nº 245', 40);

  const foto = page.locator('#foto');
  if (await foto.count()) {
    const zona = page.locator('label[for=foto]').first();
    const objetivo = (await zona.count()) ? zona : foto;
    await foco(objetivo);
    await narrar('Paso 7 · Foto', 'Si tiene la <b>foto</b> de la persona, súbala aquí: es la que sale en el carnet. Es opcional.', 3600);
  }

  const registrar = page.getByRole('button', { name: 'Registrar beneficiario' });
  await foco(registrar);
  await narrar('Paso 8', 'Revise los datos y presione <b>Registrar beneficiario</b>.', 3000);
  await clic(registrar); await foco(null);
  await page.waitForURL(/\/beneficiarios\/\d+$/, { timeout: 20000 });
  await page.waitForLoadState('networkidle');
  await espera(800);
  await foco(null);
  await narrar('Listo', 'Se guardó y se abre la <b>ficha del beneficiario</b>. Desde aquí se tramitan su autorización, carnet, faenas y guías.', 5200);

  await clic(page.getByRole('link', { name: 'Beneficiarios', exact: true }).first());
  await page.waitForURL('**/panel/beneficiarios**');
  await page.waitForLoadState('networkidle');
  const b2 = page.getByPlaceholder('Buscar…');
  await narrar('Comprobar', 'Para <b>localizarlo</b> después, búsquelo otra vez en el padrón.', 1500);
  await foco(b2);
  await clic(b2);
  await b2.fill('');
  await b2.pressSequentially(CI, { delay: 110 * LENTO });
  await b2.press('Enter');
  await page.waitForLoadState('networkidle');
  await espera(900);
  await foco(page.locator('table tbody tr').first());
  await narrar('Comprobar', 'Ahí está: <b>Ignacio Molina Guzmán</b>, ya en el padrón.', 4200);
  await foco(null);
  await narrar('', '', 600);

  grabando = false;
  await cdp.send('Page.stopScreencast');

  // Un PNG transparente por subtítulo, abajo y arriba (componer.py elige), y el cursor a 4x.
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
  console.log('frames', frames.length, 'CI', CI);
})().catch((e) => { console.error(e); process.exit(1); });
