import type { NextRequest } from "next/server";
import { forwardTemplates, templateError, validUuid } from "../../../shared";

export async function PUT(request: NextRequest, context: { params: Promise<{ uuid: string; day: string }> }) {
  const { uuid, day } = await context.params;
  if (!validUuid(uuid)) return templateError("TEMPLATE_NOT_FOUND", "Шаблон не найден", 404);
  if (!/^(?:[1-9]|[12][0-9]|3[01])$/.test(day)) return templateError("VALIDATION_ERROR", "День должен быть от 1 до 31", 422);
  return forwardTemplates(request, `/${uuid}/days/${day}`);
}
