export type ManagedUser = {
  uuid: string;
  login: string;
  name: string;
  email: string | null;
  role: "ROLE_USER" | "ROLE_ADMIN";
  isActive: boolean;
  isTestUser: boolean;
  createdAt: string;
  updatedAt: string;
};
export type UserList = {
  items: ManagedUser[];
  pagination: { page: number; perPage: number; total: number; totalPages: number };
};
export type AssignedCalendar = {
  uuid: string;
  year: number;
  template: { uuid: string; name: string };
  startedAt: string | null;
  createdAt: string;
  updatedAt: string;
};
export type AssignedCalendarList = {
  items: AssignedCalendar[];
  pagination: { page: number; perPage: number; total: number; totalPages: number };
};
export type ApiProblem = { code: string; message: string; details?: { field: string; message: string }[] };

export async function readResponse<T>(response: Response): Promise<T> {
  const body = await response.json().catch(() => null);
  if (!response.ok) {
    const problem = body as ApiProblem | null;
    throw { code: problem?.code ?? "API_ERROR", message: problem?.message ?? "Не удалось выполнить запрос", details: problem?.details ?? [] } satisfies ApiProblem;
  }
  return body as T;
}
