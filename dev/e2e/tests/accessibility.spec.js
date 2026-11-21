// Barrierefreiheit der Blöcke (WCAG 2.1 A/AA) mit axe-core.
// Geprüft werden nur die Bereiche der Fewo-Blöcke – das Theme der Website ist nicht Teil des Pakets.
const { AxeBuilder } = require('@axe-core/playwright');
const { test, expect, pickStay } = require('./helpers');

const TILE = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
const BLOCKS = ['.fewo-calendar', '.fewo-prices', '.fewo-quote', '.fewo-amenities', '.fewo-gallery', '.fewo-map', '.fewo-form', '.fewo-tour'];

test.use({ storageState: { cookies: [], origins: [] } });
test.beforeEach(async ({ page }) => {
  await page.route((url) => url.host !== 'localhost:8095', (route) => route.fulfill({ status: 200, contentType: 'image/png', body: TILE }));
});

const scan = async (page, selectors) => {
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
  for (const selector of selectors) {
    if (await page.locator(selector).count()) {
      builder = builder.include(selector);
    }
  }
  const { violations } = await builder.analyze();
  return violations.map((violation) => `${violation.id} (${violation.impact}): ${violation.help}\n`
    + violation.nodes.slice(0, 5).map((node) => `    ${node.target.join(' ')} – ${(node.failureSummary || '').split('\n').slice(1, 2).join(' ').trim()}`).join('\n'));
};

test('Demo-Seite: alle Blöcke im Ausgangszustand', async ({ page }) => {
  await page.goto('/index.php/fewo-demo');
  expect(await page.locator(BLOCKS.join(', ')).count()).toBeGreaterThanOrEqual(6);
  expect(await scan(page, BLOCKS)).toEqual([]);
});

test('Demo-Seite: aufgeklappter Kalender, Preisergebnis, geladene Karte', async ({ page }) => {
  await page.goto('/index.php/fewo-demo');
  await page.getByText('Weitere Monate anzeigen').click();
  const quote = page.locator('.fewo-quote');
  await pickStay(quote, 13, 18);
  await quote.getByRole('button', { name: 'Preis berechnen' }).click();
  await quote.getByText('Preis im Detail').click();
  await page.locator('.fewo-map').getByRole('button', { name: 'Karte laden' }).click();
  await expect(page.locator('.leaflet-container')).toBeVisible();
  expect(await scan(page, ['.fewo-calendar', '.fewo-quote', '.fewo-map'])).toEqual([]);
});

test('Preisrechner: geöffneter Kalender der Datumsfelder', async ({ page }) => {
  await page.goto('/index.php/fewo-demo');
  const quote = page.locator('.fewo-quote');
  await quote.locator('[data-fewo-date="arrival"]').click();
  await expect(quote.locator('.fewo-quote__calendar')).toBeVisible();
  expect(await scan(page, ['.fewo-quote'])).toEqual([]);
  // nach der Wahl der Anreise (Abreise wählen)
  await quote.locator('.fewo-quote__day[aria-disabled="false"]').first().click();
  expect(await scan(page, ['.fewo-quote'])).toEqual([]);
});

test('Großansicht der Galerie', async ({ page }) => {
  await page.goto('/index.php/fewo-demo');
  await page.locator('.fewo-gallery__link').first().click();
  await expect(page.locator('dialog.fewo-lightbox')).toBeVisible();
  expect(await scan(page, ['dialog.fewo-lightbox[open]'])).toEqual([]);
});

test('Anfrageformular: leer, mit Fehlermeldungen und nach dem Absenden', async ({ page }) => {
  await page.goto('/index.php/fewo-anfrage');
  expect(await scan(page, ['.fewo-form'])).toEqual([]);

  const form = page.locator('form[data-fewo-inquiry]');
  await form.evaluate((el) => { el.noValidate = true; el.submit(); });
  await expect(page.locator('.fewo-form__alert')).toBeVisible();
  expect(await scan(page, ['.fewo-form'])).toEqual([]);
});

test('Seite mit doppelten Blöcken und aufgeklappten Ausstattungslisten', async ({ page }) => {
  await page.goto('/index.php/fewo-doppelt');
  for (const summary of await page.locator('.fewo-amenities summary').all()) {
    await summary.click();
  }
  expect(await scan(page, BLOCKS)).toEqual([]);
});
