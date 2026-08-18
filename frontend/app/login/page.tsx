"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { apiFetch } from "@/lib/api";
import { setAuthToken } from "@/lib/auth-token";

type TokenResponse = {
  token: string;
  token_type: string;
  user: {
    username: string;
    role: string;
    displayName?: string;
  };
};

export default function LoginPage() {
  const router = useRouter();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const data = await apiFetch<TokenResponse>("/auth/token", {
        method: "POST",
        auth: false,
        body: JSON.stringify({
          username,
          password,
          device_name: "rms-frontend",
        }),
      });

      setAuthToken(data.token);
      router.push("/dashboard");
      router.refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Login failed");
    } finally {
      setLoading(false);
    }
  }

  return (
    <main>
      <section className="card">
        <h1>Next.js sign in</h1>
        <p>
          Sign in with Sanctum bearer tokens. Next.js at <code>/app</code> now runs reporter,
          department, president, officer, and admin mutations.
        </p>
        <form className="login-form" onSubmit={onSubmit}>
          <label>
            Username
            <input
              autoComplete="username"
              name="username"
              required
              value={username}
              onChange={(event) => setUsername(event.target.value)}
            />
          </label>
          <label>
            Password
            <input
              autoComplete="current-password"
              name="password"
              required
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
            />
          </label>
          {error ? <p className="form-error">{error}</p> : null}
          <div className="actions">
            <button disabled={loading} type="submit">
              {loading ? "Signing in…" : "Sign in"}
            </button>
            <Link className="secondary" href="/">
              Back to scaffold
            </Link>
          </div>
        </form>
      </section>
    </main>
  );
}
