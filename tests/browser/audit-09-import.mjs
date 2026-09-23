import { launch, login, base, ok } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);
await page.goto(base + "/settings/import");
await page.locator('input[type=file]').first().setInputFiles("/tmp/import-test.csv");
await page.locator('form button[type=submit]').first().click();
await page.waitForTimeout(1500);

// What did the mapping screen guess on its own?
const guessed = await page.locator("select").evaluateAll((sels) =>
  sels.filter(s => s.name && s.name.startsWith("map")).map(s => ({ name: s.name, value: s.value,
    label: s.selectedOptions[0] ? s.selectedOptions[0].text : "" })));
console.log("AUTO-MAPPING:", JSON.stringify(guessed));

// Map anything it left unset, by matching the header text to an option.
await page.evaluate(() => {
  const want = { "First Name": "first_name", "Last name": "last_name", "Email": "email",
                 "Mobile": "phone", "Company": "company", "Tags": "tags" };
  document.querySelectorAll("tr").forEach((tr) => {
    const head = tr.querySelector("td")?.textContent?.trim();
    const sel = tr.querySelector("select");
    if (!head || !sel || !want[head]) return;
    const opt = [...sel.options].find(o => o.value === want[head] || o.value.includes(want[head]));
    if (opt) sel.value = opt.value;
  });
});
const runBtn = page.locator('button:has-text("Import")').last();
await runBtn.click();
await page.waitForTimeout(3000);
const after = await page.locator("body").innerText();
const m = after.match(/created[^\n]*|updated[^\n]*|skipped[^\n]*|Row \d+[^\n]*/gi) || [];
console.log("RESULT LINES:", JSON.stringify(m.slice(0, 12), null, 1));
await page.screenshot({ path: "tmp/shots/import-result.png" });
await browser.close();
