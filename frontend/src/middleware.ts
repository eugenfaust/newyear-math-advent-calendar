import { getToken } from "next-auth/jwt";
import { NextResponse, type NextRequest } from "next/server";

export async function middleware(request: NextRequest) {
  const loginUrl = new URL("/login", request.url);
  const token = await getToken({ req: request, secret: process.env.AUTH_SECRET });
  if (!token || typeof token.accessToken !== "string" || typeof token.expiresAt !== "string" || Date.parse(token.expiresAt) <= Date.now()) {
    return NextResponse.redirect(loginUrl);
  }

  const baseUrl = process.env.INTERNAL_API_URL;
  if (!baseUrl) return new NextResponse("Authentication service is not configured", { status: 503 });
  let response: Response;
  try {
    response = await fetch(`${baseUrl}/auth/me`, { headers: { Authorization: `Bearer ${token.accessToken}` }, cache: "no-store" });
  } catch {
    return new NextResponse("Authentication service is unavailable", { status: 503 });
  }
  if (response.status === 401) return NextResponse.redirect(loginUrl);
  if (!response.ok) return new NextResponse("Authentication service is unavailable", { status: 503 });
  const user: unknown = await response.json().catch(() => null);
  if (!isUser(user)) return new NextResponse("Invalid authentication response", { status: 503 });
  if (request.nextUrl.pathname.startsWith("/admin") && !user.roles.includes("ROLE_ADMIN")) {
    return NextResponse.redirect(new URL("/cabinet", request.url));
  }
  return NextResponse.next();
}

function isUser(value: unknown): value is { roles: string[] } {
  return typeof value === "object" && value !== null && "roles" in value && Array.isArray(value.roles) && value.roles.every((role: unknown) => typeof role === "string");
}

export const config = { matcher: ["/cabinet/:path*", "/admin/:path*"] };
