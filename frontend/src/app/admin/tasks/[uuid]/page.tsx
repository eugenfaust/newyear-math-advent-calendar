import { TaskEditor } from "../task-editor";

export default async function TaskPage({ params }: { params: Promise<{ uuid: string }> }) {
  const { uuid } = await params;
  return <TaskEditor uuid={uuid} />;
}
