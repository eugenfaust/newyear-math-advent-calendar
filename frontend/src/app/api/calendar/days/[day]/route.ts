import { error, forwardCalendar } from "../../shared";
import type { NextRequest } from "next/server";

type Context = { params: Promise<{ day: string }> };

export async function GET(request: NextRequest, context: Context) {
  const { day } = await context.params;
  if (!/^(?:[1-9]|[12][0-9]|3[01])$/.test(day)) return error("VALIDATION_ERROR", "Недопустимый номер дня", 422);
  return forwardCalendar(request, `/days/${day}`);
}
