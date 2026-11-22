// Dialoge zum Hinzufügen und Bearbeiten der neun Blöcke (ohne Chat) im Bearbeitungsmodus von Concrete
const { test, expect, sql, concrete, webExec, phpLog, mail, isoDate, pickStay } = require('./helpers');
const { enterEditMode, openAddDialog, openEditDialog, submitDialog, submitExpectingErrors, publish, blocks } = require('./editmode');

const PAGE = '/index.php/fewo-e2e';
/** Datensatz des zuletzt gespeicherten Blocks eines Typs. */
const record = (table) => JSON.parse(sql(`SELECT JSON_OBJECT(${sql(`SELECT GROUP_CONCAT(CONCAT("'", COLUMN_NAME, "', ", COLUMN_NAME)) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'concrete' AND TABLE_NAME = '${table}'`)}) FROM ${table} ORDER BY bID DESC LIMIT 1`));
/** Werte mehrerer Eingabefelder in der Reihenfolge auf der Seite. */
const values = (locator) => locator.evaluateAll((fields) => fields.map((field) => field.value));
/** Nummer einer Demo-Wohnung aus dem Dashboard (siehe ../../seed.php). */
const apartmentId = (name) => Number(sql(`SELECT id FROM FewoApartments WHERE name = '${name}'`));
const APARTMENTS = ['** Bitte wählen', 'Seeherz – Belegung als ICS (Demo)', 'Seeherz (Demo)', 'Seeherzchen (Demo)'];
const STARTED = new Date().toISOString();

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { concrete('c5:exec', '/seed/e2e/reset-page.php'); });
test.beforeEach(async ({ page }) => { await enterEditMode(page, PAGE); });

test('Preisübersicht: Wohnung wählen, Anordnung, abgelaufene Saisons', async ({ page }) => {
  let dialog = await openAddDialog(page, 'fewo_preisuebersicht');
  await expect(dialog.locator('.ui-dialog-title')).toHaveText('Preisübersicht hinzufügen');
  // Die Adressen in Fewo-Office stehen im Dashboard; im Block wird nur die Wohnung gewählt
  await expect(dialog.getByLabel('Wohnung')).toBeVisible();
  await expect(dialog.getByLabel('Wohnung').locator('option')).toHaveText(APARTMENTS);
  await expect(dialog.getByRole('link', { name: 'Wohnungen verwalten' })).toHaveAttribute('href', /dashboard\/fewo_tk2\/apartments$/);
  await expect(dialog.locator('[name="pricelistUrl"], [name="refreshMinutes"]')).toHaveCount(0);
  // Vorgaben des Blocks sind gesetzt
  await expect(dialog.getByLabel(/Bedingungen anzeigen/)).toBeChecked();
  await expect(dialog.getByLabel('Anordnung der Saisons')).toHaveValue('tiles');
  await expect(dialog.getByLabel('Anordnung der Saisons').locator('option')).toHaveText(['Kacheln nebeneinander', 'Liste untereinander']);

  // ohne Wohnung nicht speicherbar: der Browser verlangt eine Auswahl, der Dialog bleibt offen
  await dialog.locator('.ui-dialog-buttonpane .btn-primary').click();
  expect(await dialog.getByLabel('Wohnung').evaluate((el) => el.validity.valueMissing)).toBe(true);
  await expect(dialog).toBeVisible();

  // Wohnung ohne Preisliste: Redakteure sehen den Grund, Besucher "Preise auf Anfrage"
  await dialog.getByLabel('Wohnung').selectOption({ label: 'Seeherz – Belegung als ICS (Demo)' });
  await dialog.getByLabel('Überschrift (optional)').fill('Unsere Preise');
  await submitDialog(page, dialog);
  const block = blocks(page, 'fewo_preisuebersicht');
  await expect(block).toHaveCount(1);
  await expect(block.locator('.fewo-notice--error')).toContainText('Preisübersicht: Es ist keine gültige Adresse für die Preisliste hinterlegt.');
  await expect(block.locator('.fewo-prices__empty')).toHaveText('Preise auf Anfrage.');

  dialog = await openEditDialog(page, 'fewo_preisuebersicht');
  await expect(dialog.locator('.ui-dialog-title')).toHaveText('Preisübersicht bearbeiten');
  await expect(dialog.getByLabel('Wohnung')).toHaveValue(String(apartmentId('Seeherz – Belegung als ICS (Demo)')));
  await dialog.getByLabel('Wohnung').selectOption({ label: 'Seeherz (Demo)' });
  await submitDialog(page, dialog);
  await expect(block.locator('.fewo-notice')).toHaveCount(0);
  await expect(block.locator('h2')).toHaveText('Unsere Preise');
  await expect(block.locator('.fewo-prices--tiles')).toHaveCount(1);
  // Die abgelaufene Saison aus den Daten erscheint nicht; nach den Saisons folgt der Standardpreis
  await expect(block.locator('.fewo-prices__season-name')).toHaveText(['Nebensaison', 'Hauptsaison', 'Zwischensaison', 'Übrige Zeiträume']);
  await expect(block.locator('.fewo-prices__season').first().locator('.fewo-prices__rate')).toHaveText([/1–2 Gäste\s*75\s€\s*\/ Nacht/, /3 Gäste\s*85\s€\s*\/ Nacht/, /4 Gäste\s*90\s€\s*\/ Nacht/]);
  await expect(block.locator('.fewo-prices__conditions li')).toHaveText(['Mindestaufenthalt: 3 Nächte', /^Endreinigung: 65\s€ \(einmalig pro Aufenthalt\)$/, 'Anzahlung: 30 % des Gesamtpreises']);
  expect(record('btFewoPreisuebersicht')).toMatchObject({ apartmentID: apartmentId('Seeherz (Demo)'), title: 'Unsere Preise', layout: 'tiles', showConditions: 1 });

  // Anordnung "Liste untereinander": jede Saison nimmt die ganze Breite ein
  dialog = await openEditDialog(page, 'fewo_preisuebersicht');
  await expect(dialog.getByLabel('Überschrift (optional)')).toHaveValue('Unsere Preise');
  await dialog.getByLabel('Überschrift (optional)').fill('Preise 2027');
  await dialog.getByLabel('Anordnung der Saisons').selectOption({ label: 'Liste untereinander' });
  await dialog.getByLabel(/Bedingungen anzeigen/).uncheck();
  await submitDialog(page, dialog);
  await expect(block.locator('h2')).toHaveText('Preise 2027');
  await expect(block.locator('.fewo-prices--list')).toHaveCount(1);
  const lefts = await block.locator('.fewo-prices__season').evaluateAll((items) => items.map((item) => Math.round(item.getBoundingClientRect().left)));
  expect(new Set(lefts).size).toBe(1);
  await expect(block).not.toContainText('Endreinigung');
  expect(record('btFewoPreisuebersicht')).toMatchObject({ title: 'Preise 2027', layout: 'list', showConditions: 0 });
});

test('Belegungsplan: Wohnung wählen, Monate, Stichwörter für "auf Anfrage"', async ({ page }) => {
  let dialog = await openAddDialog(page, 'fewo_belegungsplan');
  // Vorgaben
  await expect(dialog.getByLabel('Wohnung').locator('option')).toHaveText(APARTMENTS);
  await expect(dialog.locator('[name="monthsTotal"]')).toHaveValue('12');
  await expect(dialog.locator('[name="monthsVisible"]')).toHaveValue('4');
  await expect(dialog.locator('[name="onRequestKeywords"]')).toHaveValue('');
  await expect(dialog.locator('[name="showLegend"]')).toBeChecked();
  await expect(dialog.locator('[name="occupancyUrl"], [name="refreshMinutes"]')).toHaveCount(0);

  await dialog.locator('[name="title"]').fill('Belegung');
  await dialog.getByLabel('Wohnung').selectOption({ label: 'Seeherz – Belegung als ICS (Demo)' });
  // mehr als 36 Monate lässt schon der Browser nicht zu
  await dialog.locator('[name="monthsTotal"]').fill('40');
  await dialog.locator('.ui-dialog-buttonpane .btn-primary').click();
  expect(await dialog.locator('[name="monthsTotal"]').evaluate((el) => el.validity.rangeOverflow)).toBe(true);
  await dialog.locator('[name="monthsTotal"]').fill('12');
  await submitDialog(page, dialog);
  const block = blocks(page, 'fewo_belegungsplan');
  await expect(block).toHaveCount(1);
  expect(record('btFewoBelegungsplan')).toMatchObject({ apartmentID: apartmentId('Seeherz – Belegung als ICS (Demo)'), title: 'Belegung', monthsTotal: 12, monthsVisible: 4, onRequestKeywords: '', showLegend: 1 });
  await expect(block.locator('.fewo-notice')).toHaveCount(0);
  await expect(block.locator('.fewo-calendar__month:visible')).toHaveCount(4);
  await expect(block.locator('.fewo-calendar__days .fewo-day--pm-booked').first()).toBeVisible();
  // ohne Stichwörter gibt es keinen Zustand "auf Anfrage"
  await expect(block.locator('.fewo-calendar__legend li')).toHaveText(['Frei', 'Belegt', 'An-/Abreisetag']);
  await expect(block.locator('.fewo-day--pm-request')).toHaveCount(0);

  dialog = await openEditDialog(page, 'fewo_belegungsplan');
  await dialog.locator('[name="monthsTotal"]').fill('5');
  await dialog.locator('[name="monthsVisible"]').fill('2');
  await dialog.locator('[name="onRequestKeywords"]').fill('Betriebsferien');
  await submitDialog(page, dialog);
  await expect(block.locator('.fewo-calendar__month:visible')).toHaveCount(2);
  await expect(block.locator('.fewo-calendar__month')).toHaveCount(5);
  await expect(block.locator('.fewo-calendar__legend li')).toHaveText(['Frei', 'Belegt', 'An-/Abreisetag', 'Auf Anfrage']);
  await expect(block.locator('.fewo-day--pm-request').first()).toBeAttached();
  expect(record('btFewoBelegungsplan')).toMatchObject({ monthsTotal: 5, monthsVisible: 2, onRequestKeywords: 'Betriebsferien' });
});

test('Preisrechner: hinzufügen und bearbeiten', async ({ page }) => {
  let dialog = await openAddDialog(page, 'fewo_preisrechner');
  await expect(dialog.getByLabel('Wohnung').locator('option')).toHaveText(APARTMENTS);
  await expect(dialog.getByText('Gerechnet wird wie in Fewo-Office: Jede Nacht kostet den Preis des Tages, an dem sie endet.')).toBeVisible();
  await expect(dialog.getByText('Seite mit dem Anfrageformular (optional)')).toBeVisible();
  // Wohnung mit Preisliste im XML-Format
  await dialog.getByLabel('Wohnung').selectOption({ label: 'Seeherzchen (Demo)' });
  await dialog.locator('[name="title"]').fill('Was kostet mein Urlaub?');
  await submitDialog(page, dialog);

  const block = blocks(page, 'fewo_preisrechner');
  await expect(block.locator('h2')).toHaveText('Was kostet mein Urlaub?');
  await expect(block.getByRole('button', { name: 'Preis berechnen' })).toBeVisible();
  await expect(block.locator('[name="guests"] option')).toHaveCount(2);
  expect(record('btFewoPreisrechner')).toMatchObject({ apartmentID: apartmentId('Seeherzchen (Demo)'), title: 'Was kostet mein Urlaub?', inquiryPageID: 0 });

  dialog = await openEditDialog(page, 'fewo_preisrechner');
  await dialog.getByLabel('Wohnung').selectOption({ label: 'Seeherz (Demo)' });
  await submitDialog(page, dialog);
  await expect(block.locator('[name="guests"] option')).toHaveCount(4);
  expect(record('btFewoPreisrechner')).toMatchObject({ apartmentID: apartmentId('Seeherz (Demo)') });
});

test('Anfrageformular: Vorgaben, Prüfung der Empfängeradresse, Captcha einschalten', async ({ page }) => {
  let dialog = await openAddDialog(page, 'fewo_kontaktformular');
  await expect(dialog.locator('[name="recipientEmail"]')).toHaveValue('noreply@fewo-dev.test');
  await expect(dialog.locator('[name="referralOptions"]')).toHaveValue('Suchmaschine\nUrlaubsportal\nEmpfehlung von Bekannten\nTourist-Information');
  await expect(dialog.locator('[name="successMessage"]')).toHaveValue('Vielen Dank für Ihre Anfrage! Wir melden uns so schnell wie möglich bei Ihnen.');
  await expect(dialog.locator('[name="maxGuests"]')).toHaveValue('6');
  await expect(dialog.getByLabel('Wohnung (optional)').locator('option')).toHaveText(['Keine', ...APARTMENTS.slice(1)]);
  for (const name of ['sendConfirmation', 'askForAddress']) {
    await expect(dialog.locator(`[name="${name}"]`)).toBeChecked();
  }
  await expect(dialog.locator('[name="useCaptcha"]')).not.toBeChecked();

  // "a@b" lässt der Browser durch, der Server nicht
  await dialog.locator('[name="recipientEmail"]').fill('vermieter@lokal');
  expect(await submitExpectingErrors(page, dialog)).toContain('Bitte geben Sie eine gültige Empfängeradresse an.');
  await dialog.locator('[name="recipientEmail"]').fill('vermieter@fewo-dev.test');
  await dialog.locator('[name="formName"]').fill('E2E Formular');
  await dialog.locator('[name="accommodations"]').fill('Seeherz\n\n  Seeherzchen  \n');
  await dialog.locator('[name="maxGuests"]').fill('3');
  await dialog.locator('[name="sendConfirmation"]').uncheck();
  await submitDialog(page, dialog);

  const block = blocks(page, 'fewo_kontaktformular');
  await expect(block.locator('[name="accommodation"] option')).toHaveText(['Bitte wählen', 'Seeherz', 'Seeherzchen']);
  await expect(block.locator('[name="adults"] option')).toHaveCount(3);
  expect(record('btFewoKontaktformular')).toMatchObject({
    formName: 'E2E Formular', recipientEmail: 'vermieter@fewo-dev.test', apartmentID: 0, maxGuests: 3, accommodations: 'Seeherz\nSeeherzchen',
    askForAddress: 1, useCaptcha: 0, sendConfirmation: 0,
  });

  dialog = await openEditDialog(page, 'fewo_kontaktformular');
  await expect(dialog.locator('[name="accommodations"]')).toHaveValue('Seeherz\nSeeherzchen');
  await dialog.locator('[name="useCaptcha"]').check();
  await dialog.locator('[name="askForAddress"]').uncheck();
  await submitDialog(page, dialog);
  expect(record('btFewoKontaktformular')).toMatchObject({ useCaptcha: 1, askForAddress: 0, sendConfirmation: 0 });
  await expect(block.locator('[name="street"]')).toHaveCount(0);
});

test('Ausstattungsliste: Zeilen hinzufügen, verschieben und entfernen', async ({ page }) => {
  let dialog = await openAddDialog(page, 'fewo_ausstattungsliste');
  await expect(dialog.getByLabel('Spalten')).toHaveValue('2');
  const rows = dialog.locator('tr.fewo-item-row');
  await expect(rows).toHaveCount(1);
  await dialog.locator('[name="title"]').fill('Küche');

  await rows.nth(0).locator('[name="itemName[]"]').fill('Herd');
  // Eingabetaste in der letzten Zeile legt eine neue an und schickt den Dialog nicht ab
  await rows.nth(0).locator('[name="itemName[]"]').press('Enter');
  await expect(rows).toHaveCount(2);
  await expect(rows.nth(1).locator('[name="itemName[]"]')).toBeFocused();
  await page.keyboard.type('Spülmaschine');
  await rows.nth(1).locator('[name="itemNote[]"]').fill('mit Tabs');
  await dialog.getByRole('button', { name: 'Eintrag hinzufügen' }).click();
  await expect(rows).toHaveCount(3);
  await rows.nth(2).locator('[name="itemName[]"]').fill('Toaster');

  await rows.nth(2).getByTitle('Nach oben').click();
  await expect.poll(() => values(rows.locator('[name="itemName[]"]'))).toEqual(['Herd', 'Toaster', 'Spülmaschine']);
  await rows.nth(0).getByTitle('Nach unten').click();
  await expect.poll(() => values(rows.locator('[name="itemName[]"]'))).toEqual(['Toaster', 'Herd', 'Spülmaschine']);
  await rows.nth(1).getByTitle('Entfernen').click();
  await expect.poll(() => values(rows.locator('[name="itemName[]"]'))).toEqual(['Toaster', 'Spülmaschine']);
  await dialog.getByRole('button', { name: 'Eintrag hinzufügen' }).click(); // leere Zeile wird beim Speichern ignoriert
  await dialog.getByLabel('Spalten').selectOption('3');
  await submitDialog(page, dialog);

  const block = blocks(page, 'fewo_ausstattungsliste');
  await expect(block.locator('.fewo-amenities__title')).toHaveText('Küche');
  await expect(block.locator('.fewo-amenities__list > li')).toHaveCount(2);
  await expect(block.locator('.fewo-amenities__list > li').first()).toHaveText('Toaster');
  await expect(block.locator('.fewo-amenities__notes li')).toContainText('mit Tabs');
  expect(JSON.parse(record('btFewoAusstattungsliste').items)).toEqual([{ name: 'Toaster', note: '' }, { name: 'Spülmaschine', note: 'mit Tabs' }]);
  expect(record('btFewoAusstattungsliste')).toMatchObject({ title: 'Küche', columnCount: 3, collapsible: 0 });

  dialog = await openEditDialog(page, 'fewo_ausstattungsliste');
  await expect.poll(() => values(dialog.locator('tr.fewo-item-row [name="itemName[]"]'))).toEqual(['Toaster', 'Spülmaschine']);
  await expect.poll(() => values(dialog.locator('tr.fewo-item-row [name="itemNote[]"]'))).toEqual(['', 'mit Tabs']);
  await dialog.getByLabel(/Zunächst eingeklappt/).check();
  await submitDialog(page, dialog);
  await expect(block.locator('details.fewo-amenities__details summary')).toHaveText('Küche');
});

test('Bildergalerie: Quelle umschalten, Ordner wählen', async ({ page }) => {
  let dialog = await openAddDialog(page, 'fewo_bildergalerie');
  const folder = dialog.locator('[data-source="folder"]');
  const set = dialog.locator('[data-source="set"]');
  await expect(folder).toBeVisible();
  await expect(set).toBeHidden();
  await expect(dialog.locator('[name="showCaptions"]')).toBeChecked();
  await expect(dialog.getByLabel('Spalten')).toHaveValue('3');
  await expect(dialog.getByLabel('Reihenfolge').locator('option')).toHaveText(['Reihenfolge im Dateiset / in der Dateiverwaltung', 'Titel (A–Z)', 'Neueste zuerst', 'Älteste zuerst']);

  await dialog.getByLabel('Bilder aus').selectOption({ label: 'Dateiset' });
  await expect(folder).toBeHidden();
  await expect(set).toBeVisible();
  expect(await submitExpectingErrors(page, dialog)).toContain('Bitte wählen Sie, woher die Bilder kommen.');

  await dialog.getByLabel('Bilder aus').selectOption({ label: 'Ordner der Dateiverwaltung' });
  await expect(folder).toBeVisible();
  // Ordner werden mit ihrem Pfad angezeigt
  await expect(folder.locator('option', { hasText: /^\/Fewo Demo$/ })).toHaveText('/Fewo Demo');
  await folder.locator('select').selectOption({ label: '/Fewo Demo' });
  await dialog.getByLabel('Reihenfolge').selectOption({ label: 'Titel (A–Z)' });
  await dialog.getByLabel('Spalten').selectOption('4');
  await dialog.getByLabel('Format').selectOption('1:1');
  await submitDialog(page, dialog);

  const block = blocks(page, 'fewo_bildergalerie');
  await expect(block.locator('.fewo-gallery__link')).toHaveCount(6);
  await expect(block.locator('.fewo-gallery')).toHaveAttribute('style', /--fewo-columns: 4; --fewo-ratio: 1 \/ 1/);
  expect(record('btFewoBildergalerie')).toMatchObject({ source: 'folder', sortOrder: 'title', columnCount: 4, aspectRatio: '1:1', showCaptions: 1 });

  dialog = await openEditDialog(page, 'fewo_bildergalerie');
  await expect(dialog.locator('[data-source="folder"] select option:checked')).toHaveText('/Fewo Demo');
  await dialog.locator('[name="showCaptions"]').uncheck();
  await submitDialog(page, dialog);
  expect(record('btFewoBildergalerie').showCaptions).toBe(0);
});

test('Karte: Koordinaten einfügen und tippen, Markierungen verwalten, Kachelserver prüfen', async ({ page }) => {
  let dialog = await openAddDialog(page, 'fewo_karte');
  await expect(dialog.locator('[name="height"]')).toHaveValue('400');
  await expect(dialog.locator('[name="requireConsent"]')).toBeChecked();
  await expect(dialog.locator('[name="tileUrl"]')).toHaveValue('https://tile.openstreetmap.org/{z}/{x}/{y}.png');
  const markers = dialog.locator('.fewo-marker');
  await expect(markers).toHaveCount(1);

  // Ohne gültige Markierung nicht speicherbar
  expect(await submitExpectingErrors(page, dialog)).toContain('Bitte fügen Sie mindestens eine Markierung mit gültigen Koordinaten hinzu.');

  // Eingefügtes Koordinatenpaar wird sofort aufgeteilt
  await markers.nth(0).locator('[name="markerLat[]"]').evaluate((input) => {
    input.value = '47.7102, 9.1024';
    input.dispatchEvent(new InputEvent('input', { inputType: 'insertFromPaste', bubbles: true }));
  });
  await expect(markers.nth(0).locator('[name="markerLat[]"]')).toHaveValue('47.7102');
  await expect(markers.nth(0).locator('[name="markerLng[]"]')).toHaveValue('9.1024');
  await markers.nth(0).locator('[name="markerTitle[]"]').fill('Ferienwohnung Seeherz');
  await markers.nth(0).locator('[name="markerText[]"]').fill('Seestraße 1\n78479 Reichenau');

  // Getipptes Paar erst beim Verlassen des Feldes – auch mit Dezimalkomma
  await dialog.getByRole('button', { name: 'Markierung hinzufügen' }).click();
  await expect(markers).toHaveCount(2);
  await expect(markers.nth(1).locator('[name="markerLat[]"]')).toBeFocused();
  await page.keyboard.type('47,6995; 9,0631');
  await expect(markers.nth(1).locator('[name="markerLat[]"]')).toHaveValue('47,6995; 9,0631');
  await page.keyboard.press('Tab');
  await expect(markers.nth(1).locator('[name="markerLat[]"]')).toHaveValue('47.6995');
  await expect(markers.nth(1).locator('[name="markerLng[]"]')).toHaveValue('9.0631');
  await markers.nth(1).locator('[name="markerTitle[]"]').fill('Insel Reichenau');

  // Dritte Markierung anlegen und wieder entfernen, vierte mit unbrauchbaren Koordinaten wird ignoriert
  await dialog.getByRole('button', { name: 'Markierung hinzufügen' }).click();
  await markers.nth(2).getByRole('button', { name: 'Markierung entfernen' }).click();
  await expect(markers).toHaveCount(2);
  await dialog.getByRole('button', { name: 'Markierung hinzufügen' }).click();
  await markers.nth(2).locator('[name="markerLat[]"]').fill('irgendwo');

  await dialog.getByText('Erweitert: Kachelserver').click();
  await dialog.locator('[name="tileUrl"]').fill('http://kacheln.example/{z}/{x}/{y}.png');
  expect(await submitExpectingErrors(page, dialog)).toContain('Die Adresse des Kachelservers muss mit https:// beginnen und {z}, {x} und {y} enthalten.');
  await dialog.locator('[name="tileUrl"]').fill('');
  await dialog.locator('[name="height"]').fill('320');
  await dialog.getByLabel('Zoomstufe').selectOption('12');
  await submitDialog(page, dialog);

  const block = blocks(page, 'fewo_karte');
  await expect(block).toContainText('Karte mit 2 Markierung(en) – wird in der normalen Ansicht angezeigt.');
  const saved = record('btFewoKarte');
  expect(JSON.parse(saved.markers)).toEqual([
    { lat: 47.7102, lng: 9.1024, title: 'Ferienwohnung Seeherz', text: 'Seestraße 1\n78479 Reichenau' },
    { lat: 47.6995, lng: 9.0631, title: 'Insel Reichenau', text: '' },
  ]);
  expect(saved).toMatchObject({ zoom: 12, height: 320, requireConsent: 1, tileUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png' });

  dialog = await openEditDialog(page, 'fewo_karte');
  await expect(dialog.locator('.fewo-marker')).toHaveCount(2);
  await expect.poll(() => values(dialog.locator('[name="markerTitle[]"]'))).toEqual(['Ferienwohnung Seeherz', 'Insel Reichenau']);
  await expect(dialog.locator('[name="markerText[]"]').first()).toHaveValue('Seestraße 1\n78479 Reichenau');
  await dialog.locator('[name="requireConsent"]').uncheck();
  await submitDialog(page, dialog);
  expect(record('btFewoKarte').requireConsent).toBe(0);
});

test('360°-Rundgang: Vorgaben, Prüfung der Bilder, fehlerhafte Einstellungen', async ({ page }) => {
  let dialog = await openAddDialog(page, 'fewo_rundgang');
  await expect(dialog.locator('.ui-dialog-title')).toHaveText('360°-Rundgang hinzufügen');
  // Vorgabe: ein einzelnes Panorama
  await expect(dialog.locator('[name="images"]')).toHaveValue('panorama->https://');
  await expect(dialog.locator('[name="viewerCode"]')).toHaveValue('"type": "equirectangular",\n"panorama": "{panorama}",\n"autoLoad": true');
  await expect(dialog.getByLabel('Seitenverhältnis')).toHaveValue('3:2');
  await expect(dialog.getByLabel('Seitenverhältnis').locator('option')).toHaveText(['16:9', '3:2', '4:3', '2:1', '1:1', 'Manuell (Höhe über die CSS-Klasse im Theme)']);
  await expect(dialog.getByRole('link', { name: 'Pannellum-Dokumentation' })).toHaveAttribute('href', 'https://pannellum.org/documentation/reference/');

  await dialog.locator('[name="title"]').fill('Unser Wohnzimmer');
  await dialog.locator('[name="images"]').fill('wohnzimmer /application/files/fewo-demo-panorama-wohnzimmer.jpg');
  expect(await submitExpectingErrors(page, dialog)).toContain('Bilder: Die Zeile „wohnzimmer /application/files/fewo-demo-panorama-wohnzimmer.jpg“ hat nicht die Form name->Adresse.');
  await dialog.locator('[name="images"]').fill('panorama -> /application/files/fewo-demo-panorama-wohnzimmer.jpg\n\n');
  await dialog.locator('[name="cssClass"]').fill('mein-rundgang <b>');
  await submitDialog(page, dialog);

  const block = blocks(page, 'fewo_rundgang');
  await expect(block).toHaveCount(1);
  // Im Bearbeitungsmodus nur ein Platzhalter, damit sich der Block anklicken und verschieben lässt
  await expect(block.locator('.fewo-tour__placeholder')).toHaveText('360°-Rundgang – wird in der Besucheransicht angezeigt.');
  await expect(block.locator('.pnlm-container')).toHaveCount(0);
  expect(record('btFewoRundgang')).toMatchObject({
    title: 'Unser Wohnzimmer', images: 'panorama -> /application/files/fewo-demo-panorama-wohnzimmer.jpg', imagesMobile: '',
    viewerCode: '"type": "equirectangular",\n"panorama": "{panorama}",\n"autoLoad": true', aspectRatio: '3:2', cssClass: 'mein-rundgang b',
  });

  dialog = await openEditDialog(page, 'fewo_rundgang');
  await expect(dialog.locator('.ui-dialog-title')).toHaveText('360°-Rundgang bearbeiten');
  await dialog.getByLabel('Seitenverhältnis').selectOption('16:9');
  await dialog.locator('[name="viewerCode"]').fill('');
  await dialog.locator('.ui-dialog-buttonpane .btn-primary').click();
  expect(await dialog.locator('[name="viewerCode"]').evaluate((el) => el.validity.valueMissing)).toBe(true);
  // Fehlerhafte Einstellungen: gespeichert wird, die Ansicht meldet den Fehler (siehe Veröffentlichen)
  await dialog.locator('[name="viewerCode"]').fill('"type": "equirectangular",\n"panorama": "{panorama}",,');
  await submitDialog(page, dialog);
  expect(record('btFewoRundgang')).toMatchObject({ aspectRatio: '16:9', viewerCode: '"type": "equirectangular",\n"panorama": "{panorama}",,' });
});

test('360°-Rundgang: Fehler in den Einstellungen sehen Redakteure mit Grund, danach korrigieren', async ({ page }) => {
  await publish(page);
  const tour = page.locator('.fewo-tour');
  await expect(tour.locator('.fewo-tour__error')).toContainText('Die 360°-Ansicht kann nicht angezeigt werden.');
  await expect(tour.locator('[data-fewo-tour-details]')).not.toBeEmpty();
  await expect(tour.locator('.fewo-tour__viewer')).toBeHidden();

  await enterEditMode(page, PAGE);
  const dialog = await openEditDialog(page, 'fewo_rundgang');
  await dialog.locator('[name="viewerCode"]').fill('type: "equirectangular",\npanorama: "{panorama}",\nautoLoad: true, // Schreibweise des alten Blocks\ntitle: "Wohnzimmer"');
  await submitDialog(page, dialog);
});

test('Neuigkeiten: nur Wohnungen mit Adresse, nur die heute gültigen Neuigkeiten', async ({ page }) => {
  let dialog = await openAddDialog(page, 'fewo_neuigkeiten');
  await expect(dialog.locator('.ui-dialog-title')).toHaveText('Neuigkeiten hinzufügen');
  await expect(dialog.getByLabel('Wohnung').locator('option')).toHaveText(APARTMENTS);
  await expect(dialog.getByLabel('Gültigkeitszeitraum anzeigen')).toBeChecked();

  // Die Wohnung hat keine Adresse der Neuigkeiten: Der Block ließe sich anlegen, bliebe aber immer leer
  await dialog.getByLabel('Wohnung').selectOption({ label: 'Seeherz – Belegung als ICS (Demo)' });
  expect(await submitExpectingErrors(page, dialog)).toContain('Für die Wohnung „Seeherz – Belegung als ICS (Demo)“ ist keine Adresse der Neuigkeiten hinterlegt.');

  // In den Demo-Daten stehen vier Neuigkeiten: abgelaufen, zwei aktuelle und eine erst in zehn Tagen gültige.
  // Die Wichtige Mitteilung steht vor der Info, obwohl Fewo-Office sie danach liefert
  await dialog.getByLabel('Wohnung').selectOption({ label: 'Seeherz (Demo)' });
  await dialog.getByLabel('Überschrift (optional)').fill('Aktuelles');
  await submitDialog(page, dialog);
  const block = blocks(page, 'fewo_neuigkeiten');
  await expect(block.locator('h2')).toHaveText('Aktuelles');
  await expect(block.locator('.fewo-news__item-title')).toHaveText(['Schwimmbadpause', 'Weihnachtsmarkt']);
  await expect(block.locator('h3')).toHaveText(['Schwimmbadpause', 'Weihnachtsmarkt']);
  const items = block.locator('.fewo-news__item');
  await expect(items.locator('.fewo-news__importance')).toHaveText(['Wichtige Mitteilung', 'Info']);
  await expect(items.nth(0)).toHaveClass(/fewo-news__item--important/);
  await expect(items.nth(1)).toHaveClass(/fewo-news__item--info/);
  // rot und grün wie in Fewo-Office
  expect(await items.nth(0).locator('.fewo-news__importance').evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(198, 40, 40)');
  expect(await items.nth(1).locator('.fewo-news__importance').evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(46, 125, 50)');
  const [from, to] = [-5, 20].map((days) => isoDate(days).split('-').reverse().join('.'));
  await expect(items.nth(0).locator('.fewo-news__period')).toHaveText(`Gültig vom ${from} bis ${to}`);
  await expect(items.nth(0).locator('.fewo-news__text')).toHaveText(['Das Schwimmbad macht Betriebspause! Wir bitten um Verständnis.', 'Die Therme in Konstanz hat geöffnet.']);
  expect(record('btFewoNeuigkeiten')).toMatchObject({ apartmentID: apartmentId('Seeherz (Demo)'), title: 'Aktuelles', showPeriod: 1 });

  // Neuigkeiten als XML, nur heute gültig; ohne Überschrift sind die Titel die Überschriften der zweiten Ebene
  dialog = await openEditDialog(page, 'fewo_neuigkeiten');
  await dialog.getByLabel('Wohnung').selectOption({ label: 'Seeherzchen (Demo)' });
  await dialog.getByLabel('Überschrift (optional)').fill('');
  await dialog.getByLabel('Gültigkeitszeitraum anzeigen').uncheck();
  await submitDialog(page, dialog);
  await expect(block.locator('h2')).toHaveText(['Fahrräder kostenlos']);
  await expect(block.locator('.fewo-news__importance')).toHaveText(['Hinweis']);
  expect(await block.locator('.fewo-news__importance').evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(249, 168, 37)');
  await expect(block.locator('.fewo-news__period')).toHaveCount(0);
  expect(await block.locator('.fewo-news__text').innerHTML()).toBe('Zwei Fahrräder stehen im Keller bereit.<br>\nDen Schlüssel finden Sie in der Wohnung.');

  dialog = await openEditDialog(page, 'fewo_neuigkeiten');
  await dialog.getByLabel('Wohnung').selectOption({ label: 'Seeherz (Demo)' });
  await dialog.getByLabel('Überschrift (optional)').fill('Aktuelles');
  await dialog.getByLabel('Gültigkeitszeitraum anzeigen').check();
  await submitDialog(page, dialog);
});

test('Veröffentlichen: Besucher sehen alle neun Blöcke, das Log bleibt sauber', async ({ page, browser }) => {
  for (const handle of ['fewo_neuigkeiten', 'fewo_preisuebersicht', 'fewo_belegungsplan', 'fewo_preisrechner', 'fewo_kontaktformular', 'fewo_ausstattungsliste', 'fewo_bildergalerie', 'fewo_karte', 'fewo_rundgang']) {
    await expect(blocks(page, handle), handle).toHaveCount(1);
  }
  await publish(page);

  const guest = await browser.newContext({ storageState: { cookies: [], origins: [] }, locale: 'de-DE' });
  const view = await guest.newPage();
  const external = [];
  await view.route((url) => url.host !== 'localhost:8095', (route) => {
    external.push(route.request().url());
    return route.fulfill({ status: 200, contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64') });
  });
  await view.goto(PAGE);
  await expect(view.locator('.fewo-news__item-title')).toHaveText(['Schwimmbadpause', 'Weihnachtsmarkt']);
  await expect(view.locator('.fewo-prices, .fewo-pricelist, [class^="fewo-price"]').first()).toBeVisible();
  await expect(view.locator('.fewo-calendar .fewo-notice')).toHaveCount(0); // Hinweise nur für Redakteure
  await expect(view.locator('.fewo-calendar__month:visible')).toHaveCount(2);
  await expect(view.locator('.fewo-quote h2')).toHaveText('Was kostet mein Urlaub?');
  await expect(view.locator('.fewo-amenities summary')).toHaveText('Küche');
  await expect(view.locator('.fewo-gallery__link')).toHaveCount(6);
  // Karte ohne Zustimmungsabfrage lädt sofort
  await expect(view.locator('.fewo-map .leaflet-container')).toBeVisible();
  await expect(view.locator('.fewo-map .leaflet-marker-icon')).toHaveCount(2);
  expect(external.every((url) => /^https:\/\/tile\.openstreetmap\.org\/12\//.test(url) || /fonts\.(googleapis|gstatic)\.com/.test(url))).toBe(true);
  // Rundgang mit den korrigierten Einstellungen; Besucher sehen keine technischen Einzelheiten
  await expect(view.locator('.fewo-tour .pnlm-title-box')).toHaveText('Wohnzimmer');
  await expect(view.locator('.fewo-tour__error')).toBeHidden();
  await expect(view.locator('.fewo-tour.mein-rundgang')).toHaveCount(1);

  // Vier Nächte in der Nebensaison (2 Gäste, 75 €) zuzüglich 65 € Endreinigung
  const quote = view.locator('.fewo-quote');
  await pickStay(quote, 13, 17);
  await quote.getByRole('button', { name: 'Preis berechnen' }).click();
  await expect(quote.locator('.fewo-quote__total-amount')).toHaveText(/^365,00\s€$/);

  // Captcha: ohne und mit falschem Code abgelehnt, mit richtigem Code angenommen
  await mail.clear();
  const form = view.locator('form[data-fewo-inquiry]');
  const fill = async () => {
    await form.locator('[name="arrival"]').fill(isoDate(30));
    await form.locator('[name="departure"]').fill(isoDate(34));
    await form.locator('[name="accommodation"]').selectOption('Seeherzchen');
    await form.locator('[name="lastName"]').fill('Captcha');
    await form.locator('[name="email"]').fill('captcha@e2e.test');
    await form.locator('[name="consent"]').check();
  };
  await expect(form.locator('.fewo-form__captcha img')).toBeVisible();
  expect(await form.locator('.fewo-form__captcha img').evaluate((img) => img.complete && img.naturalWidth)).toBeGreaterThan(50);
  const code = form.locator('.fewo-form__captcha input[type="text"], .fewo-form__captcha input:not([type])').first();
  await fill();
  await code.fill('falsch');
  await view.waitForTimeout(3200);
  await form.getByRole('button', { name: 'Anfrage senden' }).click();
  await expect(view.getByText('Der Sicherheitscode ist nicht korrekt.')).toBeVisible();
  expect(await mail.list()).toHaveLength(0);

  // Den richtigen Code kennt nur der Server: aus der Sitzung des Besuchers lesen
  await expect(form.locator('.fewo-form__captcha img')).toBeVisible();
  await view.waitForLoadState('networkidle');
  const session = (await guest.cookies()).find((cookie) => cookie.name === 'CONCRETE').value;
  const stored = webExec(`cat /tmp/sess_${session.replace(/[^a-zA-Z0-9,-]/g, '')}`);
  const solution = stored.match(/securimage_code_disp[^;]*?s:\d+:"default";s:\d+:"([^"]+)"/);
  expect(solution, 'Captcha-Code in der Sitzung: ' + stored.slice(0, 400)).not.toBeNull();
  await fill();
  await code.fill(solution[1]);
  await view.waitForTimeout(3200);
  await form.getByRole('button', { name: 'Anfrage senden' }).click();
  await expect(view.locator('.fewo-form__success')).toBeVisible();
  const sent = await mail.waitFor(1);
  expect(sent).toHaveLength(1);
  expect(sent[0].Subject).toMatch(/^Neue Anfrage \(E2E Formular\): .*, Captcha$/);
  expect((await mail.get(sent[0].ID)).Text).toMatch(/Unterkunft: +Seeherzchen\n/);
  await guest.close();

  // Keine PHP-Warnungen aus dem Paket und keine Fehler im Concrete-Log seit Beginn dieser Datei
  expect(phpLog(STARTED).filter((line) => line.includes('fewo_tk2'))).toEqual([]);
  expect(sql(`SELECT COALESCE(GROUP_CONCAT(LEFT(message, 200) SEPARATOR ' || '), '') FROM Logs WHERE level >= 300 AND time >= ${Math.floor(Date.parse(STARTED) / 1000)} AND channel <> 'application'`)).toBe('');
});
