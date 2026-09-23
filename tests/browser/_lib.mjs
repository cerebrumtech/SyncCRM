// Shared setup for the browser tests. Every suite imports from here.
//
// Playwright is a dev dependency of this folder only -- the application itself has
// no Node, no npm and no build step. See tests/browser/README.md.
// `playwright` ships a Chromium of its own; `playwright-core` is the same API without one.
// Either works -- with playwright-core you must point PW_CHROMIUM at a Chromium yourself.
let chromium;
try {
  ({ chromium } = await import("playwright"));
} catch (e) {
  ({ chromium } = await import("playwright-core"));
}

/** Where the app under test is running. Override with BASE=... */
export const base = process.env.BASE || "http://127.0.0.1:8080";

/** Screenshots land here; the runner creates it. */
export const shots = "tmp/shots";

export async function launch(opts = {}) {
  // PW_CHROMIUM lets a CI image point at a Chromium it already has, instead of
  // the one `npx playwright install chromium` downloads.
  const launchOpts = { headless: true };
  if (process.env.PW_CHROMIUM) { launchOpts.executablePath = process.env.PW_CHROMIUM; }

  const browser = await chromium.launch(launchOpts);
  const ctx = await browser.newContext({ viewport: { width: opts.width || 1400, height: opts.height || 900 } });
  const page = await ctx.newPage();
  page.on("pageerror", (e) => console.log("PAGE ERROR:", e.message));
  page.on("console", (m) => { if (m.type() === "error") console.log("CONSOLE:", m.text().slice(0, 300)); });
  page.on("response", (r) => { if (r.status() >= 500) console.log("HTTP", r.status(), r.url()); });
  return { browser, ctx, page };
}

/** Signs in with a seeded account. `php schema/install.php --seed` creates these. */
export async function login(page, email = "owner@syncworkstech.com", password = "password123") {
  await page.goto(base + "/login");
  await page.fill("#email", email);
  await page.fill("#password", password);
  await page.click("button[type=submit]");
  await page.waitForURL("**/dashboard", { timeout: 15000 });
}

/** A failed assertion sets a non-zero exit code without stopping the run. */
export const ok = (label, cond) => {
  console.log((cond ? "PASS " : "FAIL ") + label);
  if (!cond) process.exitCode = 1;
};
