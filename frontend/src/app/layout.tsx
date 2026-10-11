import type { Metadata } from "next";
import "./globals.css";
import "katex/dist/katex.min.css";
import { AppProviders } from "@/components/providers";

export const metadata: Metadata = {
  title: "Advent Calendar",
  description: "Математический адвент-календарь",
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="ru">
      <body><AppProviders>{children}</AppProviders></body>
    </html>
  );
}
