// Anfrageformular (als Gast): Die Anfrage geht per E-Mail an die im Block hinterlegte Adresse. Gespeichert wird
// nichts – eine Seite "Buchungsanfragen" gibt es im Dashboard nicht (mehr).
const { test, expect, sql, mail, isoDate } = require('./helpers');

const FORM_PAGE = '/index.php/fewo-anfrage';
const DASHBOARD = '/index.php/dashboard/fewo_tk2/inquiries';
const GUEST = { cookies: [], origins: [] };


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
test.beforeAll(async () => { await mail.clear(); });

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
    expect(await mail.list()).toHaveLength(0);
  });

  test('Köderfeld: Spam wird stillschweigend verworfen', async ({ page }) => {
    await page.goto(FORM_PAGE);
    const form = await fillForm(page, { email: 'bot@e2e.test' });
    await form.locator('[name="website"]').evaluate((el) => { el.value = 'https://spam.example'; });
    await page.waitForTimeout(3200);
    await send(form);
    await expect(page.locator('.fewo-form__success')).toBeVisible();
    expect(await mail.list()).toHaveLength(0);
  });

  test('gültige Anfrage: Dank und zwei E-Mails auf Deutsch', async ({ page }) => {
    await page.goto(FORM_PAGE);
    const form = await fillForm(page);
    await page.waitForTimeout(3200);
    await send(form);

    await expect(page).toHaveURL(/fewo_inquiry=\d+/);
    await expect(page.locator('.fewo-form__success')).toHaveText('Vielen Dank für Ihre Anfrage! Wir melden uns schnellstmöglich.');
    await expect(page.locator('form[data-fewo-inquiry]')).toHaveCount(0);

    const messages = await mail.waitFor(2);
    expect(messages).toHaveLength(2);
    const notification = await mail.get(messages.find((m) => m.To[0].Address === 'vermieter@fewo-dev.test').ID);
    const confirmation = await mail.get(messages.find((m) => m.To[0].Address === 'erika@e2e.test').ID);

    expect(notification.Subject).toMatch(/^Neue Anfrage \(Seeherz\): \d\d\.\d\d\.–\d\d\.\d\d\.\d{4}, Erika Mustermann$/);
    expect(notification.ReplyTo[0].Address).toBe('erika@e2e.test');
    for (const text of [notification.Text, notification.HTML]) {
      for (const expected of ['Neue Anfrage über Fewo Toolkit Dev', 'Anreise', 'Abreise', 'Nächte', 'Erwachsene', 'Kinder', 'Frau Erika Mustermann', 'Seestraße 5, 78462 Konstanz, Deutschland', 'Wo gefunden?', 'Wir kommen mit Hund.']) {
        expect(text).toContain(expected);
      }
      expect(text).not.toMatch(/Arrival|Departure|Adults|Non-binding|Dashboard|dashboard/);
      // kein Preis in der Mail, und die Frage nach der Herkunft kurz statt wie im Formular
      expect(text).not.toMatch(/Unverbindlicher Preis|€|aufmerksam geworden/);
    }
    // Im Klartext steht zwischen Bezeichnung und Wert immer ein Leerzeichen
    expect(notification.Text).toMatch(/Wo gefunden\?: +Empfehlung/);

    expect(confirmation.Subject).toBe('Ihre Anfrage bei Fewo Toolkit Dev');
    expect(confirmation.ReplyTo[0].Address).toBe('vermieter@fewo-dev.test');
    expect(confirmation.Text).toContain('Guten Tag,\n\nvielen Dank für Ihre Anfrage.');
    expect(confirmation.Text).toContain('Freundliche Grüße\nFewo Toolkit Dev');
    expect(confirmation.Text).not.toMatch(/Hello|Kind regards|Thank you/);
    expect(confirmation.HTML).toContain('Eingangsbestätigung');
  });
});

test.describe('Dashboard', () => {
  test('keine Buchungsanfragen: Die Website speichert nichts, das Dashboard hat dafür keine Seite', async ({ page }) => {
    expect(sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'FewoInquiries'")).toBe('0');

    // "Fewo Toolkit 2" führt zu den Wohnungen; im Menü stehen nur Wohnungen und KI-Assistent
    await page.goto('/index.php/dashboard/fewo_tk2');
    await expect(page).toHaveURL(/dashboard\/fewo_tk2\/apartments/);
    await expect(page.getByRole('link', { name: 'Fewo Toolkit 2', exact: true }).first()).toBeVisible();
    await expect(page.locator('a[href*="/dashboard/fewo_tk2/"]').filter({ hasText: /Anfragen/ })).toHaveCount(0);
    const response = await page.goto(DASHBOARD);
    expect(response.status()).toBe(404);
  });
});
