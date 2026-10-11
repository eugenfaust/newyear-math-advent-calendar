import { getAccessToken } from "@/lib/auth-token";
import { hasSameOrigin } from "@/lib/same-origin";
import { NextRequest, NextResponse } from "next/server";

export async function forwardUsers(request: NextRequest, suffix = ""): Promise<NextResponse> {
  const mutation = request.method !== "GET";
  if (mutation) {
    if (!hasSameOrigin(request)) return error("ACCESS_DENIED", "Недопустимый источник запроса", 403);
    if (request.method !== "DELETE" && !request.headers.get("content-type")?.startsWith("application/json")) {
      return error("INVALID_CONTENT_TYPE", "Ожидается JSON", 415);
    }
  }
  const token = await getAccessToken(request);
  if (!token) return error("UNAUTHENTICATED", "Требуется вход", 401);
  const baseUrl = process.env.INTERNAL_API_URL;
  if (!baseUrl) return error("SERVICE_UNAVAILABLE", "Сервис недоступен", 503);
  const path = `/admin/users${suffix}`;
  const query = request.method === "GET" ? request.nextUrl.search : "";
  try {
    const response = await fetch(`${baseUrl}${path}${query}`, {
      method: request.method,
      headers: {
        Authorization: `Bearer ${token}`,
        ...(mutation && request.method !== "DELETE" ? { "Content-Type": "application/json" } : {}),
      },
      body: mutation && request.method !== "DELETE" ? await request.text() : undefined,
      cache: "no-store",
    });
    return new NextResponse(response.status === 204 ? null : await response.text(), {
      status: response.status,
      headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" },
    });
  } catch {
    return error("SERVICE_UNAVAILABLE", "Сервис недоступен", 503);
  }
}

export function validUserId(uuid: string): boolean {
  return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(uuid);
}

export function error(code: string, message: string, status: number): NextResponse {
  return NextResponse.json({ code, message }, { status, headers: { "Cache-Control": "no-store" } });
}
