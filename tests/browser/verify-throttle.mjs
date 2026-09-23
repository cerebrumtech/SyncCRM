import { launch, base, ok } from "./_lib.mjs";
const { browser, page } = await launch();
let locked = null;
for (let i = 1; i <= 12; i++) {
  await page.goto(base + "/login");
  await page.fill("#email", "owner@syncworkstech.com");
  await page.fill("#password", "WRONG" + i);
  await page.click("button[type=submit]");
  await page.waitForLoadState("domcontentloaded");
  const t = await page.locator("body").innerText();
  if (/too many/i.test(t) && locked === null) locked = i;
}
ok(`brute force is stopped (first blocked at attempt ${locked})`, locked !== null && locked <= 11);
// Correct password must still be refused while locked.
await page.goto(base + "/login");
await page.fill("#email", "owner@syncworkstech.com");
await page.fill("#password", "password123");
await page.click("button[type=submit]");
await page.waitForLoadState("domcontentloaded");
const after = await page.locator("body").innerText();
ok("correct password is also refused during lockout", /too many/i.test(after) && !page.url().includes("dashboard"));
console.log("message shown:", (after.match(/Too many[^.]*\./) || ["(none)"])[0]);
await browser.close();
