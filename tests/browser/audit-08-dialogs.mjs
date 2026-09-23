// Opens every dialog the app can open and checks it is actually a readable surface.
// Page screenshots never caught this: the defect only exists in the open state.
import { launch, login, base, ok } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);

const pages = ["/contacts", "/companies", "/deals", "/deals?view=list", "/activities",
  "/products", "/settings/users", "/settings/teams", "/settings/pipelines",
  "/settings/fields", "/settings/organization"];

let checked = 0, bad = 0;
for (const path of pages) {
  await page.goto(base + path, { waitUntil: "domcontentloaded" }).catch(() => {});
  const openers = await page.locator("[data-open]").evaluateAll((els) =>
    [...new Set(els.map((e) => e.getAttribute("data-open")))]);
  for (const id of openers) {
    const dlg = page.locator("#" + id);
    if (!(await dlg.count())) continue;
    // Open it directly; some openers are inside rows that need hovering.
    await page.evaluate((i) => { const d = document.getElementById(i); if (d && !d.open) d.showModal(); }, id);
    await page.waitForTimeout(150);
    const s = await page.evaluate((i) => {
      const d = document.getElementById(i);
      const cs = getComputedStyle(d);
      const r = d.getBoundingClientRect();
      const pad = parseFloat(cs.paddingTop) || 0;
      // Is the submit button reachable without scrolling inside the dialog?
      const btn = d.querySelector("button[type=submit]");
      const needsScroll = d.scrollHeight > d.clientHeight + 2;
      return { bg: cs.backgroundColor, opaque: !/rgba\(0, 0, 0, 0\)|transparent/.test(cs.backgroundColor),
               shadow: cs.boxShadow !== "none", h: Math.round(r.height), vh: window.innerHeight, pad,
               hasSubmit: !!btn, needsScroll,
               overflows: r.height > window.innerHeight + 2, scrolls: cs.overflowY === "auto" || cs.overflowY === "scroll" };
    }, id);
    checked++;
    if (!s.opaque) { bad++; console.log(`FAIL ${path} #${id}: transparent (${s.bg})`); }
    if (s.pad < 12) { bad++; console.log(`FAIL ${path} #${id}: padding ${s.pad}px -- content sits on the edge`); }
    if (s.hasSubmit && s.needsScroll) { console.log(`INFO ${path} #${id}: must scroll to reach the submit button`); }
    if (s.overflows && !s.scrolls) { bad++; console.log(`FAIL ${path} #${id}: ${s.h}px tall in ${s.vh}px viewport and does not scroll`); }
    await page.evaluate((i) => { const d = document.getElementById(i); if (d && d.open) d.close(); }, id);
  }
}
ok(`all ${checked} dialogs have an opaque surface and fit or scroll`, bad === 0);
await browser.close();
