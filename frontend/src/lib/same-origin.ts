import type { NextRequest } from "next/server";

export function hasSameOrigin(request: NextRequest): boolean {
  const origin = request.headers.get("origin");
  const host = request.headers.get("host");
  const protocol = request.headers.get("x-forwarded-proto") ?? request.nextUrl.protocol.slice(0, -1);
  return Boolean(origin && host && ["http", "https"].includes(protocol) && origin === `${protocol}://${host}`);
}
