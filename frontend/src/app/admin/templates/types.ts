export type TemplateTask = { uuid: string; title: string; isActive: boolean };
export type TemplateDay = { day: number; task: TemplateTask | null };
export type TemplateSummary = {
  uuid: string;
  name: string;
  description: string | null;
  year: number | null;
  isActive: boolean;
  filledDays: number;
  isComplete: boolean;
  createdAt: string;
  updatedAt: string;
};
export type TemplateDetail = TemplateSummary & { days: TemplateDay[] };
export type TemplateList = {
  items: TemplateSummary[];
  pagination: { page: number; perPage: number; total: number; totalPages: number };
};
export type ApiProblem = { code: string; message: string; details?: { field: string; message: string }[] };

export async function readTemplateResponse<T>(response: Response): Promise<T> {
  if (response.status === 204) return undefined as T;
  const body: unknown = await response.json().catch(() => null);
  if (!response.ok) {
    const problem = body && typeof body === "object" ? body as Partial<ApiProblem> : null;
    throw { code: problem?.code ?? "API_ERROR", message: problem?.message ?? "Не удалось выполнить запрос", details: problem?.details ?? [] } satisfies ApiProblem;
  }
  return body as T;
}
