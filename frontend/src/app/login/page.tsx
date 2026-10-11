import { auth } from "@/auth";
import { redirect } from "next/navigation";
import { LoginForm } from "./login-form";
import styles from "./login.module.scss";

export default async function LoginPage() {
  const session = await auth();
  if (session?.user?.uuid) redirect(session.user.roles.includes("ROLE_ADMIN") ? "/admin" : "/cabinet");
  return <main className={styles.page}><LoginForm /></main>;
}
