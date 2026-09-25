// Editing cells in the sheet view, driven the way a person drives it: click, type, Enter.
//
// The API behind this is covered elsewhere; what this suite checks is the part only a
// browser can tell you — that the cell turns into an input, that Enter saves and Esc
// does not, and that a refused value puts the old text back rather than leaving a lie
// on screen.
//
// Needs a contact whose first name is "ZZSHEETUI". scripts around this suite create and
// remove it; run.sh is not aware of it, so it is run on its own.

import { launch, login, base, ok } from "./_lib.mjs";

const { browser, page } = await launch();
await login(page);

await page.goto(base + "/contacts?view=sheet&q=ZZSHEETUI");
await page.waitForSelector("[data-sheet-kind]");

const row = page.locator("tr[data-id]").first();
ok("the test contact is on the sheet", (await row.count()) === 1);

const cell = (col) => row.locator(`td[data-col="${col}"]`);

// Waits for the save itself rather than a fixed pause: the local dev server answers in
// about two seconds, a real one in milliseconds, and a timer that suits one breaks on
// the other.
const saved = () => page.waitForResponse((r) => r.url().includes("/api/sheet/"), { timeout: 20000 });

// ---------------------------------------------------------------- a cell opens
ok("a job title cell is marked editable", (await cell("title").getAttribute("data-edit")) === "job_title");
await cell("title").click();
ok("clicking turns it into an input", (await cell("title").locator("input").count()) === 1);

// --------------------------------------------------------------- Enter saves
await cell("title").locator("input").fill("Managing Director");
const firstSave = saved();
await page.keyboard.press("Enter");
await firstSave;
await page.waitForTimeout(150);
ok("the cell shows the new value", (await cell("title").textContent()).trim() === "Managing Director");

await page.reload();
await page.waitForSelector("[data-sheet-kind]");
ok("and it survives a reload", (await cell("title").textContent()).trim() === "Managing Director");

// ------------------------------------------------------------- Escape cancels
await cell("title").click();
await cell("title").locator("input").fill("Something Else");
await page.keyboard.press("Escape");
await page.waitForTimeout(200);
ok("Escape restores the old text", (await cell("title").textContent()).trim() === "Managing Director");
await page.reload();
await page.waitForSelector("[data-sheet-kind]");
ok("and nothing was saved", (await cell("title").textContent()).trim() === "Managing Director");

// ------------------------------------------------- a refused value comes back
await cell("email").click();
await cell("email").locator("input").fill("definitely-not-an-email");
const rejected = saved();
await page.keyboard.press("Enter");
await rejected;
await page.waitForTimeout(150);
const status = (await page.textContent("[data-sheet-status]")) || "";
ok("a bad email is reported to the person", status.toLowerCase().includes("valid email"));
await page.reload();
await page.waitForSelector("[data-sheet-kind]");
ok("and the bad value was not stored", !(await cell("email").textContent()).includes("definitely-not"));

// -------------------------------------------------------------- a dropdown
await cell("owner").click();
ok("the owner cell opens a dropdown", (await cell("owner").locator("select").count()) === 1);
await page.keyboard.press("Escape");

// --------------------------------------------------- read-only columns stay so
ok("Created is not editable", (await cell("created").getAttribute("data-edit")) === null);
ok("Deals count is not editable", (await cell("deals").getAttribute("data-edit")) === null);
await cell("created").click();
ok("clicking a read-only cell does nothing", (await cell("created").locator("input").count()) === 0);

// ---------------------------------------------------- links still open records
const nameLink = row.locator('td[data-col="name"] a');
ok("the name is still a link to the record", (await nameLink.count()) === 1);

await browser.close();
