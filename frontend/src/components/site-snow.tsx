import styles from "./site-snow.module.scss";

const FLAKES = [
  [3, 7, 18], [9, 4, 23], [15, 6, 16], [21, 3, 26],
  [28, 5, 20], [35, 4, 28], [42, 7, 22], [49, 3, 17],
  [56, 5, 25], [63, 4, 19], [70, 6, 27], [77, 3, 21],
  [84, 5, 16], [91, 4, 24], [97, 6, 18],
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
