export type ManagedTask = {
  uuid: string;
  title: string;
  content: string;
  imageUrl: string | null;
  answerType: "NONE" | "NUMBER" | "TEXT" | "MULTIPLE_CHOICE";
  choices: string[] | null;
  correctAnswer: string | null;
  solution: string | null;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
};
export type TaskList = {
  items: ManagedTask[];
  pagination: { page: number; perPage: number; total: number; totalPages: number };
};
export type ApiProblem = { code: string; message: string; details?: { field: string; message: string }[] };

export async function readTaskResponse<T>(response: Response): Promise<T> {
  if (response.status === 204) return undefined as T;
  const body: unknown = await response.json().catch(() => null);
  if (!response.ok) {
    const problem = body && typeof body === "object" ? body as Partial<ApiProblem> : null;
    throw { code: problem?.code ?? "API_ERROR", message: problem?.message ?? "Не удалось выполнить запрос", details: problem?.details ?? [] } satisfies ApiProblem;
  }
  return body as T;
}
