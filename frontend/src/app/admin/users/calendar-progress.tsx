"use client";

import { useQuery } from "@tanstack/react-query";
import Image from "next/image";
import { TREE_SLOTS } from "@/lib/tree-slots";
import type { UserCalendar } from "@/lib/calendar";
import type { UserTree } from "@/lib/tree";
import { readResponse, type AssignedCalendar } from "./types";
import styles from "./users.module.scss";

type AdminProgress = { calendar: AssignedCalendar; progress: UserCalendar; tree: UserTree };
const labels = { locked: "Ещё закрыт", available: "Доступен", expired: "Срок истёк", completed: "Выполнен" } as const;

export function CalendarProgress({ uuid, year }: { uuid: string; year: number }) {
  const { data, isPending, isError, refetch } = useQuery({
    queryKey: ["admin-calendar-progress", uuid, year],
    queryFn: async ({ signal }) => readResponse<AdminProgress>(await fetch(`/api/admin/users/${uuid}/calendars/${year}/progress`, { signal, cache: "no-store" })),
  });
  if (isPending) return <p role="status">Загрузка прогресса…</p>;
  if (isError || !data) return <p role="alert" className={styles.error}>Не удалось загрузить прогресс. <button type="button" onClick={() => void refetch()}>Повторить</button></p>;

  const ornaments = new Map(data.tree.ornaments.filter((item) => item.treeSlot !== null).map((item) => [item.treeSlot, item]));
  return <section className={styles.progressPanel} aria-labelledby="progress-heading">
    <div className={styles.progressHeading}>
      <div><span className={styles.calendarEyebrow}>Назначенный календарь · {year}</span><h3 id="progress-heading">{data.calendar.template.name}</h3></div>
      <strong>{data.progress.completedDays} / 31 выполнено</strong>
    </div>
    <div className={styles.progressBody}>
      <div>
        <p className={styles.progressCaption}>Состояние дней</p>
        <ol className={styles.progressDays}>{data.progress.days.map((day) => <li key={day.day} className={styles[day.status]} title={`${day.day} декабря: ${labels[day.status]}`} aria-label={`${day.day} декабря: ${labels[day.status]}`}><span>{day.day}</span><small aria-hidden="true">{day.status === "completed" ? "✓" : day.status === "available" ? "○" : day.status === "expired" ? "×" : "·"}</small></li>)}</ol>
        <p className={styles.progressLegend}>✓ Выполнен · ○ Доступен · × Срок истёк · · Ещё закрыт</p>
      </div>
      <div className={styles.adminTree}>
        <div className={styles.adminTreeHeading}><p className={styles.progressCaption}>Ёлка пользователя</p><span>{ornaments.size} из 31 мест занято</span></div>
        <svg viewBox="0 0 440 710" role="img" aria-label={`Ёлка: ${ornaments.size} игрушек размещено, ${data.tree.ornaments.length - ornaments.size} без места`}>
          <path d="M203 582h34v85h-34z" fill="#855f62" />
          <path d="M220 58 146 198h28L105 315h31L67 438h35L44 647h352l-58-209h35l-69-123h31l-69-117h28Z" fill="#165d68" stroke="#9ac7bf" strokeWidth="3" />
          <path d="m220 33 8 17 19 3-14 13 3 19-16-9-16 9 3-19-14-13 19-3Z" fill={data.tree.completed ? "#f8cb73" : "#b8cbd3"} />
          {TREE_SLOTS.map((slot) => { const item = ornaments.get(slot.id); return <g key={slot.id}><circle cx={slot.x} cy={slot.y} r="21" fill={item ? "#eff7f6" : "#27717a"} stroke={item ? "#f5d898" : "#76adb0"} strokeWidth="2" />{item ? <image href={item.imageUrl} x={slot.x - 19} y={slot.y - 19} width="38" height="38"><title>{item.name}, место {slot.id}</title></image> : <text x={slot.x} y={slot.y + 4} textAnchor="middle" fill="#e7f3f0" fontSize="11">{slot.id}</text>}</g>; })}
        </svg>
        {data.tree.ornaments.some((item) => item.treeSlot === null) && <div className={styles.unplacedAdmin}><span>Без места</span><ul>{data.tree.ornaments.filter((item) => item.treeSlot === null).map((item) => <li key={item.uuid}><Image src={item.imageUrl} alt="" width={30} height={30} unoptimized />{item.name}</li>)}</ul></div>}
      </div>
    </div>
  </section>;
}
