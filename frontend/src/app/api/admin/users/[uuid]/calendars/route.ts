import { error, forwardUsers, validUserId } from "../../shared";
import type { NextRequest } from "next/server";

type Context = { params: Promise<{ uuid: string }> };
async function forward(request: NextRequest, context: Context) {
  const { uuid } = await context.params;
  return validUserId(uuid) ? forwardUsers(request, `/${uuid}/calendars`) : error("USER_NOT_FOUND", "Пользователь не найден", 404);
}
export const GET = forward;
export const POST = forward;
