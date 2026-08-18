"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { apiFetch } from "@/lib/api";
import { clearAuthToken } from "@/lib/auth-token";
import { bladeConsoleUrl } from "@/lib/roles";
import type { RmsUser } from "@/lib/types";

type AppShellProps = {
  user: RmsUser;
  title: string;
  children: React.ReactNode;
};

export function AppShell({ user, title, children }: AppShellProps) {
  const router = useRouter();

  async function onLogout() {
    try {
      await apiFetch("/auth/logout", { method: "POST" });
    } catch {
      // ignore
    } finally {
      clearAuthToken();
      router.replace("/login");
      router.refresh();
    }
  }

  return (
    <div className="app-shell">
      <header className="app-header">
        <div>
          <p className="app-kicker">RMS Next.js</p>
          <h1>{title}</h1>
        </div>
        <div className="app-user">
          <span>{user.displayName ?? user.username}</span>
          <span className="app-role">{user.roleLabel ?? user.role}</span>
          <button type="button" onClick={onLogout}>
            Sign out
          </button>
        </div>
      </header>
      <nav className="app-nav">
        <Link href="/dashboard">Dashboard</Link>
        <Link href="/tickets">Tickets</Link>
        {user.role === "supervisor" ? <Link href="/tickets/new">New report</Link> : null}
        <Link href="/notifications">Notifications</Link>
        <Link href="/departments">Departments</Link>
        {user.role === "admin" ? <Link href="/positions">Positions</Link> : null}
        {user.role === "admin" ? <Link href="/users">Users</Link> : null}
        {user.role === "admin" ? <Link href="/settings">Settings</Link> : null}
        {user.role === "admin" ? <Link href="/audit-logs">Audit logs</Link> : null}
        <a href={bladeConsoleUrl(user.role)}>Blade console</a>
        <Link className="secondary" href="/">
          Scaffold
        </Link>
      </nav>
      <div className="app-content">{children}</div>
    </div>
  );
}
