import Image from "next/image";
import ReactMarkdown from "react-markdown";
import remarkMath from "remark-math";
import rehypeKatex from "rehype-katex";
import styles from "./task-content.module.scss";

/** Shared renderer for the admin preview and the user's day page. Raw HTML is discarded. */
export function TaskContent({ title, content, imageUrl }: { title: string; content: string; imageUrl?: string | null }) {
  return <article className={styles.task}>
    <h2>{title || "Название задания"}</h2>
    {imageUrl && <div className={styles.image}><Image src={imageUrl} alt={`Иллюстрация к заданию «${title}»`} width={900} height={560} unoptimized /></div>}
    <div className={styles.markdown}>
      <ReactMarkdown remarkPlugins={[remarkMath]} rehypePlugins={[[rehypeKatex, { trust: false, throwOnError: false }]]} skipHtml>{content || "Текст задания появится здесь."}</ReactMarkdown>
    </div>
  </article>;
}
