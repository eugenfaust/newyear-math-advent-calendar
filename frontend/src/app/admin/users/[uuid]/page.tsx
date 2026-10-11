import { UserEditor } from "../user-editor";

export default async function UserPage({ params }: { params: Promise<{ uuid: string }> }) {
  const { uuid } = await params;
  return <UserEditor uuid={uuid} />;
}
