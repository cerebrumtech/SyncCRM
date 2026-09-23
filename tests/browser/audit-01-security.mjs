// Layer: security. Multi-tenant isolation, authorisation, CSRF, XSS, injection.
// The app holds one organisation's sales data per tenant; a leak across tenants is S1.
import { launch, login, base, ok } from "./_lib.mjs";

const { browser, page } = await launch();

// ---------------------------------------------------------------- unauthenticated access
const guarded = ["/dashboard", "/contacts", "/companies", "/deals", "/activities",
  "/products", "/settings/users", "/settings/fields", "/settings/organisation", "/reports"];
for (const path of guarded) {
  const res = await page.goto(base + path, { waitUntil: "domcontentloaded" });
  const landed = page.url();
  ok(`anon cannot reach ${path} (-> ${landed.replace(base, "") || "/"}, ${res.status()})`,
    landed.includes("/login") || res.status() === 403 || res.status() === 404);
}

// ---------------------------------------------------------------- sign in as owner
await login(page);

// Collect real ids belonging to THIS org, to compare against a second org later.
await page.goto(base + "/contacts");
const ownContactHref = await page.locator('a[href*="/contacts/"]').first().getAttribute("href");
const ownContactId = (ownContactHref || "").split("/").pop();
ok(`seeded contact id found (${ownContactId})`, !!ownContactId);

// ---------------------------------------------------------------- IDOR: ids from another org
// Seed a second organisation directly, then try to read its rows as the first org's owner.
const foreign = JSON.parse(process.env.FOREIGN || "{}");
if (foreign.contactId) {
  for (const [label, path] of [
    ["contact", `/contacts/${foreign.contactId}`],
    ["company", `/companies/${foreign.companyId}`],
    ["deal", `/deals/${foreign.dealId}`],
  ]) {
    if (!path.endsWith("undefined")) {
      const res = await page.goto(base + path, { waitUntil: "domcontentloaded" });
      const body = await page.locator("body").innerText();
      const leaked = body.includes("ZZ-FOREIGN");
      ok(`cross-org ${label} is not readable (HTTP ${res.status()})`, !leaked && res.status() !== 200);
    }
  }
}

// ---------------------------------------------------------------- stored XSS
const XSS = `<img src=x onerror="window.__xss=1">`;
await page.goto(base + "/contacts");
await page.click('[data-open="contact-dialog"]');
await page.fill('#contact-dialog input[name="first_name"]', XSS);
await page.fill('#contact-dialog input[name="last_name"]', "XssProbe");
await page.click('#contact-dialog button[type=submit]');
await page.waitForLoadState("domcontentloaded");
await page.goto(base + "/contacts?q=XssProbe");
const fired = await page.evaluate(() => window.__xss === 1);
ok("stored XSS in contact name does not execute", !fired);
const shown = await page.locator("body").innerText();
ok("XSS payload is shown as literal text", shown.includes("<img") || shown.includes("onerror"));

// ---------------------------------------------------------------- SQL injection probes
for (const probe of ["' OR 1=1--", "'; DROP TABLE contacts;--", "1 UNION SELECT 1,2,3"]) {
  const res = await page.goto(base + "/contacts?q=" + encodeURIComponent(probe), { waitUntil: "domcontentloaded" });
  ok(`search survives injection probe ${JSON.stringify(probe.slice(0, 18))} (HTTP ${res.status()})`, res.status() === 200);
}
// The table must still exist after the DROP attempt.
const afterDrop = await page.goto(base + "/contacts", { waitUntil: "domcontentloaded" });
ok("contacts table still present after DROP probe", afterDrop.status() === 200);

// ---------------------------------------------------------------- CSRF
const csrf = await page.evaluate(async (b) => {
  const r = await fetch(b + "/contacts", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "first_name=CsrfProbe&last_name=NoToken",
  });
  return { status: r.status, text: (await r.text()).slice(0, 200) };
}, base);
ok(`POST without CSRF token is rejected (HTTP ${csrf.status})`, csrf.status === 403 || /not allowed/i.test(csrf.text));

// ---------------------------------------------------------------- privilege: member vs admin
const ctx2 = await browser.newContext();
const member = await ctx2.newPage();
await login(member, "priya@syncworkstech.com", "password123");
const adminOnly = ["/settings/users", "/settings/organisation", "/settings/fields", "/settings/pipelines"];
for (const path of adminOnly) {
  const res = await member.goto(base + path, { waitUntil: "domcontentloaded" });
  const url = member.url();
  const blocked = res.status() === 403 || res.status() === 404 || !url.includes(path);
  ok(`member is denied ${path} (HTTP ${res.status()})`, blocked);
}

// ---------------------------------------------------------------- session cookie flags
const cookies = await (await member.context()).cookies();
const sess = cookies.find((c) => /session/i.test(c.name));
ok("session cookie is HttpOnly", !!sess && sess.httpOnly === true);
ok("session cookie is SameSite=Lax or Strict", !!sess && /Lax|Strict/i.test(sess.sameSite || ""));

await browser.close();
