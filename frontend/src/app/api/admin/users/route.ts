import { forwardUsers } from "./shared";
import type { NextRequest } from "next/server";

export const GET = (request: NextRequest) => forwardUsers(request);
export const POST = (request: NextRequest) => forwardUsers(request);
