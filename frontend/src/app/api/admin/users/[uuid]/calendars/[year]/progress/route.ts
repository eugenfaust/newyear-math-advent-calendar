import { error, forwardUsers, validUserId } from "../../../../shared";
import type { NextRequest } from "next/server";

type Context = { params: Promise<{ uuid: string; year: string }> };

export async function GET(request: NextRequest, context: Context) {
  const { uuid, year } = await context.params;
  if (!validUserId(uuid)) return error("USER_NOT_FOUND", "Пользователь не найден", 404);
  if (!/^[1-9][0-9]{0,3}$/.test(year)) return error("VALIDATION_ERROR", "Некорректный год", 422);
  return forwardUsers(request, `/${uuid}/calendars/${year}/progress`);
}
