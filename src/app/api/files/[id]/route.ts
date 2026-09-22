import { NextResponse } from "next/server";
import { getCurrentUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { readUpload } from "@/lib/storage";

export async function GET(_req: Request, ctx: RouteContext<"/api/files/[id]">) {
  const user = await getCurrentUser();
  if (!user) return new NextResponse("Unauthorized", { status: 401 });
  const { id } = await ctx.params;
  const file = await prisma.attachment.findFirst({ where: { id, orgId: user.orgId } });
  if (!file) return new NextResponse("Not found", { status: 404 });
  const body = await readUpload(file.storagePath);
  const inline = file.mimeType.startsWith("image/") || file.mimeType === "application/pdf";
  return new NextResponse(new Uint8Array(body), {
    headers: {
      "Content-Type": file.mimeType,
      "Content-Length": String(file.size),
      "Content-Disposition": `${inline ? "inline" : "attachment"}; filename="${encodeURIComponent(file.filename)}"`,
      "Cache-Control": "private, max-age=0",
    },
  });
}
