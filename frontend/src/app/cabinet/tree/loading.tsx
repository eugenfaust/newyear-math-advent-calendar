import styles from "../cabinet.module.scss";

export default function TreeLoading() {
  return <section className={styles.message} role="status" aria-live="polite"><p className={styles.eyebrow}>Моя ёлка</p><h1>Собираем игрушки…</h1></section>;
}
