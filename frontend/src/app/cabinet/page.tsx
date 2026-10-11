import { auth } from "@/auth";
import { ApiError } from "@/lib/api";
import { getCalendar } from "@/lib/calendar-server";
import Link from "next/link";
import { redirect } from "next/navigation";
import styles from "./cabinet.module.scss";

export const dynamic = "force-dynamic";

const weekdays = ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"];

function calendarError(error: unknown) {
  if (error instanceof ApiError) {
    if (error.code === "UNAUTHENTICATED") redirect("/login");
    if (error.code === "ADVENT_CALENDAR_NOT_FOUND") return <section className={styles.message} role="status"><span className={styles.eyebrow}>Календарь</span><h1>Календарь ещё не назначен</h1><p>Когда организатор назначит вам календарь на этот год, здесь появятся задания.</p></section>;
    if (error.code === "ACCESS_DENIED") return <section className={styles.message} role="alert"><h1>Нет доступа к календарю</h1><p>Обновите страницу или войдите снова.</p></section>;
  }
  throw error;
}

export default async function CabinetPage() {
  const session = await auth();
  if (!session?.user?.uuid) redirect("/login");

  let calendar;
  try { calendar = await getCalendar(); }
  catch (error) { return calendarError(error); }

  const firstWeekday = (new Date(`${calendar.year}-12-01T00:00:00Z`).getUTCDay() + 6) % 7;

  return <section className={styles.cabinet} aria-labelledby="cabinet-title">
    <div className={styles.intro}>
      <div><p className={styles.eyebrow}>Математический декабрь · {calendar.year}</p><h1 id="cabinet-title">Ваш календарь, {session.user.displayName}</h1><p className={styles.lede}>Каждый день — новая задача. К пропущенным дням можно вернуться в любое время.</p></div>
      <Link className={styles.treeLink} href="/cabinet/tree">Перейти к ёлке <span aria-hidden="true">↗</span></Link>
    </div>

    <div className={styles.overview}>
      <div><span className={styles.statNumber}>{calendar.completedDays}<span> / {calendar.totalDays}</span></span><span className={styles.statLabel}>заданий выполнено</span></div>
      <div><span className={styles.statNumber}>{calendar.availableDays}</span><span className={styles.statLabel}>дней открыто</span></div>
      <div className={styles.progressBlock}><div className={styles.progressText}><span>Прогресс календаря</span><span>{calendar.completedDays} из {calendar.totalDays}</span></div><progress value={calendar.completedDays} max={calendar.totalDays} aria-label="Прогресс календаря" /></div>
    </div>

    <div className={styles.gridHeading}><div><p className={styles.eyebrow}>01—31 декабря</p><h2>Выберите день</h2></div><div className={styles.legend} aria-label="Обозначения"><span><i className={styles.availableDot} />Доступен</span><span><i className={styles.completedDot} />Выполнен</span><span><i className={styles.lockedDot} />Закрыт</span></div></div>
    <div className={styles.calendarGrid} aria-label={`Календарь на декабрь ${calendar.year} года`}>
      {weekdays.map((weekday) => <span className={styles.weekday} key={weekday}>{weekday}</span>)}
      {Array.from({ length: firstWeekday }, (_, index) => <span className={styles.spacer} key={`spacer-${index}`} aria-hidden="true" />)}
      {calendar.days.map((day) => {
        const label = `${day.day} декабря: ${day.status === "completed" ? "выполнено" : day.status === "available" ? "доступно" : `откроется ${formatDate(day.availableOn)}`}`;
        const content = <><span className={styles.dayNumber}>{day.day}</span><span className={styles.dayState}>{day.status === "completed" ? "Готово" : day.status === "available" ? "Открыть" : "Закрыто"}</span><span className={styles.dayMark} aria-hidden="true">{day.status === "completed" ? "✓" : day.status === "locked" ? "·" : "↗"}</span></>;
        return day.status === "locked"
          ? <div key={day.day} className={`${styles.day} ${styles.locked}`} aria-label={label} title={label}>{content}</div>
          : <Link key={day.day} className={`${styles.day} ${day.status === "completed" ? styles.completed : styles.available}`} href={`/cabinet/day/${day.day}`} aria-label={label} prefetch={false}>{content}</Link>;
      })}
    </div>
  </section>;
}

function formatDate(date: string): string {
  const [, month, day] = date.split("-");
  return `${Number(day)}.${month}`;
}
