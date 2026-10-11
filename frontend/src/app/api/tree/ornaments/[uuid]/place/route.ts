import { getAccessToken } from "@/lib/auth-token";
import { hasSameOrigin } from "@/lib/same-origin";
import { error } from "../../../../calendar/shared";
import { NextRequest, NextResponse } from "next/server";

type Context = { params: Promise<{ uuid: string }> };

export async function POST(request: NextRequest, context: Context): Promise<NextResponse> {
  if (!hasSameOrigin(request)) return error("ACCESS_DENIED", "Недопустимый источник запроса", 403);
  if (!request.headers.get("content-type")?.toLowerCase().startsWith("application/json")) {
    return error("INVALID_CONTENT_TYPE", "Ожидается JSON", 415);
  }
  const { uuid } = await context.params;
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(uuid)) {
    return error("VALIDATION_ERROR", "Недопустимый UUID игрушки", 422);
  }
  let body: unknown;
  try { body = await request.json(); } catch { return error("VALIDATION_ERROR", "Ожидается JSON с номером слота", 422); }
  if (typeof body !== "object" || body === null || !("slot" in body) || !Number.isInteger(body.slot) || typeof body.slot !== "number" || body.slot < 1 || body.slot > 31) {
    return error("VALIDATION_ERROR", "Номер слота должен быть от 1 до 31", 422);
  }
  const token = await getAccessToken(request);
  if (!token) return error("UNAUTHENTICATED", "Требуется вход", 401);
  const baseUrl = process.env.INTERNAL_API_URL;
  if (!baseUrl) return error("SERVICE_UNAVAILABLE", "Сервис недоступен", 503);
  try {
    const response = await fetch(`${baseUrl}/tree/ornaments/${uuid}/place${request.nextUrl.search}`, {
      method: "POST",
      headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
      body: JSON.stringify({ slot: body.slot }),
      cache: "no-store",
    });
    return new NextResponse(await response.text(), {
      status: response.status,
      headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" },
    });
  } catch {
    return error("SERVICE_UNAVAILABLE", "Сервис недоступен", 503);
  }
}
