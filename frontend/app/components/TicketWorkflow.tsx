"use client";

import { FormEvent, useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import type { DepartmentItem, RmsUser, TicketDetail } from "@/lib/types";

type TicketWorkflowProps = {
  ticket: TicketDetail;
  user: RmsUser;
  busy: boolean;
  onBusy: (busy: boolean) => void;
  onError: (message: string | null) => void;
  onUpdated: (ticket: TicketDetail) => void;
};

const EDITABLE_STATUSES = new Set(["draft", "returned", "ownership_rejected"]);
const DEPT_OWN_STATUSES = new Set(["assigned"]);
const DEPT_EXECUTE_STATUSES = new Set(["in_progress", "reopened"]);
const DEPT_REASSIGN_STATUSES = new Set(["assigned", "in_progress", "reopened"]);
const DEPT_CLOSE_STATUSES = new Set(["pending_audit"]);
const PRESIDENT_PLAN_STATUSES = new Set(["pending_president"]);
const PRESIDENT_FINAL_STATUSES = new Set(["pending_president_final"]);
const OFFICER_REOPEN_STATUSES = new Set(["closed", "resolved"]);

async function mutateTicket(
  reference: string,
  path: string,
  method: string,
  body?: Record<string, unknown>,
): Promise<TicketDetail> {
  const data = await apiFetch<{ ticket: TicketDetail }>(
    `/tickets/${encodeURIComponent(reference)}${path}`,
    {
      method,
      body: body ? JSON.stringify(body) : undefined,
    },
  );
  return data.ticket;
}

export function canEditReport(ticket: TicketDetail, user: RmsUser): boolean {
  return (
    EDITABLE_STATUSES.has(ticket.status ?? "") &&
    (user.role === "supervisor" || ticket.submittedBy === user.username)
  );
}

export function TicketWorkflow({
  ticket,
  user,
  busy,
  onBusy,
  onError,
  onUpdated,
}: TicketWorkflowProps) {
  const [departments, setDepartments] = useState<DepartmentItem[]>([]);
  const [comment, setComment] = useState("");
  const [reason, setReason] = useState("");
  const [planSummary, setPlanSummary] = useState(ticket.actionPlan?.summary ?? "");
  const [planSteps, setPlanSteps] = useState((ticket.actionPlan?.steps ?? []).join("\n"));
  const [targetDate, setTargetDate] = useState(ticket.actionPlan?.targetDate ?? "");
  const [submitPlan, setSubmitPlan] = useState(false);
  const [targetDepartment, setTargetDepartment] = useState("");
  const [personName, setPersonName] = useState("");
  const [personRole, setPersonRole] = useState("");
  const [decision, setDecision] = useState("approve");
  const [decisionNote, setDecisionNote] = useState("");

  useEffect(() => {
    apiFetch<{ departments: DepartmentItem[] }>("/departments")
      .then((data) => setDepartments(data.departments ?? []))
      .catch(() => setDepartments([]));
  }, []);

  async function run(action: () => Promise<TicketDetail>) {
    onBusy(true);
    onError(null);
    try {
      const next = await action();
      onUpdated(next);
      setComment("");
      setReason("");
      setPersonName("");
      setPersonRole("");
      setDecisionNote("");
    } catch (err) {
      onError(err instanceof Error ? err.message : "Action failed");
    } finally {
      onBusy(false);
    }
  }

  const status = ticket.status ?? "";
  const isDept = user.role === "dept_head";
  const isPresident = user.role === "president";
  const isOfficer = user.role === "rm_officer";
  const comments = ticket.threadComments ?? [];

  return (
    <div className="workflow">
      {comments.length > 0 ? (
        <section>
          <h3>Thread</h3>
          <ul className="link-list">
            {comments.map((item, index) => (
              <li key={item.id ?? String(index)}>
                <strong>{item.authorName ?? "Unknown"}</strong>
                {item.at ? <span> — {item.at}</span> : null}
                <div>{item.body ?? ""}</div>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <form
        className="ticket-form"
        onSubmit={(event: FormEvent) => {
          event.preventDefault();
          void run(() =>
            mutateTicket(ticket.reference, "/comments", "POST", { comment }),
          );
        }}
      >
        <h3>Add comment</h3>
        <label>
          Comment
          <textarea
            required
            value={comment}
            onChange={(event) => setComment(event.target.value)}
          />
        </label>
        <div className="actions">
          <button disabled={busy} type="submit">
            Post comment
          </button>
        </div>
      </form>

      {isDept && DEPT_OWN_STATUSES.has(status) ? (
        <section className="ticket-form">
          <h3>Ownership</h3>
          <label>
            Optional note / reject reason
            <textarea value={reason} onChange={(event) => setReason(event.target.value)} />
          </label>
          <div className="actions">
            <button
              disabled={busy}
              type="button"
              onClick={() =>
                void run(() =>
                  mutateTicket(ticket.reference, "/accept", "POST", { comment: reason }),
                )
              }
            >
              Accept ownership
            </button>
            <button
              className="secondary"
              disabled={busy}
              type="button"
              onClick={() =>
                void run(() =>
                  mutateTicket(ticket.reference, "/reject", "POST", { reason }),
                )
              }
            >
              Reject
            </button>
          </div>
        </section>
      ) : null}

      {isDept && DEPT_EXECUTE_STATUSES.has(status) ? (
        <>
          <form
            className="ticket-form"
            onSubmit={(event) => {
              event.preventDefault();
              void run(() =>
                mutateTicket(ticket.reference, "/action-plan", "PUT", {
                  summary: planSummary,
                  steps: planSteps,
                  targetDate,
                  submitForReview: submitPlan,
                }),
              );
            }}
          >
            <h3>Action plan</h3>
            <label>
              Summary
              <textarea
                required
                value={planSummary}
                onChange={(event) => setPlanSummary(event.target.value)}
              />
            </label>
            <label>
              Steps (one per line)
              <textarea
                value={planSteps}
                onChange={(event) => setPlanSteps(event.target.value)}
              />
            </label>
            <label>
              Target date
              <input
                type="date"
                value={targetDate}
                onChange={(event) => setTargetDate(event.target.value)}
              />
            </label>
            <label className="checkbox">
              <input
                checked={submitPlan}
                onChange={(event) => setSubmitPlan(event.target.checked)}
                type="checkbox"
              />
              Submit for review
            </label>
            <div className="actions">
              <button disabled={busy} type="submit">
                Save action plan
              </button>
            </div>
          </form>

          <form
            className="ticket-form"
            onSubmit={(event) => {
              event.preventDefault();
              void run(() =>
                mutateTicket(ticket.reference, "/return", "POST", { reason }),
              );
            }}
          >
            <h3>Return for revision</h3>
            <label>
              Reason
              <textarea
                required
                value={reason}
                onChange={(event) => setReason(event.target.value)}
              />
            </label>
            <div className="actions">
              <button className="secondary" disabled={busy} type="submit">
                Return to reporter
              </button>
            </div>
          </form>

          <form
            className="ticket-form"
            onSubmit={(event) => {
              event.preventDefault();
              void run(() =>
                mutateTicket(ticket.reference, "/personnel", "POST", {
                  personName,
                  personRole,
                }),
              );
            }}
          >
            <h3>Assign personnel</h3>
            <label>
              Name
              <input
                required
                value={personName}
                onChange={(event) => setPersonName(event.target.value)}
              />
            </label>
            <label>
              Role
              <input
                value={personRole}
                onChange={(event) => setPersonRole(event.target.value)}
              />
            </label>
            <div className="actions">
              <button disabled={busy} type="submit">
                Assign
              </button>
            </div>
          </form>
        </>
      ) : null}

      {isDept && DEPT_REASSIGN_STATUSES.has(status) ? (
        <form
          className="ticket-form"
          onSubmit={(event) => {
            event.preventDefault();
            void run(() =>
              mutateTicket(ticket.reference, "/reassign", "POST", {
                reason,
                targetDepartment,
              }),
            );
          }}
        >
          <h3>Reassign</h3>
          <label>
            Target department
            <select
              required
              value={targetDepartment}
              onChange={(event) => setTargetDepartment(event.target.value)}
            >
              <option value="">Select department</option>
              {departments.map((dept) => (
                <option key={dept.id} value={dept.name}>
                  {dept.name}
                </option>
              ))}
            </select>
          </label>
          <label>
            Reason
            <textarea
              required
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            />
          </label>
          <div className="actions">
            <button className="secondary" disabled={busy} type="submit">
              Reassign
            </button>
          </div>
        </form>
      ) : null}

      {isDept && DEPT_CLOSE_STATUSES.has(status) ? (
        <form
          className="ticket-form"
          onSubmit={(event) => {
            event.preventDefault();
            void run(() =>
              mutateTicket(ticket.reference, "/close", "POST", {
                closingNotes: reason,
              }),
            );
          }}
        >
          <h3>Close ticket</h3>
          <label>
            Closing notes
            <textarea value={reason} onChange={(event) => setReason(event.target.value)} />
          </label>
          <div className="actions">
            <button disabled={busy} type="submit">
              Close after review
            </button>
          </div>
        </form>
      ) : null}

      {isPresident &&
      (PRESIDENT_PLAN_STATUSES.has(status) || PRESIDENT_FINAL_STATUSES.has(status)) ? (
        <form
          className="ticket-form"
          onSubmit={(event) => {
            event.preventDefault();
            void run(() =>
              mutateTicket(ticket.reference, "/president-decision", "POST", {
                decision,
                note: decisionNote,
              }),
            );
          }}
        >
          <h3>Presidential decision</h3>
          <label>
            Decision
            <select value={decision} onChange={(event) => setDecision(event.target.value)}>
              {PRESIDENT_FINAL_STATUSES.has(status) ? (
                <>
                  <option value="approve">Approve</option>
                  <option value="close">Close</option>
                  <option value="return">Return</option>
                </>
              ) : (
                <>
                  <option value="approve">Approve</option>
                  <option value="reject">Reject</option>
                  <option value="return">Return</option>
                </>
              )}
            </select>
          </label>
          <label>
            Note
            <textarea
              value={decisionNote}
              onChange={(event) => setDecisionNote(event.target.value)}
            />
          </label>
          <div className="actions">
            <button disabled={busy} type="submit">
              Record decision
            </button>
          </div>
        </form>
      ) : null}

      {isOfficer && OFFICER_REOPEN_STATUSES.has(status) ? (
        <form
          className="ticket-form"
          onSubmit={(event) => {
            event.preventDefault();
            void run(() =>
              mutateTicket(ticket.reference, "/reopen", "POST", {
                reason,
                department: targetDepartment || ticket.department,
              }),
            );
          }}
        >
          <h3>Reopen ticket</h3>
          <label>
            Department
            <select
              value={targetDepartment}
              onChange={(event) => setTargetDepartment(event.target.value)}
            >
              <option value="">{ticket.department ?? "Current department"}</option>
              {departments.map((dept) => (
                <option key={dept.id} value={dept.name}>
                  {dept.name}
                </option>
              ))}
            </select>
          </label>
          <label>
            Reason
            <textarea
              required
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            />
          </label>
          <div className="actions">
            <button disabled={busy} type="submit">
              Reopen
            </button>
          </div>
        </form>
      ) : null}
    </div>
  );
}
