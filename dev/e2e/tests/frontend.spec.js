// Besucheransicht der Blöcke: Belegungsplan, Preisrechner, Galerie, Karte, mobile Ansicht
const { test, expect, isoDate, pickStay, showDay } = require('./helpers');

const DEMO = '/index.php/fewo-demo';
const GUEST = { cookies: [], origins: [] };
// 1×1-PNG als Ersatz für Kartenkacheln, damit die Tests keine fremden Server belasten
const TILE = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');

/**
 * Fängt Anfragen an fremde Server ab und merkt sie sich.
 * Die Schriften des Concrete-Themes "Atomik" (Google Fonts) gehören nicht zum Paket und zählen nicht mit.
 */
const trackExternal = async (page) => {
  const external = [];
  await page.route((url) => url.host !== 'localhost:8095', (route) => {
    const url = route.request().url();
    if (/^https?:\/\/fonts\.(googleapis|gstatic)\.com\//.test(url)) {
      return route.fulfill({ status: 200, contentType: 'text/css', body: '' });
    }
    external.push(url);
    return route.fulfill({ status: 200, contentType: 'image/png', body: TILE });
  });
  return external;
};

test.use({ storageState: GUEST });

test('Demo-Seite lädt ohne Anfragen an fremde Server', async ({ page }) => {
  const external = await trackExternal(page);
  await page.goto(DEMO, { waitUntil: 'networkidle' });
  expect(external).toEqual([]);
  // Jede id kommt nur einmal vor
  expect(await page.evaluate(() => {
    const ids = [...document.querySelectorAll('[id]')].map((el) => el.id);
    return ids.filter((id, index) => ids.indexOf(id) !== index);
  })).toEqual([]);
});

test('Belegungsplan: weitere Monate ein- und ausblenden', async ({ page }) => {
  await page.goto(DEMO);
  const calendar = page.locator('.fewo-calendar').first();
  const months = calendar.locator('.fewo-calendar__month:visible');
  await expect(months).toHaveCount(3);
  await expect(calendar.locator('.fewo-day--today')).toHaveCount(1);
  await expect(calendar.locator('.fewo-calendar__legend li')).toHaveText(['Frei', 'Belegt', 'An-/Abreisetag']);

  await calendar.getByText('Weitere Monate anzeigen').click();
  await expect(months).toHaveCount(12);
  await calendar.getByText('Weniger Monate anzeigen').click();
  await expect(months).toHaveCount(3);

  // An- und Abreisetage sind halb belegt
  await expect(calendar.locator('.fewo-day--am-free.fewo-day--pm-booked').first()).toHaveAttribute('title', /Anreisetag – ab dem Nachmittag belegt/);
  await expect(calendar.locator('.fewo-day--am-booked.fewo-day--pm-free').first()).toHaveAttribute('title', /Abreisetag – bis zum Vormittag belegt, ab dem Nachmittag frei/);
});

test('Belegungsplan: aufgeklappte Monate stehen in denselben Spalten wie die immer sichtbaren', async ({ page }) => {
  await page.goto(DEMO);
  const calendar = page.locator('.fewo-calendar').first();
  await calendar.getByText('Weitere Monate anzeigen').click();
  // linke Kanten der Kalenderblätter je Raster: [immer sichtbar, aufgeklappt]
  const columns = () => calendar.locator('.fewo-calendar__months').evaluateAll((grids) => grids.map(
    (grid) => [...new Set([...grid.children].map((month) => Math.round(month.getBoundingClientRect().left)))].sort((a, b) => a - b),
  ));
  const widths = () => calendar.locator('.fewo-calendar__month').evaluateAll((months) => [...new Set(months.map((month) => Math.round(month.getBoundingClientRect().width)))]);

  // Die Demo-Seite zeigt anfangs 3 Monate: auch darunter stehen 3 Kalenderblätter pro Zeile, alle gleich breit
  let [visible, more] = await columns();
  expect(visible).toHaveLength(3);
  expect(more).toEqual(visible);
  expect(await widths()).toHaveLength(1);

  // Auf schmalen Bildschirmen passen weniger nebeneinander – in beiden Rastern gleich viele
  await page.setViewportSize({ width: 620, height: 900 });
  [visible, more] = await columns();
  expect(visible.length).toBeLessThan(3);
  expect(more).toEqual(visible);
  expect(await widths()).toHaveLength(1);
});

test('Preisübersicht: Saisons aus Fewo-Office als Kacheln, abgelaufene Saisons fehlen', async ({ page }) => {
  await page.goto(DEMO);
  const prices = page.locator('.fewo-prices');
  await expect(prices).toHaveClass(/fewo-prices--tiles/);
  await expect(prices.locator('.fewo-prices__season-name')).toHaveText(['Nebensaison', 'Hauptsaison', 'Zwischensaison', 'Übrige Zeiträume']);
  await expect(prices).not.toContainText('Vergangene Saison');
  await expect(prices.locator('.fewo-prices__season-dates').first()).toHaveText(/\d\d\.\d\d\.\d{4}\s+–\s+\d\d\.\d\d\.\d{4}/);
  // Hauptsaison: Preise für 1, 2 und 4 Gäste hinterlegt – 3 Gäste zahlen den Preis für 4
  await expect(prices.locator('.fewo-prices__season').nth(1).locator('.fewo-prices__rate dt')).toHaveText(['1 Gast', '2 Gäste', '3–4 Gäste']);
  await expect(prices.locator('.fewo-prices__conditions li')).toHaveText(['Mindestaufenthalt: 3 Nächte', /^Endreinigung: 65\s€/, 'Anzahlung: 30 % des Gesamtpreises']);
  // Kacheln stehen nebeneinander
  const tops = await prices.locator('.fewo-prices__season').evaluateAll((items) => items.map((item) => Math.round(item.getBoundingClientRect().top)));
  expect(tops[0]).toBe(tops[1]);
  // Hinweise zum Datenabruf sehen nur Redakteure
  await expect(page.locator('.fewo-notice')).toHaveCount(0);
});

test('Preisrechner: der Kalender der Datumsfelder zeigt die Belegung, belegte Tage lassen sich nicht wählen', async ({ page }) => {
  await page.goto(DEMO);
  const quote = page.locator('.fewo-quote');
  const picker = quote.locator('.fewo-quote__calendar');
  const arrivalField = quote.locator('[data-fewo-date="arrival"]');
  const departureField = quote.locator('[data-fewo-date="departure"]');
  const day = (offset) => quote.locator(`[data-date="${isoDate(offset)}"]`);
  const fieldText = (offset) => isoDate(offset).split('-').reverse().join('.');

  // Kein eigener Kalender auf der Seite: er öffnet sich erst an den Feldern, an Stelle des Kalenders des Browsers
  await expect(picker).toBeHidden();
  await expect(arrivalField).toHaveAttribute('type', 'text');
  await expect(arrivalField).toHaveAttribute('aria-expanded', 'false');
  await arrivalField.click();
  await expect(picker).toBeVisible();
  await expect(picker).toHaveAttribute('role', 'dialog');
  await expect(arrivalField).toHaveAttribute('aria-expanded', 'true');
  await expect(quote.locator('.fewo-quote__calendar-hint')).toHaveText('Bitte wählen Sie den Anreisetag.');
  await expect(quote.locator('.fewo-quote__calendar-legend li')).toHaveText(['Frei', 'Belegt', 'Ihr Aufenthalt']);
  // direkt unter dem Feld
  const fieldBox = await arrivalField.boundingBox();
  const pickerBox = await picker.boundingBox();
  expect(pickerBox.y - (fieldBox.y + fieldBox.height)).toBeLessThan(20);
  expect(pickerBox.y).toBeGreaterThan(fieldBox.y + fieldBox.height - 1);

  // Demo-Belegung: Tage 3–8, 8–12, 20–40, 40–47 ab heute. Belegte Nächte sind gesperrt, der Abreisetag einer
  // Buchung ist als Anreisetag wählbar.
  await showDay(quote, 5);
  await expect(day(5)).toHaveAttribute('aria-disabled', 'true');
  await expect(day(5)).toHaveAttribute('aria-label', /: belegt$/);
  await day(5).click({ force: true });
  await expect(quote.locator('[name="arrival"]')).toHaveValue('');
  await showDay(quote, 12);
  await expect(day(12)).toHaveAttribute('aria-disabled', 'false');

  // Anreise wählen: der Kalender wechselt zum Feld "Abreise". Sie ist bis zum Beginn der nächsten Buchung
  // (Tag 20) möglich, dahinter nicht
  await day(12).click();
  await expect(quote.locator('[name="arrival"]')).toHaveValue(isoDate(12));
  await expect(arrivalField).toHaveValue(fieldText(12));
  await expect(picker).toBeVisible();
  await expect(departureField).toHaveAttribute('aria-expanded', 'true');
  await expect(quote.locator('.fewo-quote__calendar-hint')).toHaveText('Bitte wählen Sie den Abreisetag.');
  await showDay(quote, 21);
  await expect(day(21)).toHaveAttribute('aria-disabled', 'true');
  await showDay(quote, 20);
  await expect(day(20)).toHaveAttribute('aria-disabled', 'false');
  await day(20).click();
  // Abreise gewählt: der Kalender schließt sich
  await expect(picker).toBeHidden();
  await expect(quote.locator('[name="departure"]')).toHaveValue(isoDate(20));
  await expect(departureField).toHaveValue(fieldText(20));
  await expect(departureField).toBeFocused();
  await quote.getByRole('button', { name: 'Preis berechnen' }).click();
  // 8 Nächte × 75 € + 65 € Endreinigung
  await expect(quote.locator('.fewo-quote__total-amount')).toHaveText(/^665,00\s€$/);

  // Erneut geöffnet zeigt der Kalender den gewählten Aufenthalt; ein Klick außerhalb schließt ihn
  await departureField.click();
  await showDay(quote, 12);
  await expect(day(12)).toHaveAttribute('aria-label', /: Anreise$/);
  await showDay(quote, 20);
  await expect(day(20)).toHaveAttribute('aria-label', /: Abreise$/);
  await quote.locator('h2').click();
  await expect(picker).toBeHidden();
  // Eine neue Anreise verwirft den berechneten Preis
  await arrivalField.click();
  await showDay(quote, 13);
  await day(13).click();
  await expect(quote.locator('.fewo-quote__total-amount')).toHaveCount(0);
  await expect(quote.locator('[name="departure"]')).toHaveValue('');
  // Ohne Abreise wird nicht berechnet: der Kalender bleibt am Feld "Abreise" offen
  await page.keyboard.press('Escape');
  await expect(picker).toBeHidden();
  let requests = 0;
  page.on('request', (request) => { requests += request.method() === 'POST' ? 1 : 0; });
  await quote.getByRole('button', { name: 'Preis berechnen' }).click();
  await expect(picker).toBeVisible();
  await expect(quote.locator('.fewo-quote__calendar-hint')).toHaveText('Bitte wählen Sie den Abreisetag.');
  // Sollte dennoch ein belegter Zeitraum in den Feldern stehen: Fehlermeldung statt Berechnung
  await quote.locator('[name="departure"]').evaluate((input, value) => { input.value = value; }, isoDate(22));
  await quote.getByRole('button', { name: 'Preis berechnen' }).click();
  await expect(quote.locator('.fewo-quote__error')).toHaveText('Die Wohnung ist im gewählten Zeitraum bereits belegt. Bitte wählen Sie einen anderen Zeitraum.');
  expect(requests).toBe(0);
});

test('Preisrechner: Kalender der Datumsfelder mit der Tastatur bedienen', async ({ page }) => {
  await page.goto(DEMO);
  const quote = page.locator('.fewo-quote');
  const picker = quote.locator('.fewo-quote__calendar');
  const day = (offset) => quote.locator(`[data-date="${isoDate(offset)}"]`);

  // Fokus im Feld öffnet den Kalender, Pfeil nach unten wechselt hinein
  await quote.locator('[data-fewo-date="arrival"]').focus();
  await expect(picker).toBeVisible();
  await page.keyboard.press('ArrowDown');
  await expect(day(0)).toBeFocused();
  // Pfeiltasten bewegen den Fokus, die Eingabetaste wählt: Anreise morgen, Abreise am Tag 3 (Beginn der nächsten Buchung)
  await page.keyboard.press('ArrowRight');
  await expect(day(1)).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(quote.locator('[name="arrival"]')).toHaveValue(isoDate(1));
  await page.keyboard.press('ArrowRight');
  await page.keyboard.press('ArrowRight');
  await expect(day(3)).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(quote.locator('[name="departure"]')).toHaveValue(isoDate(3));
  await expect(picker).toBeHidden();
  await expect(quote.locator('[data-fewo-date="departure"]')).toBeFocused();

  // Eingabetaste im Feld öffnet erneut, Escape schließt und lässt den Fokus im Feld
  await page.keyboard.press('Enter');
  await expect(picker).toBeVisible();
  await expect(day(3)).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(picker).toBeHidden();
  await expect(quote.locator('[data-fewo-date="departure"]')).toBeFocused();
  // Weiter mit Tab: der Kalender bleibt geschlossen
  await page.keyboard.press('Tab');
  await expect(quote.locator('[name="guests"]')).toBeFocused();
  await expect(picker).toBeHidden();
});

test('Preisrechner: Berechnung ohne Neuladen, Fehlermeldung, Übergabe an das Anfrageformular', async ({ page }) => {
  await page.goto(DEMO);
  const quote = page.locator('.fewo-quote');
  await page.evaluate(() => { window.fewoNotReloaded = true; });

  await pickStay(quote, 13, 18);
  await quote.locator('[name="guests"]').selectOption('3');
  await quote.getByRole('button', { name: 'Preis berechnen' }).click();

  await expect(quote.locator('.fewo-quote__total-amount')).toHaveText(/^\d+(,\d\d)?\s€$/);
  expect(await page.evaluate(() => window.fewoNotReloaded)).toBe(true);
  await expect(quote.getByText('Unverbindliche Berechnung.')).toBeVisible();
  await quote.getByText('Preis im Detail').click();
  // 3 Gäste, Nebensaison: 5 Nächte × 85 € + 65 € Endreinigung
  await expect(quote.locator('.fewo-quote__total-amount')).toHaveText(/^490,00\s€$/);
  await expect(quote.locator('.fewo-quote__table')).toContainText('5 Nächte ×');
  await expect(quote.locator('.fewo-quote__table')).toContainText('Endreinigung');
  await expect(quote.locator('.fewo-quote__table')).toContainText('Anzahlung (30 %)');

  // Zu kurzer Aufenthalt
  await pickStay(quote, 13, 14);
  await quote.getByRole('button', { name: 'Preis berechnen' }).click();
  await expect(quote.locator('.fewo-quote__error')).toHaveText('Der Mindestaufenthalt beträgt 3 Nächte.');
  await expect(quote.locator('.fewo-quote__total-amount')).toHaveCount(0);
  expect(await page.evaluate(() => window.fewoNotReloaded)).toBe(true);

  // Schaltfläche führt mit den Reisedaten zum Anfrageformular
  await pickStay(quote, 13, 18);
  await quote.getByRole('button', { name: 'Preis berechnen' }).click();
  await quote.getByRole('link', { name: 'Jetzt anfragen' }).click();
  await expect(page).toHaveURL(/fewo-anfrage\?/);
  const form = page.locator('form[data-fewo-inquiry]');
  await expect(form.locator('[name="arrival"]')).toHaveValue(isoDate(13));
  await expect(form.locator('[name="departure"]')).toHaveValue(isoDate(18));
  await expect(form.locator('[name="adults"]')).toHaveValue('3');
});

test.describe('ohne JavaScript', () => {
  test.use({ javaScriptEnabled: false });

  test('Preisrechner und Galerie funktionieren auch ohne JavaScript', async ({ page }) => {
    await page.goto(DEMO);
    const quote = page.locator('.fewo-quote');
    await quote.locator('[name="arrival"]').fill(isoDate(13));
    await quote.locator('[name="departure"]').fill(isoDate(18));
    await quote.getByRole('button', { name: 'Preis berechnen' }).click();
    await expect(page).toHaveURL(/#fewo-quote-\d+$/);
    await expect(quote.locator('.fewo-quote__total-amount')).toHaveText(/\d+\s€/);
    await expect(quote.locator('[name="arrival"]')).toHaveValue(isoDate(13));

    // Auch ohne JavaScript gibt es für belegte Zeiträume keinen Preis: die Prüfung übernimmt der Server
    await quote.locator('[name="arrival"]').fill(isoDate(13));
    await quote.locator('[name="departure"]').fill(isoDate(22));
    await quote.getByRole('button', { name: 'Preis berechnen' }).click();
    await expect(quote.locator('.fewo-quote__error')).toHaveText('Die Wohnung ist im gewählten Zeitraum bereits belegt. Bitte wählen Sie einen anderen Zeitraum.');
    await expect(quote.locator('.fewo-quote__total-amount')).toHaveCount(0);
    // Ohne JavaScript bleiben die Datumsfelder des Browsers
    await expect(quote.locator('.fewo-quote__calendar')).toBeHidden();
    await expect(quote.locator('[name="arrival"]')).toHaveAttribute('type', 'date');

    // Vorschaubild verlinkt direkt auf das große Bild, Karte auf OpenStreetMap
    await expect(page.locator('.fewo-gallery__link').first()).toHaveAttribute('href', /\.(jpe?g|png|webp)/);
    await expect(page.locator('.fewo-map noscript')).toHaveCount(1);
  });
});

test('360°-Rundgang: Räume wechseln, Bilder für Mobilgeräte', async ({ page, browser }) => {
  const images = [];
  page.on('request', (request) => { if (/fewo-demo-panorama/.test(request.url())) images.push(request.url().split('/').pop()); });
  await page.goto(DEMO);
  const tour = page.locator('.fewo-tour');
  await expect(tour.locator('h2')).toHaveText('Rundgang');
  await expect(tour.locator('.fewo-tour__viewer')).toHaveAttribute('aria-label', 'Rundgang');
  await expect(tour.locator('.pnlm-container canvas')).toBeVisible();
  await expect(tour.locator('.pnlm-title-box')).toHaveText('Wohnzimmer');
  await expect(tour.locator('.fewo-tour__error')).toBeHidden();
  // Seitenverhältnis 16:9
  const box = await tour.locator('.fewo-tour__viewer').boundingBox();
  expect(Math.abs(box.width / box.height - 16 / 9)).toBeLessThan(0.02);
  // Hotspot in der Schreibweise des alten Blocks (hotspotFontAwesome) führt ins Schlafzimmer
  const hotspot = tour.locator('.pnlm-hotspot-base.custom-hotspot');
  await expect(hotspot).toHaveCount(1);
  await expect(hotspot.locator('span.fa.fa-bed')).toHaveCount(1);
  await hotspot.click({ force: true });
  await expect(tour.locator('.pnlm-title-box')).toHaveText('Schlafzimmer');
  expect(images).toEqual(['fewo-demo-panorama-wohnzimmer.jpg', 'fewo-demo-panorama-schlafzimmer.jpg']);

  // Telefon: die kleineren Bilder werden geladen
  const phone = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, locale: 'de-DE' });
  const mobile = await phone.newPage();
  const mobileImages = [];
  mobile.on('request', (request) => { if (/fewo-demo-panorama/.test(request.url())) mobileImages.push(request.url().split('/').pop()); });
  await mobile.goto(DEMO);
  await expect(mobile.locator('.fewo-tour .pnlm-title-box')).toHaveText('Wohnzimmer');
  expect(mobileImages).toEqual(['fewo-demo-panorama-wohnzimmer-mobil.jpg']);
  await phone.close();
});

test('Bildergalerie: Großansicht mit Tastatur und Schaltflächen', async ({ page }) => {
  await page.goto(DEMO);
  const links = page.locator('.fewo-gallery__link');
  const dialog = page.locator('dialog.fewo-lightbox');
  const counter = dialog.locator('.fewo-lightbox__counter');
  await expect(links).toHaveCount(6);

  await links.nth(0).click();
  await expect(dialog).toBeVisible();
  await expect(counter).toHaveText('Bild 1 von 6');
  await expect(dialog.locator('.fewo-lightbox__caption')).toHaveText('Blick in die Ferienwohnung – Bild 1');
  await expect(dialog.locator('.fewo-lightbox__image')).toHaveJSProperty('complete', true);
  expect(await dialog.locator('.fewo-lightbox__image').evaluate((img) => img.naturalWidth)).toBeGreaterThan(800);
  await expect(page.locator('html')).toHaveClass(/fewo-lightbox-open/);

  await page.keyboard.press('ArrowRight');
  await expect(counter).toHaveText('Bild 2 von 6');
  await page.keyboard.press('ArrowLeft');
  await page.keyboard.press('ArrowLeft');
  await expect(counter).toHaveText('Bild 6 von 6'); // läuft im Kreis
  await dialog.getByRole('button', { name: 'Nächstes Bild' }).click();
  await expect(counter).toHaveText('Bild 1 von 6');
  await dialog.getByRole('button', { name: 'Vorheriges Bild' }).click();
  await expect(counter).toHaveText('Bild 6 von 6');

  // Tabulator erreicht nur die Großansicht, nie die Seite dahinter
  for (let i = 0; i < 5; i++) {
    await page.keyboard.press('Tab');
    expect(await page.evaluate(() => document.activeElement === document.body || !!document.activeElement.closest('dialog.fewo-lightbox'))).toBe(true);
  }

  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(page.locator('html')).not.toHaveClass(/fewo-lightbox-open/);
  await expect(links.nth(5)).toBeFocused(); // Fokus zurück auf dem zuletzt gezeigten Bild

  // Öffnen per Tastatur, Schließen über die Schaltfläche und über den Hintergrund
  await links.nth(2).focus();
  await page.keyboard.press('Enter');
  await expect(counter).toHaveText('Bild 3 von 6');
  await dialog.getByRole('button', { name: 'Schließen' }).click();
  await expect(dialog).toBeHidden();
  await links.nth(2).click();
  await dialog.click({ position: { x: 5, y: 5 } });
  await expect(dialog).toBeHidden();
});

test('Karte: lädt erst nach Zustimmung; "Karten immer laden" merkt sich die Entscheidung', async ({ page }) => {
  const external = await trackExternal(page);
  await page.goto(DEMO, { waitUntil: 'networkidle' });
  const map = page.locator('.fewo-map');
  await expect(map.getByText('Beim Laden der Karte werden Daten an OpenStreetMap übertragen.')).toBeVisible();
  await expect(map.getByRole('link', { name: 'Karte in neuem Fenster öffnen' })).toHaveAttribute('href', /openstreetmap\.org\/\?mlat=47\.7102&mlon=9\.1024/);
  await expect(page.locator('.leaflet-container')).toHaveCount(0);
  expect(external).toEqual([]);

  await map.getByRole('button', { name: 'Karte laden' }).click();
  await expect(map.locator('.leaflet-container')).toBeVisible();
  await expect(map.locator('[data-fewo-map-consent]')).toHaveCount(0);
  await expect(map.locator('.leaflet-marker-icon')).toHaveCount(2);
  await expect.poll(() => external.filter((url) => url.startsWith('https://tile.openstreetmap.org/')).length).toBeGreaterThan(0);
  expect(external.filter((url) => !url.startsWith('https://tile.openstreetmap.org/'))).toEqual([]);
  await expect(map.locator('.leaflet-control-attribution')).toContainText('OpenStreetMap');
  // Markierungssymbole kommen aus dem Paket
  expect(await map.locator('.leaflet-marker-icon').first().evaluate((img) => img.complete && img.naturalWidth > 0 && img.src)).toContain('/packages/fewo_tk2/assets/leaflet/images/');

  await map.locator('.leaflet-marker-icon[title="Ferienwohnung Seeherz"]').click({ force: true });
  await expect(map.locator('.leaflet-popup-content')).toContainText('Ferienwohnung Seeherz');
  await expect(map.locator('.leaflet-popup-content')).toContainText('78479 Reichenau');

  // Ohne Häkchen wird beim nächsten Besuch wieder gefragt
  await page.reload();
  await expect(map.getByRole('button', { name: 'Karte laden' })).toBeVisible();
  await map.getByLabel('Karten immer laden').check();
  await map.getByRole('button', { name: 'Karte laden' }).click();
  await expect(map.locator('.leaflet-container')).toBeVisible();
  await page.reload();
  await expect(map.locator('.leaflet-container')).toBeVisible();
  await expect(map.locator('[data-fewo-map-consent]')).toHaveCount(0);
});

test.describe('auf dem Smartphone (390 × 844)', () => {
  test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

  for (const path of [DEMO, '/index.php/fewo-anfrage', '/index.php/fewo-doppelt']) {
    test(`${path} passt in die Bildschirmbreite`, async ({ page }, testInfo) => {
      await trackExternal(page);
      await page.goto(path);
      // Elemente der Blöcke, die rechts über den Bildschirm hinausragen
      const overflowing = await page.evaluate(() => [...document.querySelectorAll('[class*="fewo-"]')]
        .filter((el) => el.getClientRects().length && !el.closest('dialog, .fewo-visually-hidden, .fewo-form__hp'))
        .filter((el) => el.getBoundingClientRect().right > document.documentElement.clientWidth + 1 || el.getBoundingClientRect().left < -1)
        .map((el) => `${el.className} (${Math.round(el.getBoundingClientRect().left)}–${Math.round(el.getBoundingClientRect().right)})`));
      expect(overflowing).toEqual([]);
      // Die Seite ist nicht breiter als eine Seite ohne Fewo-Blöcke (das Theme Atomik ragt selbst 3 px über)
      const pageOverflow = () => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      const withBlocks = await pageOverflow();
      await page.screenshot({ path: testInfo.outputPath('mobil.png'), fullPage: true });
      await page.goto('/index.php/blog');
      expect(withBlocks).toBeLessThanOrEqual(await pageOverflow());
      return;
      await page.screenshot({ path: testInfo.outputPath('mobil.png'), fullPage: true });
    });
  }

  test('Großansicht der Galerie reagiert auf Wischen', async ({ page }) => {
    await page.goto(DEMO);
    await page.locator('.fewo-gallery__link').first().tap();
    const dialog = page.locator('dialog.fewo-lightbox');
    const counter = dialog.locator('.fewo-lightbox__counter');
    await expect(counter).toHaveText('Bild 1 von 6');
    const swipe = (fromX, toX) => dialog.evaluate((el, [from, to]) => {
      const touch = (x) => new Touch({ identifier: 1, target: el, clientX: x, clientY: 300 });
      el.dispatchEvent(new TouchEvent('touchstart', { changedTouches: [touch(from)], bubbles: true }));
      el.dispatchEvent(new TouchEvent('touchend', { changedTouches: [touch(to)], bubbles: true }));
    }, [fromX, toX]);
    await swipe(300, 80); // nach links wischen = nächstes Bild
    await expect(counter).toHaveText('Bild 2 von 6');
    await swipe(80, 300);
    await expect(counter).toHaveText('Bild 1 von 6');
    await swipe(200, 220); // zu kurz: kein Wechsel
    await expect(counter).toHaveText('Bild 1 von 6');
    // Bild passt auf den Bildschirm
    const box = await dialog.locator('.fewo-lightbox__image').boundingBox();
    expect(box.width).toBeLessThanOrEqual(390);
    expect(box.height).toBeLessThanOrEqual(844);
  });
});
