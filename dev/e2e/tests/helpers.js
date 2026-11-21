const base = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');

const DEV_DIR = path.resolve(__dirname, '../..');
const MAILPIT = process.env.FEWO_MAILPIT_URL || 'http://localhost:8025';

/**
 * Wie der normale Test, schlägt aber zusätzlich fehl, wenn auf der Seite
 * JavaScript-Fehler oder Serverfehler (HTTP 5xx) auftreten.
 */
const test = base.test.extend({
  page: async ({ page }, use) => {
    const problems = [];
    page.on('pageerror', (error) => problems.push('JavaScript: ' + error.message));
    page.on('response', (response) => {
      if (response.status() >= 500) {
        problems.push(`HTTP ${response.status()}: ${response.url()}`);
      }
    });
    await use(page);
    base.expect(problems, 'Fehler während des Tests').toEqual([]);
  },
});

/** Führt SQL in der Datenbank des Testprojekts aus und gibt die Ausgabe zurück. */
const sql = (query) => execFileSync(
  'docker',
  ['compose', 'exec', '-T', 'db', 'mariadb', '-uconcrete', '-pconcrete', 'concrete', '-N', '-B', '-r', '-e', query],
  { cwd: DEV_DIR, encoding: 'utf8' },
).trim();

/** Führt einen Befehl der Concrete-Konsole im Web-Container aus. */
const concrete = (...args) => execFileSync(
  'docker',
  ['compose', 'exec', '-T', '-u', 'www-data', '-w', '/srv/app/public', 'web', './concrete/bin/concrete', ...args],
  { cwd: DEV_DIR, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] },
).trim();

/** Führt einen Shell-Befehl im Web-Container aus (als root). */
const webExec = (command) => execFileSync(
  'docker',
  ['compose', 'exec', '-T', '-w', '/srv/app/public', 'web', 'bash', '-c', command],
  { cwd: DEV_DIR, encoding: 'utf8' },
).trim();

/** PHP-Warnungen, Deprecations und Fehler aus dem Log des Web-Containers seit dem Zeitpunkt `since`. */
const phpLog = (since) => execFileSync(
  'docker',
  ['compose', 'logs', '--no-log-prefix', '--since', since, 'web'],
  { cwd: DEV_DIR, encoding: 'utf8' },
).split('\n').filter((line) => /PHP (Warning|Deprecated|Notice|Fatal|Parse)/.test(line));

const mail = {
  clear: () => fetch(MAILPIT + '/api/v1/messages', { method: 'DELETE' }),
  list: async () => (await (await fetch(MAILPIT + '/api/v1/messages')).json()).messages,
  /** Liefert eine E-Mail vollständig; Zeilenenden im Klartext sind auf \n vereinheitlicht. */
  get: async (id) => {
    const message = await (await fetch(`${MAILPIT}/api/v1/message/${id}`)).json();
    message.Text = (message.Text || '').replace(/\r\n/g, '\n');
    return message;
  },
  /** Wartet, bis mindestens `count` E-Mails eingegangen sind. */
  waitFor: async (count, timeout = 15_000) => {
    const end = Date.now() + timeout;
    for (;;) {
      const messages = await mail.list();
      if (messages.length >= count || Date.now() > end) {
        return messages;
      }
      await new Promise((resolve) => setTimeout(resolve, 300));
    }
  },
};

/** ISO-Datum (JJJJ-MM-TT) in `days` Tagen. */
const isoDate = (days) => {
  const date = new Date();
  date.setDate(date.getDate() + days);
  return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
};

/**
 * Wählt im Preisrechner An- und Abreise (in Tagen ab heute): im Kalender der beiden Felder oder – ohne
 * JavaScript bzw. ohne Belegung der Wohnung – direkt in den Datumsfeldern des Browsers.
 */
const pickStay = async (quote, arrivalOffset, departureOffset) => {
  if (await quote.locator('input[type="date"][name="arrival"]').count()) {
    await quote.locator('[name="arrival"]').fill(isoDate(arrivalOffset));
    await quote.locator('[name="departure"]').fill(isoDate(departureOffset));
    return;
  }
  await quote.locator('[data-fewo-date="arrival"]').click();
  for (const offset of [arrivalOffset, departureOffset]) {
    await showDay(quote, offset);
    await quote.locator(`[data-date="${isoDate(offset)}"]`).click();
  }
};

/** Blättert im geöffneten Kalender des Preisrechners zum Monat des Tages. */
const showDay = async (quote, offset) => {
  const target = isoDate(offset);
  for (let i = 0; i < 30 && !(await quote.locator(`[data-date="${target}"]`).count()); i++) {
    const first = await quote.locator('[data-date]').first().getAttribute('data-date');
    await quote.getByRole('button', { name: target < first ? 'Vorheriger Monat' : 'Nächster Monat' }).click();
  }
};

module.exports = { test, expect: base.expect, sql, concrete, webExec, phpLog, mail, isoDate, pickStay, showDay };
