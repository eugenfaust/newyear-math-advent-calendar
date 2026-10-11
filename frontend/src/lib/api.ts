import "server-only";

export type ApiErrorCode = "UNAUTHENTICATED" | "ACCESS_DENIED" | string;
export type ApiUser = { uuid: string; login: string; displayName: string; roles: string[] };
export type LoginResponse = { accessToken: string; expiresAt: string; user: ApiUser };
export type ApiErrorBody = { code: ApiErrorCode; message: string };

export class ApiError extends Error {
  constructor(public readonly status: number, public readonly code: ApiErrorCode, message: string) {
    super(message);
    this.name = "ApiError";
  }
}

export async function apiRequest<T>(path: `/api/v1/${string}`, token: string, init: RequestInit = {}): Promise<T> {
  const baseUrl = process.env.INTERNAL_API_URL;
  if (!baseUrl) throw new Error("INTERNAL_API_URL is not configured");
  const headers = new Headers(init.headers);
  headers.set("Authorization", `Bearer ${token}`);
  const response = await fetch(`${baseUrl}${path.slice(7)}`, {
    ...init,
    cache: "no-store",
    headers,
  });
  if (response.status === 204) return undefined as T;
  const body: unknown = await response.json().catch(() => null);
  if (!response.ok) {
    const error = isApiErrorBody(body) ? body : { code: response.status === 401 ? "UNAUTHENTICATED" : response.status === 403 ? "ACCESS_DENIED" : "API_ERROR", message: "Ошибка сервера" };
    throw new ApiError(response.status, error.code, error.message);
  }
  return body as T;
}

function isApiErrorBody(value: unknown): value is ApiErrorBody {
  return typeof value === "object" && value !== null && "code" in value && typeof value.code === "string" && "message" in value && typeof value.message === "string";
}
