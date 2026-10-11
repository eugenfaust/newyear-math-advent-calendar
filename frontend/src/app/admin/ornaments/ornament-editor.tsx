"use client";
/* eslint-disable @next/next/no-img-element -- Preview uses a local blob URL before upload. */

import { Button } from "@astryxdesign/core/Button";
import { TextInput } from "@astryxdesign/core/TextInput";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { readOrnamentResponse, type ApiProblem, type ManagedOrnament } from "./types";
import styles from "./ornaments.module.scss";

export function OrnamentEditor({ uuid }: { uuid?: string }) {
  const router = useRouter();
  const client = useQueryClient();
  const [currentId, setCurrentId] = useState(uuid);
  const [name, setName] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [savedImageUrl, setSavedImageUrl] = useState<string | null>(null);
  const [nameError, setNameError] = useState("");
  const [imageError, setImageError] = useState("");
  const [formError, setFormError] = useState("");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);
  const ornament = useQuery({
    queryKey: ["admin-ornament", uuid], enabled: Boolean(uuid),
    queryFn: async ({ signal }) => readOrnamentResponse<ManagedOrnament>(await fetch(`/api/admin/ornaments/${uuid}`, { signal })),
  });
  useEffect(() => {
    if (ornament.data) { setName(ornament.data.name); setIsActive(ornament.data.isActive); setSavedImageUrl(ornament.data.imageUrl); }
  }, [ornament.data]);
  useEffect(() => {
    if (!imageFile) { setPreviewUrl(null); return; }
    const url = URL.createObjectURL(imageFile);
    setPreviewUrl(url);
    return () => URL.revokeObjectURL(url);
  }, [imageFile]);

  function chooseImage(file: File | undefined) {
    setImageError("");
    if (!file) { setImageFile(null); return; }
    if (!["image/svg+xml", "image/png", "image/webp"].includes(file.type) || file.size === 0 || file.size > 5 * 1024 * 1024) {
      setImageError("Выберите SVG, PNG или WebP до 5 МБ");
      setImageFile(null);
      return;
    }
    setImageFile(file);
  }

  function showProblem(problem: ApiProblem) {
    for (const detail of problem.details ?? []) {
      if (detail.field === "name") setNameError(detail.message);
      if (detail.field === "image") setImageError(detail.message);
    }
    setFormError(problem.message || "Не удалось сохранить игрушку");
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); setNameError(""); setImageError(""); setFormError(""); setNotice("");
    if (!name.trim() || name.trim().length > 150) { setNameError("Название: от 1 до 150 символов"); return; }
    if (!currentId && !imageFile) { setImageError("Добавьте изображение для новой игрушки"); return; }
    setBusy(true);
    try {
      const payload = currentId ? { name: name.trim(), isActive: isActive && Boolean(savedImageUrl) } : { name: name.trim(), isActive: false };
      let saved = await readOrnamentResponse<ManagedOrnament>(await fetch(currentId ? `/api/admin/ornaments/${currentId}` : "/api/admin/ornaments", {
        method: currentId ? "PATCH" : "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload),
      }));
      setCurrentId(saved.uuid);
      if (imageFile) {
        const data = new FormData(); data.set("image", imageFile);
        saved = await readOrnamentResponse<ManagedOrnament>(await fetch(`/api/admin/ornaments/${saved.uuid}/image`, { method: "POST", body: data }));
        setImageFile(null); setSavedImageUrl(saved.imageUrl);
      }
      if (isActive && !saved.isActive && saved.imageUrl) {
        saved = await readOrnamentResponse<ManagedOrnament>(await fetch(`/api/admin/ornaments/${saved.uuid}`, {
          method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ isActive: true }),
        }));
      }
      setIsActive(saved.isActive); setSavedImageUrl(saved.imageUrl);
      client.setQueryData(["admin-ornament", saved.uuid], saved);
      await client.invalidateQueries({ queryKey: ["admin-ornaments"] });
      setNotice("Игрушка сохранена");
      if (!uuid) router.replace(`/admin/ornaments/${saved.uuid}`);
      else router.refresh();
    } catch (problem) { showProblem(problem as ApiProblem); }
    finally { setBusy(false); }
  }

  async function deactivate() {
    if (!currentId || !window.confirm("Отключить игрушку? Уже выданные экземпляры останутся на ёлках.")) return;
    setBusy(true); setFormError(""); setNotice("");
    try {
      await readOrnamentResponse<void>(await fetch(`/api/admin/ornaments/${currentId}`, { method: "DELETE" }));
      setIsActive(false);
      client.setQueryData<ManagedOrnament>(["admin-ornament", currentId], (old) => old ? { ...old, isActive: false } : old);
      await client.invalidateQueries({ queryKey: ["admin-ornaments"] });
      setNotice("Игрушка отключена");
    } catch (problem) { showProblem(problem as ApiProblem); }
    finally { setBusy(false); }
  }

  const imageUrl = previewUrl ?? savedImageUrl;
  return <section className={styles.section}>
    <Link href="/admin/ornaments" className={styles.back}>← К игрушкам</Link>
    <header className={styles.heading}><div><span className={styles.eyebrow}>Коллекция · Редактор</span><h1>{uuid ? "Редактировать игрушку" : "Новая игрушка"}</h1><p>Картинка сохранится в библиотеке и будет доступна после отключения игрушки.</p></div></header>
    {uuid && ornament.isPending ? <p role="status">Загрузка игрушки…</p> : ornament.isError ? <div role="alert" className={styles.errorBox}>Не удалось загрузить игрушку. <button type="button" onClick={() => ornament.refetch()}>Повторить</button></div> : <div className={styles.editorGrid}>
      <form className={styles.form} onSubmit={save}>
        <div><TextInput label="Название" value={name} onChange={setName} width="100%" isRequired status={nameError ? { type: "error", message: nameError } : undefined} /></div>
        <label className={styles.field}>Изображение <span className={styles.hint}>SVG, PNG или WebP, до 5 МБ. Для SVG разрешены простые фигуры и контуры.</span><input type="file" accept="image/svg+xml,image/png,image/webp" onChange={(event) => chooseImage(event.target.files?.[0])} /></label>
        {imageError && <span role="alert" className={styles.error}>{imageError}</span>}
        <label className={styles.check}><input type="checkbox" checked={isActive} onChange={(event) => setIsActive(event.target.checked)} />Активна для новых наград</label>
        {formError && <p role="alert" className={styles.error}>{formError}</p>}
        {notice && <p role="status" className={styles.success}>{notice}</p>}
        <div className={styles.actions}><Button label={currentId ? "Сохранить изменения" : "Создать игрушку"} type="submit" variant="primary" isLoading={busy} />{currentId && isActive && <Button label="Отключить" type="button" variant="secondary" onClick={deactivate} isDisabled={busy} />}</div>
      </form>
      <aside className={styles.previewPanel}><span className={styles.eyebrow}>Предпросмотр на ёлке</span><div className={styles.previewStage}><span className={styles.previewThread} aria-hidden="true" />{imageUrl ? <img src={imageUrl} alt={name.trim() || "Предпросмотр игрушки"} /> : <span className={styles.previewEmpty}>Выберите изображение</span>}</div><p>{name.trim() || "Название игрушки"}</p></aside>
    </div>}
  </section>;
}
