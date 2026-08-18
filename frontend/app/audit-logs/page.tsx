"use client";

import { FormEvent, useEffect, useState } from "react";
import { AppShell } from "@/app/components/AppShell";
import { apiDownload, apiFetch } from "@/lib/api";
import type { AuditLogItem } from "@/lib/types";
import { useCurrentUser } from "@/lib/use-current-user";

export default function AuditLogsPage() {
  const { user, loading } = useCurrentUser();
  const [logs, setLogs] = useState<AuditLogItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [q, setQ] = useState("");
  const [action, setAction] = useState("");
  const isAdmin = user?.role === "admin";

  function load(search = q, actionFilter = action) {
    const params = new URLSearchParams();
    if (search.trim()) {
      params.set("q", search.trim());
    }
    if (actionFilter) {
      params.set("action", actionFilter);
    }
    const suffix = params.toString() ? `?${params.toString()}` : "";
    return apiFetch<{ logs: AuditLogItem[] }>(`/audit-logs${suffix}`).then((data) => {
      setLogs(data.logs ?? []);
      setError(null);
    });
  }

  useEffect(() => {
    if (!user || !isAdmin) {
      return;
    }
    apiFetch<{ logs: AuditLogItem[] }>("/audit-logs")
      .then((data) => {
        setLogs(data.logs ?? []);
        setError(null);
      })
      .catch((err: Error) => setError(err.message));
  }, [user, isAdmin]);

  async function onSearch(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    try {
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not load audit logs");
    } finally {
      setBusy(false);
    }
  }

  async function onExport() {
    setBusy(true);
    try {
      const params = new URLSearchParams();
      if (q.trim()) {
        params.set("q", q.trim());
      }
      if (action) {
        params.set("action", action);
      }
      const suffix = params.toString() ? `?${params.toString()}` : "";
      await apiDownload(`/audit-logs/export${suffix}`, "audit-logs.csv");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not export audit logs");
    } finally {
      setBusy(false);
    }
  }

  if (loading || !user) {
    return (
      <main>
        <section className="card">
          <p>Loading audit logs…</p>
        </section>
      </main>
    );
  }

  return (
    <AppShell user={user} title="Audit logs">
      <section className="card">
        {!isAdmin ? <p>Administrator access is required.</p> : null}
        {error ? <p className="form-error">{error}</p> : null}
        {isAdmin ? (
          <>
            <form className="filters" onSubmit={onSearch}>
              <label>
                Search
                <input value={q} onChange={(event) => setQ(event.target.value)} />
              </label>
              <label>
                Action
                <input value={action} onChange={(event) => setAction(event.target.value)} />
              </label>
              <div className="actions">
                <button disabled={busy} type="submit">
                  Filter
                </button>
                <button className="secondary" disabled={busy} type="button" onClick={() => void onExport()}>
                  Export CSV
                </button>
              </div>
            </form>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Description</th>
                  </tr>
                </thead>
                <tbody>
                  {logs.map((log) => (
                    <tr key={log.id ?? `${log.at}-${log.username}-${log.action}`}>
                      <td>{log.at ?? "—"}</td>
                      <td>{log.username ?? "—"}</td>
                      <td>{log.action ?? "—"}</td>
                      <td>{log.module ?? "—"}</td>
                      <td>{log.description ?? "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {logs.length === 0 ? <p>No audit logs match the filters.</p> : null}
          </>
        ) : null}
      </section>
    </AppShell>
  );
}
