// Mehrere Blöcke desselben Typs auf einer Seite (/fewo-doppelt) dürfen sich nicht gegenseitig beeinflussen
const { test, expect, sql, mail, isoDate, pickStay } = require('./helpers');

const PAGE = '/index.php/fewo-doppelt';
const TILE = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');

test.use({ storageState: { cookies: [], origins: [] } });
test.beforeEach(async ({ page }) => {
  await page.route((url) => url.host !== 'localhost:8095', (route) => route.fulfill({ status: 200, contentType: 'image/png', body: TILE }));
  await page.goto(PAGE);
});

test('jede id kommt nur einmal vor', async ({ page }) => {
  expect(await page.evaluate(() => {
    const ids = [...document.querySelectorAll('[id]')].map((el) => el.id);
    return ids.filter((id, index) => ids.indexOf(id) !== index);
  })).toEqual([]);
  // Beschriftungen zeigen auf ein Feld im eigenen Block
  expect(await page.evaluate(() => [...document.querySelectorAll('[class*="fewo-"] label[for]')]
    .filter((label) => { const field = document.getElementById(label.htmlFor); return !field || field.closest('form') !== label.closest('form'); })
    .map((label) => label.htmlFor))).toEqual([]);
});

test('Belegungspläne zeigen jeweils ihren eigenen Kalender', async ({ page }) => {
  const calendars = page.locator('.fewo-calendar');
  await expect(calendars).toHaveCount(2);
  await expect(calendars.nth(0).locator('h2')).toHaveText('Belegung Seeherz');
  await expect(calendars.nth(1).locator('h2')).toHaveText('Belegung Seeherzchen');
  // Erster Kalender: ICS mit dem Stichwort "Betriebsferien" für "auf Anfrage". Zweiter: XML, die nächsten 14 Tage belegt
  const day = (calendar, offset) => {
    const date = new Date();
    date.setDate(date.getDate() + offset);
    const label = date.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    return calendar.locator(`.fewo-day[title^="${label}:"]`);
  };
  await expect(day(calendars.nth(1), 5)).toHaveAttribute('title', /: belegt$/);
  await expect(day(calendars.nth(0), 5)).toHaveAttribute('title', /: (belegt|Abreisetag.*|Anreisetag.*)$/);
  await expect(day(calendars.nth(1), 30)).toHaveAttribute('title', /: frei$/);
  await expect(day(calendars.nth(0), 30)).toHaveAttribute('title', /: auf Anfrage$/);
  await expect(calendars.nth(0).locator('.fewo-calendar__legend li')).toHaveCount(4);
  await expect(calendars.nth(1).locator('.fewo-calendar__legend li')).toHaveCount(3); // ohne "Auf Anfrage"

  // "Weitere Monate" wirkt nur auf den angeklickten Kalender
  await calendars.nth(1).getByText('Weitere Monate anzeigen').click();
  await expect(calendars.nth(1).locator('.fewo-calendar__month:visible')).toHaveCount(6);
  await expect(calendars.nth(0).locator('.fewo-calendar__month:visible')).toHaveCount(2);
});

test('Preisübersichten zeigen ihre eigene Preisliste in der gewählten Anordnung', async ({ page }) => {
  const prices = page.locator('.fewo-prices');
  await expect(prices).toHaveCount(2);
  // Liste: Saisons untereinander über die ganze Breite
  await expect(prices.nth(0)).toHaveClass(/fewo-prices--list/);
  await expect(prices.nth(0).locator('.fewo-prices__season-name')).toHaveText(['Nebensaison', 'Hauptsaison', 'Zwischensaison', 'Übrige Zeiträume']);
  const boxes = await prices.nth(0).locator('.fewo-prices__season').evaluateAll((items) => items.map((item) => item.getBoundingClientRect()).map((box) => [Math.round(box.left), Math.round(box.top)]));
  expect(new Set(boxes.map(([left]) => left)).size).toBe(1);
  expect(new Set(boxes.map(([, top]) => top)).size).toBe(4);
  // Kacheln: Preisliste im XML-Format
  await expect(prices.nth(1)).toHaveClass(/fewo-prices--tiles/);
  await expect(prices.nth(1).locator('.fewo-prices__season-name')).toHaveText(['Ganzjährig']);
  await expect(prices.nth(1).locator('.fewo-prices__rate')).toHaveText([/1–2 Gäste\s*55\s€\s*\/ Nacht/]);
  await expect(prices.nth(1).locator('.fewo-prices__conditions li')).toHaveText(['Mindestaufenthalt: 2 Nächte', /^Endreinigung: 40\s€/]);
});

test('Preisrechner rechnen unabhängig voneinander', async ({ page }) => {
  const quotes = page.locator('.fewo-quote');
  await expect(quotes).toHaveCount(2);
  await expect(quotes.nth(0).locator('[name="guests"] option')).toHaveCount(4);
  await expect(quotes.nth(1).locator('[name="guests"] option')).toHaveCount(2);

  await pickStay(quotes.nth(1), 30, 33);
  await quotes.nth(1).getByRole('button', { name: 'Preis berechnen' }).click();
  // 3 Nächte × 55 € + 40 € Endreinigung
  await expect(quotes.nth(1).locator('.fewo-quote__total-amount')).toHaveText(/^205,00\s€$/);
  await expect(quotes.nth(0).locator('[data-fewo-quote-result]')).toBeEmpty();
  await expect(quotes.nth(0).locator('[name="arrival"]')).toHaveValue('');

  await pickStay(quotes.nth(0), 13, 14);
  await quotes.nth(0).getByRole('button', { name: 'Preis berechnen' }).click();
  await expect(quotes.nth(0).locator('.fewo-quote__error')).toHaveText('Der Mindestaufenthalt beträgt 3 Nächte.');
  await expect(quotes.nth(1).locator('.fewo-quote__total-amount')).toHaveText(/^205,00\s€$/);
});

test.describe('ohne JavaScript', () => {
  test.use({ javaScriptEnabled: false });

  test('Preisrechner: das Ergebnis erscheint nur im abgeschickten Block', async ({ page }) => {
    const quotes = page.locator('.fewo-quote');
    await pickStay(quotes.nth(1), 30, 33);
    await quotes.nth(1).getByRole('button', { name: 'Preis berechnen' }).click();
    await expect(quotes.nth(1).locator('.fewo-quote__total-amount')).toHaveText(/^205,00\s€$/);
    await expect(quotes.nth(0).locator('.fewo-quote__total-amount')).toHaveCount(0);
    await expect(quotes.nth(0).locator('[name="guests"] option')).toHaveCount(4);
    await expect(page.locator('.fewo-form form')).toHaveCount(2);
  });
});

test('Preisrechner zeigen im Kalender die Belegung ihrer eigenen Wohnung', async ({ page }) => {
  const quotes = page.locator('.fewo-quote');
  const day = (index, offset) => quotes.nth(index).locator(`[data-date="${isoDate(offset)}"]`);
  // Seeherz: Tage 3–12 belegt. Seeherzchen: Tage 1–15 belegt.
  await quotes.nth(0).locator('[data-fewo-date="arrival"]').click();
  await expect(quotes.nth(0).locator('.fewo-quote__calendar')).toBeVisible();
  await expect(quotes.nth(1).locator('.fewo-quote__calendar')).toBeHidden();
  await expect(day(0, 2)).toHaveAttribute('aria-disabled', 'false');
  await expect(day(0, 5)).toHaveAttribute('aria-disabled', 'true');

  // Wechsel ins Feld des zweiten Rechners (hier mit der Tastatur, das Feld liegt unter dem offenen Kalender):
  // sein Kalender öffnet sich, der des ersten schließt sich
  await quotes.nth(1).locator('[data-fewo-date="arrival"]').focus();
  await expect(quotes.nth(1).locator('.fewo-quote__calendar')).toBeVisible();
  await expect(quotes.nth(0).locator('.fewo-quote__calendar')).toBeHidden();
  await expect(day(1, 2)).toHaveAttribute('aria-disabled', 'true');
  await expect(day(1, 5)).toHaveAttribute('aria-disabled', 'true');
  // Anreise heute: Abreise nur morgen möglich, weil dann die nächste Buchung beginnt
  await day(1, 0).click();
  await expect(day(1, 1)).toHaveAttribute('aria-disabled', 'false');
  await expect(day(1, 3)).toHaveAttribute('aria-disabled', 'true');
  await page.keyboard.press('Escape');

  // Derselbe Zeitraum ist bei Seeherz frei
  await pickStay(quotes.nth(0), 0, 3);
  await expect(quotes.nth(0).locator('[name="departure"]')).toHaveValue(isoDate(3));
  await expect(quotes.nth(0).locator('.fewo-quote__error')).toHaveCount(0);
});

test('360°-Rundgänge laufen unabhängig voneinander', async ({ page }) => {
  const tours = page.locator('.fewo-tour');
  await expect(tours).toHaveCount(2);
  // Einstellungen als JSON, je ein eigenes Bild und Seitenverhältnis
  await expect(tours.nth(0).locator('.pnlm-title-box')).toHaveText('Wohnzimmer');
  await expect(tours.nth(1).locator('.pnlm-title-box')).toHaveText('Schlafzimmer (eigene Einstellungen)');
  await expect(tours.locator('.pnlm-container canvas')).toHaveCount(2);
  const ratio = async (index) => { const box = await tours.nth(index).locator('.fewo-tour__viewer').boundingBox(); return box.width / box.height; };
  expect(Math.abs(await ratio(0) - 2)).toBeLessThan(0.02);
  expect(Math.abs(await ratio(1) - 4 / 3)).toBeLessThan(0.02);
  await expect(page.locator('.fewo-tour__error:visible')).toHaveCount(0);
});

test('Ausstattungslisten klappen einzeln auf, Fußnoten bleiben im eigenen Block', async ({ page }) => {
  const lists = page.locator('.fewo-amenities');
  await expect(lists).toHaveCount(2);
  await expect(lists.nth(0).getByText('Dusche')).toBeHidden();
  await lists.nth(1).locator('summary').click();
  await expect(lists.nth(1).getByText('Doppelbett')).toBeVisible();
  await expect(lists.nth(0).getByText('Dusche')).toBeHidden();
  const href = await lists.nth(1).locator('sup a').getAttribute('href');
  await expect(lists.nth(1).locator(href)).toHaveText(/180 × 200 cm/);
});

test('Galerien haben je eine eigene Großansicht', async ({ page }) => {
  const galleries = page.locator('.fewo-gallery');
  await expect(galleries).toHaveCount(2);
  await expect(page.locator('dialog.fewo-lightbox')).toHaveCount(2);
  // zweite Galerie: neueste zuerst
  await galleries.nth(1).locator('.fewo-gallery__link').first().click();
  const open = page.locator('dialog.fewo-lightbox[open]');
  await expect(open).toHaveCount(1);
  await expect(open.locator('.fewo-lightbox__caption')).toHaveText('Blick in die Ferienwohnung – Bild 6');
  await page.keyboard.press('ArrowRight');
  await expect(open.locator('.fewo-lightbox__counter')).toHaveText('Bild 2 von 6');
  await page.keyboard.press('Escape');
  await galleries.nth(0).locator('.fewo-gallery__link').first().click();
  await expect(open.locator('.fewo-lightbox__counter')).toHaveText('Bild 1 von 6');
  await expect(open.locator('.fewo-lightbox__caption')).toHaveText('Blick in die Ferienwohnung – Bild 1');
});

test('Karten: Zustimmung gilt für die angeklickte Karte, mit "immer laden" für alle', async ({ page }) => {
  const maps = page.locator('.fewo-map');
  await expect(maps).toHaveCount(2);
  await maps.nth(0).getByRole('button', { name: 'Karte laden' }).click();
  await expect(maps.nth(0).locator('.leaflet-container')).toBeVisible();
  await expect(maps.nth(1).locator('.leaflet-container')).toHaveCount(0);
  await expect(maps.nth(1).getByRole('button', { name: 'Karte laden' })).toBeVisible();

  await page.reload();
  await maps.nth(1).getByLabel('Karten immer laden').check();
  await maps.nth(1).getByRole('button', { name: 'Karte laden' }).click();
  await expect(maps.nth(0).locator('.leaflet-container')).toBeVisible();
  await expect(maps.nth(1).locator('.leaflet-container')).toBeVisible();
  await expect(page.locator('[data-fewo-map-consent]')).toHaveCount(0);
  await expect(maps.nth(0).locator('.leaflet-marker-icon')).toHaveAttribute('title', 'Seeherz');
  await expect(maps.nth(1).locator('.leaflet-marker-icon')).toHaveAttribute('title', 'Seeherzchen');
});

test('Anfrageformulare: Fehler und Dank erscheinen nur im abgeschickten Formular', async ({ page }) => {
  await mail.clear();
  const blocks = page.locator('.fewo-form');
  await expect(blocks).toHaveCount(2);
  await expect(blocks.nth(0).locator('[name="adults"] option')).toHaveCount(4);
  await expect(blocks.nth(1).locator('[name="adults"] option')).toHaveCount(2);

  const fill = async (block, overrides = {}) => {
    const values = { arrival: isoDate(30), departure: isoDate(33), adults: '2', lastName: 'Doppelt', email: 'doppelt@e2e.test', ...overrides };
    for (const [name, value] of Object.entries(values)) {
      const input = block.locator(`[name="${name}"]`);
      await (await input.evaluate((el) => el.tagName === 'SELECT') ? input.selectOption(value) : input.fill(value));
    }
    await block.locator('[name="consent"]').check();
  };

  // Fehler im zweiten Formular
  await fill(blocks.nth(1), { phone: 'keine' });
  await blocks.nth(1).getByRole('button', { name: 'Anfrage senden' }).click();
  await expect(blocks.nth(1).locator('.fewo-form__alert')).toBeVisible();
  await expect(blocks.nth(1).getByText('Bitte geben Sie eine gültige Telefonnummer an.')).toBeVisible();
  await expect(blocks.nth(0).locator('.fewo-form__alert')).toHaveCount(0);
  await expect(blocks.nth(0).locator('[name="lastName"]')).toHaveValue('');
  await expect(blocks.nth(1).locator('[name="lastName"]')).toHaveValue('Doppelt');

  // Erfolg im zweiten Formular
  await blocks.nth(1).locator('[name="phone"]').fill('');
  await page.waitForTimeout(3200);
  await blocks.nth(1).getByRole('button', { name: 'Anfrage senden' }).click();
  await expect(blocks.nth(1).locator('.fewo-form__success')).toHaveText('Danke für Ihre Anfrage für Seeherzchen!');
  await expect(blocks.nth(0).locator('.fewo-form__success')).toHaveCount(0);
  await expect(blocks.nth(0).locator('form')).toBeVisible();
  const messages = await mail.waitFor(1);
  expect(messages).toHaveLength(1); // keine Bestätigung an den Gast, weil im Block abgeschaltet
  expect(messages[0].Subject).toContain('Neue Anfrage (Seeherzchen)');
  // 3 Nächte × 55 € + 40 € aus der Preisliste des zweiten Formulars
  expect((await mail.get(messages[0].ID)).Text).toMatch(/Unverbindlicher Preis: +205,00\s€/);
});
