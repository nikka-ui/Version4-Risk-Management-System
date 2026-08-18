"use client";

import { useEffect, useState } from "react";
import { AppShell } from "@/app/components/AppShell";
import { apiFetch } from "@/lib/api";
import type { NotificationItem } from "@/lib/types";
import { useCurrentUser } from "@/lib/use-current-user";

export default function NotificationsPage() {
  const { user, loading } = useCurrentUser();
  const [items, setItems] = useState<NotificationItem[]>([]);
  const [unread, setUnread] = useState(0);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!user) {
      return;
    }

    Promise.all([
      apiFetch<{ notifications: NotificationItem[] }>("/notifications"),
      apiFetch<{ unread: number }>("/notifications/unread-count"),
    ])
      .then(([list, count]) => {
        setItems(list.notifications ?? []);
        setUnread(count.unread ?? 0);
        setError(null);
      })
      .catch((err: Error) => setError(err.message));
  }, [user]);

  async function markAllRead() {
    try {
      await apiFetch("/notifications/read-all", { method: "POST" });
      setUnread(0);
      setItems((current) => current.map((item) => ({ ...item, read: true })));
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to mark read");
    }
  }

  if (loading || !user) {
    return (
      <main>
        <section className="card">
          <p>Loading notifications…</p>
        </section>
      </main>
    );
  }

  return (
    <AppShell user={user} title="Notifications">
      <section className="card">
        <p>
          Unread: <strong>{unread}</strong>
        </p>
        {error ? <p className="form-error">{error}</p> : null}
        <div className="actions">
          <button type="button" onClick={markAllRead}>
            Mark all read
          </button>
        </div>
        <ul className="link-list">
          {items.map((item) => (
            <li key={String(item.id)} className={item.read ? "muted" : ""}>
              <strong>{item.title ?? "Notification"}</strong>
              {item.body ? <span> — {item.body}</span> : null}
            </li>
          ))}
        </ul>
        {items.length === 0 ? <p>No notifications.</p> : null}
      </section>
    </AppShell>
  );
}
