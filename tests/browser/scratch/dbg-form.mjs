import { launch, login, base } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);
await page.goto(base + "/contacts");
await page.click('[data-open="contact-dialog"]');
await page.fill('#contact-dialog #first_name', "PlainProbe");
await page.fill('#contact-dialog #last_name', "ProbeX");
const g = await page.evaluate(() => {
  const dlg = document.querySelector('#contact-dialog');
  const sel = document.querySelector('#cf-preferred_language');
  const d = dlg.getBoundingClientRect(), s = sel.getBoundingClientRect();
  return { dialogTop: Math.round(d.top), dialogBottom: Math.round(d.bottom),
           fieldTop: Math.round(s.top), fieldBottom: Math.round(s.bottom),
           insideDialogViewport: s.top >= d.top && s.bottom <= d.bottom,
           dialogScrollable: dlg.scrollHeight > dlg.clientHeight,
           optionsCount: sel.options.length,
           firstOption: sel.options[0] ? JSON.stringify(sel.options[0].text) : null,
           labelText: (sel.closest('.field')?.querySelector('label')?.textContent || '').trim() };
});
console.log("GEOMETRY:", JSON.stringify(g, null, 1));
await page.screenshot({ path: "tmp/shots/audit-dialog-before.png" });
await page.click('#contact-dialog button[type=submit]');
await page.waitForTimeout(1200);
const after = await page.evaluate(() => {
  const sel = document.querySelector('#cf-preferred_language');
  const s = sel.getBoundingClientRect();
  return { fieldTopAfter: Math.round(s.top), focused: document.activeElement === sel,
           inViewport: s.top >= 0 && s.bottom <= window.innerHeight };
});
console.log("AFTER SUBMIT:", JSON.stringify(after));
await page.screenshot({ path: "tmp/shots/audit-dialog-after.png" });
await browser.close();
