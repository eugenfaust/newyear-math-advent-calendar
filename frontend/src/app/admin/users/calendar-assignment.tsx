"use client";

import { Button } from "@astryxdesign/core/Button";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { readTemplateResponse, type TemplateList } from "../templates/types";
import { readResponse, type ApiProblem, type AssignedCalendar, type AssignedCalendarList } from "./types";
import styles from "./users.module.scss";
import { CalendarProgress } from "./calendar-progress";

export function CalendarAssignment({ uuid }: { uuid: string }) {
  const queryClient = useQueryClient();
  const [year, setYear] = useState(String(new Date().getFullYear()));
  const [templateUuid, setTemplateUuid] = useState("");
  const [templatePage, setTemplatePage] = useState(1);
  const [calendarPage, setCalendarPage] = useState(1);
  const [selectedYear, setSelectedYear] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const calendars = useQuery({
    queryKey: ["user-calendars", uuid, calendarPage],
    queryFn: async ({ signal }) => readResponse<AssignedCalendarList>(await fetch(`/api/admin/users/${uuid}/calendars?page=${calendarPage}&perPage=20`, { signal })),
  });
  const templates = useQuery({
    queryKey: ["assignable-templates", templatePage],
    queryFn: async ({ signal }) => readTemplateResponse<TemplateList>(await fetch(`/api/admin/templates?page=${templatePage}&perPage=100&isActive=true`, { signal })),
  });
  const choices = templates.data?.items.filter((template) => template.isComplete && (template.year === null || template.year === Number(year))) ?? [];

  async function assign(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setNotice("");
    const parsedYear = Number(year);
    if (!Number.isInteger(parsedYear) || parsedYear < 1 || parsedYear > 9999) { setError("Укажите год от 1 до 9999."); return; }
    if (!templateUuid) { setError("Выберите заполненный шаблон."); return; }
    setSaving(true);
    try {
      const result = await readResponse<AssignedCalendar>(await fetch(`/api/admin/users/${uuid}/calendars`, {
        method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ templateUuid, year: parsedYear }),
      }));
      setNotice(`Календарь на ${result.year} год назначен.`);
      setTemplateUuid("");
      setCalendarPage(1);
      setSelectedYear(result.year);
      await queryClient.invalidateQueries({ queryKey: ["user-calendars", uuid] });
    } catch (problem) {
      const api = problem as ApiProblem;
      setError(api.code === "CALENDAR_ALREADY_ASSIGNED" ? "На этот год календарь уже назначен." : api.message ?? "Не удалось назначить календарь.");
    } finally { setSaving(false); }
  }

  return <section className={styles.calendarPanel} aria-labelledby="calendar-heading">
    <div className={styles.calendarHeading}><div><span className={styles.calendarEyebrow}>Декабрь по годам</span><h2 id="calendar-heading">Календари пользователя</h2></div><span className={styles.calendarCount}>31 день в каждом календаре</span></div>
    {calendars.isPending ? <p role="status">Загрузка календарей…</p> : calendars.isError ? <p role="alert" className={styles.error}>Не удалось загрузить календари. <button type="button" onClick={() => calendars.refetch()}>Повторить</button></p> : calendars.data?.items.length ? <>
      <ul className={styles.calendarList}>{calendars.data.items.map((calendar) => <li key={calendar.uuid}><strong>{calendar.year}</strong><span>{calendar.template.name}</span><button type="button" className={styles.progressToggle} aria-expanded={selectedYear === calendar.year} onClick={() => setSelectedYear(selectedYear === calendar.year ? null : calendar.year)}>{selectedYear === calendar.year ? "Скрыть прогресс" : "Прогресс и ёлка"}</button></li>)}</ul>
      {selectedYear !== null && <CalendarProgress key={selectedYear} uuid={uuid} year={selectedYear} />}
      {calendars.data.pagination.totalPages > 1 && <div className={styles.calendarPager}><Button label="Назад" type="button" variant="secondary" isDisabled={calendarPage <= 1} onClick={() => setCalendarPage(calendarPage - 1)} /><span>Страница {calendarPage} из {calendars.data.pagination.totalPages}</span><Button label="Далее" type="button" variant="secondary" isDisabled={calendarPage >= calendars.data.pagination.totalPages} onClick={() => setCalendarPage(calendarPage + 1)} /></div>}
    </> : <p>Календарей пока нет. Выберите год и готовый шаблон.</p>}
    <form className={styles.calendarForm} onSubmit={assign}>
      <label className={styles.field}>Год<input type="number" min="1" max="9999" step="1" required value={year} onChange={(event) => { setYear(event.target.value); setTemplateUuid(""); }} /></label>
      <label className={styles.field}>Готовый шаблон<select required value={templateUuid} onChange={(event) => setTemplateUuid(event.target.value)} disabled={templates.isPending || templates.isError}>
        <option value="">Выберите шаблон</option>{choices.map((template) => <option value={template.uuid} key={template.uuid}>{template.name}</option>)}
      </select></label>
      <Button label="Назначить календарь" type="submit" variant="primary" isDisabled={saving || templates.isPending || templates.isError} isLoading={saving} />
    </form>
    {templates.isError && <p role="alert" className={styles.error}>Не удалось загрузить шаблоны. <button type="button" onClick={() => templates.refetch()}>Повторить</button></p>}
    {!templates.isPending && !templates.isError && choices.length === 0 && <p>Для этого года на странице нет активных заполненных шаблонов.</p>}
    {templates.data && templates.data.pagination.totalPages > 1 && <div className={styles.calendarPager}><span>Шаблоны: страница {templatePage} из {templates.data.pagination.totalPages}</span><Button label="Предыдущие" type="button" variant="secondary" isDisabled={templatePage <= 1} onClick={() => { setTemplatePage(templatePage - 1); setTemplateUuid(""); }} /><Button label="Следующие" type="button" variant="secondary" isDisabled={templatePage >= templates.data.pagination.totalPages} onClick={() => { setTemplatePage(templatePage + 1); setTemplateUuid(""); }} /></div>}
    {error && <p role="alert" className={styles.error}>{error}</p>}
    {notice && <p role="status" className={styles.success}>{notice}</p>}
  </section>;
}
