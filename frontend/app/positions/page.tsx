"use client";

import { FormEvent, useEffect, useState } from "react";
import { AppShell } from "@/app/components/AppShell";
import { apiFetch } from "@/lib/api";
import type { PositionItem } from "@/lib/types";
import { useCurrentUser } from "@/lib/use-current-user";

export default function PositionsPage() {
  const { user, loading } = useCurrentUser();
  const [positions, setPositions] = useState<PositionItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [name, setName] = useState("");
  const [editingId, setEditingId] = useState<string | null>(null);

  const isAdmin = user?.role === "admin";

  function load() {
    return apiFetch<{ positions: PositionItem[] }>("/positions").then((data) => {
      setPositions(data.positions ?? []);
      setError(null);
    });
  }

  useEffect(() => {
    if (!user) {
      return;
    }

    load().catch((err: Error) => setError(err.message));
  }, [user]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!isAdmin) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      if (editingId) {
        await apiFetch(`/positions/${encodeURIComponent(editingId)}`, {
          method: "PATCH",
          body: JSON.stringify({ name }),
        });
      } else {
        await apiFetch("/positions", {
          method: "POST",
          body: JSON.stringify({ name }),
        });
      }
      setName("");
      setEditingId(null);
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not save position");
    } finally {
      setBusy(false);
    }
  }

  async function onDeactivate(id: string) {
    if (!window.confirm("Deactivate this position?")) {
      return;
    }
    setBusy(true);
    try {
      await apiFetch(`/positions/${encodeURIComponent(id)}`, { method: "DELETE" });
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not deactivate position");
    } finally {
      setBusy(false);
    }
  }

  if (loading || !user) {
    return (
      <main>
        <section className="card">
          <p>Loading positions…</p>
        </section>
      </main>
    );
  }

  return (
    <AppShell user={user} title="Positions">
      <section className="card">
        {error ? <p className="form-error">{error}</p> : null}
        {isAdmin ? (
          <form className="ticket-form" onSubmit={onSubmit}>
            <h2>{editingId ? "Edit position" : "Create position"}</h2>
            <label>
              Name
              <input required value={name} onChange={(event) => setName(event.target.value)} />
            </label>
            <div className="actions">
              <button disabled={busy} type="submit">
                {editingId ? "Save position" : "Create position"}
              </button>
              {editingId ? (
                <button
                  className="secondary"
                  type="button"
                  onClick={() => {
                    setEditingId(null);
                    setName("");
                  }}
                >
                  Cancel
                </button>
              ) : null}
            </div>
          </form>
        ) : null}

        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Active</th>
                {isAdmin ? <th>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {positions.map((position) => (
                <tr key={position.id}>
                  <td>{position.name}</td>
                  <td>{position.active === false ? "No" : "Yes"}</td>
                  {isAdmin ? (
                    <td>
                      <div className="actions">
                        <button
                          className="secondary"
                          type="button"
                          onClick={() => {
                            setEditingId(position.id);
                            setName(position.name);
                          }}
                        >
                          Edit
                        </button>
                        <button
                          className="secondary"
                          disabled={busy}
                          type="button"
                          onClick={() => void onDeactivate(position.id)}
                        >
                          Deactivate
                        </button>
                      </div>
                    </td>
                  ) : null}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {positions.length === 0 ? <p>No positions returned.</p> : null}
      </section>
    </AppShell>
  );
}
