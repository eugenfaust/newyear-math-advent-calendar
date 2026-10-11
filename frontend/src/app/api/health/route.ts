import { NextResponse } from "next/server";

export const dynamic = "force-dynamic";

export async function GET(): Promise<NextResponse> {
  const baseUrl = process.env.INTERNAL_API_URL;

  if (!baseUrl) {
    return NextResponse.json(
      { code: "API_NOT_CONFIGURED", message: "Backend URL is not configured." },
      { status: 503 },
    );
  }

  try {
    const response = await fetch(`${baseUrl}/health`, { cache: "no-store" });
    if (!response.ok) {
      throw new Error("Backend health check failed");
    }
    return NextResponse.json({ status: "ok" });
  } catch {
    return NextResponse.json(
      { code: "API_UNAVAILABLE", message: "Backend is unavailable." },
      { status: 503 },
    );
  }
}

