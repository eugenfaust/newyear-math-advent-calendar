import type { Metadata } from "next";
import "./globals.css";
import "katex/dist/katex.min.css";
import { AppProviders } from "@/components/providers";
import { SiteSnow } from "@/components/site-snow";

export const metadata: Metadata = {
  title: "Advent Calendar",
  description: "Математический адвент-календарь",
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="ru">
      <body><AppProviders>{children}</AppProviders><SiteSnow /></body>
    </html>
  );
}
