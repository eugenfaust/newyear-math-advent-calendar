import type { NextRequest } from "next/server";
import { forwardOrnaments, ornamentError, validOrnamentId } from "../../shared";

export async function POST(request: NextRequest, context: { params: Promise<{ uuid: string }> }) {
  const { uuid } = await context.params;
  return validOrnamentId(uuid) ? forwardOrnaments(request, `/${uuid}/image`, true) : ornamentError("ORNAMENT_NOT_FOUND", "Игрушка не найдена", 404);
}
