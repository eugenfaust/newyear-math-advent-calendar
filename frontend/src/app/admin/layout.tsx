import { auth } from "@/auth";
import { AppShell } from "@/components/app-shell";
import { redirect } from "next/navigation";

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const session = await auth();
  if (!session?.user?.uuid) redirect("/login");
  if (!session.user.roles.includes("ROLE_ADMIN")) redirect("/cabinet");
  return <AppShell user={session.user} area="admin">{children}</AppShell>;
}
