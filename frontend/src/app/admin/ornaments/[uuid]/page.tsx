import { OrnamentEditor } from "../ornament-editor";

export default async function OrnamentPage({ params }: { params: Promise<{ uuid: string }> }) {
  const { uuid } = await params;
  return <OrnamentEditor uuid={uuid} />;
}
