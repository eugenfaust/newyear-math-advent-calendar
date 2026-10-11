"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button } from "@astryxdesign/core/Button";
import { TextInput } from "@astryxdesign/core/TextInput";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { TaskContent } from "@/components/task-content";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { z } from "zod";
import { readTaskResponse, type ApiProblem, type ManagedTask } from "./types";
import styles from "./tasks.module.scss";

const schema = z.object({
  title: z.string().trim().min(1, "Введите название").max(150, "Максимум 150 символов"),
  content: z.string().min(1, "Введите текст задания").max(50000, "Максимум 50 000 символов").refine((value) => value.trim().length > 0, "Введите текст задания"),
  correctAnswer: z.string().max(10000, "Максимум 10 000 символов"),
  solution: z.string().max(50000, "Максимум 50 000 символов"),
  isActive: z.boolean(),
});
type Values = z.infer<typeof schema>;
const defaults: Values = { title: "", content: "", correctAnswer: "", solution: "", isActive: true };

export function TaskEditor({ uuid }: { uuid?: string }) {
  const router = useRouter();
  const client = useQueryClient();
  const [currentId, setCurrentId] = useState(uuid);
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [savedImageUrl, setSavedImageUrl] = useState<string | null | undefined>(undefined);
  const [imageError, setImageError] = useState("");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);
  const [showPreview, setShowPreview] = useState(true);
  const task = useQuery({
    queryKey: ["admin-task", uuid],
    enabled: Boolean(uuid),
    queryFn: async ({ signal }) => readTaskResponse<ManagedTask>(await fetch(`/api/admin/tasks/${uuid}`, { signal })),
  });
  const { control, handleSubmit, reset, setError, watch, formState: { errors, isSubmitting } } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: defaults });
  const values = watch();
  useEffect(() => {
    if (task.data) reset({ title: task.data.title, content: task.data.content, correctAnswer: task.data.correctAnswer ?? "", solution: task.data.solution ?? "", isActive: task.data.isActive });
  }, [task.data, reset]);
  useEffect(() => {
    if (!imageFile) { setImagePreview(null); return; }
    const url = URL.createObjectURL(imageFile);
    setImagePreview(url);
    return () => URL.revokeObjectURL(url);
  }, [imageFile]);

  function chooseImage(file: File | undefined) {
    setImageError("");
    if (!file) { setImageFile(null); return; }
    if (!["image/png", "image/jpeg", "image/webp"].includes(file.type) || file.size > 5 * 1024 * 1024) {
      setImageError("Выберите PNG, JPEG или WebP до 5 МБ");
      setImageFile(null);
      return;
    }
    setImageFile(file);
  }

  function showProblem(problem: ApiProblem) {
    for (const detail of problem.details ?? []) {
      if (detail.field === "image") setImageError(detail.message);
      else if (detail.field in defaults) setError(detail.field as keyof Values, { message: detail.message });
    }
    setError("root", { message: problem.message || "Не удалось сохранить задание" });
  }

  const submit = handleSubmit(async (form) => {
    setNotice("");
    setImageError("");
    const payload = { title: form.title.trim(), content: form.content, answerType: "NONE", correctAnswer: form.correctAnswer || null, solution: form.solution || null, isActive: form.isActive };
    try {
      const saved = await readTaskResponse<ManagedTask>(await fetch(currentId ? `/api/admin/tasks/${currentId}` : "/api/admin/tasks", {
        method: currentId ? "PATCH" : "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload),
      }));
      setCurrentId(saved.uuid);
      let result = saved;
      if (imageFile) {
        const data = new FormData();
        data.set("image", imageFile);
        result = await readTaskResponse<ManagedTask>(await fetch(`/api/admin/tasks/${saved.uuid}/image`, { method: "POST", body: data }));
        setImageFile(null);
      }
      setSavedImageUrl(result.imageUrl);
      reset({ title: result.title, content: result.content, correctAnswer: result.correctAnswer ?? "", solution: result.solution ?? "", isActive: result.isActive });
      client.setQueryData(["admin-task", result.uuid], result);
      await client.invalidateQueries({ queryKey: ["admin-tasks"] });
      setNotice("Задание сохранено");
      if (!uuid) router.replace(`/admin/tasks/${result.uuid}`);
      else router.refresh();
    } catch (problem) { showProblem(problem as ApiProblem); }
  });

  async function removeImage() {
    if (imageFile) { setImageFile(null); return; }
    if (!currentId || !savedImage) return;
    setBusy(true); setNotice("");
    try {
      await readTaskResponse<void>(await fetch(`/api/admin/tasks/${currentId}/image`, { method: "DELETE" }));
      setSavedImageUrl(null);
      setNotice("Изображение удалено");
    } catch (problem) { showProblem(problem as ApiProblem); }
    finally { setBusy(false); }
  }

  async function deactivate() {
    if (!currentId || !window.confirm("Отключить задание? Оно останется в библиотеке и назначенных календарях.")) return;
    setBusy(true); setNotice("");
    try {
      await readTaskResponse<void>(await fetch(`/api/admin/tasks/${currentId}`, { method: "DELETE" }));
      client.setQueryData<ManagedTask>(["admin-task", currentId], (old) => old ? { ...old, isActive: false } : old);
      reset({ ...values, isActive: false });
      await client.invalidateQueries({ queryKey: ["admin-tasks"] });
      setNotice("Задание отключено");
    } catch (problem) { showProblem(problem as ApiProblem); }
    finally { setBusy(false); }
  }

  const loading = Boolean(uuid) && task.isPending;
  const savedImage = savedImageUrl === undefined ? task.data?.imageUrl : savedImageUrl;
  return <section className={styles.section}>
    <Link href="/admin/tasks" className={styles.back}>← К заданиям</Link>
    <header className={styles.heading}><div><span className={styles.eyebrow}>Библиотека · Редактор</span><h1>{uuid ? "Редактировать задание" : "Новое задание"}</h1><p>Текст и формулы справа показываются так же, как на странице дня.</p></div></header>
    {loading ? <p role="status">Загрузка задания…</p> : task.isError ? <div role="alert" className={styles.errorBox}>Не удалось загрузить задание. <button type="button" onClick={() => task.refetch()}>Повторить</button></div> : <div className={styles.editorGrid}>
      <form className={styles.form} onSubmit={submit}>
        <Controller name="title" control={control} render={({ field, fieldState }) => <TextInput label="Название" value={field.value} onChange={field.onChange} onBlur={field.onBlur} htmlName={field.name} ref={field.ref} isRequired width="100%" status={fieldState.error ? { type: "error", message: fieldState.error.message } : undefined} />} />
        <label className={styles.field}>Текст задания <span className={styles.hint}>Markdown; формулы: $...$ и $$...$$</span><Controller name="content" control={control} render={({ field }) => <textarea {...field} rows={12} aria-invalid={Boolean(errors.content)} aria-describedby={errors.content ? "content-error" : undefined} />} />{errors.content && <span id="content-error" className={styles.error}>{errors.content.message}</span>}</label>
        <div className={styles.imageField}><label className={styles.field}>Изображение <span className={styles.hint}>PNG, JPEG или WebP, до 5 МБ и 8 Мп</span><input type="file" accept="image/png,image/jpeg,image/webp" onChange={(event) => chooseImage(event.target.files?.[0])} /></label>{(imageFile || savedImage) && <button type="button" className={styles.textButton} onClick={removeImage} disabled={busy}>Убрать изображение</button>}{imageError && <span role="alert" className={styles.error}>{imageError}</span>}</div>
        <label className={styles.field}>Тип ответа<select value="NONE" disabled><option value="NONE">Без автоматической проверки</option></select></label>
        <Controller name="correctAnswer" control={control} render={({ field }) => <label className={styles.field}>Правильный ответ <span className={styles.hint}>Не показывается пользователю до выполнения</span><textarea {...field} rows={2} aria-invalid={Boolean(errors.correctAnswer)} /></label>} />
        {errors.correctAnswer && <span className={styles.error}>{errors.correctAnswer.message}</span>}
        <Controller name="solution" control={control} render={({ field }) => <label className={styles.field}>Решение <textarea {...field} rows={5} aria-invalid={Boolean(errors.solution)} /></label>} />
        {errors.solution && <span className={styles.error}>{errors.solution.message}</span>}
        <label className={styles.check}><Controller name="isActive" control={control} render={({ field }) => <input type="checkbox" name={field.name} checked={field.value} onChange={(event) => field.onChange(event.target.checked)} ref={field.ref} />} />Активно</label>
        {errors.root && <p role="alert" className={styles.error}>{errors.root.message}</p>}
        {notice && <p role="status" className={styles.success}>{notice}</p>}
        <div className={styles.actions}><Button label={currentId ? "Сохранить изменения" : "Создать задание"} type="submit" variant="primary" isLoading={isSubmitting} />{currentId && values.isActive && <Button label="Отключить" type="button" variant="secondary" onClick={deactivate} isLoading={busy} />}</div>
      </form>
      <aside className={styles.previewPanel}><div className={styles.previewHeader}><div><span className={styles.eyebrow}>Предпросмотр</span><p>Так задание увидит пользователь</p></div><button type="button" className={styles.textButton} onClick={() => setShowPreview(!showPreview)}>{showPreview ? "Скрыть" : "Показать"}</button></div>{showPreview && <TaskContent title={values.title} content={values.content} imageUrl={imagePreview ?? savedImage} />}</aside>
    </div>}
  </section>;
}
