import { launch, login, base } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);
await page.goto(base + "/contacts");
await page.click('[data-open="contact-dialog"]');
await page.waitForTimeout(700);
const s = await page.evaluate(() => {
  const d = document.querySelector("#contact-dialog");
  const cs = getComputedStyle(d);
  return { bg: cs.backgroundColor, border: cs.border, radius: cs.borderRadius,
           shadow: cs.boxShadow.slice(0,60), zIndex: cs.zIndex, position: cs.position,
           classes: d.className, open: d.open, isModal: d.matches(":modal") };
});
console.log("DIALOG:", JSON.stringify(s, null, 1));
await page.screenshot({ path: "tmp/shots/dialog-before.png" });
await browser.close();
