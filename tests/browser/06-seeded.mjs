import { launch, login, base, shots, ok } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);
ok("seeded dashboard has won revenue", (await page.textContent("[data-testid=stats]")).includes("₹1.42 L"));
await page.screenshot({ path: `${shots}/16-seeded-dashboard.png`, fullPage: true });
await page.goto(base + "/deals");
ok("seeded board has 8 deals", (await page.locator("[data-testid=kanban-card]").count()) === 8);
await page.screenshot({ path: `${shots}/17-seeded-board.png` });
await page.goto(base + "/contacts");
ok("8 contacts", (await page.textContent("h1 + p")).includes("8 contacts"));
await page.goto(base + "/companies?tag=priority");
ok("shared saved view active", (await page.locator("[data-testid=saved-views] .bg-primary").count()) === 1);
await page.goto(base + "/deals/1");
ok("seeded line items", (await page.locator("[data-tab=items]").textContent()).includes("2"));
await page.goto(base + "/activities?range=overdue&assignee=all");
ok("seeded overdue task", (await page.textContent("body")).includes("Follow up on KYC pilot pricing"));

// With seeded data there ARE closed deals, so the chart must render its drill-downs.
await page.goto(base + "/dashboard");
ok("seeded won/lost chart has 12 drill-downs",
   (await page.locator("[data-testid=won-lost] a").count()) === 12);

await browser.close();
