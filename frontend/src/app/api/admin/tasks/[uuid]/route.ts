import type { NextRequest } from "next/server";
import { forwardTasks, taskError, validTaskId } from "../shared";

type Context = { params: Promise<{ uuid: string }> };
async function forward(request: NextRequest, context: Context) {
  const { uuid } = await context.params;
  return validTaskId(uuid) ? forwardTasks(request, `/${uuid}`) : taskError("TASK_NOT_FOUND", "Задание не найдено", 404);
}
export const GET = forward;
export const PATCH = forward;
export const DELETE = forward;
