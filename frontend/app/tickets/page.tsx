"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { AppShell } from "@/app/components/AppShell";
import { apiFetch } from "@/lib/api";
import type { TicketListItem } from "@/lib/types";
import { useCurrentUser } from "@/lib/use-current-user";

export default function TicketsPage() {
  const { user, loading } = useCurrentUser();
  const [tickets, setTickets] = useState<TicketListItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [mineOnly, setMineOnly] = useState(false);

  useEffect(() => {
    if (!user) {
      return;
    }

    const params = new URLSearchParams();
    params.set("limit", "100");
    if (search.trim()) {
      params.set("search", search.trim());
    }
    if (status) {
      params.set("status", status);
    }
    if (mineOnly) {
      params.set("mine", "1");
    }

    apiFetch<{ tickets: TicketListItem[] }>(`/tickets?${params.toString()}`)
      .then((data) => {
        setTickets(data.tickets ?? []);
        setError(null);
      })
      .catch((err: Error) => setError(err.message));
  }, [user, search, status, mineOnly]);

  if (loading || !user) {
    return (
      <main>
        <section className="card">
          <p>Loading tickets…</p>
        </section>
      </main>
    );
  }

  return (
    <AppShell user={user} title="Tickets">
      <section className="card">
        <div className="filters">
          <label>
            Search
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Reference or title"
            />
          </label>
          <label>
            Status
            <select value={status} onChange={(event) => setStatus(event.target.value)}>
              <option value="">All</option>
              <option value="draft">Draft</option>
              <option value="assigned">Assigned</option>
              <option value="in_progress">In progress</option>
              <option value="closed">Closed</option>
            </select>
          </label>
          {user.role === "supervisor" ? (
            <label className="checkbox">
              <input
                checked={mineOnly}
                onChange={(event) => setMineOnly(event.target.checked)}
                type="checkbox"
              />
              My tickets only
            </label>
          ) : null}
        </div>

        {error ? <p className="form-error">{error}</p> : null}

        {user.role === "supervisor" ? (
          <div className="actions">
            <Link href="/tickets/new">New risk report</Link>
          </div>
        ) : null}

        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Reference</th>
                <th>Title</th>
                <th>Status</th>
                <th>Department</th>
                <th>Reporter</th>
              </tr>
            </thead>
            <tbody>
              {tickets.map((ticket) => (
                <tr key={ticket.reference}>
                  <td>
                    <Link href={`/tickets/${ticket.reference}`}>{ticket.reference}</Link>
                  </td>
                  <td>{ticket.title ?? "—"}</td>
                  <td>{ticket.status ?? "—"}</td>
                  <td>{ticket.department ?? "—"}</td>
                  <td>{ticket.submittedByName ?? ticket.submittedBy ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {tickets.length === 0 ? <p>No tickets match your filters.</p> : null}
      </section>
    </AppShell>
  );
}
