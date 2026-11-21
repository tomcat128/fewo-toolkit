// Meldet sich einmal als Administrator an und speichert die Sitzung für alle Tests.
const { chromium } = require('@playwright/test');

module.exports = async (config) => {
  const { baseURL } = config.projects[0].use;
  const browser = await chromium.launch();
  const page = await browser.newPage({ locale: 'de-DE' });
  await page.goto(baseURL + '/index.php/login');
  await page.fill('#uName', process.env.FEWO_ADMIN_USER || 'admin');
  await page.fill('#uPassword', process.env.FEWO_ADMIN_PASSWORD || 'admin');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/login')),
    page.click('form.concrete-login-form button.btn-primary'),
  ]);

  // Hinweis von Concrete zur Datenerhebung einmalig bestätigen, er verdeckt sonst Schaltflächen
  await page.goto(baseURL + '/index.php/dashboard/fewo_tk2/apartments');
  const notice = page.getByRole('button', { name: 'Akzeptieren und Schließen' });
  if (await notice.count()) {
    await notice.click();
    await page.waitForTimeout(500);
  }

  // Begrüßungs- und Ankündigungsdialoge von Concrete wegklicken (erscheinen nach einer frischen Installation)
  await page.goto(baseURL + '/index.php');
  for (let round = 0; round < 6; round++) {
    const modal = page.locator('#concrete-announcement-modal.show');
    try {
      await modal.waitFor({ timeout: 2500 });
    } catch {
      break;
    }
    // Begrüßung: "Got It!", Abfrage der Website-Angaben: "Skip"
    await modal.locator('button', { hasText: /Got It|Verstanden|Skip|Überspringen|Close|Schließen/i }).first().click();
    await page.waitForTimeout(1000);
    await page.reload();
  }
  await page.context().storageState({ path: '.auth/admin.json' });
  await browser.close();
};
