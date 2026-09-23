import { launch, login, base } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);
await page.goto(base + "/files/3", { waitUntil: "load" });
await page.waitForTimeout(1000);
const r = await page.evaluate(() => ({ title: document.title, origin: location.origin,
  cookiesReadable: document.cookie.length >= 0 }));
console.log("RESULT:", JSON.stringify(r));
console.log(r.title === "XSS-FIRED"
  ? "CONFIRMED: script from uploaded SVG executed on the application origin"
  : "NOT executed (title unchanged)");
await browser.close();
