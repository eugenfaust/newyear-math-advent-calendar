"use client";

import { Button } from "@astryxdesign/core/Button";
import { TextInput } from "@astryxdesign/core/TextInput";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { ManagedTask, TaskList } from "../tasks/types";
import { readTaskResponse } from "../tasks/types";
import { TaskContent } from "@/components/task-content";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { readTemplateResponse, type ApiProblem, type TemplateDetail } from "./types";
import styles from "./templates.module.scss";

const days = Array.from({ length: 31 }, (_, index) => index + 1);

export function TemplateEditor({ uuid }: { uuid?: string }) {
  const router = useRouter();
  const client = useQueryClient();
  const [currentId, setCurrentId] = useState(uuid);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [year, setYear] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [selectedDay, setSelectedDay] = useState<number | null>(null);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [taskPage, setTaskPage] = useState(1);
  const [previewId, setPreviewId] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [nameError, setNameError] = useState("");
  const [notice, setNotice] = useState("");
  const initializedId = useRef<string | null>(null);
  const pickerRef = useRef<HTMLDialogElement | null>(null);
  const template = useQuery({
    queryKey: ["admin-template", uuid], enabled: Boolean(uuid),
    queryFn: async ({ signal }) => readTemplateResponse<TemplateDetail>(await fetch(`/api/admin/templates/${uuid}`, { signal })),
  });
  const tasks = useQuery({
    queryKey: ["admin-template-tasks", taskPage, query], enabled: selectedDay !== null,
    queryFn: async ({ signal }) => {
      const params = new URLSearchParams({ page: String(taskPage), perPage: "20", search: query, isActive: "true" });
      return readTaskResponse<TaskList>(await fetch(`/api/admin/tasks?${params}`, { signal }));
    },
  });
  const preview = useQuery({
    queryKey: ["admin-task", previewId], enabled: Boolean(previewId),
    queryFn: async ({ signal }) => readTaskResponse<ManagedTask>(await fetch(`/api/admin/tasks/${previewId}`, { signal })),
  });
  useEffect(() => {
    if (!template.data || initializedId.current === template.data.uuid) return;
    initializedId.current = template.data.uuid;
    setName(template.data.name);
    setDescription(template.data.description ?? "");
    setYear(template.data.year === null ? "" : String(template.data.year));
    setIsActive(template.data.isActive);
  }, [template.data]);
  useEffect(() => {
    const picker = pickerRef.current;
    if (!picker) return;
    if (selectedDay !== null && !picker.open) picker.showModal();
    if (selectedDay === null && picker.open) picker.close();
  }, [selectedDay]);

  function showProblem(problem: ApiProblem, fallback: string) {
    setError(problem.message || fallback);
    setNameError(problem.details?.find((detail) => detail.field === "name")?.message ?? "");
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(""); setNameError(""); setNotice("");
    if (!name.trim() || name.trim().length > 150) { setNameError("Название: от 1 до 150 символов"); return; }
    if (description.length > 10000) { setError("Описание: максимум 10 000 символов"); return; }
    const numericYear = Number(year);
    if (year && (!/^\d{1,4}$/.test(year) || numericYear < 1)) { setError("Год должен быть числом от 1 до 9999"); return; }
    setBusy(true);
    try {
      const saved = await readTemplateResponse<TemplateDetail>(await fetch(currentId ? `/api/admin/templates/${currentId}` : "/api/admin/templates", {
        method: currentId ? "PATCH" : "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: name.trim(), description: description || null, year: year ? numericYear : null, isActive }),
      }));
      setCurrentId(saved.uuid);
      setName(saved.name); setDescription(saved.description ?? ""); setYear(saved.year === null ? "" : String(saved.year)); setIsActive(saved.isActive);
      initializedId.current = saved.uuid;
      client.setQueryData(["admin-template", saved.uuid], saved);
      await client.invalidateQueries({ queryKey: ["admin-templates"] });
      setNotice("Шаблон сохранён");
      if (!uuid) router.replace(`/admin/templates/${saved.uuid}`);
    } catch (problem) { showProblem(problem as ApiProblem, "Не удалось сохранить шаблон"); }
    finally { setBusy(false); }
  }

  async function setDayTask(day: number, taskUuid: string | null) {
    if (!currentId) return;
    setBusy(true); setError(""); setNotice("");
    try {
      const saved = await readTemplateResponse<TemplateDetail>(await fetch(`/api/admin/templates/${currentId}/days/${day}`, {
        method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ taskUuid }),
      }));
      client.setQueryData(["admin-template", currentId], saved);
      await client.invalidateQueries({ queryKey: ["admin-templates"] });
      setNotice(taskUuid ? `Задание для ${day} декабря выбрано` : `День ${day} декабря очищен`);
      setSelectedDay(null); setPreviewId(null);
    } catch (problem) { showProblem(problem as ApiProblem, "Не удалось изменить день"); }
    finally { setBusy(false); }
  }

  async function duplicate() {
    if (!currentId) return;
    setBusy(true); setError(""); setNotice("");
    try {
      const copied = await readTemplateResponse<TemplateDetail>(await fetch(`/api/admin/templates/${currentId}/duplicate`, {
        method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(data && data.name.length > 141 ? { name: `${data.name.slice(0, 140)}… (копия)` } : {}),
      }));
      await client.invalidateQueries({ queryKey: ["admin-templates"] });
      router.push(`/admin/templates/${copied.uuid}`);
    } catch (problem) { showProblem(problem as ApiProblem, "Не удалось скопировать шаблон"); }
    finally { setBusy(false); }
  }

  async function deactivate() {
    if (!currentId || !window.confirm("Отключить шаблон? Его дни и созданные из него календари сохранятся.")) return;
    setBusy(true); setError(""); setNotice("");
    try {
      await readTemplateResponse<void>(await fetch(`/api/admin/templates/${currentId}`, { method: "DELETE" }));
      client.setQueryData<TemplateDetail>(["admin-template", currentId], (old) => old ? { ...old, isActive: false } : old);
      await client.invalidateQueries({ queryKey: ["admin-templates"] });
      setIsActive(false); setNotice("Шаблон отключён");
    } catch (problem) { showProblem(problem as ApiProblem, "Не удалось отключить шаблон"); }
    finally { setBusy(false); }
  }

  const data = template.data;
  const selected = data?.days.find((item) => item.day === selectedDay);
  const inactiveDays = data?.days.filter((item) => item.task && !item.task.isActive).map((item) => item.day) ?? [];
  const missingDays = data?.days.filter((item) => !item.task).map((item) => item.day) ?? [];
  const ready = Boolean(data?.isActive && data.isComplete && inactiveDays.length === 0);
  return <section className={styles.section}>
    <Link href="/admin/templates" className={styles.back}>← К шаблонам</Link>
    <header className={styles.heading}><div><span className={styles.eyebrow}>Конструктор · 31 день</span><h1>{data?.name ?? (uuid ? "Шаблон" : "Новый шаблон")}</h1><p>Каждый день может содержать одно задание. Задание можно использовать несколько раз.</p></div>{currentId && <Button label="Создать копию" type="button" variant="secondary" onClick={duplicate} isDisabled={busy} />}</header>
    {uuid && template.isPending ? <p role="status">Загрузка шаблона…</p> : uuid && template.isError ? <div role="alert" className={styles.errorBox}>Не удалось загрузить шаблон. <button type="button" onClick={() => template.refetch()}>Повторить</button></div> : <>
      <form className={styles.details} onSubmit={save}>
        <div className={styles.detailsFields}><TextInput label="Название" value={name} onChange={setName} width="100%" isRequired status={nameError ? { type: "error", message: nameError } : undefined} /><label className={styles.field}>Год <span className={styles.hint}>Необязательно</span><input inputMode="numeric" value={year} onChange={(event) => setYear(event.target.value)} placeholder="Любой год" /></label></div>
        <label className={styles.field}>Описание <span className={styles.hint}>Необязательно</span><textarea rows={3} value={description} onChange={(event) => setDescription(event.target.value)} /></label>
        <label className={styles.check}><input type="checkbox" checked={isActive} onChange={(event) => setIsActive(event.target.checked)} />Активен для назначения</label>
        <div className={styles.actions}><Button label={currentId ? "Сохранить изменения" : "Создать шаблон"} type="submit" variant="primary" isLoading={busy} />{currentId && data?.isActive && <Button label="Отключить" type="button" variant="secondary" onClick={deactivate} isDisabled={busy} />}</div>
      </form>
      {error && selectedDay === null && <p role="alert" className={styles.errorBox}>{error}</p>}{notice && <p role="status" className={styles.success}>{notice}</p>}
      {data && <>
        <div className={styles.progressHeader}><div><span className={styles.eyebrow}>Ведомость декабря</span><h2>Задания по дням</h2></div><strong>{data.filledDays}<span> / 31</span></strong></div>
        <div className={styles.readiness} role="status"><strong>{ready ? "Готов к назначению" : "Пока не готов к назначению"}</strong><span>{!data.isActive ? "Шаблон отключён. " : ""}{missingDays.length ? `Пустые дни: ${missingDays.join(", ")}. ` : ""}{inactiveDays.length ? `Отключённые задания: дни ${inactiveDays.join(", ")}.` : ""}{ready ? "Все дни заполнены активными заданиями." : ""}</span></div>
        <div className={styles.dayGrid}>{days.map((day) => {
          const task = data.days.find((item) => item.day === day)?.task;
          return <button key={day} type="button" className={`${styles.dayCard} ${task ? styles.filled : styles.missing} ${selectedDay === day ? styles.selected : ""}`} onClick={() => { setSelectedDay(day); setPreviewId(null); setSearch(""); setQuery(""); setTaskPage(1); setError(""); }} aria-pressed={selectedDay === day}>
            <span className={styles.dayNumber}>{String(day).padStart(2, "0")}<small>дек</small></span><span className={styles.dayInfo}><strong>{task?.title ?? "Выбрать задание"}</strong><small>{task ? task.isActive ? "Задание выбрано" : "Задание отключено" : "День пуст"}</small></span><span className={styles.dayArrow} aria-hidden>↗</span>
          </button>;
        })}</div>
        <dialog ref={pickerRef} className={styles.picker} aria-label={selectedDay === null ? "Выбор задания" : `Задание для ${selectedDay} декабря`} onClose={() => { setSelectedDay(null); setPreviewId(null); }}>
          {selectedDay !== null && <>
          <div className={styles.pickerHeader}><div><span className={styles.eyebrow}>День {String(selectedDay).padStart(2, "0")}</span><h2>Выбрать задание</h2></div><button type="button" className={styles.textButton} onClick={() => { setSelectedDay(null); setPreviewId(null); }}>Закрыть</button></div>
          {error && <p role="alert" className={styles.errorBox}>{error}</p>}
          {selected?.task && <div className={styles.currentTask}><span>Сейчас: <strong>{selected.task.title}</strong>{!selected.task.isActive && " · отключено"}</span><div><Link href={`/admin/tasks/${selected.task.uuid}`} target="_blank" rel="noopener noreferrer">Посмотреть задание ↗</Link><button type="button" onClick={() => setDayTask(selectedDay, null)} disabled={busy}>Очистить день</button></div></div>}
          <form className={styles.filters} onSubmit={(event) => { event.preventDefault(); setTaskPage(1); setQuery(search.trim()); }}><TextInput label="Поиск задания" value={search} onChange={setSearch} width="100%" /><Button label="Найти" type="submit" variant="secondary" /></form>
          {tasks.isPending ? <p role="status">Загрузка заданий…</p> : tasks.isError ? <div role="alert" className={styles.errorBox}>Не удалось загрузить задания. <button type="button" onClick={() => tasks.refetch()}>Повторить</button></div> : tasks.data?.items.length ? <>
            <div className={styles.taskList}>{tasks.data.items.map((task) => <div className={styles.taskOption} key={task.uuid}><span>{task.title}</span><div><button type="button" onClick={() => setPreviewId(previewId === task.uuid ? null : task.uuid)} aria-expanded={previewId === task.uuid}>Просмотр</button><button type="button" onClick={() => setDayTask(selectedDay, task.uuid)} disabled={busy}>Выбрать</button></div></div>)}</div>
            <div className={styles.pagination}><span>Найдено {tasks.data.pagination.total}</span><div><Button label="Назад" type="button" variant="secondary" isDisabled={taskPage <= 1} onClick={() => setTaskPage(taskPage - 1)} /><span>Страница {taskPage} из {tasks.data.pagination.totalPages}</span><Button label="Далее" type="button" variant="secondary" isDisabled={taskPage >= tasks.data.pagination.totalPages} onClick={() => setTaskPage(taskPage + 1)} /></div></div>
          </> : <p className={styles.empty}>Активных заданий с таким названием нет. <Link href="/admin/tasks/create">Создать задание</Link></p>}
          {previewId && <div className={styles.preview}><div className={styles.pickerHeader}><h3>Предпросмотр задания</h3><Link href={`/admin/tasks/${previewId}`} target="_blank" rel="noopener noreferrer">Открыть редактор ↗</Link></div>{preview.isPending ? <p role="status">Загрузка предпросмотра…</p> : preview.isError ? <div role="alert">Не удалось открыть задание. <button type="button" onClick={() => preview.refetch()}>Повторить</button></div> : preview.data && <TaskContent title={preview.data.title} content={preview.data.content} imageUrl={preview.data.imageUrl} />}</div>}
          </>}
        </dialog>
      </>}
    </>}
  </section>;
}
