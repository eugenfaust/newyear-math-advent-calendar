"use client";
/* eslint-disable @next/next/no-img-element -- Ornament SVGs are served as image resources. */

import { Button } from "@astryxdesign/core/Button";
import { TextInput } from "@astryxdesign/core/TextInput";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { readOrnamentResponse, type OrnamentList } from "./types";
import styles from "./ornaments.module.scss";

export function OrnamentsList() {
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const ornaments = useQuery({
    queryKey: ["admin-ornaments", page, query, status],
    queryFn: async ({ signal }) => {
      const params = new URLSearchParams({ page: String(page), perPage: "20", search: query });
      if (status !== "all") params.set("isActive", status);
      return readOrnamentResponse<OrnamentList>(await fetch(`/api/admin/ornaments?${params}`, { signal }));
    },
  });
  const data = ornaments.data;
  return <section className={styles.section}>
    <header className={styles.heading}><div><span className={styles.eyebrow}>Коллекция декабря</span><h1>Игрушки</h1><p>Создавайте украшения, которые появятся на личных ёлках.</p></div><Button label="Добавить игрушку" href="/admin/ornaments/create" variant="primary" /></header>
    <form className={styles.filters} onSubmit={(event) => { event.preventDefault(); setPage(1); setQuery(search.trim()); }}>
      <TextInput label="Поиск по названию" value={search} onChange={setSearch} width="100%" />
      <label className={styles.field}>Статус<select value={status} onChange={(event) => { setPage(1); setStatus(event.target.value); }}><option value="all">Все</option><option value="true">Активные</option><option value="false">Отключённые</option></select></label>
      <Button label="Найти" type="submit" variant="secondary" />
    </form>
    {ornaments.isPending ? <p role="status">Загрузка игрушек…</p> : ornaments.isError ? <div role="alert" className={styles.errorBox}>Не удалось загрузить игрушки. <button type="button" onClick={() => ornaments.refetch()}>Повторить</button></div> : data?.items.length ? <>
      <div className={styles.gallery}>
        {data.items.map((ornament) => <Link href={`/admin/ornaments/${ornament.uuid}`} className={styles.tile} key={ornament.uuid}>
          <span className={styles.hanger} aria-hidden="true" />
          <span className={styles.tileImage}>{ornament.imageUrl ? <img src={ornament.imageUrl} alt="" /> : <span className={styles.placeholder} aria-hidden="true">✧</span>}</span>
          <span className={styles.tileName}>{ornament.name}</span><span className={styles.tileStatus}>{ornament.isActive ? "Активна" : "Отключена"}</span>
        </Link>)}
      </div>
      <div className={styles.pagination}><span>Показано {data.items.length} из {data.pagination.total}</span><div><Button label="Назад" type="button" variant="secondary" isDisabled={page <= 1} onClick={() => setPage(page - 1)} /><span>Страница {page} из {data.pagination.totalPages}</span><Button label="Далее" type="button" variant="secondary" isDisabled={page >= data.pagination.totalPages} onClick={() => setPage(page + 1)} /></div></div>
    </> : <p className={styles.empty}>Игрушек с такими параметрами нет. Измените фильтр или добавьте первую игрушку.</p>}
  </section>;
}
