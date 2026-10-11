import { getAccessToken } from "@/lib/auth-token";
import { NextRequest, NextResponse } from "next/server";

export async function forwardCalendar(request: NextRequest, suffix = ""): Promise<NextResponse> {
  const token = await getAccessToken(request);
  if (!token) return error("UNAUTHENTICATED", "Требуется вход", 401);
  const baseUrl = process.env.INTERNAL_API_URL;
  if (!baseUrl) return error("SERVICE_UNAVAILABLE", "Сервис недоступен", 503);
  try {
    const response = await fetch(`${baseUrl}/calendar${suffix}${request.nextUrl.search}`, {
      headers: { Authorization: `Bearer ${token}` },
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

export function error(code: string, message: string, status: number): NextResponse {
  return NextResponse.json({ code, message }, { status, headers: { "Cache-Control": "no-store" } });
}
