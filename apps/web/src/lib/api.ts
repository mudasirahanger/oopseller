const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";
const TOKEN_KEY = "oopseller_token";
const ORGANIZATION_KEY = "oopseller_organization_id";

export class ApiError extends Error {
  constructor(message: string, public status: number, public details?: unknown) {
    super(message);
  }
}

export function authStorage() {
  if (typeof window === "undefined") return { token: null, organizationId: null };
  return {
    token: window.localStorage.getItem(TOKEN_KEY),
    organizationId: window.localStorage.getItem(ORGANIZATION_KEY),
  };
}

export function saveAuth(token: string, organizationId: number) {
  window.localStorage.setItem(TOKEN_KEY, token);
  window.localStorage.setItem(ORGANIZATION_KEY, String(organizationId));
}

export function clearAuth() {
  window.localStorage.removeItem(TOKEN_KEY);
  window.localStorage.removeItem(ORGANIZATION_KEY);
}

export async function apiFetch<T>(path: string, options: RequestInit = {}, authenticated = true): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.body && !(options.body instanceof FormData)) headers.set("Content-Type", "application/json");

  if (authenticated) {
    const { token, organizationId } = authStorage();
    if (!token) throw new ApiError("Authentication required.", 401);
    headers.set("Authorization", `Bearer ${token}`);
    if (organizationId) headers.set("X-Organization-Id", organizationId);
  }

  const response = await fetch(`${API_URL}${path}`, { ...options, headers, cache: "no-store" });
  const body = response.status === 204 ? null : await response.json().catch(() => null);

  if (!response.ok) {
    if (response.status === 401 && authenticated && typeof window !== "undefined") {
      clearAuth();
      window.location.assign("/login");
    }
    const validation = body?.errors ? Object.values(body.errors).flat().join(" ") : null;
    throw new ApiError(validation || body?.message || `Request failed (${response.status}).`, response.status, body);
  }

  return body as T;
}
