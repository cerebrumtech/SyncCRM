import { launch, login, base } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);
for (const p of ["/dashboard", "/companies", "/deals?view=list"]) {
  const r = await page.goto(base + p, { waitUntil: "domcontentloaded" });
  const t = await page.locator("body").innerText();
  console.log(`${p} -> HTTP ${r.status()} | ${/Something went wrong/.test(t) ? "ERROR PAGE" : "ok"}`);
}
await page.goto(base + "/companies");
console.log("companies listed:", (await page.locator("tbody tr").count()));
const txt = await page.locator("body").innerText();
console.log("Devanagari rendered:", /भवानी/.test(txt));
await page.screenshot({ path: "tmp/shots/real-companies.png" });
await browser.close();
