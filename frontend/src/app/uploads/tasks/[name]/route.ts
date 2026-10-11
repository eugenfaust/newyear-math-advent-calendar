import { NextRequest, NextResponse } from "next/server";

export async function GET(_request: NextRequest, context: { params: Promise<{ name: string }> }) {
  const { name } = await context.params;
  if (!/^[a-f0-9]{32}\.(png|jpg|webp)$/.test(name)) return new NextResponse(null, { status: 404 });
  const api = process.env.INTERNAL_API_URL;
  if (!api) return new NextResponse(null, { status: 503 });
  try {
    const response = await fetch(`${new URL(api).origin}/uploads/tasks/${name}`, { cache: "no-store" });
    if (!response.ok) return new NextResponse(null, { status: response.status === 404 ? 404 : 502 });
    const type = name.endsWith(".jpg") ? "image/jpeg" : name.endsWith(".webp") ? "image/webp" : "image/png";
    return new NextResponse(response.body, { headers: { "Content-Type": type, "Cache-Control": "public, max-age=31536000, immutable", "X-Content-Type-Options": "nosniff" } });
  } catch {
    return new NextResponse(null, { status: 502 });
  }
}
