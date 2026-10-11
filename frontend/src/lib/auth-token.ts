import "server-only";

import { getToken } from "next-auth/jwt";
import type { NextRequest } from "next/server";

/** Use only in server route handlers. Never return this token to the browser. */
export async function getAccessToken(request: NextRequest): Promise<string | null> {
  const jwt = await getToken({ req: request, secret: process.env.AUTH_SECRET });
  if (!jwt || typeof jwt.accessToken !== "string") return null;
  if (typeof jwt.expiresAt !== "string" || Date.parse(jwt.expiresAt) <= Date.now()) return null;
  return jwt.accessToken;
}
