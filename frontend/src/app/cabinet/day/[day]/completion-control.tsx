"use client";

import type { CalendarDayCompletion, CalendarTask } from "@/lib/calendar";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import Image from "next/image";
import { motion, useReducedMotion } from "motion/react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import styles from "../../cabinet.module.scss";

type CompletionError = { code: string; message: string };

function isCompletionError(value: unknown): value is CompletionError {
  return typeof value === "object" && value !== null && "code" in value && typeof value.code === "string" && "message" in value && typeof value.message === "string";
}

function errorMessage(error: unknown): string {
  if (!isCompletionError(error)) return "Не удалось завершить задание. Проверьте соединение и попробуйте ещё раз.";
  switch (error.code) {
    case "NO_ACTIVE_ORNAMENTS": return "Пока нет игрушек для награды. Попробуйте завершить задание позже.";
    case "INCORRECT_ANSWER": return "Ответ неверный. Попробуйте ещё раз.";
    case "VALIDATION_ERROR": return "Введите ответ и попробуйте ещё раз.";
    case "ADVENT_DAY_LOCKED": return "Этот день ещё закрыт. Обновите страницу, чтобы увидеть дату открытия.";
    case "ADVENT_DAY_EXPIRED": return "Срок выполнения этого задания истёк. Обновите календарь.";
    case "ADVENT_CALENDAR_NOT_FOUND": return "Календарь не найден. Вернитесь к списку дней и обновите страницу.";
    case "ADVENT_DAY_NOT_FOUND": return "Задание не найдено. Вернитесь к списку дней и обновите страницу.";
    case "UNAUTHENTICATED": return "Сеанс закончился. Войдите снова, чтобы завершить задание.";
    case "ACCESS_DENIED": return "Нет доступа к этому календарю. Обновите страницу или войдите снова.";
    case "SERVICE_UNAVAILABLE": return "Сервис временно недоступен. Попробуйте ещё раз позже.";
    default: return error.message || "Не удалось завершить задание. Попробуйте ещё раз.";
  }
}

async function completeDay(day: number, year: number, answer?: string): Promise<CalendarDayCompletion> {
  const response = await fetch(`/api/calendar/days/${day}/complete?year=${year}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(answer === undefined ? {} : { answer }),
    cache: "no-store",
  });
  const body: unknown = await response.json().catch(() => null);
  if (!response.ok) throw isCompletionError(body) ? body : { code: "API_ERROR", message: "Не удалось завершить задание." };
  return body as CalendarDayCompletion;
}

export function CompletionControl({ day, year, completed, answerType, choices }: { day: number; year: number; completed: boolean; answerType: CalendarTask["answerType"]; choices: string[] }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const dialogRef = useRef<HTMLDialogElement>(null);
  const [reward, setReward] = useState<CalendarDayCompletion["reward"] | null>(null);
  const [imageFailed, setImageFailed] = useState(false);
  const [answer, setAnswer] = useState("");
  const reducedMotion = useReducedMotion();
  const mutation = useMutation({
    mutationFn: (submitted?: string) => completeDay(day, year, submitted),
    onSuccess: (result) => {
      setImageFailed(false);
      setReward(result.reward);
      void queryClient.invalidateQueries({ queryKey: ["calendar"] });
      void queryClient.invalidateQueries({ queryKey: ["calendar-day", year, day] });
      void queryClient.invalidateQueries({ queryKey: ["tree", year] });
      router.refresh();
    },
  });

  useEffect(() => {
    if (reward && !dialogRef.current?.open) dialogRef.current?.showModal();
  }, [reward]);

  return <>
    {completed || reward ? <p className={styles.completedNote}>Вы выполнили это задание.</p> : <form className={styles.completionArea} onSubmit={(event) => { event.preventDefault(); if (!mutation.isPending) mutation.mutate(answerType === "NONE" ? undefined : answer); }}>
      {answerType === "MULTIPLE_CHOICE" ? <fieldset className={styles.answerChoices}>
        <legend>Выберите ответ</legend>
        {choices.map((choice, index) => <label key={index} className={styles.answerChoice}><input type="radio" name="answer" value={choice} checked={answer === choice} onChange={() => { setAnswer(choice); mutation.reset(); }} required />{choice}</label>)}
      </fieldset> : answerType !== "NONE" && <label className={styles.answerLabel} htmlFor="day-answer">
        Ваш ответ
        <input id="day-answer" className={styles.answerInput} type="text" inputMode={answerType === "NUMBER" ? "decimal" : "text"} value={answer} onChange={(event) => { setAnswer(event.target.value); mutation.reset(); }} maxLength={10000} required aria-invalid={mutation.isError && isCompletionError(mutation.error) && mutation.error.code === "INCORRECT_ANSWER"} />
      </label>}
      <button type="submit" className={styles.completeButton} disabled={mutation.isPending}>
        {mutation.isPending ? "Проверяем ответ…" : answerType === "NONE" ? "Задание выполнено" : "Проверить ответ"}
      </button>
      {mutation.isError && <p className={styles.completionError} role="alert">{errorMessage(mutation.error)}</p>}
    </form>}
    <dialog ref={dialogRef} className={styles.rewardDialog} aria-labelledby="reward-title" onClose={() => setReward(null)}>
      {reward && <div className={styles.rewardCard}>
        <p className={styles.eyebrow}>День {day} завершён</p>
        <h2 id="reward-title">Игрушка для вашей ёлки</h2>
        <motion.div className={styles.rewardDisplay} initial={reducedMotion ? false : { scale: 0.4, rotate: -15, opacity: 0 }} animate={{ scale: 1, rotate: 0, opacity: 1 }} transition={{ type: "spring", stiffness: 230, damping: 19 }}>
          <span className={styles.rewardThread} aria-hidden="true" />
          {imageFailed ? <span className={styles.rewardFallback} role="img" aria-label="Изображение игрушки недоступно">✦</span> : <Image src={reward.imageUrl} alt={reward.name} width={160} height={160} unoptimized onError={() => setImageFailed(true)} />}
        </motion.div>
        <p className={styles.rewardName}>{reward.name}</p>
        <div className={styles.rewardActions}>
          <Link href="/cabinet/tree" className={styles.completeButton}>Перейти к ёлке</Link>
          <button type="button" className={styles.rewardClose} onClick={() => dialogRef.current?.close()}>Остаться здесь</button>
        </div>
      </div>}
    </dialog>
  </>;
}
