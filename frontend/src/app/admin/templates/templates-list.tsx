"use client";

import { Button } from "@astryxdesign/core/Button";
import { TextInput } from "@astryxdesign/core/TextInput";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { readTemplateResponse, type TemplateList } from "./types";
import styles from "./templates.module.scss";

export function TemplatesList() {
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const templates = useQuery({
    queryKey: ["admin-templates", page, query, status],
    queryFn: async ({ signal }) => {
      const params = new URLSearchParams({ page: String(page), perPage: "20", search: query });
      if (status !== "all") params.set("isActive", status);
      return readTemplateResponse<TemplateList>(await fetch(`/api/admin/templates?${params}`, { signal }));
    },
  });
  const data = templates.data;
  return <section className={styles.section}>
    <header className={styles.heading}><div><span className={styles.eyebrow}>План декабря</span><h1>Шаблоны календаря</h1><p>Соберите задания по дням, затем используйте готовый шаблон для назначения.</p></div><Button label="Создать шаблон" href="/admin/templates/create" variant="primary" /></header>
    <form className={styles.filters} onSubmit={(event) => { event.preventDefault(); setPage(1); setQuery(search.trim()); }}>
      <TextInput label="Поиск по названию" value={search} onChange={setSearch} width="100%" />
      <label className={styles.field}>Статус<select value={status} onChange={(event) => { setPage(1); setStatus(event.target.value); }}><option value="all">Все</option><option value="true">Активные</option><option value="false">Отключённые</option></select></label>
      <Button label="Найти" type="submit" variant="secondary" />
    </form>
    {templates.isPending ? <p role="status">Загрузка шаблонов…</p> : templates.isError ? <div role="alert" className={styles.errorBox}>Не удалось загрузить шаблоны. <button type="button" onClick={() => templates.refetch()}>Повторить</button></div> : data?.items.length ? <>
      <div className={styles.list}>{data.items.map((template) => <Link className={styles.listItem} href={`/admin/templates/${template.uuid}`} key={template.uuid}>
        <span className={styles.listTitle}>{template.name}</span>
        <span className={styles.listMeta}>{template.year ? `${template.year} год · ` : "Любой год · "}{template.filledDays} из 31 дня · {template.isActive ? "Активен" : "Отключён"}</span>
        <span className={styles.listProgress} aria-label={`Заполнено ${template.filledDays} из 31 дня`}><span style={{ width: `${template.filledDays / 31 * 100}%` }} /></span>
        <span aria-hidden className={styles.arrow}>↗</span>
      </Link>)}</div>
      <div className={styles.pagination}><span>Показано {data.items.length} из {data.pagination.total}</span><div><Button label="Назад" type="button" variant="secondary" isDisabled={page <= 1} onClick={() => setPage(page - 1)} /><span>Страница {page} из {data.pagination.totalPages}</span><Button label="Далее" type="button" variant="secondary" isDisabled={page >= data.pagination.totalPages} onClick={() => setPage(page + 1)} /></div></div>
    </> : <p className={styles.empty}>Шаблонов с такими параметрами нет. Измените фильтр или создайте первый шаблон.</p>}
  </section>;
}
