import Link from "next/link";
import styles from "../cabinet.module.scss";

export default function TreePage() {
  return <section className={styles.message}><p className={styles.eyebrow}>Моя ёлка</p><h1>Ёлка скоро появится</h1><p>Здесь будут игрушки за выполненные задания.</p><Link href="/cabinet" className={styles.backLink}>← К календарю</Link></section>;
}
