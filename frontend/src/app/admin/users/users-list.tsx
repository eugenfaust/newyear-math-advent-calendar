"use client";

import { Button } from "@astryxdesign/core/Button";
import { TextInput } from "@astryxdesign/core/TextInput";
import { useEffect, useState } from "react";
import Link from "next/link";
import { readResponse, type ManagedUser, type UserList } from "./types";
import styles from "./users.module.scss";

export function UsersList() {
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState<UserList | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const abort = new AbortController();
    setLoading(true);
    setError("");
    fetch(`/api/admin/users?page=${page}&perPage=20&search=${encodeURIComponent(query)}`, { signal: abort.signal })
      .then(readResponse<UserList>)
      .then(setData)
      .catch((problem) => { if (!abort.signal.aborted) setError(problem?.message ?? "Не удалось загрузить пользователей"); })
      .finally(() => { if (!abort.signal.aborted) setLoading(false); });
    return () => abort.abort();
  }, [page, query]);

  return <section className={styles.section}>
    <div className={styles.heading}><div><h1>Пользователи</h1><p>Управление доступом к адвент-календарю</p></div><Button label="Добавить пользователя" href="/admin/users/new" variant="primary" /></div>
    <form className={styles.search} onSubmit={(event) => { event.preventDefault(); setPage(1); setQuery(search.trim()); }}>
      <TextInput label="Поиск по имени, логину или email" value={search} onChange={setSearch} width="100%" />
      <Button label="Найти" type="submit" variant="secondary" />
    </form>
    {error && <p role="alert" className={styles.error}>{error}</p>}
    {loading ? <p>Загрузка…</p> : data?.items.length ? <>
      <div className={styles.tableWrap}><table className={styles.table}><thead><tr><th>Имя</th><th>Логин</th><th>Email</th><th>Роль</th><th>Статус</th><th></th></tr></thead><tbody>
        {data.items.map((user: ManagedUser) => <tr key={user.uuid}><td><Link href={`/admin/users/${user.uuid}`}>{user.name}</Link></td><td>{user.login}</td><td>{user.email ?? "—"}</td><td>{user.role === "ROLE_ADMIN" ? "Администратор" : "Пользователь"}</td><td>{user.isActive ? "Активен" : "Отключён"}</td><td><Link href={`/admin/users/${user.uuid}`}>Открыть</Link></td></tr>)}
      </tbody></table></div>
      <div className={styles.pagination}><span>Показано {data.items.length} из {data.pagination.total}</span><div><Button label="Назад" type="button" variant="secondary" isDisabled={page <= 1} onClick={() => setPage(page - 1)} /><span>Страница {page} из {data.pagination.totalPages}</span><Button label="Далее" type="button" variant="secondary" isDisabled={page >= data.pagination.totalPages} onClick={() => setPage(page + 1)} /></div></div>
    </> : !error && <p>Пользователи не найдены.</p>}
  </section>;
}
