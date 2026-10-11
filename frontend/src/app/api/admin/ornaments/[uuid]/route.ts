import type { NextRequest } from "next/server";
import { forwardOrnaments, ornamentError, validOrnamentId } from "../shared";

type Context = { params: Promise<{ uuid: string }> };
async function forward(request: NextRequest, context: Context) {
  const { uuid } = await context.params;
  return validOrnamentId(uuid) ? forwardOrnaments(request, `/${uuid}`) : ornamentError("ORNAMENT_NOT_FOUND", "Игрушка не найдена", 404);
}
export const GET = forward;
export const PATCH = forward;
export const DELETE = forward;
