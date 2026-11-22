// Dashboard › Fewo Toolkit 2 › Neuigkeiten: Stufen der Wichtigkeit (Code, Bezeichnung, Farbe, Reihenfolge) und
// Gültigkeitszeitraum – für alle Blöcke "Neuigkeiten" der Website.
const { test, expect, webExec } = require('./helpers');

const PAGE = '/index.php/dashboard/fewo_tk2/news';
const DEMO = '/index.php/fewo-demo';
const CONFIG = 'application/config/generated_overrides/fewo_tk2/news.php';
const reset = () => webExec(`rm -f ${CONFIG}`);
const values = (locator) => locator.evaluateAll((fields) => fields.map((field) => field.value));

test.describe.configure({ mode: 'serial' });
test.beforeAll(reset);
test.afterAll(reset);

test('Vorgaben wie in Fewo-Office, im Menü nach den Preisen', async ({ page }) => {
  await page.goto(PAGE);
  await expect(page.getByRole('heading', { name: 'Neuigkeiten', exact: true })).toBeVisible();
  await expect(page.getByRole('switch', { name: 'Gültigkeitszeitraum anzeigen' })).toBeChecked();
  const levels = page.locator('#fewo-news-levels');
  expect(await values(levels.locator('[name="levelCode[]"]'))).toEqual(['important', 'notice', 'info']);
  expect(await values(levels.locator('[name="levelLabel[]"]'))).toEqual(['Wichtige Mitteilung', 'Hinweis', 'Information']);
  expect(await values(levels.locator('[name="levelColor[]"]'))).toEqual(['#c62828', '#f9a825', '#2e7d32']);
  await expect(page.locator('#ccm-dashboard-page, body').getByRole('link', { name: 'Neuigkeiten', exact: true }).first()).toBeVisible();
});

test('Stufen und Zeitraum einstellen: gilt für alle Blöcke', async ({ page }) => {
  await page.goto(PAGE);
  // "info" heißt "Gut zu wissen", wird blau und steht oben; "notice" entfällt
  const rows = page.locator('#fewo-news-levels tbody tr');
  await rows.nth(2).locator('[name="levelLabel[]"]').fill('Gut zu wissen');
  await rows.nth(2).locator('[name="levelColor[]"]').fill('#1565c0');
  await rows.nth(2).getByRole('button', { name: 'Nach oben' }).click();
  await rows.nth(1).getByRole('button', { name: 'Nach oben' }).click();
  await rows.nth(2).getByRole('button', { name: 'Entfernen' }).click();
  expect(await values(page.locator('[name="levelCode[]"]'))).toEqual(['info', 'important']);
  await page.getByRole('switch', { name: 'Gültigkeitszeitraum anzeigen' }).uncheck();

  // Jeder Code nur einmal; Eingaben bleiben erhalten
  await page.locator('[name="levelCode[]"]').nth(1).fill('info');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByText('Jeder Code darf nur einer Stufe zugeordnet sein.')).toBeVisible();
  expect(await values(page.locator('[name="levelLabel[]"]'))).toEqual(['Gut zu wissen', 'Wichtige Mitteilung']);
  await expect(page.getByRole('switch', { name: 'Gültigkeitszeitraum anzeigen' })).not.toBeChecked();
  await page.locator('[name="levelCode[]"]').nth(1).fill('important');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByText('Die Einstellungen für die Neuigkeiten wurden gespeichert.')).toBeVisible();

  // Auf der Website: Information zuerst, blau, ohne Zeitraum – in allen Blöcken
  for (const path of [DEMO, '/index.php/fewo-doppelt']) {
    await page.goto(path);
    const news = page.locator('.fewo-news').last();
    await expect(news.locator('.fewo-news__importance')).toHaveText(['Gut zu wissen', 'Wichtige Mitteilung']);
    await expect(news.locator('.fewo-news__item-title')).toHaveText(['Weihnachtsmarkt', 'Schwimmbadpause']);
    expect(await news.locator('.fewo-news__importance').first().evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(21, 101, 192)');
    await expect(page.locator('.fewo-news__period')).toHaveCount(0);
  }
  // Seeherzchen liefert nur "notice": ohne Stufe grau und ohne Bezeichnung
  const small = page.locator('.fewo-news').first();
  await expect(small.locator('.fewo-news__item-title')).toHaveText(['Fahrräder kostenlos']);
  await expect(small.locator('.fewo-news__importance')).toHaveCount(0);
  expect(await small.locator('.fewo-news__item').evaluate((el) => getComputedStyle(el).borderLeftColor)).toBe('rgb(107, 114, 128)');
});

test('Vorgaben wiederherstellen', async ({ page }) => {
  await page.goto(PAGE);
  await page.getByRole('button', { name: 'Vorgaben wiederherstellen' }).click();
  expect(await values(page.locator('[name="levelCode[]"]'))).toEqual(['important', 'notice', 'info']);
  await page.getByRole('switch', { name: 'Gültigkeitszeitraum anzeigen' }).check();
  await page.getByRole('button', { name: 'Speichern' }).click();
  await page.goto(DEMO);
  await expect(page.locator('.fewo-news').first().locator('.fewo-news__importance')).toHaveText(['Wichtige Mitteilung', 'Information']);
  await expect(page.locator('.fewo-news__period').first()).toBeVisible();
});
