"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button } from "@astryxdesign/core/Button";
import { TextInput } from "@astryxdesign/core/TextInput";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { z } from "zod";
import { readResponse, type ApiProblem, type ManagedUser } from "./types";
import { CalendarAssignment } from "./calendar-assignment";
import styles from "./users.module.scss";

const schema = z.object({
  name: z.string().trim().min(1, "Введите имя").max(150, "Максимум 150 символов"),
  login: z.string().trim().regex(/^[a-zA-Z0-9_.-]{1,100}$/, "1–100 латинских букв, цифр, _, . или -"),
  email: z.string().trim().refine((value) => !value || (value.length <= 180 && z.email().safeParse(value).success), "Введите корректный email"),
  role: z.enum(["ROLE_USER", "ROLE_ADMIN"]),
  isActive: z.boolean(),
  isTestUser: z.boolean(),
  password: z.string(),
});
type Values = z.infer<typeof schema>;
const defaults: Values = { name: "", login: "", email: "", role: "ROLE_USER", isActive: true, isTestUser: false, password: "" };

export function UserEditor({ uuid }: { uuid: string }) {
  const creating = uuid === "new";
  const router = useRouter();
  const [loading, setLoading] = useState(!creating);
  const [loadError, setLoadError] = useState("");
  const [notice, setNotice] = useState("");
  const [deactivating, setDeactivating] = useState(false);
  const { control, handleSubmit, reset, setError, watch, formState: { errors, isSubmitting } } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: defaults });
  const isActive = watch("isActive");

  useEffect(() => {
    if (creating) return;
    const abort = new AbortController();
    fetch(`/api/admin/users/${uuid}`, { signal: abort.signal })
      .then(readResponse<ManagedUser>)
      .then((user) => reset({ name: user.name, login: user.login, email: user.email ?? "", role: user.role, isActive: user.isActive, isTestUser: user.isTestUser, password: "" }))
      .catch((problem) => { if (!abort.signal.aborted) setLoadError(problem?.message ?? "Не удалось загрузить пользователя"); })
      .finally(() => { if (!abort.signal.aborted) setLoading(false); });
    return () => abort.abort();
  }, [creating, reset, uuid]);

  function showProblem(problem: ApiProblem) {
    if (problem.code === "LOGIN_TAKEN") { setError("login", { message: "Этот логин уже занят" }); return; }
    if (problem.details?.length) {
      for (const detail of problem.details) {
        if (detail.field in defaults) setError(detail.field as keyof Values, { message: detail.message });
      }
    }
    setError("root", { message: problem.message ?? "Не удалось сохранить пользователя" });
  }

  const submit = handleSubmit(async (values) => {
    setNotice("");
    if (creating && Array.from(values.password).length < 6) { setError("password", { message: "Минимум 6 символов" }); return; }
    if (values.password && Array.from(values.password).length < 6) { setError("password", { message: "Минимум 6 символов" }); return; }
    const payload: Record<string, unknown> = { name: values.name.trim(), login: values.login.trim(), email: values.email.trim() || null, role: values.role, isActive: values.isActive, isTestUser: values.isTestUser };
    if (values.password) payload.password = values.password;
    try {
      const response = await fetch(creating ? "/api/admin/users" : `/api/admin/users/${uuid}`, { method: creating ? "POST" : "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
      const user = await readResponse<ManagedUser>(response);
      if (creating) router.replace(`/admin/users/${user.uuid}`);
      else { reset({ ...values, password: "" }); setNotice("Изменения сохранены"); router.refresh(); }
    } catch (problem) { showProblem(problem as ApiProblem); }
  });

  async function deactivate() {
    if (!window.confirm("Отключить доступ этому пользователю? История календарей сохранится.")) return;
    setDeactivating(true);
    setNotice("");
    try {
      const response = await fetch(`/api/admin/users/${uuid}`, { method: "DELETE" });
      await readResponse<void>(response);
      reset({ ...watch(), isActive: false, password: "" });
      setNotice("Доступ отключён");
      router.refresh();
    } catch (problem) { setError("root", { message: (problem as ApiProblem).message ?? "Не удалось отключить доступ" }); }
    finally { setDeactivating(false); }
  }

  return <section className={styles.section}>
    <Link href="/admin/users">← К списку пользователей</Link>
    <h1>{creating ? "Новый пользователь" : "Пользователь"}</h1>
    {loading ? <p>Загрузка…</p> : loadError ? <p role="alert" className={styles.error}>{loadError}</p> : <form className={styles.form} onSubmit={submit}>
      <Controller name="name" control={control} render={({ field, fieldState }) => <TextInput label="Имя" value={field.value} onChange={field.onChange} onBlur={field.onBlur} htmlName={field.name} ref={field.ref} isRequired status={fieldState.error ? { type: "error", message: fieldState.error.message } : undefined} width="100%" />} />
      <Controller name="login" control={control} render={({ field, fieldState }) => <TextInput label="Логин" value={field.value} onChange={field.onChange} onBlur={field.onBlur} htmlName={field.name} ref={field.ref} autoComplete="username" isRequired status={fieldState.error ? { type: "error", message: fieldState.error.message } : undefined} width="100%" />} />
      <Controller name="email" control={control} render={({ field, fieldState }) => <TextInput label="Email" type="email" value={field.value} onChange={field.onChange} onBlur={field.onBlur} htmlName={field.name} ref={field.ref} autoComplete="email" status={fieldState.error ? { type: "error", message: fieldState.error.message } : undefined} width="100%" />} />
      <label className={styles.field}>Роль<Controller name="role" control={control} render={({ field }) => <select {...field}><option value="ROLE_USER">Пользователь</option><option value="ROLE_ADMIN">Администратор</option></select>} /></label>
      <label className={styles.check}><Controller name="isActive" control={control} render={({ field }) => <input type="checkbox" name={field.name} checked={field.value} onChange={(event) => field.onChange(event.target.checked)} ref={field.ref} />} />Активен</label>
      <div><label className={styles.check}><Controller name="isTestUser" control={control} render={({ field }) => <input type="checkbox" name={field.name} checked={field.value} onChange={(event) => field.onChange(event.target.checked)} ref={field.ref} />} />Тестовый пользователь</label><p className={styles.hint}>Все 31 задания назначенного календаря доступны без ожидания даты открытия.</p></div>
      <Controller name="password" control={control} render={({ field, fieldState }) => <TextInput label={creating ? "Пароль (минимум 6 символов)" : "Новый пароль (оставьте пустым, чтобы не менять)"} type="password" value={field.value} onChange={field.onChange} onBlur={field.onBlur} htmlName={field.name} ref={field.ref} autoComplete="new-password" isRequired={creating} status={fieldState.error ? { type: "error", message: fieldState.error.message } : undefined} width="100%" />} />
      {errors.root && <p role="alert" className={styles.error}>{errors.root.message}</p>}
      {notice && <p role="status" className={styles.success}>{notice}</p>}
      <div className={styles.actions}><Button label={creating ? "Создать" : "Сохранить"} type="submit" variant="primary" isLoading={isSubmitting} />{!creating && isActive && <Button label="Отключить доступ" type="button" variant="secondary" onClick={deactivate} isLoading={deactivating} />}</div>
    </form>}
    {!creating && !loading && !loadError && <CalendarAssignment uuid={uuid} />}
  </section>;
}
