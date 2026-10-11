import { Button } from "@astryxdesign/core/Button";
import { Card } from "@astryxdesign/core/Card";
import { IconCalendar, IconLogout } from "@tabler/icons-react";
import { signOut } from "@/auth";
import Link from "next/link";
import type { ApiUser } from "@/lib/api";
import styles from "./app-shell.module.scss";

export function AppShell({ user, area, children }: { user: ApiUser; area: "cabinet" | "admin"; children: React.ReactNode }) {
  return <div className={styles.shell}>
    <header className={styles.header}>
      <div className={styles.brand}><IconCalendar size={25} aria-hidden /><span>Адвент-календарь</span></div>
      <div className={styles.user}><span>{user.displayName}</span><form action={async () => { "use server"; await signOut({ redirectTo: "/login" }); }}><Button label="Выйти" type="submit" icon={<IconLogout size={17} aria-hidden />} /></form></div>
    </header>
    <main className={styles.main}>
      <nav className={styles.nav} aria-label="Разделы"><Button label="Мой календарь" href="/cabinet" variant={area === "cabinet" ? "primary" : "secondary"} />{user.roles.includes("ROLE_ADMIN") && <Button label="Администрирование" href="/admin" variant={area === "admin" ? "primary" : "secondary"} />}</nav>
      {area === "admin" && <nav className={styles.subnav} aria-label="Администрирование"><Link href="/admin/users">Пользователи</Link><Link href="/admin/tasks">Задания</Link></nav>}
      <Card className={styles.content}>{children}</Card>
    </main>
  </div>;
}
