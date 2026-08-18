"use client";

import { FormEvent, useEffect, useState } from "react";
import { AppShell } from "@/app/components/AppShell";
import { apiFetch } from "@/lib/api";
import type { DepartmentItem } from "@/lib/types";
import { useCurrentUser } from "@/lib/use-current-user";

const emptyForm = {
  name: "",
  code: "",
  description: "",
  head: "",
};

export default function DepartmentsPage() {
  const { user, loading } = useCurrentUser();
  const [departments, setDepartments] = useState<DepartmentItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState<string | null>(null);

  const isAdmin = user?.role === "admin";

  function load() {
    return apiFetch<{ departments: DepartmentItem[] }>("/departments").then((data) => {
      setDepartments(data.departments ?? []);
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
        await apiFetch(`/departments/${encodeURIComponent(editingId)}`, {
          method: "PATCH",
          body: JSON.stringify(form),
        });
      } else {
        await apiFetch("/departments", {
          method: "POST",
          body: JSON.stringify(form),
        });
      }
      setForm(emptyForm);
      setEditingId(null);
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not save department");
    } finally {
      setBusy(false);
    }
  }

  async function onDeactivate(id: string) {
    if (!window.confirm("Deactivate this department?")) {
      return;
    }
    setBusy(true);
    try {
      await apiFetch(`/departments/${encodeURIComponent(id)}`, { method: "DELETE" });
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not deactivate department");
    } finally {
      setBusy(false);
    }
  }

  if (loading || !user) {
    return (
      <main>
        <section className="card">
          <p>Loading departments…</p>
        </section>
      </main>
    );
  }

  return (
    <AppShell user={user} title="Departments">
      <section className="card">
        {error ? <p className="form-error">{error}</p> : null}
        {isAdmin ? (
          <form className="ticket-form" onSubmit={onSubmit}>
            <h2>{editingId ? "Edit department" : "Create department"}</h2>
            <label>
              Name
              <input
                required
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
              />
            </label>
            <label>
              Code
              <input
                required
                value={form.code}
                onChange={(event) => setForm({ ...form, code: event.target.value })}
              />
            </label>
            <label>
              Description
              <textarea
                value={form.description}
                onChange={(event) => setForm({ ...form, description: event.target.value })}
              />
            </label>
            <label>
              Head
              <input
                value={form.head}
                onChange={(event) => setForm({ ...form, head: event.target.value })}
              />
            </label>
            <div className="actions">
              <button disabled={busy} type="submit">
                {editingId ? "Save department" : "Create department"}
              </button>
              {editingId ? (
                <button
                  className="secondary"
                  type="button"
                  onClick={() => {
                    setEditingId(null);
                    setForm(emptyForm);
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
                <th>Code</th>
                <th>Head</th>
                <th>Active</th>
                {isAdmin ? <th>Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {departments.map((dept) => (
                <tr key={dept.id}>
                  <td>{dept.name}</td>
                  <td>{dept.code ?? "—"}</td>
                  <td>{dept.head ?? "—"}</td>
                  <td>{dept.active === false ? "No" : "Yes"}</td>
                  {isAdmin ? (
                    <td>
                      <div className="actions">
                        <button
                          className="secondary"
                          type="button"
                          onClick={() => {
                            setEditingId(dept.id);
                            setForm({
                              name: dept.name,
                              code: dept.code ?? "",
                              description: dept.description ?? "",
                              head: dept.head ?? "",
                            });
                          }}
                        >
                          Edit
                        </button>
                        <button
                          className="secondary"
                          disabled={busy}
                          type="button"
                          onClick={() => void onDeactivate(dept.id)}
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
        {departments.length === 0 ? <p>No departments returned.</p> : null}
      </section>
    </AppShell>
  );
}
