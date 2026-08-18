"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { AppShell } from "@/app/components/AppShell";
import { TicketForm } from "@/app/components/TicketForm";
import { apiFetch } from "@/lib/api";
import { getAuthToken } from "@/lib/auth-token";
import type { TicketDetail, TicketDraftPayload } from "@/lib/types";
import { useCurrentUser } from "@/lib/use-current-user";

async function uploadEvidence(reference: string, file: File): Promise<void> {
  const token = getAuthToken();
  const body = new FormData();
  body.append("file", file);
  await apiFetch(`/tickets/${encodeURIComponent(reference)}/attachments/upload`, {
    method: "POST",
    body,
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });
}

export default function NewTicketPage() {
  const router = useRouter();
  const { user, loading } = useCurrentUser();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function onSave(payload: TicketDraftPayload, file: File | null, submit: boolean) {
    setBusy(true);
    setError(null);
    try {
      const created = await apiFetch<{ ticket: TicketDetail }>("/tickets", {
        method: "POST",
        body: JSON.stringify(payload),
      });
      const reference = created.ticket.reference;
      if (file) {
        await uploadEvidence(reference, file);
      }
      if (submit) {
        await apiFetch(`/tickets/${encodeURIComponent(reference)}/submit`, { method: "POST" });
      }
      router.push(`/tickets/${encodeURIComponent(reference)}`);
      router.refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not save ticket");
    } finally {
      setBusy(false);
    }
  }

  if (loading || !user) {
    return (
      <main>
        <section className="card">
          <p>Loading…</p>
        </section>
      </main>
    );
  }

  return (
    <AppShell user={user} title="New risk report">
      <section className="card">
        <p>
          Create a draft through <code>POST /api/v1/tickets</code>, attach evidence, then optionally
          submit. Department and later workflow actions live on the ticket detail page.
        </p>
        <TicketForm busy={busy} error={error} evidenceRequired onSave={onSave} />
      </section>
    </AppShell>
  );
}
