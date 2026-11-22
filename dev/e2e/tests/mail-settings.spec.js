// Dashboard › Fewo Toolkit 2 › E-Mail-Versand: über Concrete oder ein eigenes SMTP-Postfach, Testversand.
// Im Testprojekt verschickt Concrete über Mailpit; als "eigenes Postfach" dient derselbe Mailpit mit anderem Absender.
const { test, expect, mail, webExec, isoDate } = require('./helpers');

const PAGE = '/index.php/dashboard/fewo_tk2/mail';
const CONFIG = 'application/config/generated_overrides/fewo_tk2/mail.php';
const reset = () => webExec(`rm -f ${CONFIG}`);

test.describe.configure({ mode: 'serial' });
test.beforeAll(async () => { reset(); await mail.clear(); });
test.afterAll(reset);

const testMail = async (page, recipient) => {
  await page.getByLabel('Empfänger der Testmail').fill(recipient);
  await page.getByRole('button', { name: 'Testmail senden' }).click();
};

test('Vorgabe: Versand über Concrete, Testmail', async ({ page }) => {
  await page.goto(PAGE);
  await expect(page.getByRole('heading', { name: 'E-Mail-Versand', exact: true })).toBeVisible();
  await expect(page.getByLabel('E-Mail-Einstellungen von Concrete verwenden')).toBeChecked();
  await expect(page.getByText('Derzeit über den SMTP-Server mailpit.')).toBeVisible();
  await expect(page.locator('#fewo-mail-smtp')).toBeHidden();
  // Empfänger vorbelegt mit der Adresse des angemeldeten Benutzers
  await expect(page.getByLabel('Empfänger der Testmail')).not.toHaveValue('');

  await testMail(page, 'test-concrete@e2e.test');
  await expect(page.getByText('Die Testmail wurde an test-concrete@e2e.test verschickt.')).toBeVisible();
  const [message] = await mail.waitFor(1);
  expect(message.Subject).toBe('Testmail von Fewo Toolkit Dev');
  expect((await mail.get(message.ID)).Text).toContain('Verschickt mit den E-Mail-Einstellungen von Concrete.');
});

test('eigenes SMTP-Postfach: Prüfung, Speichern, Testmail mit eigenem Absender', async ({ page }) => {
  await mail.clear();
  await page.goto(PAGE);
  await page.getByLabel('Eigenes SMTP-Postfach verwenden').check();
  await expect(page.locator('#fewo-mail-smtp')).toBeVisible();
  // Port folgt der Verschlüsselung
  await page.getByLabel('Verschlüsselung').selectOption('ssl');
  await expect(page.getByLabel('Port')).toHaveValue('465');
  await page.getByLabel('Verschlüsselung').selectOption('none');
  await expect(page.getByLabel('Port')).toHaveValue('25');

  // ohne Server und Absender nicht speicherbar; Eingaben bleiben erhalten
  await page.getByLabel('Absendername').fill('Fewo SMTP');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByText('Bitte geben Sie den SMTP-Server an, z. B. smtp.example.com.')).toBeVisible();
  await expect(page.getByText('Bitte geben Sie eine gültige Absenderadresse an.')).toBeVisible();
  await expect(page.getByLabel('Eigenes SMTP-Postfach verwenden')).toBeChecked();
  await expect(page.getByLabel('Absendername')).toHaveValue('Fewo SMTP');

  // EHLO-Name: leer = Domain der Website (hier die aufgerufene Adresse); leer gesendet lehnen Server mit 501 ab
  await expect(page.getByLabel('Name dieser Website für den SMTP-Server (EHLO, optional)')).toHaveAttribute('placeholder', /^(localhost|[a-z0-9.-]+|\[[0-9a-f:.]+\])$/);
  await page.getByLabel('SMTP-Server', { exact: true }).fill('mailpit');
  await page.getByLabel('Port').fill('1025');
  await page.getByLabel('Absenderadresse').fill('anfragen@fewo-smtp.test');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByText('Die Einstellungen für den E-Mail-Versand wurden gespeichert.')).toBeVisible();
  await expect(page.getByLabel('SMTP-Server', { exact: true })).toHaveValue('mailpit');
  await expect(page.getByLabel('Verschlüsselung')).toHaveValue('none');

  await testMail(page, 'test-smtp@e2e.test');
  await expect(page.getByText('Die Testmail wurde an test-smtp@e2e.test verschickt.')).toBeVisible();
  const [message] = await mail.waitFor(1);
  expect(message.From).toEqual({ Name: 'Fewo SMTP', Address: 'anfragen@fewo-smtp.test' });
  expect((await mail.get(message.ID)).Text).toContain('Verschickt über das SMTP-Postfach mailpit (Port 1025) mit dem Absender anfragen@fewo-smtp.test.');
});

test('Anfragen gehen über das eigene Postfach', async ({ browser }) => {
  await mail.clear();
  const guest = await browser.newContext({ storageState: { cookies: [], origins: [] }, locale: 'de-DE' });
  const page = await guest.newPage();
  await page.goto('/index.php/fewo-anfrage');
  const form = page.locator('form[data-fewo-inquiry]');
  await form.locator('[name="arrival"]').fill(isoDate(40));
  await form.locator('[name="departure"]').fill(isoDate(44));
  await form.locator('[name="accommodation"]').selectOption({ index: 1 });
  await form.locator('[name="lastName"]').fill('Postfach');
  await form.locator('[name="email"]').fill('smtp-gast@e2e.test');
  await form.locator('[name="consent"]').check();
  await page.waitForTimeout(3200); // Mindestzeit des Spamschutzes
  await form.getByRole('button', { name: 'Anfrage senden' }).click();
  await expect(page.locator('.fewo-form__success')).toBeVisible();
  const messages = await mail.waitFor(2);
  expect(messages).toHaveLength(2);
  // Benachrichtigung und Eingangsbestätigung mit dem Absender des Postfachs; Antworten gehen an den Gast
  expect(messages.map((m) => m.From.Address)).toEqual(['anfragen@fewo-smtp.test', 'anfragen@fewo-smtp.test']);
  const notification = await mail.get(messages.find((m) => m.To[0].Address === 'vermieter@fewo-dev.test').ID);
  expect(notification.ReplyTo[0].Address).toBe('smtp-gast@e2e.test');
  await guest.close();
});

test('Passwort verschlüsselt gespeichert, nicht erreichbarer Server wird gemeldet', async ({ page }) => {
  await page.goto(PAGE);
  await page.getByLabel('Benutzername').fill('postfach');
  await page.getByLabel('Passwort', { exact: true }).fill('Geheimes Kennwort 1');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByText('Die Einstellungen für den E-Mail-Versand wurden gespeichert.')).toBeVisible();
  // Das Feld bleibt leer und sagt, dass ein Passwort gespeichert ist; in der Konfiguration steht es nicht im Klartext
  await expect(page.getByLabel('Passwort', { exact: true })).toHaveValue('');
  await expect(page.getByLabel('Passwort', { exact: true })).toHaveAttribute('placeholder', 'Gespeichert – zum Ändern überschreiben');
  const stored = webExec(`cat ${CONFIG}`);
  expect(stored).toContain("'password' =>");
  expect(stored).not.toContain('Geheimes Kennwort');

  // Speichern ohne neues Passwort behält das gespeicherte
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByLabel('Passwort', { exact: true })).toHaveAttribute('placeholder', 'Gespeichert – zum Ändern überschreiben');
  // entfernen
  await page.getByLabel('Gespeichertes Passwort entfernen').check();
  await page.getByLabel('Benutzername').fill('');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.getByLabel('Gespeichertes Passwort entfernen')).toHaveCount(0);

  await page.getByLabel('SMTP-Server', { exact: true }).fill('smtp.nicht-vorhanden.invalid');
  await page.getByRole('button', { name: 'Speichern' }).click();
  await testMail(page, 'test-fehler@e2e.test');
  await expect(page.getByText(/Die Testmail konnte nicht verschickt werden: .+/)).toBeVisible();

  // zurück zu Concrete
  await page.getByLabel('E-Mail-Einstellungen von Concrete verwenden').check();
  await page.getByRole('button', { name: 'Speichern' }).click();
  await expect(page.locator('#fewo-mail-smtp')).toBeHidden();
});
