import { ApiError } from "@/lib/api";
import { getTree } from "@/lib/tree-server";
import { redirect } from "next/navigation";
import { TreeExperience } from "./tree-experience";
import styles from "../cabinet.module.scss";

export const dynamic = "force-dynamic";

export default async function TreePage() {
  let tree;
  try { tree = await getTree(); }
  catch (error) {
    if (error instanceof ApiError) {
      if (error.code === "UNAUTHENTICATED") redirect("/login");
      if (error.code === "ADVENT_CALENDAR_NOT_FOUND") return <section className={styles.message} role="status"><h1>Ёлка ещё не появилась</h1><p>Когда вам назначат календарь на этот год, здесь появится место для игрушек.</p></section>;
    }
    throw error;
  }
  return <TreeExperience initialTree={tree} />;
}
