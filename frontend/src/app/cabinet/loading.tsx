import styles from "./cabinet.module.scss";

export default function CabinetLoading() {
  return <section className={styles.message} role="status" aria-live="polite"><p className={styles.eyebrow}>Математический декабрь</p><h1>Загружаем календарь…</h1></section>;
}
