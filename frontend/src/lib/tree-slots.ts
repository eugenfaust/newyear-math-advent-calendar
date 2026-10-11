export type TreeSlot = { id: number; x: number; y: number };

// Coordinates belong to the SVG viewBox. IDs are the only values persisted by the API.
const rows = [
  { y: 142, xs: [220] },
  { y: 204, xs: [182, 258] },
  { y: 270, xs: [147, 220, 293] },
  { y: 338, xs: [116, 184, 256, 324] },
  { y: 408, xs: [91, 155, 220, 285, 349] },
  { y: 480, xs: [73, 132, 191, 249, 308, 367] },
  { y: 548, xs: [88, 154, 220, 286, 352] },
  { y: 613, xs: [96, 158, 220, 282, 344] },
];

export const TREE_SLOTS: readonly TreeSlot[] = rows.flatMap((row, rowIndex) => {
  const previousCount = rows.slice(0, rowIndex).reduce((sum, item) => sum + item.xs.length, 0);
  return row.xs.map((x, index) => ({ id: previousCount + index + 1, x, y: row.y }));
});
