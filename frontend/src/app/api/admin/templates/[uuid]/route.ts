import type { NextRequest } from "next/server";
import { forwardTemplates, templateError, validUuid } from "../shared";

type Context = { params: Promise<{ uuid: string }> };
async function forward(request: NextRequest, context: Context) {
  const { uuid } = await context.params;
  return validUuid(uuid) ? forwardTemplates(request, `/${uuid}`) : templateError("TEMPLATE_NOT_FOUND", "Шаблон не найден", 404);
}
export const GET = forward;
export const PATCH = forward;
export const DELETE = forward;
