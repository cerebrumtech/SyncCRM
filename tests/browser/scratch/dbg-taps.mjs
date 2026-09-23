import { launch, login, base } from "./_lib.mjs";
const { browser, page } = await launch({ width: 390 });
await login(page);
await page.goto(base + "/dashboard");
await page.waitForTimeout(600);
const small = await page.evaluate(() =>
  [...document.querySelectorAll("a,button,input[type=checkbox],select")]
    .map(e => { const r = e.getBoundingClientRect(); return { cls: e.className.slice(0,50), tag: e.tagName,
       w: Math.round(r.width), h: Math.round(r.height) }; })
    .filter(x => x.w > 0 && x.h > 0 && (x.h < 32 || x.w < 32)));
const byClass = {};
for (const s of small) { const k = s.tag + "|" + (s.cls || "(none)"); byClass[k] = (byClass[k]||0)+1; }
console.log(JSON.stringify(Object.entries(byClass).sort((a,b)=>b[1]-a[1]).slice(0,8), null, 1));
await browser.close();
