"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { AppShell } from "@/app/components/AppShell";
import { TicketForm } from "@/app/components/TicketForm";
import { canEditReport, TicketWorkflow } from "@/app/components/TicketWorkflow";
import { apiFetch } from "@/lib/api";
import { getAuthToken } from "@/lib/auth-token";
import type { TicketDetail, TicketDraftPayload } from "@/lib/types";
import { useCurrentUser } from "@/lib/use-current-user";

function fiveFromTicket(ticket: TicketDetail): TicketFiveFields {
  const nested = ticket.fiveW1H ?? {};
  return {
    what: ticket.what ?? nested.what ?? "",
    why: ticket.why ?? nested.why ?? "",
    where: ticket.where ?? nested.where ?? "",
    when: ticket.when ?? nested.when ?? "",
    who: ticket.who ?? nested.who ?? "",
    how: ticket.how ?? nested.how ?? "",
  };
}

type TicketFiveFields = {
  what: string;
  why: string;
  where: string;
  when: string;
  who: string;
  how: string;
};

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

export default function TicketDetailPage() {
  const params = useParams<{ reference: string }>();
  const reference = params.reference;
  const router = useRouter();
  const { user, loading } = useCurrentUser();
  const [ticket, setTicket] = useState<TicketDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [editing, setEditing] = useState(false);

  useEffect(() => {
    if (!user || !reference) {
      return;
    }

    apiFetch<{ ticket: TicketDetail }>(`/tickets/${encodeURIComponent(reference)}`)
      .then((data) => {
        setTicket(data.ticket);
        setError(null);
      })
      .catch((err: Error) => setError(err.message));
  }, [user, reference]);

  const canMutate = !!user && !!ticket && canEditReport(ticket, user);
  const canDelete = canMutate && ticket?.status === "draft";
  const five = ticket ? fiveFromTicket(ticket) : null;

  async function onSave(payload: TicketDraftPayload, file: File | null, submit: boolean) {
    if (!ticket) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const updated = await apiFetch<{ ticket: TicketDetail }>(
        `/tickets/${encodeURIComponent(ticket.reference)}`,
        { method: "PATCH", body: JSON.stringify(payload) },
      );
      if (file) {
        await uploadEvidence(updated.ticket.reference, file);
      }
      let next = updated.ticket;
      if (submit) {
        const submitted = await apiFetch<{ ticket: TicketDetail }>(
          `/tickets/${encodeURIComponent(updated.ticket.reference)}/submit`,
          { method: "POST" },
        );
        next = submitted.ticket;
      }
      setTicket(next);
      setEditing(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not update ticket");
    } finally {
      setBusy(false);
    }
  }

  async function onDelete() {
    if (!ticket || !window.confirm(`Delete draft ${ticket.reference}?`)) {
      return;
    }
    setBusy(true);
    try {
      await apiFetch(`/tickets/${encodeURIComponent(ticket.reference)}`, { method: "DELETE" });
      router.push("/tickets");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not delete draft");
      setBusy(false);
    }
  }

  if (loading || !user) {
    return (
      <main>
        <section className="card">
          <p>Loading ticket…</p>
        </section>
      </main>
    );
  }

  return (
    <AppShell user={user} title={reference}>
      <section className="card">
        <div className="actions">
          <Link className="secondary" href="/tickets">
            Back to tickets
          </Link>
          {canMutate && !editing ? (
            <>
              <button type="button" onClick={() => setEditing(true)}>
                {ticket?.status === "draft" ? "Edit draft" : "Revise report"}
              </button>
              {canDelete ? (
                <button className="secondary" disabled={busy} type="button" onClick={onDelete}>
                  Delete draft
                </button>
              ) : null}
            </>
          ) : null}
        </div>

        {error ? <p className="form-error">{error}</p> : null}

        {ticket && editing && canMutate ? (
          <TicketForm
            busy={busy}
            error={error}
            initial={{
              title: ticket.title ?? "",
              location: ticket.location ?? "",
              mitigationApproach: ticket.mitigationApproach ?? "",
              evidenceCount: ticket.evidenceCount ?? 0,
              ...five,
            }}
            onSave={onSave}
            saveLabel={ticket.status === "draft" ? "Save draft" : "Save revision"}
            submitLabel="Save and submit"
          />
        ) : ticket ? (
          <>
            <h2>{ticket.title ?? "Untitled ticket"}</h2>
            <dl className="meta">
              <div>
                <dt>Status</dt>
                <dd>{ticket.status ?? "—"}</dd>
              </div>
              <div>
                <dt>Department</dt>
                <dd>{ticket.department ?? "—"}</dd>
              </div>
              <div>
                <dt>Ownership</dt>
                <dd>{ticket.ownership?.state ?? "—"}</dd>
              </div>
              <div>
                <dt>Reporter</dt>
                <dd>{ticket.submittedByName ?? ticket.submittedBy ?? "—"}</dd>
              </div>
              <div>
                <dt>Evidence</dt>
                <dd>{ticket.evidenceCount ?? 0}</dd>
              </div>
            </dl>

            <h3>5W1H</h3>
            <dl className="meta">
              {(["what", "why", "where", "when", "who", "how"] as const).map((field) => (
                <div key={field}>
                  <dt>{field}</dt>
                  <dd>{five?.[field] || "—"}</dd>
                </div>
              ))}
            </dl>

            {ticket.actionPlan?.summary ? (
              <>
                <h3>Action plan</h3>
                <p>{ticket.actionPlan.summary}</p>
              </>
            ) : null}

            <TicketWorkflow
              busy={busy}
              ticket={ticket}
              user={user}
              onBusy={setBusy}
              onError={setError}
              onUpdated={setTicket}
            />
          </>
        ) : (
          !error && <p>Loading ticket details…</p>
        )}
      </section>
    </AppShell>
  );
}
