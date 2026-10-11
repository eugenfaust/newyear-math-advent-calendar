import { forwardCalendar } from "./shared";
import type { NextRequest } from "next/server";

export function GET(request: NextRequest) {
  return forwardCalendar(request);
}
