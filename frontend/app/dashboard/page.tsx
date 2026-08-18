"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { AppShell } from "@/app/components/AppShell";
import { apiFetch } from "@/lib/api";
import { bladeConsoleUrl } from "@/lib/roles";
import type { TicketListItem } from "@/lib/types";
import { useCurrentUser } from "@/lib/use-current-user";

export default function DashboardPage() {
  const { user, loading } = useCurrentUser();
  const [recent, setRecent] = useState<TicketListItem[]>([]);

  useEffect(() => {
    if (!user) {
      return;
    }

    const query =
      user.role === "supervisor" ? "/tickets?mine=1&limit=5" : "/tickets?limit=5";

    apiFetch<{ tickets: TicketListItem[] }>(query)
      .then((data) => setRecent(data.tickets ?? []))
      .catch(() => setRecent([]));
  }, [user]);

  if (loading || !user) {
    return (
      <main>
        <section className="card">
          <p>Loading session…</p>
        </section>
      </main>
    );
  }

  return (
    <AppShell user={user} title="Dashboard">
      <section className="card">
        <p>
          Phase 16 slice 3: Next.js runs reporter, department, president, officer, and admin
          mutations through the Laravel API. Blade remains a parallel console.
        </p>
        <dl className="meta">
          <div>
            <dt>Username</dt>
            <dd>{user.username}</dd>
          </div>
          <div>
            <dt>Role</dt>
            <dd>{user.roleLabel ?? user.role}</dd>
          </div>
          {user.department ? (
            <div>
              <dt>Department</dt>
              <dd>{user.department}</dd>
            </div>
          ) : null}
        </dl>
      </section>

      <section className="card">
        <h2>Recent tickets</h2>
        {recent.length === 0 ? (
          <p>No tickets loaded.</p>
        ) : (
          <ul className="link-list">
            {recent.map((ticket) => (
              <li key={ticket.reference}>
                <Link href={`/tickets/${ticket.reference}`}>
                  {ticket.reference} — {ticket.title ?? "Untitled"} ({ticket.status})
                </Link>
              </li>
            ))}
          </ul>
        )}
        <div className="actions">
          <Link href="/tickets">View all tickets</Link>
          <a className="secondary" href={bladeConsoleUrl(user.role)}>
            Open Blade workflow
          </a>
        </div>
      </section>
    </AppShell>
  );
}
