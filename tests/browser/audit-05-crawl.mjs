// Visits every page, tab and filter as each role, in development mode so PHP notices
// and warnings render into the HTML instead of being swallowed.
import { launch, login, base } from "./_lib.mjs";

const problems = [];
const note = (where, kind, detail) => problems.push({ where, kind, detail });

// PHP diagnostics that production mode hides. 'Notice' alone is too loose (a page may
// legitimately say "Notice"), so match the rendered PHP prefix shapes.
const PHP_NOISE = /(Warning|Notice|Deprecated|Fatal error|Parse error)\s*:\s*[A-Z]|Undefined (variable|index|array key|property|offset)|Trying to access array offset|must be of type|Call to (a member function|undefined)|preg_\w+\(\)|htmlspecialchars\(\)|Attempt to read property/;

async function visit(page, path, label) {
  let status = 0;
  page.once("response", (r) => { if (r.url().includes(path.split("?")[0])) status = r.status(); });
  let res;
  try {
    res = await page.goto(base + path, { waitUntil: "domcontentloaded", timeout: 20000 });
  } catch (e) {
    note(label || path, "NAVIGATION", e.message.slice(0, 90));
    return null;
  }
  const code = res ? res.status() : status;
  const html = await page.content();
  const text = await page.locator("body").innerText().catch(() => "");
  if (code >= 500) note(label || path, "HTTP " + code, "server error");
  const m = html.match(PHP_NOISE);
  if (m) {
    const at = html.indexOf(m[0]);
    note(label || path, "PHP", html.slice(Math.max(0, at - 20), at + 150).replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim());
  }
  if (/Something went wrong/i.test(text)) note(label || path, "ERROR PAGE", "app-level exception");
  return { code, text, html };
}

for (const [who, email] of [["owner", "owner@syncworkstech.com"], ["member", "rahul@syncworkstech.com"]]) {
  const { browser, page } = await launch();
  page.on("console", (m) => {
    if (m.type() === "error" && !/favicon|ERR_CERT|fonts\.googleapis/.test(m.text())) {
      note(`${who}:${page.url().replace(base, "")}`, "CONSOLE", m.text().slice(0, 110));
    }
  });
  await login(page, email);

  // Real ids from the seeded data, so detail pages are exercised with content.
  await page.goto(base + "/contacts");
  const cId = (await page.locator('a[href*="/contacts/"]').first().getAttribute("href").catch(() => ""))?.split("/").pop();
  await page.goto(base + "/companies");
  const coId = (await page.locator('a[href*="/companies/"]').first().getAttribute("href").catch(() => ""))?.split("/").pop();
  await page.goto(base + "/deals?view=list");
  const dId = (await page.locator('a[href*="/deals/"]').first().getAttribute("href").catch(() => ""))?.split("/").pop();
  await page.goto(base + "/products");
  const pId = (await page.locator('a[href*="/products/"]').first().getAttribute("href").catch(() => ""))?.split("/").pop();

  const pages = [
    "/dashboard", "/contacts", "/companies", "/deals", "/deals?view=list", "/activities",
    "/activities?view=calendar", "/products",
    "/settings/users", "/settings/teams", "/settings/pipelines", "/settings/fields",
    "/settings/import", "/settings/organization", "/settings/audit",
    // filters and edge inputs on list pages
    "/contacts?q=", "/contacts?q=%20", "/contacts?page=9999", "/contacts?sort=bogus",
    "/deals?pipeline=99999", "/activities?type=BOGUS", "/contacts?owner=99999",
    // export endpoints
    "/export/contacts", "/export/companies", "/export/deals", "/export/activities", "/export/bogus",
    // search api
    "/api/search/contacts?q=a", "/api/search/companies?q=a", "/api/search/bogus?q=a",
    // non-existent ids
    "/contacts/999999", "/companies/999999", "/deals/999999", "/products/999999",
    // non-numeric where a number is expected
    "/contacts/abc", "/files/abc",
  ];
  if (cId) pages.push(`/contacts/${cId}`);
  if (coId) pages.push(`/companies/${coId}`);
  if (dId) pages.push(`/deals/${dId}`);
  if (pId) pages.push(`/products/${pId}`);

  for (const p of pages) await visit(page, p, `${who}:${p}`);

  // Every tab on every detail page: tabs are client-side, so they need clicking.
  for (const [kind, id] of [["contacts", cId], ["companies", coId], ["deals", dId]]) {
    if (!id) continue;
    await visit(page, `/${kind}/${id}`, `${who}:/${kind}/${id}`);
    const tabs = await page.locator("[data-tab]").all();
    for (const t of tabs) {
      const name = await t.getAttribute("data-tab");
      try {
        await t.click({ timeout: 4000 });
        await page.waitForTimeout(250);
        const html = await page.content();
        const m = html.match(PHP_NOISE);
        if (m) note(`${who}:/${kind}/${id}#${name}`, "PHP", m[0]);
        const vis = await page.locator(`[data-panel="${name}"], #${name}`).first().isVisible().catch(() => null);
        if (vis === false) note(`${who}:/${kind}/${id}#${name}`, "TAB", "panel did not become visible");
      } catch (e) {
        note(`${who}:/${kind}/${id}#${name}`, "TAB", e.message.slice(0, 70));
      }
    }
  }
  await browser.close();
}

console.log("\n================ CRAWL RESULT ================");
if (!problems.length) console.log("No problems found.");
const seen = new Set();
for (const p of problems) {
  const k = p.kind + "|" + p.detail.slice(0, 60);
  if (seen.has(k)) continue;
  seen.add(k);
  console.log(`[${p.kind}] ${p.where}\n    ${p.detail}`);
}
console.log(`\n${problems.length} raw, ${seen.size} distinct`);
