// Erzeugt Bildschirmfotos der Blöcke und Dashboard-Seiten unter docs/screenshots/.
//   node make-screenshots.js      (Testprojekt muss laufen, siehe ../setup.sh)
const path = require('node:path');
const fs = require('node:fs');
const { chromium } = require('@playwright/test');

const BASE = process.env.FEWO_BASE_URL || 'http://localhost:8095';
const OUT = path.resolve(__dirname, '../../docs/screenshots');
const isoDate = (days) => {
  const date = new Date();
  date.setDate(date.getDate() + days);
  return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
};

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const shot = async (page, name, selector) => {
    const target = selector ? page.locator(selector).first() : page;
    await target.screenshot({ path: path.join(OUT, name + '.png') });
    console.log(name + '.png');
  };

  // Besucheransicht
  const guest = await browser.newContext({ viewport: { width: 1100, height: 900 }, locale: 'de-DE', deviceScaleFactor: 1 });
  const page = await guest.newPage();
  await page.goto(BASE + '/index.php/fewo-demo', { waitUntil: 'networkidle' });
  // Fixierte Kopfzeile des Themes ausblenden, sie würde die Blöcke überdecken
  await page.addStyleTag({ content: 'nav.fixed-top, header:not([class^="fewo-"]) { display: none !important; }' });
  await shot(page, 'belegungsplan', '.fewo-calendar');
  await shot(page, 'preisuebersicht', '.fewo-prices');
  const quote = page.locator('.fewo-quote');
  // Kalender der Felder "Anreise"/"Abreise" mit der Belegung
  await quote.locator('[data-fewo-date="arrival"]').click();
  await quote.locator(`[data-date="${isoDate(13)}"]`).click();
  const box = await quote.boundingBox();
  await page.screenshot({ path: path.join(OUT, 'preisrechner-kalender.png'), clip: { x: box.x - 8, y: box.y - 8, width: box.width + 16, height: 560 } });
  console.log('preisrechner-kalender.png');
  await quote.locator(`[data-date="${isoDate(18)}"]`).click();
  await quote.getByRole('button', { name: 'Preis berechnen' }).click();
  await quote.getByText('Preis im Detail').click();
  await shot(page, 'preisrechner', '.fewo-quote');
  await shot(page, 'ausstattungsliste', '.fewo-amenities');
  await page.locator('.fewo-tour').scrollIntoViewIfNeeded();
  await page.waitForTimeout(1500); // Panorama laden
  await shot(page, 'rundgang', '.fewo-tour');
  await shot(page, 'bildergalerie', '.fewo-gallery');
  await shot(page, 'karte-zustimmung', '.fewo-map');
  await page.locator('.fewo-map [data-fewo-map-load]').click();
  await page.waitForTimeout(2500); // Kartenkacheln laden
  await shot(page, 'karte', '.fewo-map');
  await page.locator('.fewo-gallery__link').nth(1).click();
  await page.waitForTimeout(500);
  await shot(page, 'bildergalerie-grossansicht');
  await page.goto(BASE + '/index.php/fewo-anfrage');
  await page.addStyleTag({ content: 'nav.fixed-top, header:not([class^="fewo-"]) { display: none !important; }' });
  await shot(page, 'anfrageformular', '.fewo-form');
  // Preisübersicht in der Anordnung "Liste untereinander"
  await page.goto(BASE + '/index.php/fewo-doppelt');
  await page.addStyleTag({ content: 'nav.fixed-top, header:not([class^="fewo-"]) { display: none !important; }' });
  await shot(page, 'preisuebersicht-liste', '.fewo-prices--list');
  await guest.close();

  // Dashboard
  const admin = await browser.newContext({ viewport: { width: 1500, height: 950 }, locale: 'de-DE', storageState: path.join(__dirname, '.auth/admin.json') });
  const dash = await admin.newPage();
  // Erst aufnehmen, wenn Concrete Seitenleiste und Kopfzeilen-Schaltflächen aufgebaut hat
  const ready = () => dash.waitForLoadState('networkidle').then(() => dash.waitForTimeout(500));
  await dash.goto(BASE + '/index.php/dashboard/fewo_tk2/apartments');
  await ready();
  await shot(dash, 'dashboard-wohnungen');
  await dash.locator('tbody a', { hasText: 'Seeherz (Demo)' }).first().click();
  await ready();
  await shot(dash, 'dashboard-wohnung-bearbeiten');
  await dash.goto(BASE + '/index.php/dashboard/fewo_tk2/inquiries');
  await ready();
  await shot(dash, 'dashboard-anfragen');
  await dash.goto(BASE + '/index.php/dashboard/fewo_tk2/assistant');
  await ready();
  await shot(dash, 'dashboard-ki-assistent');
  // Den Chat zeigt chat.png mit einer echten Antwort aus der Seeherz-Testinstanz (dev/seeherz) – im Testprojekt
  // antwortet nur die Ollama-Attrappe.
  await browser.close();
})();
