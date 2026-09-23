import { launch, login, base, ok } from "./_lib.mjs";
const browserCtx = await launch({ width: 390 });
const { browser } = browserCtx;
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });
const page = await ctx.newPage();
await page.goto(base + "/login");
await page.fill("#email","owner@syncworkstech.com"); await page.fill("#password","password123");
await page.click("button[type=submit]"); await page.waitForURL("**/dashboard");
for (const p of ["/dashboard","/contacts","/deals","/activities"]) {
  await page.goto(base + p); await page.waitForTimeout(400);
  const n = await page.evaluate(() => [...document.querySelectorAll("a.link,button,select,[role=button]")]
    .filter(e => { const r = e.getBoundingClientRect();
      return r.width>0 && r.height>0 && r.height < 40 && !e.closest("p,td,li"); }).length);
  ok(`${p}: no stand-alone control under 40px on touch (found ${n})`, n === 0);
}
await browser.close();
