// Hilfsfunktionen für den Bearbeitungsmodus von Concrete CMS 9 (Blöcke hinzufügen und bearbeiten)
const { expect } = require('@playwright/test');

/** Öffnet die Seite im Bearbeitungsmodus. */
const enterEditMode = async (page, path) => {
  await page.goto(path);
  const checkout = page.locator('[data-toolbar-action=check-out]').first();
  if (await checkout.count()) {
    await page.goto(await checkout.getAttribute('href'));
  }
  await expect(page.locator('html.ccm-edit-mode')).toHaveCount(1);
  await expect(page.locator('html.ccm-panel-ready')).toHaveCount(1);
};

const visibleDialog = (page) => page.locator('.ui-dialog:visible').last();

/** Bereichsmenü › "Block hinzufügen" › Blocktyp: liefert den Dialog mit dem Formular des Blocks. */
const openAddDialog = async (page, blockTypeHandle, area = 'Main') => {
  const handle = page.locator(`[data-area-handle="${area}"] > .ccm-area-footer .ccm-area-footer-handle`);
  await handle.scrollIntoViewIfNeeded();
  await handle.click({ force: true });
  await page.locator('a:visible', { hasText: /^Block hinzufügen$/ }).click();
  const item = page.locator(`#ccm-panel-add-block a[data-block-type-handle="${blockTypeHandle}"]`);
  await item.scrollIntoViewIfNeeded();
  await item.click();
  const dialog = visibleDialog(page);
  await expect(dialog.locator('[name="ccm-add-block-submit"]')).toHaveCount(1);
  return dialog;
};

const blocks = (page, blockTypeHandle, area = 'Main') => page.locator(`[data-area-handle="${area}"] [data-block-type-handle="${blockTypeHandle}"]`);

/** Klick auf den Block › "Block bearbeiten": liefert den Dialog mit dem Formular des Blocks. */
const openEditDialog = async (page, blockTypeHandle, index = 0) => {
  const block = blocks(page, blockTypeHandle).nth(index);
  // Oberkante des Blocks unter die fixierte Kopfzeile der Seite schieben
  await block.evaluate((el) => window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - 250));
  const box = await block.boundingBox();
  // Nicht in die Mitte klicken: dort liegt der Griff zum Verschieben
  const x = box.x + box.width / 4;
  const y = box.y + Math.min(30, box.height / 2);
  const menuItem = page.locator('a:visible', { hasText: /^Block bearbeiten$/ });
  // Concrete markiert den Block erst nach einer Mausbewegung; danach öffnet ein Klick das Menü
  await expect(async () => {
    await page.mouse.move(x + 40, y + 10);
    await page.mouse.move(x, y, { steps: 5 });
    await page.waitForTimeout(300);
    await page.mouse.click(x, y);
    await expect(menuItem).toBeVisible({ timeout: 1500 });
  }).toPass({ timeout: 15_000 });
  await menuItem.click();
  const dialog = visibleDialog(page);
  await expect(dialog.locator('[name="ccm-edit-block-submit"]')).toHaveCount(1);
  return dialog;
};

/** Schickt das Formular ab ("Hinzufügen" bzw. "Speichern") und wartet, bis der Dialog geschlossen ist. */
const submitDialog = async (page, dialog) => {
  await dialog.locator('.ui-dialog-buttonpane .btn-primary').click();
  await expect(page.locator('.ui-dialog:visible')).toHaveCount(0);
};

/** Schickt das Formular ab und liefert die Fehlermeldungen; der Dialog bleibt offen. */
const submitExpectingErrors = async (page, dialog) => {
  await dialog.locator('.ui-dialog-buttonpane .btn-primary').click();
  const errors = page.locator('.ccm-notification-danger, .ccm-notification-error, .ui-dialog .alert-danger, #ccm-popup-alert-message').filter({ visible: true }).first();
  await expect(errors).toBeVisible();
  const text = (await errors.innerText()).trim();
  // Meldung schließen
  const close = page.locator('.ccm-notification-danger .ccm-notification-actions a, .ccm-notification-danger button, .ui-dialog:has(#ccm-popup-alert-message) .ui-dialog-buttonpane button, .ui-dialog:has(#ccm-popup-alert-message) .ui-dialog-titlebar-close').filter({ visible: true }).first();
  if (await close.count()) {
    await close.click();
  }
  return text;
};

/** Veröffentlicht die Änderungen der Seite und verlässt den Bearbeitungsmodus. */
const publish = async (page) => {
  await page.locator('#ccm-toolbar [data-toolbar-action=check-in]:visible').first().click();
  const button = page.locator('#ccm-panel-check-in button[type=submit], #ccm-panel-check-in button', { hasText: /veröffentlichen/i }).first();
  await button.click();
  await expect(page.locator('html.ccm-edit-mode')).toHaveCount(0);
};

module.exports = { enterEditMode, openAddDialog, openEditDialog, submitDialog, submitExpectingErrors, publish, blocks };
