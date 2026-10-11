import type { NextRequest } from "next/server";
import { forwardTemplates } from "./shared";

export const GET = (request: NextRequest) => forwardTemplates(request);
export const POST = (request: NextRequest) => forwardTemplates(request);
