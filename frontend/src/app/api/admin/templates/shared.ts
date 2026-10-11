import { getAccessToken } from "@/lib/auth-token";
import { NextRequest, NextResponse } from "next/server";

export const validUuid = (value: string) => /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value);

export function templateError(code: string, message: string, status: number) {
  return NextResponse.json({ code, message }, { status, headers: { "Cache-Control": "no-store" } });
}

export async function forwardTemplates(request: NextRequest, suffix = ""): Promise<NextResponse> {
  if (request.method !== "GET") {
    const origin = request.headers.get("origin");
    if (!origin || origin !== request.nextUrl.origin) return templateError("ACCESS_DENIED", "Недопустимый источник запроса", 403);
    if (request.method !== "DELETE" && !request.headers.get("content-type")?.startsWith("application/json")) {
      return templateError("INVALID_CONTENT_TYPE", "Ожидается JSON", 415);
    }
  }
  const token = await getAccessToken(request);
  if (!token) return templateError("UNAUTHENTICATED", "Требуется вход", 401);
  const baseUrl = process.env.INTERNAL_API_URL;
  if (!baseUrl) return templateError("SERVICE_UNAVAILABLE", "Сервис недоступен", 503);
  try {
    const headers = new Headers({ Authorization: `Bearer ${token}` });
    let body: string | undefined;
    if (request.method !== "GET" && request.method !== "DELETE") {
      headers.set("Content-Type", "application/json");
      body = await request.text();
    }
    const response = await fetch(`${baseUrl}/admin/templates${suffix}${request.method === "GET" ? request.nextUrl.search : ""}`, {
      method: request.method, headers, body, cache: "no-store",
    });
    return new NextResponse(response.status === 204 ? null : await response.text(), {
      status: response.status,
      headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" },
    });
  } catch {
    return templateError("SERVICE_UNAVAILABLE", "Сервис недоступен", 503);
  }
}
