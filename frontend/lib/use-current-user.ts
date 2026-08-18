"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { apiFetch } from "./api";
import { getAuthToken } from "./auth-token";
import type { RmsUser } from "./types";

export function useCurrentUser() {
  const router = useRouter();
  const [user, setUser] = useState<RmsUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!getAuthToken()) {
      router.replace("/login");
      return;
    }

    apiFetch<{ user: RmsUser }>("/users/me")
      .then((data) => {
        setUser(data.user);
        setError(null);
      })
      .catch((err: Error) => {
        setError(err.message);
        router.replace("/login");
      })
      .finally(() => setLoading(false));
  }, [router]);

  return { user, loading, error };
}
