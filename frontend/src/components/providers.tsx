"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Theme } from "@astryxdesign/core/theme";
import { themes, defaultThemeSlug } from "@/astryx-themes";
import { useState } from "react";

export function AppProviders({ children }: { children: React.ReactNode }) {
  const [client] = useState(() => new QueryClient({ defaultOptions: { queries: { retry: 1, staleTime: 30_000 } } }));
  return <Theme theme={themes[defaultThemeSlug]}><QueryClientProvider client={client}>{children}</QueryClientProvider></Theme>;
}
