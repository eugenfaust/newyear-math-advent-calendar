"use client";

import type { TreeOrnament, UserTree } from "@/lib/tree";
import { TREE_SLOTS } from "@/lib/tree-slots";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Image from "next/image";
import Link from "next/link";
import { motion, useReducedMotion } from "motion/react";
import { useState } from "react";
import styles from "./tree.module.scss";

type ResponseError = { code: string; message: string };
type Flight = { uuid: string; imageUrl: string; from: { x: number; y: number }; to: { x: number; y: number } };
const SNOW = [14, 27, 39, 53, 66, 78, 88, 95];
const LIGHTS = [[150, 194], [221, 203], [284, 193], [128, 317], [220, 337], [316, 312], [101, 446], [180, 464], [266, 467], [349, 444], [77, 570], [166, 598], [267, 599], [364, 567]];

function isResponseError(value: unknown): value is ResponseError {
  return typeof value === "object" && value !== null && "code" in value && typeof value.code === "string" && "message" in value && typeof value.message === "string";
}

async function readResponse<T>(response: Response): Promise<T> {
  const body: unknown = await response.json().catch(() => null);
  if (!response.ok) throw isResponseError(body) ? body : { code: "API_ERROR", message: "Не удалось связаться с сервером." };
  return body as T;
}

async function loadTree(year: number): Promise<UserTree> {
  return readResponse<UserTree>(await fetch(`/api/tree?year=${year}`, { cache: "no-store" }));
}

async function placeOrnament(uuid: string, slot: number, year: number): Promise<TreeOrnament> {
  return readResponse<TreeOrnament>(await fetch(`/api/tree/ornaments/${uuid}/place?year=${year}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ slot }),
    cache: "no-store",
  }));
}

function placementError(error: unknown): string {
  if (!isResponseError(error)) return "Не удалось сохранить место. Проверьте соединение и попробуйте ещё раз.";
  switch (error.code) {
    case "TREE_SLOT_OCCUPIED": return "Это место уже занято. Данные ёлки обновлены — выберите свободное место.";
    case "USER_ORNAMENT_NOT_FOUND": return "Игрушка больше недоступна в этом календаре. Обновите страницу.";
    case "ADVENT_CALENDAR_NOT_FOUND": return "Календарь не найден. Вернитесь к календарю и обновите страницу.";
    case "UNAUTHENTICATED": return "Сеанс закончился. Войдите снова, чтобы украсить ёлку.";
    case "ACCESS_DENIED": return "Нет доступа к этой игрушке. Обновите страницу или войдите снова.";
    default: return error.message || "Не удалось сохранить место. Попробуйте ещё раз.";
  }
}

export function TreeExperience({ initialTree }: { initialTree: UserTree }) {
  const queryClient = useQueryClient();
  const queryKey = ["tree", initialTree.year] as const;
  const { data: tree, isFetching, isError, refetch } = useQuery({
    queryKey,
    queryFn: () => loadTree(initialTree.year),
    initialData: initialTree,
  });
  const [selectedUuid, setSelectedUuid] = useState<string | null>(null);
  const [announcement, setAnnouncement] = useState("");
  const [flight, setFlight] = useState<Flight | null>(null);
  const [recentUuid, setRecentUuid] = useState<string | null>(null);
  const [failedImages, setFailedImages] = useState<string[]>([]);
  const reducedMotion = useReducedMotion();
  const selected = tree.ornaments.find((ornament) => ornament.uuid === selectedUuid) ?? null;
  const placed = tree.ornaments.filter((ornament) => ornament.treeSlot !== null);
  const unplaced = tree.ornaments.filter((ornament) => ornament.treeSlot === null);
  const occupied = new Map(placed.map((ornament) => [ornament.treeSlot, ornament]));

  const mutation = useMutation({
    mutationFn: ({ ornament, slot }: { ornament: TreeOrnament; slot: number }) => placeOrnament(ornament.uuid, slot, tree.year),
    onSuccess: async (updated) => {
      if (!reducedMotion && updated.treeSlot !== null) {
        const source = document.querySelector(`[data-ornament="${updated.uuid}"]`)?.getBoundingClientRect();
        const target = document.querySelector(`[data-slot="${updated.treeSlot}"]`)?.getBoundingClientRect();
        if (source && target) setFlight({ uuid: updated.uuid, imageUrl: updated.imageUrl, from: { x: source.left + source.width / 2, y: source.top + source.height / 2 }, to: { x: target.left + target.width / 2, y: target.top + target.height / 2 } });
      }
      queryClient.setQueryData<UserTree>(queryKey, (previous) => previous ? {
        ...previous,
        ornaments: previous.ornaments.map((ornament) => ornament.uuid === updated.uuid ? updated : ornament),
      } : previous);
      setSelectedUuid(null);
      setAnnouncement(`${updated.name} теперь висит на месте ${updated.treeSlot}.`);
      await queryClient.invalidateQueries({ queryKey });
    },
    onError: async (error) => {
      setAnnouncement(placementError(error));
      if (isResponseError(error) && (error.code === "TREE_SLOT_OCCUPIED" || error.code === "USER_ORNAMENT_NOT_FOUND")) {
        await queryClient.invalidateQueries({ queryKey });
      }
    },
  });

  function chooseOrnament(ornament: TreeOrnament) {
    if (mutation.isPending) return;
    mutation.reset();
    const nextUuid = selectedUuid === ornament.uuid ? null : ornament.uuid;
    setSelectedUuid(nextUuid);
    setAnnouncement(nextUuid ? `${ornament.name} выбрана. Нажмите свободное место на ёлке.` : "Выбор снят.");
  }

  function activateSlot(slot: number) {
    if (mutation.isPending) return;
    const ornament = occupied.get(slot);
    if (ornament) { chooseOrnament(ornament); return; }
    if (!selected) { setAnnouncement("Сначала выберите игрушку в коллекции или на ёлке."); return; }
    if (selected.treeSlot === slot) return;
    setAnnouncement(`Сохраняем ${selected.name} на месте ${slot}…`);
    mutation.mutate({ ornament: selected, slot });
  }

  function markImageFailed(uuid: string) {
    setFailedImages((previous) => previous.includes(uuid) ? previous : [...previous, uuid]);
  }

  return <section className={styles.page} aria-labelledby="tree-title">
    <header className={styles.header}>
      <div><p className={styles.eyebrow}>Математический декабрь · {tree.year}</p><h1 id="tree-title">Моя ёлка</h1><p className={styles.lede}>Каждая решённая задача оставляет на ёлке свой знак. Выберите игрушку и найдите ей место.</p></div>
      <Link href="/cabinet" className={styles.calendarLink}>← К календарю</Link>
    </header>

    {tree.completed && <div className={styles.finish} role="status"><span className={styles.finishIcon} aria-hidden="true">✦</span><div><strong>Все 31 задачи решены!</strong><p>Звезда зажглась. Ваши игрушки по-прежнему можно размещать и перемещать.</p></div></div>}
    <div className={styles.layout}>
      <div className={styles.treePanel}>
        <div className={styles.treeTopline}><span>Ёлка · {placed.length} из 31 мест</span><span>{tree.completedDays} из {tree.totalDays} задач</span></div>
        <div className={styles.canvas}>
          <div className={styles.snow} aria-hidden="true">{SNOW.map((left, index) => <span key={left} style={{ left: `${left}%`, animationDelay: `${-index * 1.7}s` }} />)}</div>
          <svg className={styles.treeSvg} viewBox="0 0 440 710" role="group" aria-label="Ёлка с 31 местом для игрушек">
            <defs>
              <linearGradient id="fir" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stopColor="#307b77" /><stop offset=".52" stopColor="#165d68" /><stop offset="1" stopColor="#103e59" /></linearGradient>
              <linearGradient id="branch" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stopColor="#74aaa2" stopOpacity=".72" /><stop offset="1" stopColor="#0d4c5c" stopOpacity=".1" /></linearGradient>
            </defs>
            <path d="M203 582h34v85h-34z" fill="#855f62" /><path d="M175 669h90" stroke="#c8d5df" strokeWidth="3" strokeLinecap="round" />
            <path d="M220 58 146 198h28L105 315h31L67 438h35L44 647h352l-58-209h35l-69-123h31l-69-117h28Z" fill="url(#fir)" stroke="#9ac7bf" strokeWidth="2" />
            <path d="M220 58 146 198h28L105 315h31L67 438h35L44 647" fill="none" stroke="#b8d2cd" strokeOpacity=".5" strokeWidth="2" />
            <path d="M220 58 294 198h-28l69 117h-31l69 123h-35l58 209" fill="none" stroke="#092e49" strokeOpacity=".75" strokeWidth="3" />
            <path d="M157 189q63 37 126 0M114 310q106 65 212 0M78 432q142 86 284 0M52 556q168 94 336 0" fill="none" stroke="url(#branch)" strokeWidth="13" strokeLinecap="round" />
            <path d="M150 194q70 23 140-2M111 315q109 42 218-2M72 440q148 53 296-2M50 566q170 60 340-2" fill="none" stroke="#b8ded3" strokeOpacity=".62" strokeWidth="2" strokeDasharray="3 8" strokeLinecap="round" />
            {LIGHTS.map(([x, y], index) => <circle key={index} className={`${styles.light} ${styles[`light${index % 3}`]}`} cx={x} cy={y} r="3.2" fill={index % 2 ? "#f8cb73" : "#eff9e7"} />)}
            <motion.path d="m220 33 8 17 19 3-14 13 3 19-16-9-16 9 3-19-14-13 19-3Z" fill={tree.completed ? "#f8cb73" : "#b8cbd3"} stroke={tree.completed ? "#ffe3a3" : "#d8e4e8"} strokeWidth="2" style={{ transformOrigin: "220px 58px" }} initial={tree.completed && !reducedMotion ? { scale: 0.45, opacity: 0, rotate: -25 } : false} animate={{ scale: 1, opacity: 1, rotate: 0 }} transition={{ type: "spring", stiffness: 180, damping: 14 }} />
            {tree.completed && <g className={styles.starBurst} aria-hidden="true">{[0, 45, 90, 135, 180, 225, 270, 315].map((angle) => <path key={angle} d="M220 14v-9" stroke="#f8cb73" strokeWidth="2" strokeLinecap="round" transform={`rotate(${angle} 220 58)`} />)}</g>}
            {TREE_SLOTS.map((slot) => {
              const ornament = occupied.get(slot.id);
              const isSelected = ornament?.uuid === selectedUuid;
              const isTarget = Boolean(selected && !ornament);
              const label = ornament ? `Место ${slot.id}: ${ornament.name}. Нажмите, чтобы ${isSelected ? "снять выбор" : "выбрать для перемещения"}.` : selected ? `Свободное место ${slot.id}. Разместить ${selected.name}.` : `Свободное место ${slot.id}. Сначала выберите игрушку.`;
              return <g key={slot.id} data-slot={slot.id} role="button" tabIndex={0} aria-label={label} aria-pressed={Boolean(isSelected)} aria-disabled={mutation.isPending} className={`${styles.slot} ${ornament ? styles.occupied : ""} ${isTarget ? styles.target : ""} ${isSelected ? styles.selectedSlot : ""}`} onClick={() => activateSlot(slot.id)} onKeyDown={(event) => { if (event.key === "Enter" || event.key === " ") { event.preventDefault(); activateSlot(slot.id); } }}>
                <circle className={styles.slotFocus} cx={slot.x} cy={slot.y} r="29" />
                {ornament ? <g className={recentUuid === ornament.uuid && !reducedMotion ? styles.recentOrnament : undefined}><circle cx={slot.x} cy={slot.y} r="21" fill="#eff7f6" stroke="#f5d898" strokeWidth="2" />{failedImages.includes(ornament.uuid) ? <text className={styles.imageFallback} x={slot.x} y={slot.y + 7} textAnchor="middle">✦</text> : <image href={ornament.imageUrl} x={slot.x - 19} y={slot.y - 19} width="38" height="38" preserveAspectRatio="xMidYMid meet" onError={() => markImageFailed(ornament.uuid)} onAnimationEnd={() => { if (recentUuid === ornament.uuid) setRecentUuid(null); }} />}</g> : <><circle className={styles.emptySlot} cx={slot.x} cy={slot.y} r="18" /><text className={styles.slotNumber} x={slot.x} y={slot.y + 4} textAnchor="middle">{slot.id}</text></>}
                <circle className={styles.hitArea} cx={slot.x} cy={slot.y} r="29" />
              </g>;
            })}
          </svg>
        </div>
        <p className={styles.treeHint}>Нажмите игрушку, затем свободный номер на ёлке. Чтобы переместить игрушку, выберите её снова.</p>
      </div>

      <aside className={styles.inventory} aria-label="Мои игрушки">
        <div className={styles.inventoryHeader}><p className={styles.eyebrow}>Коллекция</p><h2>Мои игрушки</h2><p>{unplaced.length} без места · {placed.length} на ёлке</p></div>
        <div className={styles.feedback} role="status" aria-live="polite">{announcement || (selected ? `Выбрана «${selected.name}». Нажмите свободный номер на ёлке.` : "Выберите игрушку, чтобы подсветить свободные места.")}</div>
        {mutation.isError && <p className={styles.error} role="alert">{placementError(mutation.error)}</p>}
        {isError && <div className={styles.error} role="alert">Не удалось обновить ёлку. <button type="button" onClick={() => void refetch()}>Повторить</button></div>}
        {isFetching && <p className={styles.syncing} role="status">Обновляем ёлку…</p>}
        <h3>Ждут своего места <span>{unplaced.length}</span></h3>
        {unplaced.length ? <div className={styles.ornamentList}>{unplaced.map((ornament) => <OrnamentButton key={ornament.uuid} ornament={ornament} selected={selectedUuid === ornament.uuid} disabled={mutation.isPending} onClick={() => chooseOrnament(ornament)} />)}</div> : <p className={styles.empty}>{placed.length ? "Все полученные игрушки уже висят на ёлке. Выберите любую, чтобы переместить." : <>Здесь появятся награды за решённые задания. Новую игрушку можно получить в <Link href="/cabinet">календаре</Link>.</>}</p>}
        {placed.length > 0 && <><h3>На ёлке <span>{placed.length}</span></h3><div className={styles.ornamentList}>{placed.map((ornament) => <OrnamentButton key={ornament.uuid} ornament={ornament} selected={selectedUuid === ornament.uuid} disabled={mutation.isPending} onClick={() => chooseOrnament(ornament)} />)}</div></>}
      </aside>
    </div>
    {flight && <motion.div className={styles.flyingOrnament} initial={{ left: flight.from.x - 22, top: flight.from.y - 22, scale: .75, opacity: .8 }} animate={{ left: flight.to.x - 22, top: flight.to.y - 22, scale: 1, opacity: 1 }} transition={{ duration: .55, ease: [0.22, 1, 0.36, 1] }} onAnimationComplete={() => { setRecentUuid(flight.uuid); setFlight(null); }} aria-hidden="true">{failedImages.includes(flight.uuid) ? <span className={styles.inventoryFallback}>✦</span> : <Image src={flight.imageUrl} alt="" width={44} height={44} unoptimized onError={() => markImageFailed(flight.uuid)} />}</motion.div>}
  </section>;
}

function OrnamentButton({ ornament, selected, disabled, onClick }: { ornament: TreeOrnament; selected: boolean; disabled: boolean; onClick: () => void }) {
  const [imageFailed, setImageFailed] = useState(false);
  return <button type="button" data-ornament={ornament.uuid} className={`${styles.ornamentButton} ${selected ? styles.selectedOrnament : ""}`} aria-pressed={selected} disabled={disabled} onClick={onClick}>
    <span className={styles.ornamentImage}>{imageFailed ? <span className={styles.inventoryFallback} aria-hidden="true">✦</span> : <Image src={ornament.imageUrl} alt="" width={48} height={48} unoptimized onError={() => setImageFailed(true)} />}</span>
    <span className={styles.ornamentText}><strong>{ornament.name}</strong><small>{ornament.treeSlot === null ? "Выбрать место" : `Место ${ornament.treeSlot} · переместить`}</small></span>
    <span className={styles.ornamentArrow} aria-hidden="true">{selected ? "✓" : "↗"}</span>
  </button>;
}
