import type { NextRequest } from "next/server";
import { forwardTasks } from "./shared";

export const GET = (request: NextRequest) => forwardTasks(request);
export const POST = (request: NextRequest) => forwardTasks(request);
