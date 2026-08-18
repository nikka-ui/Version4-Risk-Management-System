"use client";

import { useEffect, useState } from "react";

type ApiHealth = {
  status?: string;
  service?: string;
  phase?: number;
  slice?: number;
};

export function ApiStatus() {
  const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "/api/v1";
  const [health, setHealth] = useState<ApiHealth | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    fetch(`${apiBase}/health`, { headers: { Accept: "application/json" } })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json() as Promise<ApiHealth>;
      })
      .then((data) => {
        if (!cancelled) {
          setHealth(data);
          setError(null);
        }
      })
      .catch((err: Error) => {
        if (!cancelled) {
          setHealth(null);
          setError(err.message);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [apiBase]);

  if (error) {
    return <p className="api-status error">API unreachable: {error}</p>;
  }

  if (!health) {
    return <p className="api-status">Loading API health…</p>;
  }

  return (
    <p className="api-status ok">
      API via nginx: {health.service ?? "rms-api"} — phase {health.phase ?? "?"} / slice{" "}
      {health.slice ?? "?"}
    </p>
  );
}
