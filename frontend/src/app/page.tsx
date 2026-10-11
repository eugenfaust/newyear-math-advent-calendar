import { auth } from "@/auth";
import { redirect } from "next/navigation";

export default async function Home() {
  const session = await auth();
  redirect(!session?.user?.uuid ? "/login" : session.user.roles.includes("ROLE_ADMIN") ? "/admin" : "/cabinet");
}
