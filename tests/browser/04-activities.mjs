import { launch, login, base, shots, ok } from "./_lib.mjs";
const { browser, page } = await launch();
const N = Date.now();
await login(page);
page.on("dialog", async (d) => { await d.accept(); });
// task from activities page
await page.goto(base + "/activities");
await page.click("[data-testid=quick-actions] button:has-text('+ Task')");
await page.waitForSelector("#activity-dialog[open]");
ok("dialog title", (await page.textContent("#activity-dialog [data-dialog-title]")) === "New task");
await page.fill("#a-title", `Send proposal ${N}`);
await page.selectOption("#a-rec", "WEEKLY");
await page.click("#activity-dialog button[type=submit]");
await page.waitForSelector("[data-testid=form-success]");
ok("task created (upcoming)", (await page.textContent("body")).includes(`Send proposal ${N}`));
// log a call on a contact with follow-up
await page.goto(base + "/contacts");
await page.click("[data-testid=contacts-table] tbody tr a");
await page.waitForURL("**/contacts/*");
const contactUrl = page.url();
await page.click("[data-testid=quick-actions] button:has-text('Log call')");
await page.waitForSelector("#activity-dialog[open]");
ok("call mode shows follow-up", await page.evaluate(() => !document.querySelector("#activity-dialog [data-logged-only]").hidden));
await page.fill("#a-title", `Intro call ${N}`);
await page.fill("#a-dur", "15");
await page.selectOption("#a-outcome", "Interested");
await page.fill("#a-fu", `Send pricing ${N}`);
await page.click("#activity-dialog button[type=submit]");
await page.waitForSelector("[data-testid=form-success]");
ok("call logged from contact", page.url().startsWith(contactUrl) && (await page.textContent("[data-testid=form-success]")).includes("Call logged"));
await page.click("[data-tab=activities]");
const acts = await page.textContent("[data-panel=activities]");
ok("call completed + follow-up task on contact", acts.includes(`Intro call ${N}`) && acts.includes(`Send pricing ${N}`) && (await page.locator("[data-panel=activities] [data-testid=activity][data-status=COMPLETED]").count()) >= 1);
await page.screenshot({ path: `${shots}/11-contact-activities.png` });
// meeting with attendees + end time from a deal
await page.goto(base + "/deals?view=list");
await page.click("[data-testid=deals-table] tbody tr a");
await page.waitForURL("**/deals/*");
await page.click("[data-testid=quick-actions] button:has-text('Meeting')");
await page.waitForSelector("#activity-dialog[open]");
await page.fill("#a-title", `Demo ${N}`);
await page.fill("#a-att", "sunita@godavari.coop, bad-email");
await page.click("#activity-dialog button[type=submit]");
await page.waitForSelector("[data-testid=form-success]");
ok("meeting created from deal", (await page.textContent("[data-testid=form-success]")).includes("Meeting created"));
// invalid: end before start
await page.click("[data-testid=quick-actions] button:has-text('Meeting')");
await page.fill("#a-title", "Bad");
await page.fill("#a-due", "2026-10-01T11:00");
await page.fill("#a-end", "2026-10-01T10:00");
await page.click("#activity-dialog button[type=submit]");
await page.waitForSelector("[data-testid=form-error]");
ok("end-before-start rejected", (await page.textContent("[data-testid=form-error]")).includes("End time"));
// complete recurring task -> next occurrence
await page.goto(base + "/activities?range=upcoming");
const row = page.locator("[data-testid=activity]", { hasText: `Send proposal ${N}` }).first();
await row.locator("form[action$='/status'] button").click();
await page.waitForSelector("[data-testid=form-success]");
ok("recurrence spawned next", (await page.textContent("[data-testid=form-success]")).includes("Next occurrence"));
await page.goto(base + "/activities?range=all");
ok("two occurrences exist", (await page.locator("[data-testid=activity]", { hasText: `Send proposal ${N}` }).count()) === 2);
// edit via dialog (data-fill)
await page.goto(base + "/activities?range=upcoming");
await page.locator("[data-testid=activity]", { hasText: `Send proposal ${N}` }).first().locator("button[data-open=activity-dialog]").click();
await page.waitForSelector("#activity-dialog[open]");
ok("edit dialog prefilled", (await page.inputValue("#a-title")).includes("Send proposal") && (await page.inputValue("#a-rec")) === "WEEKLY");
await page.fill("#a-title", `Send proposal ${N} (edited)`);
await page.click("#activity-dialog button[type=submit]");
await page.waitForSelector("[data-testid=form-success]");
ok("activity edited", (await page.textContent("body")).includes("(edited)"));
// calendar
await page.goto(base + "/activities?view=calendar&assignee=all");
ok("calendar renders month", (await page.locator("[data-testid=calendar] [data-day]").count()) >= 28);
const monthText = await page.textContent("[data-testid=calendar]");
ok("calendar shows demo meeting", monthText.includes(`Demo ${N}`));
await page.screenshot({ path: `${shots}/12-calendar.png` });
// overdue filter + counts
await page.goto(base + "/activities?range=overdue&assignee=all");
ok("overdue view renders", (await page.textContent("[data-testid=range-tabs]")).includes("Overdue"));
// dashboard
await page.goto(base + "/dashboard");
ok("dashboard stats", (await page.locator("[data-testid=stats] a").count()) === 5);
// The widget shows 6 months x won/lost drill-downs, or, when nothing closed in that
// window, a sentence instead -- those links would only lead to empty lists.
// The populated case is asserted in 06-seeded, where won deals definitely exist.
const wl = await page.locator("[data-testid=won-lost] a").count();
const wlEmpty = (await page.textContent("body")).includes("No deals won or lost");
ok(`won/lost chart (${wl} drill-downs, empty-state=${wlEmpty})`, wl === 12 || wlEmpty);
ok("up next lists my task", (await page.textContent("body")).includes(`Send proposal ${N}`));
await page.screenshot({ path: `${shots}/13-dashboard.png` });
await page.click("[data-testid=stats] a >> nth=0");
await page.waitForURL("**/deals?**");
ok("stat drills into deals list", page.url().includes("status=OPEN"));
await page.goto(base + "/dashboard?range=all&owner=" + (await page.evaluate(() => document.querySelector("select[name=owner] option:nth-child(2)").value)));
ok("owner filter works", (await page.textContent("h1 + p")).includes("is doing all time"));
await browser.close();
