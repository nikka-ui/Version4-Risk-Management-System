"use client";

import { FormEvent, useEffect, useState } from "react";
import { AppShell } from "@/app/components/AppShell";
import { apiFetch } from "@/lib/api";
import type { AdminUserItem } from "@/lib/types";
import { useCurrentUser } from "@/lib/use-current-user";

const emptyForm = {
  username: "",
  displayName: "",
  email: "",
  department: "",
  position: "",
  role: "supervisor",
  password: "",
  confirmPassword: "",
  status: "active",
};

export default function UsersPage() {
  const { user, loading } = useCurrentUser();
  const [users, setUsers] = useState<AdminUserItem[]>([]);
  const [roles, setRoles] = useState<Array<{ id: string; label: string }>>([]);
  const [departments, setDepartments] = useState<Array<{ name: string }>>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [editing, setEditing] = useState<string | null>(null);

  const isAdmin = user?.role === "admin";

  function load() {
    return apiFetch<{
      users: AdminUserItem[];
      roles: Array<{ id: string; label: string }>;
      departments: Array<{ name: string }>;
    }>("/users").then((data) => {
      setUsers(data.users ?? []);
      setRoles(data.roles ?? []);
      setDepartments(data.departments ?? []);
      setError(null);
    });
  }

  useEffect(() => {
    if (!user || !isAdmin) {
      return;
    }
    load().catch((err: Error) => setError(err.message));
  }, [user, isAdmin]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      if (editing) {
        await apiFetch(`/users/${encodeURIComponent(editing)}`, {
          method: "PATCH",
          body: JSON.stringify({
            displayName: form.displayName,
            email: form.email,
            department: form.department,
            position: form.position,
            role: form.role,
            status: form.status,
          }),
        });
      } else {
        await apiFetch("/users", {
          method: "POST",
          body: JSON.stringify(form),
        });
      }
      setForm(emptyForm);
      setEditing(null);
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not save user");
    } finally {
      setBusy(false);
    }
  }

  async function onToggle(username: string, active: boolean) {
    setBusy(true);
    try {
      await apiFetch(`/users/${encodeURIComponent(username)}/${active ? "activate" : "deactivate"}`, {
        method: "POST",
      });
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not update status");
    } finally {
      setBusy(false);
    }
  }

  async function onReset(username: string) {
    const password = window.prompt(`New password for ${username} (min 6 characters)`);
    if (!password) {
      return;
    }
    setBusy(true);
    try {
      await apiFetch(`/users/${encodeURIComponent(username)}/reset-password`, {
        method: "POST",
        body: JSON.stringify({ password, confirmPassword: password }),
      });
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not reset password");
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(username: string) {
    if (!window.confirm(`Delete ${username}?`)) {
      return;
    }
    setBusy(true);
    try {
      await apiFetch(`/users/${encodeURIComponent(username)}`, { method: "DELETE" });
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not delete user");
    } finally {
      setBusy(false);
    }
  }

  if (loading || !user) {
    return (
      <main>
        <section className="card">
          <p>Loading users…</p>
        </section>
      </main>
    );
  }

  return (
    <AppShell user={user} title="Users">
      <section className="card">
        {!isAdmin ? <p>Administrator access is required.</p> : null}
        {error ? <p className="form-error">{error}</p> : null}
        {isAdmin ? (
          <form className="ticket-form" onSubmit={onSubmit}>
            <h2>{editing ? `Edit ${editing}` : "Create user"}</h2>
            {editing ? null : (
              <label>
                Username
                <input
                  required
                  value={form.username}
                  onChange={(event) => setForm({ ...form, username: event.target.value })}
                />
              </label>
            )}
            <label>
              Display name
              <input
                required
                value={form.displayName}
                onChange={(event) => setForm({ ...form, displayName: event.target.value })}
              />
            </label>
            <label>
              Email
              <input
                type="email"
                value={form.email}
                onChange={(event) => setForm({ ...form, email: event.target.value })}
              />
            </label>
            <label>
              Department
              <select
                value={form.department}
                onChange={(event) => setForm({ ...form, department: event.target.value })}
              >
                <option value="">Select department</option>
                {departments.map((dept) => (
                  <option key={dept.name} value={dept.name}>
                    {dept.name}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Position
              <input
                value={form.position}
                onChange={(event) => setForm({ ...form, position: event.target.value })}
              />
            </label>
            <label>
              Role
              <select
                value={form.role}
                onChange={(event) => setForm({ ...form, role: event.target.value })}
              >
                {roles.map((role) => (
                  <option key={role.id} value={role.id}>
                    {role.label}
                  </option>
                ))}
              </select>
            </label>
            {editing ? null : (
              <>
                <label>
                  Password
                  <input
                    required
                    type="password"
                    value={form.password}
                    onChange={(event) => setForm({ ...form, password: event.target.value })}
                  />
                </label>
                <label>
                  Confirm password
                  <input
                    required
                    type="password"
                    value={form.confirmPassword}
                    onChange={(event) => setForm({ ...form, confirmPassword: event.target.value })}
                  />
                </label>
              </>
            )}
            <div className="actions">
              <button disabled={busy} type="submit">
                {editing ? "Save user" : "Create user"}
              </button>
              {editing ? (
                <button
                  className="secondary"
                  type="button"
                  onClick={() => {
                    setEditing(null);
                    setForm(emptyForm);
                  }}
                >
                  Cancel
                </button>
              ) : null}
            </div>
          </form>
        ) : null}

        {isAdmin ? (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Username</th>
                  <th>Name</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {users.map((item) => (
                  <tr key={item.username}>
                    <td>{item.username}</td>
                    <td>{item.displayName ?? item.username}</td>
                    <td>{item.roleLabel ?? item.role}</td>
                    <td>{item.status ?? (item.active ? "active" : "inactive")}</td>
                    <td>
                      <div className="actions">
                        <button
                          className="secondary"
                          type="button"
                          onClick={() => {
                            setEditing(item.username);
                            setForm({
                              ...emptyForm,
                              username: item.username,
                              displayName: item.displayName ?? "",
                              email: item.email ?? "",
                              department: item.department ?? "",
                              position: item.position ?? "",
                              role: item.role,
                              status: item.status ?? "active",
                            });
                          }}
                        >
                          Edit
                        </button>
                        <button
                          className="secondary"
                          disabled={busy}
                          type="button"
                          onClick={() => void onToggle(item.username, item.active === false)}
                        >
                          {item.active === false ? "Activate" : "Deactivate"}
                        </button>
                        <button
                          className="secondary"
                          disabled={busy}
                          type="button"
                          onClick={() => void onReset(item.username)}
                        >
                          Reset password
                        </button>
                        <button
                          className="secondary"
                          disabled={busy}
                          type="button"
                          onClick={() => void onDelete(item.username)}
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </section>
    </AppShell>
  );
}
