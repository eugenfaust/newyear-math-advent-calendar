import { getAccessToken } from "@/lib/auth-token";
import { error } from "../calendar/shared";
import { NextRequest, NextResponse } from "next/server";

export async function GET(request: NextRequest): Promise<NextResponse> {
  const token = await getAccessToken(request);
  if (!token) return error("UNAUTHENTICATED", "Требуется вход", 401);
  const baseUrl = process.env.INTERNAL_API_URL;
  if (!baseUrl) return error("SERVICE_UNAVAILABLE", "Сервис недоступен", 503);
  try {
    const response = await fetch(`${baseUrl}/tree${request.nextUrl.search}`, {
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
