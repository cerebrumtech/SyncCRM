import { requireUser, type CurrentUser } from "@/lib/auth";
import { PermissionError } from "@/lib/permissions";
import { ZodError } from "zod";

export type ActionResult<T = undefined> = { ok: true; data?: T } | { ok: false; error: string };

// Wraps a server action body: resolves the actor and turns known errors into a result value.
export async function act<T = undefined>(
  fn: (user: CurrentUser) => Promise<T>,
): Promise<ActionResult<T>> {
  try {
    const user = await requireUser();
    const data = await fn(user);
    return { ok: true, data };
  } catch (e) {
    if (e instanceof PermissionError) return { ok: false, error: e.message };
    if (e instanceof ZodError) return { ok: false, error: e.issues[0]?.message ?? "Invalid input" };
    if (e instanceof ActionError) return { ok: false, error: e.message };
    // redirect() and notFound() throw control-flow errors that must propagate
    if (typeof e === "object" && e !== null && "digest" in e) throw e;
    console.error(e);
    return { ok: false, error: "Something went wrong. Please try again." };
  }
}

export class ActionError extends Error {}

export function fail(message: string): never {
  throw new ActionError(message);
}

export function str(formData: FormData, key: string) {
  const v = formData.get(key);
  return typeof v === "string" ? v.trim() : "";
}

export function optStr(formData: FormData, key: string) {
  const v = str(formData, key);
  return v === "" ? null : v;
}

export function num(formData: FormData, key: string, fallback = 0) {
  const v = Number(str(formData, key));
  return Number.isFinite(v) ? v : fallback;
}

export function list(formData: FormData, key: string) {
  return str(formData, key)
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);
}
