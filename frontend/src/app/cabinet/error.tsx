"use client";

import styles from "./cabinet.module.scss";

export default function CabinetError({ reset }: { error: Error; reset: () => void }) {
  return <section className={styles.message} role="alert"><h1>Не удалось загрузить календарь</h1><p>Проверьте соединение и попробуйте снова.</p><button className={styles.retryButton} onClick={reset}>Повторить</button></section>;
}
