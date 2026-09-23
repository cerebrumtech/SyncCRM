import { launch, login, base, shots, ok } from "./_lib.mjs";
const { browser, ctx, page } = await launch();
// setup
await page.goto(base + "/");
ok("root redirects to setup", page.url().endsWith("/setup"));
await page.fill("#name", "Hanuman Kale");
await page.fill("#email", "owner@syncworkstech.com");
await page.fill("#password", "password123");
await page.click("button[type=submit]");
await page.waitForURL("**/dashboard");
ok("setup lands on dashboard", true);
await page.screenshot({ path: `${shots}/01-dashboard-fresh.png` });
// setup is now locked
const r = await page.goto(base + "/setup");
ok("setup locked after first org", page.url().endsWith("/login") || page.url().endsWith("/dashboard"));
// invite
await page.goto(base + "/settings/users");
await page.click("[data-open=invite-dialog]");
await page.fill("#invite-email", "sneha@syncworkstech.com");
await page.selectOption("#invite-role", "ADMIN");
await page.click("#invite-dialog button[type=submit]");
await page.waitForSelector("[data-testid=invite-link]");
const link = await page.textContent("[data-testid=invite-link]");
ok("invite link shown", link.includes("/invite/"));
await page.screenshot({ path: `${shots}/02-users-invite.png` });
// duplicate invite for existing user rejected
await page.click("[data-open=invite-dialog]");
await page.fill("#invite-email", "owner@syncworkstech.com");
await page.click("#invite-dialog button[type=submit]");
await page.waitForSelector("[data-testid=form-error]");
ok("existing email rejected", (await page.textContent("[data-testid=form-error]")).includes("already exists"));
await page.waitForTimeout(300); console.log("auto-open el:", await page.evaluate(() => { const el = document.querySelector("[data-auto-open]"); return el ? el.getAttribute("data-auto-open") : null; }), "dialog open:", await page.evaluate(() => document.getElementById("invite-dialog").open));
ok("dialog reopened on error", await page.evaluate(() => document.getElementById("invite-dialog").open));
// accept invite in a separate context
const ctx2 = await browser.newContext();
const p2 = await ctx2.newPage();
await p2.goto(link);
await p2.fill("#name", "Sneha Patil");
await p2.fill("#password", "password123");
await p2.click("button[type=submit]");
await p2.waitForURL("**/dashboard");
ok("invite accepted and signed in", true);
await p2.goto(base + "/settings/users");
ok("admin can open settings", (await p2.textContent("body")).includes("Sneha Patil"));
// invite link reuse fails
await p2.goto(link);
ok("used invite invalid", (await p2.textContent("body")).includes("not valid") || (await p2.textContent("body")).includes("already exists"));
await ctx2.close();
// invite a member and accept
await page.goto(base + "/settings/users");
await page.click("[data-open=invite-dialog]");
await page.fill("#invite-email", "rahul@syncworkstech.com");
await page.selectOption("#invite-role", "MEMBER");
await page.click("#invite-dialog button[type=submit]");
const link2 = await page.textContent("[data-testid=invite-link]");
const ctx3 = await browser.newContext(); const p3 = await ctx3.newPage();
await p3.goto(link2); await p3.fill("#name", "Rahul Deshmukh"); await p3.fill("#password", "password123"); await p3.click("button[type=submit]"); await p3.waitForURL("**/dashboard");
await p3.goto(base + "/settings/users");
ok("member blocked from settings", p3.url().endsWith("/dashboard"));
await ctx3.close();
// role change via select
await page.goto(base + "/settings/users");
const row = page.locator("[data-testid=user-row][data-email='rahul@syncworkstech.com']");
await row.locator("select[name=role]").selectOption("ADMIN");
await page.waitForSelector("[data-testid=form-success]");
ok("role updated", (await row.locator("select[name=role]").inputValue()) === "ADMIN");
await row.locator("select[name=role]").selectOption("MEMBER");
await page.waitForSelector("[data-testid=form-success]");
// teams
await page.goto(base + "/settings/teams");
await page.click("[data-open=team-dialog]");
await page.fill("#team-name", "Pune Sales");
await page.click("#team-dialog button[type=submit]");
await page.waitForSelector("[data-testid=team-card]");
await page.locator("[data-testid=team-card] input[type=checkbox]").nth(1).check();
await page.locator("[data-testid=team-card] input[type=checkbox]").nth(2).check();
await page.click("[data-testid=team-card] button:has-text('Save members')");
await page.waitForSelector("[data-testid=form-success]");
ok("team members saved", (await page.textContent("[data-testid=team-card]")).includes("2 members"));
await page.screenshot({ path: `${shots}/03-teams.png` });
// deactivate with reassignment
await page.goto(base + "/settings/users");
const snehaRow = page.locator("[data-testid=user-row][data-email='sneha@syncworkstech.com']");
await snehaRow.locator("button[data-open]:has-text('Deactivate')").click();
const dlg = page.locator("dialog[open]");
await dlg.locator("select[name=reassign_to]").selectOption({ label: "Rahul Deshmukh" });
await dlg.locator("button[type=submit]").click();
await page.waitForSelector("[data-testid=form-success]");
ok("user deactivated", (await snehaRow.textContent()).includes("Deactivated"));
// deactivated user cannot sign in
const ctx4 = await browser.newContext(); const p4 = await ctx4.newPage();
await p4.goto(base + "/login"); await p4.fill("#email", "sneha@syncworkstech.com"); await p4.fill("#password", "password123"); await p4.click("button[type=submit]");
await p4.waitForSelector("[data-testid=form-error]");
ok("deactivated login blocked", (await p4.textContent("[data-testid=form-error]")).includes("deactivated"));
await ctx4.close();
await snehaRow.locator("button:has-text('Reactivate')").click();
await page.waitForSelector("[data-testid=form-success]");
// org + dedupe
await page.goto(base + "/settings/organization");
await page.selectOption("#dd-contactEmail", "block");
await page.click("form[action='/settings/organization/dedupe'] button");
await page.waitForSelector("[data-testid=form-success]");
ok("dedupe rules saved", (await page.inputValue("#dd-contactEmail")) === "block");
await page.selectOption("#dd-contactEmail", "warn");
await page.click("form[action='/settings/organization/dedupe'] button");
// audit
await page.goto(base + "/settings/audit");
const n = await page.locator("[data-testid=audit-row]").count();
ok("audit rows present", n >= 6);
await page.screenshot({ path: `${shots}/04-audit.png` });
// wrong password
await page.click("form[action='/logout'] button");
await page.waitForURL("**/login");
await page.fill("#email", "owner@syncworkstech.com"); await page.fill("#password", "nope"); await page.click("button[type=submit]");
await page.waitForSelector("[data-testid=form-error]");
ok("wrong password message", (await page.textContent("[data-testid=form-error]")).includes("Incorrect"));
ok("email preserved", (await page.inputValue("#email")) === "owner@syncworkstech.com");
await browser.close();
