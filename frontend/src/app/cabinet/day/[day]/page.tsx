import { TaskContent } from "@/components/task-content";
import { ApiError } from "@/lib/api";
import { getCalendar, getCalendarDay } from "@/lib/calendar-server";
import type { CalendarDaySummary } from "@/lib/calendar";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import styles from "../../cabinet.module.scss";

export const dynamic = "force-dynamic";

type Props = { params: Promise<{ day: string }> };

function formatLongDate(date: string): string {
  const [year, month, day] = date.split("-").map(Number);
  return new Intl.DateTimeFormat("ru-RU", { day: "numeric", month: "long", year: "numeric", timeZone: "UTC" }).format(new Date(Date.UTC(year, month - 1, day)));
}

function statePage(day: number, summary: CalendarDaySummary, title: string, message: string) {
  return <section className={styles.dayPage}>
    <Link href="/cabinet" className={styles.backLink}>← К календарю</Link>
    <div className={styles.dayHeader}><p className={styles.eyebrow}>День {day} · {formatLongDate(summary.availableOn)}</p><h1>{title}</h1><p>{message}</p></div>
  </section>;
}

export default async function DayPage({ params }: Props) {
  const { day: rawDay } = await params;
  if (!/^(?:[1-9]|[12][0-9]|3[01])$/.test(rawDay)) notFound();
  const day = Number(rawDay);

  let calendar;
  try { calendar = await getCalendar(); }
  catch (error) {
    if (error instanceof ApiError) {
      if (error.code === "UNAUTHENTICATED") redirect("/login");
      if (error.code === "ADVENT_CALENDAR_NOT_FOUND") return <section className={styles.message}><h1>Календарь ещё не назначен</h1><p>Задания появятся здесь после назначения календаря.</p><Link href="/cabinet" className={styles.backLink}>К календарю</Link></section>;
    }
    throw error;
  }

  const summary = calendar.days.find((item) => item.day === day);
  if (!summary) notFound();
  if (summary.status === "locked") return statePage(day, summary, "Этот день ещё закрыт", `Задание откроется ${formatLongDate(summary.availableOn)}.`);

  let detail;
  try { detail = await getCalendarDay(day, calendar.year); }
  catch (error) {
    if (error instanceof ApiError) {
      if (error.code === "UNAUTHENTICATED") redirect("/login");
      if (error.code === "ADVENT_DAY_LOCKED") return statePage(day, summary, "Этот день ещё закрыт", `Задание откроется ${formatLongDate(summary.availableOn)}.`);
      if (error.code === "ADVENT_CALENDAR_NOT_FOUND") return statePage(day, summary, "Календарь не найден", "Вернитесь к календарю и попробуйте ещё раз.");
      if (error.code === "ADVENT_DAY_NOT_FOUND") notFound();
    }
    throw error;
  }

  return <section className={styles.dayPage}>
    <Link href="/cabinet" className={styles.backLink}>← К календарю</Link>
    <div className={styles.dayHeader}><p className={styles.eyebrow}>День {day} · {formatLongDate(detail.availableOn)}</p><h1>{detail.status === "completed" ? "Задание выполнено" : "Задание дня"}</h1></div>
    <TaskContent title={detail.task.title} content={detail.task.content} imageUrl={detail.task.imageUrl} />
    {detail.status === "completed" && <p className={styles.completedNote}>Вы выполнили это задание.</p>}
  </section>;
}
