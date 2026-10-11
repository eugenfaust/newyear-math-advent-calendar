import "server-only";

import { apiRequest } from "@/lib/api";
import type { CalendarDay, UserCalendar } from "@/lib/calendar";
import { headers } from "next/headers";
import { getToken } from "next-auth/jwt";
import { redirect } from "next/navigation";

async function accessToken(): Promise<string> {
  const requestHeaders = await headers();
  const token = await getToken({ req: { headers: requestHeaders }, secret: process.env.AUTH_SECRET });
  if (!token || typeof token.accessToken !== "string" || typeof token.expiresAt !== "string" || Date.parse(token.expiresAt) <= Date.now()) {
    redirect("/login");
  }
  return token.accessToken;
}

export async function getCalendar(): Promise<UserCalendar> {
  return apiRequest<UserCalendar>("/api/v1/calendar", await accessToken());
}

export async function getCalendarDay(day: number, year: number): Promise<CalendarDay> {
  return apiRequest<CalendarDay>(`/api/v1/calendar/days/${day}?year=${year}`, await accessToken());
}
