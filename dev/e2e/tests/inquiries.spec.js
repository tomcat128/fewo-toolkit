// Anfrageformular (als Gast) und Dashboard › Fewo Toolkit › Buchungsanfragen
const { test, expect, sql, mail, isoDate } = require('./helpers');

const FORM_PAGE = '/index.php/fewo-anfrage';
const DASHBOARD = '/index.php/dashboard/fewo_tk2/inquiries';
const GUEST = { cookies: [], origins: [] };

const cleanup = () => sql("DELETE FROM FewoInquiries WHERE email LIKE '%@e2e.test'");
const count = (where = '1') => Number(sql(`SELECT COUNT(*) FROM FewoInquiries WHERE email LIKE '%@e2e.test' AND ${where}`));

/** Füllt das Formular als Gast aus; abgeschickt wird erst mit send(). */
const fillForm = async (page, data = {}) => {
  const values = {
    arrival: isoDate(30), departure: isoDate(35), adults: '2', children: '1',
    accommodation: 'Seeherz, 42 m², max. 4 Personen', salutation: 'Frau',
    firstName: 'Erika', lastName: 'Mustermann', email: 'erika@e2e.test', phone: '+49 7531 123456',
    street: 'Seestraße 5', postalCode: '78462', city: 'Konstanz', country: 'Deutschland',
    message: 'Wir kommen mit Hund.\nIst das möglich?', referralSource: 'Empfehlung', ...data,
  };
  const form = page.locator('form[data-fewo-inquiry]');
  for (const [name, value] of Object.entries(values)) {
    const input = form.locator(`[name="${name}"]`);
    if (await input.evaluate((el) => el.tagName === 'SELECT')) {
      await input.selectOption(value);
    } else {
      await input.fill(value);
    }
  }
  await form.locator('[name="consent"]').check();
  return form;
};
const send = (form) => form.getByRole('button', { name: 'Anfrage senden' }).click();

test.describe.configure({ mode: 'serial' });
test.beforeAll(async () => { cleanup(); await mail.clear(); });
test.afterAll(cleanup);

test.describe('als Gast', () => {
  test.use({ storageState: GUEST });

  test('Prüfung auf dem Server: Fehler auf Deutsch, Eingaben bleiben erhalten', async ({ page }) => {
    await page.goto(FORM_PAGE);
    const form = await fillForm(page, { phone: 'ruf mich an', message: 'Schauen Sie mal: https://spam.example/angebot' });
    await send(form); // sofort abgeschickt: Zeitsperre greift zusätzlich

    await expect(page.locator('.fewo-form__alert')).toBeVisible();
    await expect(page.locator('.fewo-form__alert')).toBeFocused();
    await expect(page.getByText('Bitte geben Sie eine gültige Telefonnummer an.')).toBeVisible();
    await expect(page.getByText('Aus Sicherheitsgründen darf Ihre Nachricht keine Links enthalten.')).toBeVisible();
    await expect(form.locator('[name="phone"]')).toHaveAttribute('aria-invalid', 'true');
    await expect(form.locator('[name="lastName"]')).toHaveValue('Mustermann');
    await expect(form.locator('[name="arrival"]')).toHaveValue(isoDate(30));
    await expect(form.locator('[name="accommodation"]')).toHaveValue('Seeherz, 42 m², max. 4 Personen');
    expect(count()).toBe(0);
    expect(await mail.list()).toHaveLength(0);
  });

  test('zu viele Gäste und Abreise vor Anreise werden abgelehnt', async ({ page }) => {
    await page.goto(FORM_PAGE);
    const form = await fillForm(page, { adults: '4', children: '2' });
    // Browserprüfung umgehen, damit die Serverprüfung getestet wird
    await form.evaluate((el) => {
      el.noValidate = true;
      el.elements.departure.value = el.elements.arrival.value;
    });
    await page.waitForTimeout(3200);
    await form.evaluate((el) => el.submit());
    await expect(page.getByText('Es können höchstens 4 Gäste übernachten.')).toBeVisible();
    await expect(page.getByText('Die Abreise muss nach der Anreise liegen.')).toBeVisible();
    expect(count()).toBe(0);
  });

  test('Köderfeld: Spam wird stillschweigend verworfen', async ({ page }) => {
    await page.goto(FORM_PAGE);
    const form = await fillForm(page, { email: 'bot@e2e.test' });
    await form.locator('[name="website"]').evaluate((el) => { el.value = 'https://spam.example'; });
    await page.waitForTimeout(3200);
    await send(form);
    await expect(page.locator('.fewo-form__success')).toBeVisible();
    expect(count()).toBe(0);
    expect(await mail.list()).toHaveLength(0);
  });

  test('gültige Anfrage: Dank, Speicherung und zwei E-Mails auf Deutsch', async ({ page }) => {
    // Eine Anfrage jenseits der Löschfrist (Vorgabe: 365 Tage) verschwindet, sobald eine neue gespeichert wird
    sql(`INSERT INTO FewoInquiries (createdAt, status, formName, accommodation, salutation, firstName, lastName, email, phone, street, postalCode, city, country, adults, children, arrival, departure, message, referralSource, currency)
         VALUES (NOW() - INTERVAL 400 DAY, 'done', 'E2E', '', '', 'Uralt', 'Anfrage', 'uralt@e2e.test', '', '', '', '', '', 2, 0, CURDATE(), CURDATE() + INTERVAL 3 DAY, '', '', 'EUR')`);
    expect(count("email = 'uralt@e2e.test'")).toBe(1);

    await page.goto(FORM_PAGE);
    const form = await fillForm(page);
    await page.waitForTimeout(3200);
    await send(form);

    await expect(page).toHaveURL(/fewo_inquiry=\d+/);
    await expect(page.locator('.fewo-form__success')).toHaveText('Vielen Dank für Ihre Anfrage! Wir melden uns schnellstmöglich.');
    await expect(page.locator('form[data-fewo-inquiry]')).toHaveCount(0);

    expect(sql("SELECT CONCAT_WS('|', status, formName, accommodation, salutation, firstName, lastName, adults, children, postalCode, referralSource, quotedTotal > 0) FROM FewoInquiries WHERE email = 'erika@e2e.test'"))
      .toBe('new|Seeherz|Seeherz, 42 m², max. 4 Personen|Frau|Erika|Mustermann|2|1|78462|Empfehlung|1');

    expect(count("email = 'uralt@e2e.test'")).toBe(0);

    const messages = await mail.waitFor(2);
    expect(messages).toHaveLength(2);
    const notification = await mail.get(messages.find((m) => m.To[0].Address === 'vermieter@fewo-dev.test').ID);
    const confirmation = await mail.get(messages.find((m) => m.To[0].Address === 'erika@e2e.test').ID);

    expect(notification.Subject).toMatch(/^Neue Buchungsanfrage \(Seeherz\): \d\d\.\d\d\.–\d\d\.\d\d\.\d{4}, Erika Mustermann$/);
    expect(notification.ReplyTo[0].Address).toBe('erika@e2e.test');
    for (const text of [notification.Text, notification.HTML]) {
      for (const expected of ['Neue Buchungsanfrage über Fewo Toolkit Dev', 'Anreise', 'Abreise', 'Nächte', 'Erwachsene', 'Kinder', 'Unverbindlicher Preis', 'Frau Erika Mustermann', 'Seestraße 5, 78462 Konstanz, Deutschland', 'Wie sind Sie auf uns aufmerksam geworden?', 'Wir kommen mit Hund.', 'Im Dashboard ansehen', '/dashboard/fewo_tk2/inquiries/detail/']) {
        expect(text).toContain(expected);
      }
      expect(text).not.toMatch(/Arrival|Departure|Adults|Non-binding|View in the dashboard/);
    }
    // Im Klartext steht zwischen Bezeichnung und Wert immer ein Leerzeichen
    expect(notification.Text).toMatch(/Unverbindlicher Preis: +\d+,\d\d\s€/);
    expect(notification.Text).toMatch(/aufmerksam geworden\?: +Empfehlung/);

    expect(confirmation.Subject).toBe('Ihre Anfrage bei Fewo Toolkit Dev');
    expect(confirmation.ReplyTo[0].Address).toBe('vermieter@fewo-dev.test');
    expect(confirmation.Text).toContain('Guten Tag,\n\nvielen Dank für Ihre Anfrage.');
    expect(confirmation.Text).toContain('Freundliche Grüße\nFewo Toolkit Dev');
    expect(confirmation.Text).not.toMatch(/Hello|Kind regards|Thank you/);
    expect(confirmation.HTML).toContain('Eingangsbestätigung');
  });
});

test.describe('Dashboard', () => {
  test('Liste, Detail, Status ändern, löschen', async ({ page }) => {
    await page.goto(DASHBOARD);
    const listRow = page.locator('tbody tr').filter({ hasText: 'Erika Mustermann' });
    await expect(listRow).toHaveCount(1);
    await expect(listRow).toContainText('Neu');
    await expect(listRow).toContainText('Seeherz, 42 m², max. 4 Personen');
    await expect(listRow).toContainText('€');

    // Filter
    await page.getByRole('link', { name: 'Erledigt', exact: true }).click();
    await expect(listRow).toHaveCount(0);
    await page.getByRole('link', { name: /^Neu/ }).click();
    await expect(listRow).toHaveCount(1);

    await listRow.getByRole('link', { name: 'Erika Mustermann' }).click();
    await expect(page).toHaveURL(/inquiries\/detail\/\d+/);
    const main = page.locator('#ccm-dashboard-content-regular');
    for (const text of ['Reisezeitraum', '5 Nächte', 'Erwachsene: 2, Kinder: 1', 'Unverbindlicher Preis', 'erika@e2e.test', '+49 7531 123456', 'Seestraße 5', 'Empfehlung', 'Wir kommen mit Hund.']) {
      await expect(main).toContainText(text);
    }
    const reply = page.getByRole('link', { name: 'Per E-Mail antworten' });
    expect(decodeURIComponent(await reply.getAttribute('href'))).toMatch(/^mailto:erika@e2e\.test\?subject=Ihre Anfrage vom \d\d\.\d\d\.\d{4} bis \d\d\.\d\d\.\d{4}$/);

    await page.getByRole('button', { name: 'Als erledigt markieren' }).click();
    await expect(page.getByText('Der Status wurde geändert.')).toBeVisible();
    expect(count("status = 'done'")).toBe(1);
    await page.getByRole('button', { name: 'Als neu markieren' }).click();
    await expect(page.getByRole('button', { name: 'Als erledigt markieren' })).toBeVisible();
    expect(count("status = 'new'")).toBe(1);

    page.once('dialog', (dialog) => dialog.dismiss());
    await page.getByRole('button', { name: 'Anfrage löschen' }).click();
    expect(count()).toBe(1);
    page.once('dialog', (dialog) => {
      expect(dialog.message()).toBe('Diese Anfrage endgültig löschen?');
      dialog.accept();
    });
    await page.getByRole('button', { name: 'Anfrage löschen' }).click();
    await expect(page.getByText('Die Anfrage wurde gelöscht.')).toBeVisible();
    expect(count()).toBe(0);
  });

  test('Löschfrist: speichern und abgelaufene Anfragen automatisch löschen', async ({ page }) => {
    const insert = (email, ageDays) => sql(
      `INSERT INTO FewoInquiries (createdAt, status, formName, accommodation, salutation, firstName, lastName, email, phone, street, postalCode, city, country, adults, children, arrival, departure, message, referralSource, currency)
       VALUES (NOW() - INTERVAL ${ageDays} DAY, 'new', 'E2E', '', '', 'Alt', 'Anfrage ${ageDays}', '${email}', '', '', '', '', '', 2, 0, CURDATE(), CURDATE() + INTERVAL 3 DAY, '', '', 'EUR')`,
    );
    insert('alt@e2e.test', 40);
    insert('neu@e2e.test', 5);

    // 0 = nie löschen
    await page.goto(DASHBOARD);
    await page.fill('#retentionDays', '0');
    await page.locator('form[action*="save_settings"]').getByRole('button', { name: 'Speichern' }).click();
    await expect(page.getByText('Die Einstellungen wurden gespeichert.')).toBeVisible();
    await expect(page.locator('#retentionDays')).toHaveValue('0');
    await page.reload();
    expect(count()).toBe(2);

    // 30 Tage: die 40 Tage alte Anfrage verschwindet beim nächsten Aufruf der Liste
    await page.fill('#retentionDays', '30');
    await page.locator('form[action*="save_settings"]').getByRole('button', { name: 'Speichern' }).click();
    await expect(page.locator('#retentionDays')).toHaveValue('30');
    await expect(page.locator('tbody tr').filter({ hasText: 'Anfrage 5' })).toHaveCount(1);
    await expect(page.locator('tbody tr').filter({ hasText: 'Anfrage 40' })).toHaveCount(0);
    expect(count("email = 'alt@e2e.test'")).toBe(0);
    expect(count("email = 'neu@e2e.test'")).toBe(1);

    await page.fill('#retentionDays', '365');
    await page.locator('form[action*="save_settings"]').getByRole('button', { name: 'Speichern' }).click();
    await expect(page.locator('#retentionDays')).toHaveValue('365');
  });
});
