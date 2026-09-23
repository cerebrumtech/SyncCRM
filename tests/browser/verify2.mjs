import { launch, login, base } from "./_lib.mjs";
const { browser, page } = await launch();
await login(page);
const res = await page.request.get(base + "/files/4", {
  headers: { cookie: (await page.context().cookies()).map(c => `${c.name}=${c.value}`).join("; ") } });
const h = res.headers();
console.log("Content-Type:       ", h["content-type"]);
console.log("Content-Disposition:", h["content-disposition"]);
console.log("CSP:                ", (h["content-security-policy"]||"").slice(0,60));
const inline = (h["content-disposition"]||"").startsWith("inline");
const html = /text\/html|svg/i.test(h["content-type"]||"");
console.log(inline || html ? "STILL VULNERABLE" : "FIXED: served as a download, cannot execute on this origin");
await browser.close();
