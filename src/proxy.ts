import { NextResponse, type NextRequest } from "next/server";

const SESSION_COOKIE = "synccrm_session";

export function proxy(request: NextRequest) {
  const hasSession = !!request.cookies.get(SESSION_COOKIE)?.value;
  if (!hasSession) {
    const url = new URL("/login", request.url);
    url.searchParams.set("next", request.nextUrl.pathname);
    return NextResponse.redirect(url);
  }
  return NextResponse.next();
}

export const config = {
  matcher: [
    "/dashboard/:path*",
    "/contacts/:path*",
    "/companies/:path*",
    "/deals/:path*",
    "/products/:path*",
    "/activities/:path*",
    "/settings/:path*",
  ],
};
