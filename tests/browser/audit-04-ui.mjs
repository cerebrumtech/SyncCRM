import { launch, login, base, ok } from "./_lib.mjs";
const shots = "tmp/shots";
const pages = [["dashboard","/dashboard"],["deals","/deals"],["contacts","/contacts"],["activities","/activities"]];
for (const width of [1400, 390]) {
  const { browser, page } = await launch({ width });
  await login(page);
  for (const [name, path] of pages) {
    await page.goto(base + path, { waitUntil: "networkidle" }).catch(()=>{});
    await page.waitForTimeout(600);
    await page.screenshot({ path: `${shots}/ui-${width}-${name}.png`, fullPage: false });
    const m = await page.evaluate(() => ({
      hScroll: document.documentElement.scrollWidth > window.innerWidth + 2,
      overflow: document.documentElement.scrollWidth - window.innerWidth,
      tiny: [...document.querySelectorAll('body *')].filter(e => {
        const s = getComputedStyle(e); const fs = parseFloat(s.fontSize);
        return e.childElementCount === 0 && (e.textContent||"").trim().length > 3 && fs > 0 && fs < 11;
      }).length,
      smallTargets: [...document.querySelectorAll('a,button')].filter(e => {
        const r = e.getBoundingClientRect();
        return r.width > 0 && r.height > 0 && (r.height < 32 || r.width < 32);
      }).length,
    }));
    ok(`${width}px ${name}: no horizontal page scroll${m.hScroll ? ` (overflows by ${m.overflow}px)` : ""}`, !m.hScroll);
    if (width === 390) console.log(`INFO ${name}: ${m.smallTargets} tap targets under 32px, ${m.tiny} text nodes under 11px`);
  }
  await browser.close();
}
