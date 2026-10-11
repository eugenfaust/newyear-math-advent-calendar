import "server-only";

import { apiRequest } from "@/lib/api";
import { accessToken } from "@/lib/calendar-server";
import type { UserTree } from "@/lib/tree";

export async function getTree(year?: number): Promise<UserTree> {
  const path: `/api/v1/${string}` = year === undefined ? "/api/v1/tree" : `/api/v1/tree?year=${year}`;
  return apiRequest<UserTree>(path, await accessToken());
}
