"use client";
import { Button } from "@astryxdesign/core/Button";
export default function ErrorPage({ reset }: { error: Error; reset: () => void }) { return <main style={{ padding: 32 }}><h1>Не удалось загрузить страницу</h1><p>Попробуйте ещё раз.</p><Button label="Повторить" onClick={reset} /></main>; }
