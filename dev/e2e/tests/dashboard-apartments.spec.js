// Dashboard › Fewo Toolkit › Wohnungen: Adressen in Fewo-Office je Wohnung (Belegung, Preise, Neuigkeiten), Stand des Abrufs, "Jetzt aktualisieren"
const { test, expect, sql, webExec } = require('./helpers');

const DASHBOARD = '/index.php/dashboard/fewo_tk2/apartments';
const DEMO = '/index.php/fewo-demo';
// Daten im Format der Fewo-Office-Schnittstelle, die das Testprojekt selbst ausliefert (siehe ../../seed.php)
const FILES = 'localhost/application/files';
const OCCUPANCY_URL = `http://${FILES}/fewo-demo-belegung.json`;
const PRICES_URL = `http://${FILES}/fewo-demo-preise.json`;
const NEWS_URL = `http://${FILES}/fewo-demo-neuigkeiten.json`;
const NAME = 'E2E Wohnung';

const row = (page, name) => page.locator('#fewo-apartments tbody tr').filter({ has: page.getByRole('link', { name, exact: true }) });
const updateNow = (page, name) => row(page, name).getByRole('button', { name: 'Jetzt aktualisieren' }).click();
const cleanUp = () => {
  sql(`DELETE FROM FewoApartments WHERE name LIKE '${NAME}%'`);
  // Zwischenspeicher der absichtlich falschen Adressen aus dem Test
  sql(`DELETE FROM FewoFeeds WHERE url LIKE 'https://${FILES}/%' OR (kind = 'pricelist' AND url = '${OCCUPANCY_URL}') OR (kind = 'news' AND url = '${PRICES_URL}')`);
};

test.describe.configure({ mode: 'serial' });
test.beforeAll(cleanUp);
test.afterAll(cleanUp);

test('Übersicht zeigt die Demo-Wohnungen mit dem Stand des letzten Abrufs', async ({ page }) => {
  await page.goto(DEMO); // lädt Belegung, Preisliste und Neuigkeiten der Wohnung "Seeherz (Demo)", falls noch nicht geschehen
  await page.goto(DASHBOARD);
  await expect(page.getByRole('heading', { name: 'Wohnungen', exact: true })).toBeVisible();
  await expect(page.getByText('Belegung, Preise und Neuigkeiten werden nicht auf der Website gepflegt, sie kommen immer aus Fewo-Office.')).toBeVisible();
  await expect(page.locator('#fewo-apartments thead th')).toHaveText(['Wohnung', 'Belegung', 'Preisliste', 'Neuigkeiten', 'Aktualisierung alle', 'Aktionen']);

  const seeherz = row(page, 'Seeherz (Demo)');
  await expect(seeherz.locator('td').nth(1)).toContainText('OK');
  await expect(seeherz.locator('td').nth(1)).toContainText(/Stand: \d/);
  await expect(seeherz.locator('td').nth(1)).toContainText(OCCUPANCY_URL);
  // Die abgelaufene Saison aus den Demo-Daten zählt nicht mit
  await expect(seeherz.locator('td').nth(2)).toContainText('3 Saisons');
  // Von den drei Neuigkeiten der Demo-Daten gilt heute nur eine
  await expect(seeherz.locator('td').nth(3)).toContainText('OK');
  await expect(seeherz.locator('td').nth(3)).toContainText('1 aktuelle Neuigkeit');
  await expect(seeherz.locator('td').nth(3)).toContainText(NEWS_URL);
  await expect(seeherz.locator('td').nth(4)).toHaveText('1 Stunde');
  // Wohnung ohne Preisliste und ohne Neuigkeiten
  await expect(row(page, 'Seeherz – Belegung als ICS (Demo)').locator('td').nth(2)).toHaveText('nicht hinterlegt');
  await expect(row(page, 'Seeherz – Belegung als ICS (Demo)').locator('td').nth(3)).toHaveText('nicht hinterlegt');

  // Die Startseite des Pakets im Dashboard führt zu den Wohnungen
  await page.goto('/index.php/dashboard/fewo_tk2');
  await expect(page).toHaveURL(/dashboard\/fewo_tk2\/apartments/);
});

test('Preise: Saisons je Gästezahl, Mindestbelegung, Mindestdauer, Endreinigung und "Jetzt aktualisieren"', async ({ page }) => {
  await page.goto('/index.php/dashboard/fewo_tk2/prices');
  await expect(page.getByRole('heading', { name: 'Preise', exact: true })).toBeVisible();
  // im Menü zwischen Wohnungen und KI-Assistent
  expect(sql("SELECT GROUP_CONCAT(p.cPath ORDER BY c.cDisplayOrder SEPARATOR ' ') FROM PagePaths p JOIN Pages c ON c.cID = p.cID WHERE p.cPath LIKE '/dashboard/fewo_tk2/%'"))
    .toBe('/dashboard/fewo_tk2/apartments /dashboard/fewo_tk2/prices /dashboard/fewo_tk2/assistant');

  const card = page.locator('.fewo-prices-card[data-apartment="Seeherz (Demo)"]');
  await expect(card.locator('h2')).toContainText('Ferienwohnung Seeherz');
  await expect(card.locator('[data-fewo-condition="minNights"]')).toHaveText('3 Nächte');
  await expect(card.locator('[data-fewo-condition="cleaning"]')).toHaveText('65 € (einmalig je Aufenthalt)');
  await expect(card.locator('[data-fewo-condition="maxGuests"]')).toHaveText('4 Gäste');
  await expect(card.locator('[data-fewo-condition="downPayment"]')).toHaveText('30 % des Gesamtpreises');
  await expect(card.locator('thead th')).toHaveText(['Saison', 'Zeitraum', '1 Gast', '2 Gäste', '3 Gäste', '4 Gäste', 'Mindestbelegung']);
  const cells = (name) => card.locator('tbody tr').filter({ hasText: name }).locator('td');
  // Nebensaison: kein Preis für eine Person hinterlegt – es gilt der für zwei, also Mindestbelegung 2
  await expect(cells('Nebensaison').nth(0)).toHaveText(/^\d\d\.\d\d\.\d{4} – \d\d\.\d\d\.\d{4}$/);
  await expect(cells('Nebensaison').filter({ hasText: /€|Gast|Gäste/ })).toHaveText(['75 €', '75 €', '85 €', '90 €', '2 Gäste']);
  // Hauptsaison: eigener Preis für eine Person; für drei gilt der Preis der nächsthöheren Gästezahl
  await expect(cells('Hauptsaison').filter({ hasText: /€|Gast|Gäste/ })).toHaveText(['85 €', '95 €', '115 €', '115 €', '1 Gast']);
  await expect(cells('Übrige Zeiträume').nth(0)).toHaveText('Standardpreis');
  // abgelaufene Saison der Demo-Daten fehlt
  await expect(card.locator('tbody tr')).toHaveCount(4);

  const small = page.locator('.fewo-prices-card[data-apartment="Seeherzchen (Demo)"]');
  await expect(small.locator('[data-fewo-condition="downPayment"]')).toHaveText('keine');
  await expect(small.locator('thead th')).toHaveText(['Saison', 'Zeitraum', '1 Gast', '2 Gäste', 'Mindestbelegung']);
  await expect(page.locator('.fewo-prices-card[data-apartment="Seeherz – Belegung als ICS (Demo)"]')).toContainText('Für diese Wohnung ist keine Adresse der Preisliste hinterlegt.');

  // "Jetzt aktualisieren" einer Wohnung und aller Wohnungen
  const fetched = () => sql(`SELECT UNIX_TIMESTAMP(fetchedAt) FROM FewoFeeds WHERE kind = 'pricelist' ORDER BY fetchedAt DESC LIMIT 1`);
  sql("UPDATE FewoFeeds SET fetchedAt = fetchedAt - INTERVAL 10 MINUTE, attemptedAt = attemptedAt - INTERVAL 10 MINUTE WHERE kind = 'pricelist'");
  const before = Number(fetched());
  await card.getByRole('button', { name: 'Jetzt aktualisieren' }).click();
  await expect(page.getByText('Die Preise von 1 Wohnung wurden neu aus Fewo-Office geladen.')).toBeVisible();
  expect(Number(fetched())).toBeGreaterThan(before + 300);
  await page.locator('.ccm-dashboard-header-buttons').getByRole('button', { name: 'Jetzt aktualisieren' }).click();
  await expect(page.getByText('Die Preise von 2 Wohnungen wurden neu aus Fewo-Office geladen.')).toBeVisible();
});

test('Wohnung anlegen: Prüfung der Eingaben, nicht erreichbare Adresse, korrigieren', async ({ page }) => {
  await page.goto(DASHBOARD);
  await page.getByRole('link', { name: 'Wohnung hinzufügen' }).click();
  await expect(page.locator('legend')).toHaveText('Wohnung hinzufügen');
  await expect(page.getByLabel('Aktualisierung alle')).toHaveValue('60');
  await expect(page.getByLabel('Aktualisierung alle').locator('option')).toHaveText(['5 Minuten', '10 Minuten', '15 Minuten', '30 Minuten', '1 Stunde', '2 Stunden', '6 Stunden', '12 Stunden', '24 Stunden']);

  // Der Browser verlangt einen Namen und lässt in den Adressfeldern nur Adressen zu ...
  await page.getByRole('button', { name: 'Speichern' }).click();
  expect(await page.getByLabel('Name').evaluate((el) => el.validity.valueMissing)).toBe(true);
  await page.getByLabel('Name').fill(NAME);
  await page.getByLabel('Adresse der Belegung in Fewo-Office').fill('kein link');
  await page.getByRole('button', { name: 'Speichern' }).click();
  expect(await page.getByLabel('Adresse der Belegung in Fewo-Office').evaluate((el) => el.validity.typeMismatch)).toBe(true);

  // ... der Server verlangt mindestens eine Adresse und nimmt nur http(s) und webcal; Eingaben bleiben erhalten
  await page.getByLabel('Adresse der Belegung in Fewo-Office').fill('');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByText('Bitte geben Sie mindestens eine der Adressen an.')).toBeVisible();
  await expect(page.getByLabel('Name')).toHaveValue(NAME);
  await page.getByLabel('Adresse der Belegung in Fewo-Office').fill('ftp://example.org/belegung.json');
  await page.getByLabel('Adresse der Preisliste in Fewo-Office').fill('ftp://example.org/preise.json');
  await page.getByLabel('Adresse der Neuigkeiten in Fewo-Office (optional)').fill('ftp://example.org/neuigkeiten.json');
  await page.getByLabel('Aktualisierung alle').selectOption('120');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByText('Bitte geben Sie eine gültige Adresse der Belegung an (https://…).')).toBeVisible();
  await expect(page.getByText('Bitte geben Sie eine gültige Adresse der Preisliste an (https://…).')).toBeVisible();
  await expect(page.getByText('Bitte geben Sie eine gültige Adresse der Neuigkeiten an (https://…).')).toBeVisible();
  await expect(page.getByLabel('Adresse der Belegung in Fewo-Office')).toHaveValue('ftp://example.org/belegung.json');
  await expect(page.getByLabel('Aktualisierung alle')).toHaveValue('120');
  expect(sql(`SELECT COUNT(*) FROM FewoApartments WHERE name = '${NAME}'`)).toBe('0');

  // webcal:// wird zu https:// – unter dieser Adresse ist im Testprojekt nichts erreichbar.
  // Gespeichert wird trotzdem, der Fehlschlag wird gemeldet. Unter der zweiten Adresse liegt die Belegung statt der Preisliste.
  await page.getByLabel('Adresse der Belegung in Fewo-Office').fill(`webcal://${FILES}/fewo-demo-belegung.json`);
  await page.getByLabel('Adresse der Preisliste in Fewo-Office').fill(OCCUPANCY_URL);
  // ... und unter der Adresse der Neuigkeiten die Preisliste
  await page.getByLabel('Adresse der Neuigkeiten in Fewo-Office (optional)').fill(PRICES_URL);
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page).toHaveURL(/dashboard\/fewo_tk2\/apartments(\/view)?$/);
  await expect(page.getByText(`„${NAME}“: Die Daten konnten nicht aus Fewo-Office geladen werden.`)).toBeVisible();
  await expect(page.getByText('Preisliste: Die Daten enthalten keine Preisliste.')).toBeVisible();
  await expect(page.getByText('Neuigkeiten: Die Daten enthalten keine Neuigkeiten.')).toBeVisible();
  expect(sql(`SELECT CONCAT_WS('|', occupancyUrl, pricelistUrl, newsUrl, refreshMinutes) FROM FewoApartments WHERE name = '${NAME}'`))
    .toBe(`https://${FILES}/fewo-demo-belegung.json|${OCCUPANCY_URL}|${PRICES_URL}|120`);
  const apartment = row(page, NAME);
  await expect(apartment.locator('td').nth(1)).toContainText('noch nicht geladen');
  await expect(apartment.locator('td').nth(1).locator('.text-danger')).not.toBeEmpty();
  await expect(apartment.locator('td').nth(3)).toContainText('noch nicht geladen');
  await expect(apartment.locator('td').nth(4)).toHaveText('2 Stunden');
  // Die falsch eingetragene Adresse stört die Wohnung nicht, die dieselbe Adresse richtig als Belegung verwendet
  await expect(row(page, 'Seeherz (Demo)').locator('td').nth(1)).toContainText('OK');
  await expect(row(page, 'Seeherz (Demo)').locator('td').nth(1).locator('.text-danger')).toHaveCount(0);

  // Bearbeiten: richtige Adressen
  await apartment.getByRole('link', { name: 'Bearbeiten' }).click();
  await expect(page.locator('legend')).toHaveText('Wohnung bearbeiten');
  await expect(page.getByLabel('Name')).toHaveValue(NAME);
  await page.getByLabel('Adresse der Belegung in Fewo-Office').fill(OCCUPANCY_URL);
  await page.getByLabel('Adresse der Preisliste in Fewo-Office').fill(PRICES_URL);
  await page.getByLabel('Adresse der Neuigkeiten in Fewo-Office (optional)').fill(NEWS_URL);
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByText('Die Wohnung wurde gespeichert.')).toBeVisible();
  await expect(apartment.locator('td').nth(3)).toContainText('1 aktuelle Neuigkeit');
  await expect(apartment.locator('td').nth(1)).toContainText('OK');
  await expect(apartment.locator('td').nth(2)).toContainText('OK');
  await expect(apartment.locator('td').nth(2)).toContainText('3 Saisons');
  expect(sql(`SELECT COUNT(*) FROM FewoApartments WHERE name = '${NAME}'`)).toBe('1');
});

test('"Jetzt aktualisieren", Ausfall von Fewo-Office: der letzte Stand bleibt sichtbar', async ({ page, browser }) => {
  const calendar = page.locator('.fewo-calendar').first();
  const bookedDays = calendar.locator('.fewo-calendar__days .fewo-day--pm-booked');

  // Veralteter Zwischenspeicher (hier: ohne Buchungen) wird sofort ersetzt
  sql(`UPDATE FewoFeeds SET body = '{"status":{"type":"OK"},"bookings":[]}' WHERE kind = 'occupancy' AND url = '${OCCUPANCY_URL}'`);
  await page.goto(DEMO);
  await expect(bookedDays).toHaveCount(0);
  await page.goto(DASHBOARD);
  await updateNow(page, NAME);
  await expect(page.getByText(`Die Daten von „${NAME}“ wurden neu aus Fewo-Office geladen.`)).toBeVisible();
  await page.goto(DEMO);
  await expect(bookedDays.first()).toBeVisible();
  await expect(page.locator('.fewo-notice')).toHaveCount(0);

  webExec('mv application/files/fewo-demo-belegung.json application/files/fewo-demo-belegung.json.aus');
  try {
    // Adresse nicht erreichbar: Meldung im Dashboard, der letzte gute Stand bleibt erhalten
    await page.goto(DASHBOARD);
    await updateNow(page, NAME);
    await expect(page.getByText(`„${NAME}“: Die Daten konnten nicht aus Fewo-Office geladen werden.`)).toBeVisible();
    await expect(row(page, NAME).locator('td').nth(1)).toContainText('letzte Aktualisierung fehlgeschlagen');
    await expect(row(page, NAME).locator('td').nth(1)).toContainText(/Stand: \d/);
    // Die Wohnung "Seeherz (Demo)" nutzt dieselbe Adresse und zeigt denselben Stand
    await expect(row(page, 'Seeherz (Demo)').locator('td').nth(1)).toContainText('letzte Aktualisierung fehlgeschlagen');

    // Auf der Website: Belegung weiter sichtbar, Redakteure bekommen einen Hinweis, Besucher nicht
    await page.goto(DEMO);
    await expect(bookedDays.first()).toBeVisible();
    await expect(calendar.locator('.fewo-notice--warning')).toContainText('Belegungsplan: Die letzte Aktualisierung ist fehlgeschlagen');
    await expect(calendar.locator('.fewo-notice--warning')).toContainText('Angezeigt wird der Stand vom');
    const guest = await browser.newContext({ storageState: { cookies: [], origins: [] }, locale: 'de-DE' });
    const view = await guest.newPage();
    await view.goto(DEMO);
    await expect(view.locator('.fewo-calendar').first().locator('.fewo-day--pm-booked').first()).toBeVisible();
    await expect(view.locator('.fewo-notice')).toHaveCount(0);
    await guest.close();
    // Kein neuer Abrufversuch bei jedem Seitenaufruf
    const attempted = sql(`SELECT attemptedAt FROM FewoFeeds WHERE kind = 'occupancy' AND url = '${OCCUPANCY_URL}'`);
    await page.reload();
    expect(sql(`SELECT attemptedAt FROM FewoFeeds WHERE kind = 'occupancy' AND url = '${OCCUPANCY_URL}'`)).toBe(attempted);

    // Liefert die Adresse etwas Unbrauchbares (z. B. eine Fehlerseite), bleibt ebenfalls der letzte Stand erhalten
    webExec('echo "<html><body>Wartung</body></html>" > application/files/fewo-demo-belegung.json');
    await page.goto(DASHBOARD);
    await updateNow(page, NAME);
    await expect(page.getByText('Belegung: Die Daten enthalten keine Belegung.')).toBeVisible();
    await page.goto(DEMO);
    await expect(bookedDays.first()).toBeVisible();
  } finally {
    webExec('mv application/files/fewo-demo-belegung.json.aus application/files/fewo-demo-belegung.json');
  }
  await page.goto(DASHBOARD);
  await updateNow(page, NAME);
  await expect(page.getByText(`Die Daten von „${NAME}“ wurden neu aus Fewo-Office geladen.`)).toBeVisible();
  await expect(row(page, 'Seeherz (Demo)').locator('td').nth(1)).toContainText('OK');
  await page.goto(DEMO);
  await expect(page.locator('.fewo-notice')).toHaveCount(0);
});

test('Wohnung löschen', async ({ page }) => {
  await page.goto(DASHBOARD);
  page.once('dialog', (dialog) => dialog.dismiss());
  await row(page, NAME).getByRole('button', { name: 'Löschen' }).click();
  await expect(row(page, NAME)).toHaveCount(1);
  page.once('dialog', (dialog) => {
    expect(dialog.message()).toBe(`Die Wohnung „${NAME}“ löschen? Blöcke, die sie verwenden, zeigen dann keine Daten mehr an.`);
    dialog.accept();
  });
  await row(page, NAME).getByRole('button', { name: 'Löschen' }).click();
  await expect(page.getByText('Die Wohnung wurde gelöscht.')).toBeVisible();
  await expect(row(page, NAME)).toHaveCount(0);
  // Die Demo-Wohnungen und ihre Blöcke sind nicht betroffen
  await expect(row(page, 'Seeherz (Demo)')).toHaveCount(1);
});
