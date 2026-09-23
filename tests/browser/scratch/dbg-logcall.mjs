import { launch, login, base } from "./_lib.mjs";
const { browser } = await launch();
const ctx = await browser.newContext({ viewport: { width: 1290, height: 703 } });
const page = await ctx.newPage();
await page.goto(base + "/login");
await page.fill("#email","owner@syncworkstech.com"); await page.fill("#password","password123");
await page.click("button[type=submit]"); await page.waitForURL("**/dashboard");
await page.goto(base + "/deals?view=list");
const href = await page.locator('a[href*="/deals/"]').first().getAttribute("href");
await page.goto(base + href);
const logBtn = page.locator('button:has-text("Log call")').first();
if (await logBtn.count()) { await logBtn.click(); } else {
  await page.evaluate(() => document.getElementById("activity-dialog")?.showModal());
}
await page.waitForTimeout(700);
await page.screenshot({ path: "tmp/shots/logcall-after.png" });
const m = await page.evaluate(() => { const d = document.querySelector("dialog[open]"); const r = d.getBoundingClientRect();
  return { pad: getComputedStyle(d).padding, w: Math.round(r.width), h: Math.round(r.height), top: Math.round(r.top), vh: window.innerHeight }; });
console.log(JSON.stringify(m));
await browser.close();
