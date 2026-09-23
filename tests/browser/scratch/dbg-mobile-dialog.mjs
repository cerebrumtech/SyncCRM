import { launch, login, base } from "./_lib.mjs";
const { browser, page } = await launch({ width: 390 });
await login(page);
await page.goto(base + "/contacts");
await page.click('[data-open="contact-dialog"]');
await page.waitForTimeout(600);
const m = await page.evaluate(() => {
  const d = document.querySelector("#contact-dialog"); const r = d.getBoundingClientRect();
  return { w: Math.round(r.width), vw: window.innerWidth, h: Math.round(r.height), vh: window.innerHeight,
           fits: r.width <= window.innerWidth && r.height <= window.innerHeight };
});
console.log("MOBILE DIALOG:", JSON.stringify(m));
await page.screenshot({ path: "tmp/shots/dialog-mobile.png" });
await browser.close();
