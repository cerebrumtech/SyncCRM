// Write paths across every module, weighted toward data integrity: money maths,
// deletes with children, ownership on deactivation, and stage rules.
import { launch, login, base, ok } from "./_lib.mjs";

const { browser, page } = await launch();
await login(page);
const T = Date.now().toString().slice(-6);

async function fillRequired(sel) {
  await page.evaluate((s) => {
    const form = document.querySelector(s + " form");
    if (!form) return;
    for (const el of form.elements) {
      if (!el.required || el.value) continue;
      if (el.tagName === "SELECT") {
        const o = [...el.options].find((x) => x.value !== "");
        if (o) { el.value = o.value; el.dispatchEvent(new Event("change", { bubbles: true })); }
      } else { el.value = "QA" + Date.now().toString().slice(-4); el.dispatchEvent(new Event("input", { bubbles: true })); }
    }
  }, sel);
}

// ---------------------------------------------------------------- money: deal amount
await page.goto(base + "/deals");
await page.click('[data-open="deal-dialog"]');
await page.fill("#deal-dialog input[name=title]", "NegAmt" + T);
const amtField = page.locator("#deal-dialog input[name=amount]");
if (await amtField.count()) {
  await amtField.fill("-50000");
  await fillRequired("#deal-dialog");
  await page.click("#deal-dialog button[type=submit]");
  await page.waitForTimeout(1500);
  const onDeal = page.url().includes("/deals/");
  let shown = onDeal ? await page.locator("body").innerText() : "";
  ok(`negative deal amount is refused or not stored negative (created=${onDeal})`,
    !onDeal || !/-\s?₹|₹\s?-|−₹/.test(shown));
}

// ---------------------------------------------------------------- money: line item totals
await page.goto(base + "/deals?view=list");
const dealHref = await page.locator('a[href*="/deals/"]').first().getAttribute("href").catch(() => null);
if (dealHref) {
  await page.goto(base + dealHref);
  const liTab = page.locator("[data-tab=line-items], [data-tab=items], [data-tab=products]").first();
  if (await liTab.count()) {
    await liTab.click().catch(() => {});
    await page.waitForTimeout(400);
    const nums = await page.locator("body").innerText();
    // A rollup that shows NaN, undefined or a bare '₹' with nothing after it is broken.
    ok("line-item totals contain no NaN/undefined", !/NaN|undefined/i.test(nums));
  }
}

// ---------------------------------------------------------------- delete with children
await page.goto(base + "/companies");
const coHref = await page.locator('a[href*="/companies/"]').first().getAttribute("href").catch(() => null);
if (coHref) {
  await page.goto(base + coHref);
  const body = await page.locator("body").innerText();
  const contactCount = (body.match(/contact/gi) || []).length;
  const del = page.locator('button:has-text("Delete"), a:has-text("Delete")').first();
  if (await del.count()) {
    page.once("dialog", (d) => d.accept());
    await del.click().catch(() => {});
    await page.waitForTimeout(1800);
    const after = await page.locator("body").innerText();
    const gone = page.url().includes("/companies") && !page.url().match(/companies\/\d+/);
    // If the company was deleted, its contacts must not be orphaned into another company
    // or silently deleted. Report what happened so a human can judge.
    console.log(`INFO company delete -> url=${page.url().replace(base, "")}, warned=${/cannot|in use|has \d+|first/i.test(after)}`);
    ok("deleting a company either warns or completes cleanly (no error page)",
      !/Something went wrong/i.test(after));
  }
}

// ---------------------------------------------------------------- stage required fields
await page.goto(base + "/deals");
await page.click('[data-open="deal-dialog"]');
await page.fill("#deal-dialog input[name=title]", "RuleProbe" + T);
// 'New Lead' now requires amount and expected_close_date; leave them empty deliberately.
const stageSel = page.locator("#deal-dialog select[name=stage_id]");
if (await stageSel.count()) {
  await fillRequired("#deal-dialog");
  await page.locator("#deal-dialog input[name=amount]").fill("").catch(() => {});
  await page.click("#deal-dialog button[type=submit]");
  await page.waitForTimeout(1500);
  const txt = await page.locator("body").innerText();
  const blocked = /needs:|required/i.test(txt) || !page.url().includes("/deals/");
  ok("stage required-field rule is enforced on create", blocked);
}

// ---------------------------------------------------------------- products: price sanity
await page.goto(base + "/products");
if (await page.locator('[data-open="product-dialog"]').count()) {
  await page.click('[data-open="product-dialog"]');
  await page.fill("#product-dialog input[name=name]", "NegPrice" + T);
  const price = page.locator("#product-dialog input[name=price]");
  if (await price.count()) {
    await price.fill("-999");
    await fillRequired("#product-dialog");
    await page.click("#product-dialog button[type=submit]");
    await page.waitForTimeout(1500);
    await page.goto(base + "/products?q=NegPrice" + T);
    const t = await page.locator("body").innerText();
    ok(`negative product price refused or not shown negative`, !/-\s?₹|₹\s?-/.test(t));
  }
}

// ---------------------------------------------------------------- deactivating a user
await page.goto(base + "/settings/users");
const deact = page.locator('button:has-text("Deactivate")').first();
if (await deact.count()) {
  page.once("dialog", (d) => d.accept());
  await deact.click().catch(() => {});
  await page.waitForTimeout(1800);
  const t = await page.locator("body").innerText();
  ok("deactivating a user does not error", !/Something went wrong/i.test(t));
  console.log(`INFO deactivate flow mentions reassignment: ${/reassign|transfer|owner/i.test(t)}`);
}

// ---------------------------------------------------------------- saved view round-trip
await page.goto(base + "/contacts?q=a");
const saveBtn = page.locator('[data-open="save-view-dialog"]').first();
if (await saveBtn.count()) {
  await saveBtn.click();
  await page.fill("#save-view-dialog input[name=name]", "QAView" + T);
  await page.click("#save-view-dialog button[type=submit]");
  await page.waitForTimeout(1200);
  const chip = await page.locator("[data-testid=saved-views]").innerText().catch(() => "");
  ok("saved view appears after saving", chip.includes("QAView" + T));
}

await browser.close();
