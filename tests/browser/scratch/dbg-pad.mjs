import { launch, login, base } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);
await page.goto(base + "/deals?view=list");
const href = await page.locator('a[href*="/deals/"]').first().getAttribute("href");
await page.goto(base + href);
for (const id of ["activity-dialog", "deal-dialog"]) {
  const has = await page.locator("#" + id).count();
  if (!has) continue;
  await page.evaluate((i) => { const d = document.getElementById(i); if (d && !d.open) d.showModal(); }, id);
  await page.waitForTimeout(250);
  const m = await page.evaluate((i) => {
    const d = document.getElementById(i); const cs = getComputedStyle(d); const r = d.getBoundingClientRect();
    return { id: i, padding: cs.padding, maxWidth: cs.maxWidth, width: Math.round(r.width),
             height: Math.round(r.height), vw: window.innerWidth, vh: window.innerHeight,
             left: Math.round(r.left), top: Math.round(r.top) };
  }, id);
  console.log(JSON.stringify(m));
  await page.evaluate((i) => document.getElementById(i)?.close(), id);
}
await browser.close();
