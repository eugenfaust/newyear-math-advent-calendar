"use client";

import { Button } from "@astryxdesign/core/Button";
import { TextInput } from "@astryxdesign/core/TextInput";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { readTaskResponse, type TaskList } from "./types";
import styles from "./tasks.module.scss";

export function TasksList() {
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const tasks = useQuery({
    queryKey: ["admin-tasks", page, query, status],
    queryFn: async ({ signal }) => {
      const params = new URLSearchParams({ page: String(page), perPage: "20", search: query });
      if (status !== "all") params.set("isActive", status);
      return readTaskResponse<TaskList>(await fetch(`/api/admin/tasks?${params}`, { signal }));
    },
  });
  const data = tasks.data;
  return <section className={styles.section}>
    <header className={styles.heading}><div><span className={styles.eyebrow}>Библиотека · Математика</span><h1>Задания</h1><p>Создавайте задачи для декабрьского календаря.</p></div><Button label="Создать задание" href="/admin/tasks/create" variant="primary" /></header>
    <form className={styles.filters} onSubmit={(event) => { event.preventDefault(); setPage(1); setQuery(search.trim()); }}>
      <TextInput label="Поиск по названию и тексту" value={search} onChange={setSearch} width="100%" />
      <label className={styles.field}>Статус<select value={status} onChange={(event) => { setPage(1); setStatus(event.target.value); }}><option value="all">Все</option><option value="true">Активные</option><option value="false">Отключённые</option></select></label>
      <Button label="Найти" type="submit" variant="secondary" />
    </form>
    {tasks.isPending ? <p role="status">Загрузка заданий…</p> : tasks.isError ? <div role="alert" className={styles.errorBox}>Не удалось загрузить задания. <button type="button" onClick={() => tasks.refetch()}>Повторить</button></div> : data?.items.length ? <>
      <div className={styles.list}>
        {data.items.map((task) => <Link className={styles.listItem} href={`/admin/tasks/${task.uuid}`} key={task.uuid}>
          <span className={styles.listTitle}>{task.title}</span><span className={styles.listMeta}>{task.imageUrl ? "С иллюстрацией · " : ""}{task.isActive ? "Активно" : "Отключено"}</span><span aria-hidden className={styles.arrow}>↗</span>
        </Link>)}
      </div>
      <div className={styles.pagination}><span>Показано {data.items.length} из {data.pagination.total}</span><div><Button label="Назад" type="button" variant="secondary" isDisabled={page <= 1} onClick={() => setPage(page - 1)} /><span>Страница {page} из {data.pagination.totalPages}</span><Button label="Далее" type="button" variant="secondary" isDisabled={page >= data.pagination.totalPages} onClick={() => setPage(page + 1)} /></div></div>
    </> : <p className={styles.empty}>Заданий с такими параметрами нет. Измените фильтр или создайте новое задание.</p>}
  </section>;
}
