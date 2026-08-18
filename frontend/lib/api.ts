import { clearAuthToken, getAuthToken } from "./auth-token";

export const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "/api/v1";

type ApiOptions = Omit<RequestInit, "headers"> & {
  headers?: Record<string, string>;
  auth?: boolean;
};

export async function apiFetch<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    ...options.headers,
  };

  if (options.body !== undefined && !(options.body instanceof FormData) && !headers["Content-Type"]) {
    headers["Content-Type"] = "application/json";
  }

  if (options.auth !== false) {
    const token = getAuthToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
  }

  const response = await fetch(`${apiBase}${path}`, {
    ...options,
    headers,
  });

  if (response.status === 401) {
    clearAuthToken();
  }

  let payload: unknown = null;
  const text = await response.text();
  if (text) {
    payload = JSON.parse(text) as unknown;
  }

  if (!response.ok) {
    const payloadObject =
      typeof payload === "object" && payload !== null
        ? (payload as Record<string, unknown>)
        : null;

    const fieldErrors = payloadObject?.errors;
    if (typeof fieldErrors === "object" && fieldErrors !== null) {
      const firstField = Object.values(fieldErrors as Record<string, unknown>)[0];
      if (Array.isArray(firstField) && typeof firstField[0] === "string") {
        throw new Error(firstField[0]);
      }
    }

    const message =
      typeof payloadObject?.message === "string"
        ? payloadObject.message
        : `HTTP ${response.status}`;

    throw new Error(message);
  }

  return payload as T;
}

export async function apiDownload(path: string, filename: string): Promise<void> {
  const headers: Record<string, string> = { Accept: "*/*" };
  const token = getAuthToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${apiBase}${path}`, { headers });
  if (response.status === 401) {
    clearAuthToken();
  }
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
