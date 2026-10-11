"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { Button } from "@astryxdesign/core/Button";
import { Card } from "@astryxdesign/core/Card";
import { TextInput } from "@astryxdesign/core/TextInput";
import { IconCalendar } from "@tabler/icons-react";
import { signIn } from "next-auth/react";
import { useRouter } from "next/navigation";
import { Controller, useForm } from "react-hook-form";
import { z } from "zod";
import styles from "./login.module.scss";

const schema = z.object({ login: z.string().trim().min(1, "Введите логин"), password: z.string().min(1, "Введите пароль") });
type Values = z.infer<typeof schema>;

export function LoginForm() {
  const router = useRouter();
  const { control, handleSubmit, setError, formState: { isSubmitting, errors } } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { login: "", password: "" } });
  const submit = handleSubmit(async ({ login, password }) => {
    try {
      const result = await signIn("credentials", { login, password, redirect: false });
      if (result?.error) {
        setError("root", { message: result.error === "CredentialsSignin" ? "Неверный логин или пароль" : "Не удалось войти. Попробуйте позже." });
        return;
      }
      router.replace("/");
      router.refresh();
    } catch {
      setError("root", { message: "Сервис входа недоступен. Попробуйте позже." });
    }
  });

  return <Card className={styles.card}>
    <div className={styles.brand}><IconCalendar size={28} aria-hidden /><span>Адвент-календарь</span></div>
    <h1>Вход</h1>
    <p className={styles.lead}>Войдите, чтобы открыть свой календарь.</p>
    <form onSubmit={submit} className={styles.form}>
      <Controller name="login" control={control} render={({ field, fieldState }) => <TextInput label="Логин" value={field.value} onChange={field.onChange} onBlur={field.onBlur} htmlName={field.name} ref={field.ref} autoComplete="username" isRequired status={fieldState.error ? { type: "error", message: fieldState.error.message } : undefined} width="100%" />} />
      <Controller name="password" control={control} render={({ field, fieldState }) => <TextInput label="Пароль" type="password" value={field.value} onChange={field.onChange} onBlur={field.onBlur} htmlName={field.name} ref={field.ref} autoComplete="current-password" isRequired status={fieldState.error ? { type: "error", message: fieldState.error.message } : undefined} width="100%" />} />
      {errors.root && <p role="alert" className={styles.error}>{errors.root.message}</p>}
      <Button label="Войти" type="submit" variant="primary" isLoading={isSubmitting} width="100%" />
    </form>
  </Card>;
}
