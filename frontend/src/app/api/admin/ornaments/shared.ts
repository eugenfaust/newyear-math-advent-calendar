import { getAccessToken } from "@/lib/auth-token";
import { hasSameOrigin } from "@/lib/same-origin";
import { NextRequest, NextResponse } from "next/server";

export const validOrnamentId = (uuid: string) => /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(uuid);

export function ornamentError(code: string, message: string, status: number) {
  return NextResponse.json({ code, message }, { status, headers: { "Cache-Control": "no-store" } });
}

export async function forwardOrnaments(request: NextRequest, suffix = "", image = false): Promise<NextResponse> {
  if (request.method !== "GET") {
    if (!hasSameOrigin(request)) return ornamentError("ACCESS_DENIED", "Недопустимый источник запроса", 403);
    if (request.method !== "DELETE") {
      const expected = image ? "multipart/form-data;" : "application/json";
      if (!request.headers.get("content-type")?.startsWith(expected)) return ornamentError("INVALID_CONTENT_TYPE", image ? "Ожидается изображение" : "Ожидается JSON", 415);
    }
  }
  const token = await getAccessToken(request);
  if (!token) return ornamentError("UNAUTHENTICATED", "Требуется вход", 401);
  const baseUrl = process.env.INTERNAL_API_URL;
  if (!baseUrl) return ornamentError("SERVICE_UNAVAILABLE", "Сервис недоступен", 503);
  try {
    const headers = new Headers({ Authorization: `Bearer ${token}` });
    let body: BodyInit | undefined;
    if (request.method !== "GET" && request.method !== "DELETE") {
      if (image) {
        const incoming = await request.formData();
        const file = incoming.get("image");
        if (!(file instanceof File) || incoming.getAll("image").length !== 1 || [...incoming.keys()].some((key) => key !== "image")) {
          return ornamentError("VALIDATION_ERROR", "Выберите один файл изображения", 422);
        }
        const form = new FormData();
        form.set("image", file);
        body = form;
      } else {
        headers.set("Content-Type", "application/json");
        body = await request.text();
      }
    }
    const response = await fetch(`${baseUrl}/admin/ornaments${suffix}${request.method === "GET" ? request.nextUrl.search : ""}`, {
      method: request.method, headers, body, cache: "no-store",
    });
    return new NextResponse(response.status === 204 ? null : await response.text(), {
      status: response.status,
      headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" },
    });
  } catch {
    return ornamentError("SERVICE_UNAVAILABLE", "Сервис недоступен", 503);
  }
}
