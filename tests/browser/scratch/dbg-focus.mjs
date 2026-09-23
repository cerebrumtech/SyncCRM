import { launch, login, base } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);
await page.goto(base + "/activities");
const id = (await page.locator("[id^=activity-]").first().getAttribute("id").catch(()=>null) || "").replace("activity-","");
console.log("picked activity id:", id || "(none found on default view)");
for (const url of [`/activities?focus=${id}`, `/activities?focus=${id}&range=all&assignee=all`]) {
  await page.goto(base + url);
  await page.waitForTimeout(600);
  const info = await page.evaluate((i) => ({
    marker: !!document.querySelector("[data-focus-activity]"),
    markerVal: document.querySelector("[data-focus-activity]")?.getAttribute("data-focus-activity"),
    target: !!document.getElementById("activity-" + i),
    cls: document.getElementById("activity-" + i)?.className || "",
  }), id);
  console.log(url, "->", JSON.stringify(info).slice(0, 180));
}
await browser.close();
