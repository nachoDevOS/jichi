// Graba un clip corto del Manual: desde la ficha de un documento APROBADO, el botón de
// imprimir y el papel que sale. El Chromium sin pantalla no muestra PDF, así que el PDF
// real se baja con la sesión, se pasa a PNG (pdf-a-png.py) y se ve en un visor dibujado.
// Uso: DOC=autorizacion|carnet|faena ID=n SALIDA=<carpeta> node docs/manual/grabar-impresion.cjs
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const SALIDA = process.env.SALIDA || path.join(require('os').tmpdir(), 'jichi-impresion');
const DIR = path.join(SALIDA, 'frames');
const W = 1920, H = 1080;
const LENTO = Number(process.env.LENTO || 5);
fs.rmSync(SALIDA, { recursive: true, force: true });
fs.mkdirSync(DIR, { recursive: true });

// Cada documento: su ficha, el texto del botón y lo que se narra sobre el papel.
const DOCS = {
  autorizacion: {
    ficha: (id) => `/panel/aprovechamientos/${id}`, boton: 'Autorización', paso: 'Paso 2 · Imprimir la autorización',
    antes: 'Ya aprobada, con el botón <b>Autorización</b> se imprime el papel oficial para el pescador.',
    papel: ['Es la <b>Autorización de Pesca</b>: el volumen autorizado, la embarcación y hasta cuándo vale.',
      'Abajo lleva el <b>código QR</b>: con él cualquiera comprueba que el papel es auténtico.'],
  },
  carnet: {
    ficha: (id) => `/panel/carnets/${id}`, boton: 'Carnet', paso: 'Paso 4 · Imprimir el carnet',
    antes: 'Aprobado el carnet, con el botón <b>Carnet</b> se imprime la credencial.',
    papel: ['El <b>carnet de pescador</b>: foto, datos, asociación, volumen autorizado y el QR.',
      'Atrás va el <b>reglamento</b> y el recuadro de la firma.'],
  },
  faena: {
    ficha: (id) => `/panel/faenas/${id}`, boton: 'Faena', paso: 'Paso 6 · Imprimir el permiso de faena',
    antes: 'Con el botón <b>Faena</b> se imprime el permiso de la salida.',
    papel: ['El <b>permiso de faena</b>: los datos del talonario, la región y los kilos con que sale.',
      'El pescador lo lleva en cada salida; el QR permite verificarlo en el control.'],
  },
};
const doc = DOCS[process.env.DOC];
if (!doc || !process.env.ID) throw new Error('Falta DOC=autorizacion|carnet|faena e ID=n');

const frames = [], eventos = [];
let grabando = false;
const ahora = () => Date.now() / 1000;
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
    args: ['--disable-features=LocalNetworkAccessChecks,PrivateNetworkAccessChecks', '--hide-scrollbars', '--force-device-scale-factor=2'],
  });
  const ctx = await browser.newContext({ viewport: { width: W, height: H }, deviceScaleFactor: 2, locale: 'es-BO' });
  const page = await ctx.newPage();
  const cdp = await ctx.newCDPSession(page);

  const narrar = async (paso, txt, ms = 3200) => { eventos.push({ tipo: 'narra', t: ahora(), paso, html: txt }); await espera(ms); };
  const caja = async (loc) => { await loc.scrollIntoViewIfNeeded(); await espera(250); return loc.boundingBox(); };
  const foco = async (loc, ms = 0) => { const r = loc ? await caja(loc) : null; eventos.push({ tipo: 'foco', t: ahora(), r }); if (ms) await espera(ms); };
  const mover = async (loc) => { const r = await caja(loc); const x = r.x + r.width / 2, y = r.y + r.height / 2; eventos.push({ tipo: 'cursor', t: ahora(), x, y }); await espera(800); return [x, y]; };

  await page.goto(`${BASE}/login`);
  await page.waitForLoadState('networkidle');
  const emergencia = page.getByText('Acceso de emergencia');
  if (await emergencia.isVisible().catch(() => false)) await emergencia.click();
  await page.getByPlaceholder('admin@admin.com').fill(process.env.USUARIO || 'admin@admin.com');
  await page.locator('input[type=password]').fill(process.env.CLAVE || 'password');
  await page.locator('form button[type=submit]').click();
  await page.waitForURL('**/panel/**');
  await page.goto(BASE + doc.ficha(process.env.ID));
  await page.waitForLoadState('networkidle');

  // El PDF real, con la sesión; a PNG antes de grabar para que la espera no entre al video.
  const boton = page.getByRole('link', { name: new RegExp(`^${doc.boton}$`) }).first();
  const href = await boton.getAttribute('href');
  const resp = await page.request.get(href);
  if (!resp.ok()) throw new Error(`El PDF respondió ${resp.status()}`);
  const pdf = path.join(SALIDA, 'documento.pdf');
  fs.writeFileSync(pdf, await resp.body());
  const pngs = JSON.parse(execFileSync('python', [path.join(__dirname, 'pdf-a-png.py'), pdf, SALIDA], { encoding: 'utf8' }));
  const imagenes = pngs.map(({ ruta, vertical }) => ({ src: `data:image/png;base64,${fs.readFileSync(ruta).toString('base64')}`, vertical }));
  await page.evaluate(() => window.scrollTo(0, 0));
  await espera(600);

  grabando = true;
  cdp.on('Page.screencastFrame', ({ data, metadata, sessionId }) => {
    cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
    if (!grabando) return;
    const archivo = path.join(DIR, `f${String(frames.length).padStart(6, '0')}.jpg`);
    fs.writeFileSync(archivo, Buffer.from(data, 'base64'));
    frames.push({ archivo, t: metadata.timestamp });
  });
  await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 95, maxWidth: W * 2, maxHeight: H * 2 });
  await espera(300);
  eventos.push({ tipo: 'cursor', t: ahora(), x: 1500, y: 760, salto: true });

  await foco(boton);
  await narrar(doc.paso, doc.antes, 1400);
  // Clic dibujado, no real: el enlace abre otra pestaña que el screencast no ve.
  const [x, y] = await mover(boton);
  eventos.push({ tipo: 'clic', t: ahora(), x, y });
  await espera(400);
  await foco(null);

  // El visor: como el del navegador, una pestaña con el papel sobre fondo gris.
  await page.evaluate(({ imagenes, nombre }) => {
    const v = document.createElement('div');
    v.id = 'demo-visor';
    v.style.cssText = 'position:fixed;inset:0;z-index:9999;background:#525659;overflow:auto;opacity:0;transition:opacity .25s';
    v.innerHTML = `<div style="position:sticky;top:0;height:56px;background:#323639;color:#f1f3f4;display:flex;align-items:center;
      padding:0 24px;font:500 17px system-ui;box-shadow:0 2px 6px rgba(0,0,0,.4);z-index:1">${nombre}</div>`
      // La hoja vertical (A4) va a lo ancho y se recorre por zonas: entera, la letra no se lee.
      + imagenes.map(({ src, vertical }, i) => `<div style="position:relative;margin:28px auto;width:${vertical ? 1500 : 1100}px">
          <img id="demo-hoja-${i}" src="${src}" style="display:block;width:100%;box-shadow:0 4px 16px rgba(0,0,0,.5);background:#fff">
          <div id="demo-arriba-${i}" style="position:absolute;left:0;right:0;top:0;height:40%"></div>
          <div id="demo-abajo-${i}" style="position:absolute;left:0;right:0;bottom:0;height:28%"></div></div>`).join('');
    document.body.appendChild(v);
    requestAnimationFrame(() => { v.style.opacity = '1'; });
  }, { imagenes, nombre: `${doc.boton} — PDF` });
  await page.locator('#demo-hoja-0').waitFor();
  await espera(800);

  for (let i = 0; i < imagenes.length; i++) {
    if (imagenes[i].vertical) {
      await foco(page.locator(`#demo-arriba-${i}`));
      await narrar(doc.paso, doc.papel[0], 4800);
      await foco(page.locator(`#demo-abajo-${i}`));
      await narrar(doc.paso, doc.papel[1], 4600);
    } else {
      await foco(page.locator(`#demo-hoja-${i}`));
      await narrar(doc.paso, doc.papel[Math.min(i, doc.papel.length - 1)], 4600);
    }
  }
  await foco(null);
  await espera(600);
  await page.evaluate(() => document.getElementById('demo-visor').remove());
  await espera(700);
  await narrar('', '', 300);

  grabando = false;
  await cdp.send('Page.stopScreencast');

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
  await browser.close();
  console.log('### TERMINADO', process.env.DOC, 'hojas', imagenes.length, 'frames', frames.length);
})().catch((e) => { console.error('### ERROR', e); process.exit(1); });
