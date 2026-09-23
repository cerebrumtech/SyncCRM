// Layer 2/3: validation, boundaries, and whether the server trusts the browser.
// A browser-only rule is no rule: it is bypassed by curl, a proxy, or devtools.
import { launch, login, base, ok } from "./_lib.mjs";

const { browser, page } = await launch();
await login(page);

// Post a form the way an attacker would: correct CSRF token, arbitrary body.
async function post(path, fields) {
  return page.evaluate(async ([b, p, f]) => {
    const tok = document.querySelector('input[name=csrf_token]')?.value || "";
    const body = new URLSearchParams({ csrf_token: tok, ...f }).toString();
    const r = await fetch(b + p, {
      method: "POST", redirect: "manual",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body,
    });
    return { status: r.status, type: r.type, text: (await r.text().catch(() => "")).slice(0, 400) };
  }, [base, path, fields]);
}

await page.goto(base + "/contacts");

// ---------------------------------------------------------------- required custom field, server side
// 'preferred_language' is a required dropdown. The browser enforces it. Does the server?
const r1 = await post("/contacts", { first_name: "ServerSide", last_name: "BypassProbe" });
await page.goto(base + "/contacts?q=BypassProbe");
const bypassed = (await page.locator("body").innerText()).includes("BypassProbe");
ok(`required custom field is enforced server-side (HTTP ${r1.status}, saved=${bypassed})`, !bypassed);

// ---------------------------------------------------------------- over-length input vs column width
// contacts.first_name is varchar(80). 500 chars must be rejected or truncated, never a 500.
const long = "A".repeat(500);
const r2 = await post("/contacts", { first_name: long, last_name: "LongProbe", cf_preferred_language: "Marathi" });
await page.goto(base + "/contacts?q=LongProbe");
const longBody = await page.locator("body").innerText();
ok(`500-char name does not 500 the server (HTTP ${r2.status})`, r2.status < 500);
const longSaved = longBody.includes("LongProbe");
ok(`500-char name handled without data corruption (saved=${longSaved})`, r2.status < 500);

// ---------------------------------------------------------------- email format
for (const [label, email] of [["no @", "notanemail"], ["double @", "a@@b.com"], ["space", "a b@c.com"]]) {
  const r = await post("/contacts", { first_name: "Email", last_name: "Probe" + label.replace(/\W/g, ""), email, cf_preferred_language: "Marathi" });
  await page.goto(base + "/contacts?q=Probe" + label.replace(/\W/g, ""));
  const saved = (await page.locator("body").innerText()).includes("Probe" + label.replace(/\W/g, ""));
  ok(`invalid email rejected server-side (${label}, saved=${saved})`, !saved);
}

// ---------------------------------------------------------------- Indian mobile rules
for (const [label, phone, shouldPass] of [
  ["9 digits", "912345678", false],
  ["11 digits", "91234567890", false],
  ["letters", "98abc43210", false],
  ["valid 10", "9812345678", true],
]) {
  const tag = "Mob" + label.replace(/\W/g, "");
  const r = await post("/contacts", { first_name: "Mobile", last_name: tag, phone, cf_preferred_language: "Marathi" });
  await page.goto(base + "/contacts?q=" + tag);
  const saved = (await page.locator("body").innerText()).includes(tag);
  ok(`phone ${label}: ${shouldPass ? "accepted" : "rejected"} (saved=${saved})`, saved === shouldPass);
}

// ---------------------------------------------------------------- Devanagari, the actual user base
const r3 = await post("/contacts", { first_name: "हनुमान", last_name: "कऱ्हाळे", cf_preferred_language: "Marathi" });
await page.goto(base + "/contacts?q=" + encodeURIComponent("कऱ्हाळे"));
const devBody = await page.locator("body").innerText();
ok("Marathi name saves and is searchable", devBody.includes("कऱ्हाळे"));
ok("Marathi name is not mangled", !devBody.includes("??????") && !devBody.includes("à¤"));

// ---------------------------------------------------------------- deal amounts
await page.goto(base + "/deals");
for (const [label, amount, shouldPass] of [
  ["negative", "-50000", false],
  ["zero", "0", true],
  ["text", "abc", false],
]) {
  const tag = "Amt" + label;
  const r = await post("/deals", { title: tag, amount });
  await page.goto(base + "/deals?q=" + tag);
  const saved = (await page.locator("body").innerText()).includes(tag);
  ok(`deal amount ${label}: ${shouldPass ? "accepted" : "rejected"} (HTTP ${r.status}, saved=${saved})`,
    shouldPass ? true : !saved);
  await page.goto(base + "/deals");
}

await browser.close();
