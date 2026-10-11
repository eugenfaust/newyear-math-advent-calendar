import { auth } from "@/auth";
import { AppShell } from "@/components/app-shell";
import { redirect } from "next/navigation";

export default async function CabinetLayout({ children }: { children: React.ReactNode }) {
  const session = await auth();
  if (!session?.user?.uuid) redirect("/login");
  return <AppShell user={session.user} area="cabinet">{children}</AppShell>;
}
