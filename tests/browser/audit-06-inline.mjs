import { launch, login, base, ok } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);

// 1. Custom field: Options box must appear only for a dropdown.
await page.goto(base + "/settings/fields");
await page.click('[data-open="field-dialog"]');   // the select lives inside this dialog
await page.waitForTimeout(300);
const t = page.locator("[data-field-type]").first();
const opts = page.locator("[data-options-field]").first();
if (await t.count()) {
  await t.selectOption("TEXT").catch(()=>{});
  await page.waitForTimeout(200);
  const hiddenForText = await opts.isHidden();
  await t.selectOption("SELECT");
  await page.waitForTimeout(200);
  const shownForSelect = await opts.isVisible();
  ok("field Options box hidden for TEXT", hiddenForText);
  ok("field Options box shown for SELECT", shownForSelect);
} else { console.log("SKIP no field-type select"); }

// 2. Pipelines: opening a stage editor pre-ticks its required fields.
await page.goto(base + "/settings/pipelines");
const btn = page.locator("[data-open=stage-edit-dialog]").first();
if (await btn.count()) {
  const req = JSON.parse((await btn.getAttribute("data-required")) || "[]");
  await btn.click();
  await page.waitForTimeout(300);
  const checked = await page.locator("#stage-edit-dialog input[name='required_fields[]']:checked")
    .evaluateAll((els) => els.map((e) => e.value));
  ok(`stage editor pre-ticks required fields (expected ${JSON.stringify(req)}, got ${JSON.stringify(checked)})`,
     JSON.stringify(req.sort()) === JSON.stringify(checked.sort()));
} else { console.log("SKIP no stage edit button"); }

// 3. Activities: a focused activity gets highlighted.
await page.goto(base + "/activities?range=all&assignee=all");
// Only numeric ids are real activities; 'activity-dialog' is the modal.
const ids = await page.locator("[id^=activity-]").evaluateAll((els) =>
  els.map((e) => e.id.replace("activity-", "")).filter((v) => /^\d+$/.test(v)));
const first = ids[0];
if (first) {
  const id = first;
  await page.goto(base + `/activities?focus=${id}&range=all&assignee=all`);
  await page.waitForTimeout(500);
  const cls = await page.locator(`#activity-${id}`).getAttribute("class");
  ok("focused activity is highlighted", (cls || "").includes("bg-primary-50"));
} else { console.log("SKIP no activities"); }
await browser.close();
