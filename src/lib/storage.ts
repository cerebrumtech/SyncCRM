import fs from "node:fs/promises";
import path from "node:path";
import crypto from "node:crypto";

const ROOT = path.resolve(process.env.UPLOAD_DIR ?? "./uploads");
export const MAX_UPLOAD_BYTES = 15 * 1024 * 1024;

export async function saveUpload(orgId: string, file: File) {
  const safeName = file.name.replace(/[^\w.\-]+/g, "_").slice(0, 120) || "file";
  const rel = path.join(orgId, `${crypto.randomBytes(8).toString("hex")}-${safeName}`);
  const abs = path.join(ROOT, rel);
  await fs.mkdir(path.dirname(abs), { recursive: true });
  await fs.writeFile(abs, Buffer.from(await file.arrayBuffer()));
  return { storagePath: rel, filename: file.name, mimeType: file.type || "application/octet-stream", size: file.size };
}

export async function readUpload(storagePath: string) {
  const abs = path.resolve(ROOT, storagePath);
  if (!abs.startsWith(ROOT + path.sep)) throw new Error("Invalid path");
  return fs.readFile(abs);
}

export async function removeUpload(storagePath: string) {
  const abs = path.resolve(ROOT, storagePath);
  if (!abs.startsWith(ROOT + path.sep)) return;
  await fs.rm(abs, { force: true });
}
