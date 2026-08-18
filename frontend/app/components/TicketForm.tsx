"use client";

import { FormEvent, useState } from "react";
import type { TicketDraftPayload } from "@/lib/types";

const FIELDS: Array<{ key: keyof TicketDraftPayload; label: string; textarea?: boolean }> = [
  { key: "title", label: "Title" },
  { key: "location", label: "Location" },
  { key: "what", label: "What", textarea: true },
  { key: "why", label: "Why", textarea: true },
  { key: "where", label: "Where", textarea: true },
  { key: "when", label: "When" },
  { key: "who", label: "Who" },
  { key: "how", label: "How", textarea: true },
  { key: "mitigationApproach", label: "Mitigation approach", textarea: true },
];

type TicketFormProps = {
  initial?: Partial<TicketDraftPayload>;
  evidenceRequired?: boolean;
  error?: string | null;
  busy?: boolean;
  saveLabel?: string;
  submitLabel?: string;
  onSave: (payload: TicketDraftPayload, file: File | null, submit: boolean) => Promise<void>;
};

export function TicketForm({
  initial,
  evidenceRequired = false,
  error,
  busy = false,
  saveLabel = "Save draft",
  submitLabel = "Save and submit",
  onSave,
}: TicketFormProps) {
  const [values, setValues] = useState<TicketDraftPayload>({
    title: initial?.title ?? "",
    location: initial?.location ?? "",
    what: initial?.what ?? "",
    why: initial?.why ?? "",
    where: initial?.where ?? "",
    when: initial?.when ?? "",
    who: initial?.who ?? "",
    how: initial?.how ?? "",
    mitigationApproach: initial?.mitigationApproach ?? "",
    evidenceCount: initial?.evidenceCount ?? 0,
  });
  const [file, setFile] = useState<File | null>(null);
  const [intent, setIntent] = useState<"draft" | "submit">("draft");

  function update(key: keyof TicketDraftPayload, value: string) {
    setValues((current) => ({ ...current, [key]: value }));
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const evidenceCount = file || values.evidenceCount > 0 ? Math.max(1, values.evidenceCount || 1) : 0;
    await onSave({ ...values, evidenceCount: Math.max(evidenceCount, 1) }, file, intent === "submit");
  }

  return (
    <form className="ticket-form" onSubmit={handleSubmit}>
      {FIELDS.map((field) => (
        <label key={field.key}>
          {field.label}
          {field.textarea ? (
            <textarea
              name={field.key}
              required={field.key !== "mitigationApproach"}
              rows={3}
              value={String(values[field.key] ?? "")}
              onChange={(event) => update(field.key, event.target.value)}
            />
          ) : (
            <input
              name={field.key}
              required={field.key !== "mitigationApproach"}
              value={String(values[field.key] ?? "")}
              onChange={(event) => update(field.key, event.target.value)}
            />
          )}
        </label>
      ))}
      <label>
        Evidence file {evidenceRequired ? "(PDF, PNG, or JPG — required)" : "(optional if already attached)"}
        <input
          accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
          name="evidence"
          required={evidenceRequired && values.evidenceCount < 1}
          type="file"
          onChange={(event) => setFile(event.target.files?.[0] ?? null)}
        />
      </label>
      {error ? <p className="form-error">{error}</p> : null}
      <div className="actions">
        <button disabled={busy} type="submit" onClick={() => setIntent("draft")}>
          {busy && intent === "draft" ? "Saving…" : saveLabel}
        </button>
        <button
          className="secondary"
          disabled={busy}
          type="submit"
          onClick={() => setIntent("submit")}
        >
          {busy && intent === "submit" ? "Submitting…" : submitLabel}
        </button>
      </div>
    </form>
  );
}
