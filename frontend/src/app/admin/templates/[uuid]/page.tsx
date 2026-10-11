import { TemplateEditor } from "../template-editor";

export default async function TemplatePage({ params }: { params: Promise<{ uuid: string }> }) {
  const { uuid } = await params;
  return <TemplateEditor uuid={uuid} />;
}
