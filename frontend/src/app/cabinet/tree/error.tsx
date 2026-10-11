"use client";

import styles from "../cabinet.module.scss";

export default function TreeError({ reset }: { error: Error; reset: () => void }) {
  return <section className={styles.message} role="alert"><h1>Не удалось загрузить ёлку</h1><p>Проверьте соединение и попробуйте снова.</p><button type="button" className={styles.retryButton} onClick={reset}>Повторить</button></section>;
}
