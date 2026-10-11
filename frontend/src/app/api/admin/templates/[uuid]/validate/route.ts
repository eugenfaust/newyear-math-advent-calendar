import type { NextRequest } from "next/server";
import { forwardTemplates, templateError, validUuid } from "../../shared";

export async function GET(request: NextRequest, context: { params: Promise<{ uuid: string }> }) {
  const { uuid } = await context.params;
  return validUuid(uuid) ? forwardTemplates(request, `/${uuid}/validate`) : templateError("TEMPLATE_NOT_FOUND", "Шаблон не найден", 404);
}
