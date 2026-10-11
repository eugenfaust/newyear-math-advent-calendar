import styles from "./site-snow.module.scss";

const FLAKES = [
  [2, 9, 18], [7, 6, 23], [12, 8, 16], [17, 5, 26],
  [22, 10, 21], [27, 7, 28], [32, 6, 19], [37, 9, 24],
  [42, 5, 17], [47, 8, 27], [52, 7, 20], [57, 10, 25],
  [62, 6, 18], [67, 9, 22], [72, 5, 29], [77, 8, 16],
  [82, 7, 24], [87, 10, 20], [92, 6, 27], [97, 8, 19],
] as const;

export function SiteSnow() {
  return <div className={styles.snow} aria-hidden="true">
    {FLAKES.map(([left, size, duration], index) =>
      <span
        key={left}
        className={styles.flake}
        style={{
          left: `${left}%`,
          width: size,
          height: size,
          animationDuration: `${duration}s`,
          animationDelay: `${-index * 2.9}s`,
        }}
      />
    )}
  </div>;
}
