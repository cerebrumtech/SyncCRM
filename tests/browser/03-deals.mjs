import { launch, login, base, shots, ok } from "./_lib.mjs";
const { browser, page } = await launch({ width: 2600 });
const N = Date.now();
await login(page);
// products
await page.goto(base + "/products");
await page.click("[data-open=product-dialog]");
await page.fill("#pname", `SyncLMS Standard ${N}`);
await page.fill("#psku", `LMS-${N}`);
await page.fill("#pprice", "120000");
await page.click("#product-dialog button[type=submit]");
await page.waitForSelector("[data-testid=form-success]");
ok("product created", (await page.textContent("[data-testid=products-table]")).includes(`SyncLMS Standard ${N}`));
await page.click("[data-open=product-dialog]");
await page.fill("#pname", "Dup SKU");
await page.fill("#psku", `LMS-${N}`);
await page.fill("#pprice", "1");
await page.click("#product-dialog button[type=submit]");
await page.waitForSelector("[data-testid=form-error]");
ok("duplicate SKU rejected", (await page.textContent("[data-testid=form-error]")).includes("already used"));
// pipeline settings: require amount for Proposal Sent
await page.goto(base + "/settings/pipelines");
const salesCard = page.locator("[data-testid=pipeline-card][data-name=Sales]");
ok("default pipelines seeded", (await page.locator("[data-testid=pipeline-card]").count()) === 2 && (await salesCard.locator("[data-testid=stage-row]").count()) === 7);
await salesCard.locator("[data-testid=stage-row]", { hasText: "Proposal Sent" }).locator("button:has-text('Edit')").click();
await page.check("#stage-edit-dialog input[value=amount]");
await page.click("#stage-edit-dialog button[type=submit]");
await page.waitForSelector("[data-testid=form-success]");
ok("stage rule saved", (await salesCard.locator("[data-testid=stage-row]", { hasText: "Proposal Sent" }).textContent()).includes("Amount"));
await page.screenshot({ path: `${shots}/08-pipelines.png` });
// new deal with 0 amount in New Lead
await page.goto(base + "/deals");
await page.click("[data-open=deal-dialog]");
await page.fill("#deal-dialog #title", `Deal ${N}`);
await page.fill("#deal-dialog [data-picker][data-name=company_id] input[type=text]", "Acme");
await page.waitForSelector("#deal-dialog [data-picker][data-name=company_id] .picker-list button");
await page.locator("#deal-dialog [data-picker][data-name=company_id] .picker-list button").first().click();
await page.click("#deal-dialog button[type=submit]");
await page.waitForURL("**/deals/*");
const dealUrl = page.url(); const dealId = dealUrl.split("/").pop();
ok("deal created", (await page.textContent("h1")).includes(`Deal ${N}`));
// stage stepper: move to Proposal Sent -> blocked (amount required)
page.once("dialog", async (d) => { ok("missing field alert", d.message().includes("Amount")); await d.accept(); });
const mv1 = page.waitForResponse((r) => r.url().includes("/move"));
await page.selectOption("[data-stage-select]", { label: "Proposal Sent (60%)" });
await mv1; await page.waitForTimeout(800);
// line items -> amount from items
await page.click("[data-tab=items]");
await page.click("[data-add-row]");
await page.selectOption("[data-line-items] tbody select", { label: `SyncLMS Standard ${N}` });
await page.fill("[data-line-items] tbody input[name$='[quantity]']", "2");
ok("line total computed", (await page.textContent("[data-line-items] [data-grand]")).replace(/\D/g, "") === "283200");
await page.click("[data-line-items] button[type=submit]");
await page.waitForSelector("[data-testid=form-success]");
ok("amount rolled up", (await page.textContent("body")).includes("2,83,200") && (await page.textContent("body")).includes("from line items"));
// now stepper to Proposal Sent works
const mv2 = page.waitForResponse((r) => r.url().includes("/move"));
await page.selectOption("[data-stage-select]", { label: "Proposal Sent (60%)" });
await mv2; await page.waitForLoadState("load"); await page.waitForTimeout(800);
ok("stage changed", (await page.textContent("h1 + p, .card")).length > 0 && (await page.locator("[data-testid=stage-stepper] .font-semibold.text-navy").textContent()).includes("Proposal Sent"));
await page.screenshot({ path: `${shots}/09-deal.png` });
// kanban drag to Lost -> lost reason dialog
await page.goto(base + "/deals");
const card = page.locator(`[data-deal="${dealId}"]`);
const lostCol = page.locator("[data-testid=kanban-col][data-stage-name=Lost] [data-column]");
await page.evaluate(() => { const b = document.querySelector("[data-kanban]"); b.scrollLeft = b.scrollWidth; });
const cb = await card.boundingBox(); const lb = await lostCol.boundingBox();
console.log("drag1 card", JSON.stringify(cb), "lost", JSON.stringify(lb));
await page.mouse.move(cb.x + cb.width / 2, cb.y + 20); await page.mouse.down();
await page.mouse.move(cb.x + cb.width / 2 + 10, cb.y + 30, { steps: 5 });
await page.mouse.move(lb.x + lb.width / 2, lb.y + 40, { steps: 15 });
await page.mouse.up();
await page.waitForSelector("#lost-reason-dialog[open]", { timeout: 10000 }).catch(async (e) => { await page.screenshot({ path: `${shots}/fail-kanban.png` }); console.log("url", page.url(), "open", await page.evaluate(() => document.getElementById("lost-reason-dialog").open)); throw e; });
ok("lost reason dialog opened after drop", true);
await page.selectOption("#lost-reason", "Price too high");
await page.click("#lost-reason-dialog button[type=submit]");
await page.waitForLoadState("load"); await page.waitForTimeout(500);
ok("deal in Lost column with reason", (await page.locator("[data-testid=kanban-col][data-stage-name=Lost]").textContent()).includes("Price too high"));
await page.screenshot({ path: `${shots}/10-kanban.png` });
// drag back to Qualified -> reopen
const card2 = page.locator(`[data-deal="${dealId}"]`);
const qCol = page.locator("[data-testid=kanban-col][data-stage-name=Qualified] [data-column]");
await page.evaluate(() => { const b = document.querySelector("[data-kanban]"); b.scrollLeft = b.scrollWidth; });
const cb2 = await card2.boundingBox(); const qb = await qCol.boundingBox();
await page.mouse.move(cb2.x + cb2.width / 2, cb2.y + 20); await page.mouse.down(); await page.mouse.move(cb2.x + 10, cb2.y + 30, { steps: 5 }); await page.mouse.move(qb.x + qb.width / 2, qb.y + 40, { steps: 15 }); await page.mouse.up();
await page.waitForLoadState("load"); await page.waitForTimeout(600);
ok("deal reopened in Qualified", (await page.locator("[data-testid=kanban-col][data-stage-name=Qualified]").textContent()).includes(`Deal ${N}`));
// handoff to Onboarding
await page.goto(dealUrl);
await page.click("[data-dropdown]"); await page.click("button[data-open=handoff-dialog]");
await page.click("#handoff-dialog button[type=submit]");
await page.waitForSelector("[data-testid=form-success]");
ok("handoff created copy in Onboarding", page.url() !== dealUrl && (await page.textContent("body")).includes("Onboarding") && (await page.textContent("body")).includes("Handed off from"));
ok("line items copied", (await page.locator("[data-tab=items]").textContent()).includes("1"));
// list view + status filter + audit on timeline
await page.goto(base + "/deals?view=list&status=OPEN");
ok("list view", (await page.locator("[data-testid=deals-table] tbody tr").count()) >= 1);
await page.goto(dealUrl);
ok("timeline has stage changes", (await page.textContent("[data-panel=timeline]")).includes("Marked lost") && (await page.textContent("[data-panel=timeline]")).includes("Reopened"));
await browser.close();
