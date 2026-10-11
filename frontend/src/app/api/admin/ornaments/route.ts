import type { NextRequest } from "next/server";
import { forwardOrnaments } from "./shared";

export const GET = (request: NextRequest) => forwardOrnaments(request);
export const POST = (request: NextRequest) => forwardOrnaments(request);
