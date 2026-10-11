import { TaskContent, TaskMarkdown } from "@/components/task-content";
import { ApiError } from "@/lib/api";
import { getCalendar, getCalendarDay } from "@/lib/calendar-server";
import type { CalendarDaySummary } from "@/lib/calendar";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import styles from "../../cabinet.module.scss";
import { CompletionControl } from "./completion-control";

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
  if (summary.status === "expired") return statePage(day, summary, "Срок выполнения истёк", "Задание осталось невыполненным. Вернитесь к календарю и выберите доступный день.");

  let detail;
  try { detail = await getCalendarDay(day, calendar.year); }
  catch (error) {
    if (error instanceof ApiError) {
      if (error.code === "UNAUTHENTICATED") redirect("/login");
      if (error.code === "ADVENT_DAY_LOCKED") return statePage(day, summary, "Этот день ещё закрыт", `Задание откроется ${formatLongDate(summary.availableOn)}.`);
      if (error.code === "ADVENT_DAY_EXPIRED") return statePage(day, summary, "Срок выполнения истёк", "Задание осталось невыполненным. Вернитесь к календарю и выберите доступный день.");
      if (error.code === "ADVENT_CALENDAR_NOT_FOUND") return statePage(day, summary, "Календарь не найден", "Вернитесь к календарю и попробуйте ещё раз.");
      if (error.code === "ADVENT_DAY_NOT_FOUND") notFound();
    }
    throw error;
  }

  return <section className={styles.dayPage}>
    <Link href="/cabinet" className={styles.backLink}>← К календарю</Link>
    <div className={styles.dayHeader}><p className={styles.eyebrow}>День {day} · {formatLongDate(detail.availableOn)}</p><h1>{detail.status === "completed" ? "Задание выполнено" : "Задание дня"}</h1></div>
    <TaskContent title={detail.task.title} content={detail.task.content} imageUrl={detail.task.imageUrl} />
    <CompletionControl day={day} year={calendar.year} completed={detail.status === "completed"} answerType={detail.task.answerType} choices={detail.task.choices ?? []} />
    {detail.status === "completed" && <section className={styles.answerSection} aria-label="Ответ и решение">
      {detail.task.correctAnswer || detail.task.solution ? <details className={styles.answerDisclosure}>
        <summary>{detail.task.correctAnswer && detail.task.solution ? "Показать ответ и решение" : detail.task.correctAnswer ? "Показать ответ" : "Показать решение"}</summary>
        <div className={styles.answerContent}>
          {detail.task.correctAnswer && <div><h2>Правильный ответ</h2><TaskMarkdown content={detail.task.correctAnswer} /></div>}
          {detail.task.solution && <div><h2>Решение</h2><TaskMarkdown content={detail.task.solution} /></div>}
        </div>
      </details> : <p className={styles.answerEmpty}>Ответ и решение для этого задания пока не добавлены.</p>}
    </section>}
  </section>;
}
