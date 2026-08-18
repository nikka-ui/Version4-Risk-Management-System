const STORAGE_KEY = "rms.sanctumToken";

export function getAuthToken(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  return window.sessionStorage.getItem(STORAGE_KEY);
}

export function setAuthToken(token: string): void {
  window.sessionStorage.setItem(STORAGE_KEY, token);
}

export function clearAuthToken(): void {
  window.sessionStorage.removeItem(STORAGE_KEY);
}
