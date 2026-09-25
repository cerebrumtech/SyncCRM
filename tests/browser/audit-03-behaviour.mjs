// Layer 4/5: real user behaviour, file upload safety, and regression traps.
import { launch, login, base, ok } from "./_lib.mjs";
import fs from "node:fs";

const { browser, page } = await launch();
await login(page);
const T = Date.now().toString().slice(-6);

// Fill every required field in a dialog so the form can actually submit.
async function fillRequired(sel) {
  await page.evaluate((s) => {
    const form = document.querySelector(s + " form");
    if (!form) return;
    for (const el of form.elements) {
      if (!el.required || el.value) continue;
      if (el.tagName === "SELECT") {
        const opt = [...el.options].find((o) => o.value !== "");
        if (opt) { el.value = opt.value; el.dispatchEvent(new Event("change", { bubbles: true })); }
      } else if (!el.value) {
        el.value = "QA" + Date.now().toString().slice(-4);
        el.dispatchEvent(new Event("input", { bubbles: true }));
      }
    }
  }, sel);
}

// ---------------------------------------------------------------- double submit -> duplicate rows?
await page.goto(base + "/contacts");
await page.click('[data-open="contact-dialog"]');
await page.fill("#contact-dialog #first_name", "Double");
await page.fill("#contact-dialog #last_name", "Submit" + T);
await fillRequired("#contact-dialog");
const btn = page.locator("#contact-dialog button[type=submit]");
await btn.click({ clickCount: 2, delay: 10 }).catch(() => {});
await page.waitForTimeout(2500);
await page.goto(base + "/contacts?q=Submit" + T);
const rows = await page.locator("tbody tr").count();
ok(`double-click create makes exactly one contact (found ${rows})`, rows === 1);

// ---------------------------------------------------------------- deal amount, through the real UI
await page.goto(base + "/deals");
const hasDealDialog = await page.locator('[data-open="deal-dialog"]').count();
if (hasDealDialog) {
  await page.click('[data-open="deal-dialog"]');
  await page.fill("#deal-dialog input[name=title]", "NegAmt" + T);
  const amt = page.locator("#deal-dialog input[name=amount]");
  if (await amt.count()) {
    await amt.fill("-50000");
    await fillRequired("#deal-dialog");
    await page.locator('#deal-dialog input[name="products[]"]').first().check().catch(() => {});  // a deal needs a product
    await page.click("#deal-dialog button[type=submit]");
    await page.waitForTimeout(2000);
    await page.goto(base + "/deals?q=NegAmt" + T);
    const negSaved = (await page.locator("body").innerText()).includes("NegAmt" + T);
    let negValue = null;
    if (negSaved) {
      const link = await page.locator('a[href*="/deals/"]').first().getAttribute("href");
      await page.goto(base + link);
      negValue = await page.locator("body").innerText();
    }
    ok(`negative deal amount rejected, or not stored as negative (saved=${negSaved})`,
      !negSaved || !/-\s?₹|₹\s?-/.test(negValue || ""));
  } else { console.log("SKIP amount field not present in deal dialog"); }
}

// ---------------------------------------------------------------- file upload safety
await page.goto(base + "/contacts");
const firstContact = await page.locator('a[href*="/contacts/"]').first().getAttribute("href");
await page.goto(base + firstContact);
const filesTab = page.locator("[data-tab=files]");
if (await filesTab.count()) {
  await filesTab.click();
  const input = page.locator('input[type=file]').first();
  if (await input.count()) {
    const dir = "tmp/uploads-probe";
    fs.mkdirSync(dir, { recursive: true });
    // A file that would execute if it ever landed inside the web root.
    const php = `${dir}/evil${T}.php`;
    fs.writeFileSync(php, "<?php echo 'EXECUTED-" + T + "'; ?>");
    await input.setInputFiles(php);
    const submit = page.locator("form:has(input[type=file]) button[type=submit]").first();
    if (await submit.count()) await submit.click();
    await page.waitForTimeout(2000);
    const body = await page.locator("body").innerText();
    const accepted = body.includes(`evil${T}.php`);
    console.log(`INFO .php upload ${accepted ? "ACCEPTED" : "rejected"}`);
    if (accepted) {
      // Accepted is survivable only if it is never served as PHP.
      const link = await page.locator(`a:has-text("evil${T}.php")`).first().getAttribute("href").catch(() => null);
      if (link) {
        const res = await page.goto(base + link, { waitUntil: "domcontentloaded" });
        const served = await page.content();
        ok("uploaded .php is not executed when downloaded",
          !served.includes("EXECUTED-" + T) || served.includes("<?php"));
        const ct = (res.headers()["content-type"] || "");
        console.log(`INFO download content-type: ${ct}, disposition: ${res.headers()["content-disposition"] || "(none)"}`);
      }
    } else {
      ok("dangerous .php upload rejected", true);
    }
  } else { console.log("SKIP no file input found"); }
} else { console.log("SKIP no files tab"); }

// ---------------------------------------------------------------- empty state
await page.goto(base + "/contacts?q=zzzznothingmatchesthis");
const empty = await page.locator("body").innerText();
ok("no-results search shows a message, not a blank table",
  /no |none|nothing|0 contact/i.test(empty));

// ---------------------------------------------------------------- deleting a company that has contacts
await page.goto(base + "/companies");
const compLink = await page.locator('a[href*="/companies/"]').first().getAttribute("href");
if (compLink) {
  await page.goto(base + compLink);
  const beforeText = await page.locator("body").innerText();
  const hasContacts = /contact/i.test(beforeText);
  const delBtn = page.locator('button:has-text("Delete"), a:has-text("Delete")').first();
  console.log(`INFO company page has delete control: ${await delBtn.count() > 0}, mentions contacts: ${hasContacts}`);
}

// ---------------------------------------------------------------- session invalidated on logout
await page.goto(base + "/dashboard");
const logout = page.locator('a[href*="logout"], button:has-text("Sign out"), a:has-text("Sign out")').first();
if (await logout.count()) {
  await logout.click().catch(() => {});
  await page.waitForTimeout(1200);
  const res = await page.goto(base + "/dashboard", { waitUntil: "domcontentloaded" });
  ok("after sign-out the dashboard is no longer reachable", page.url().includes("/login"));
}

await browser.close();
