"use client";

import { FormEvent, useEffect, useState } from "react";
import { AppShell } from "@/app/components/AppShell";
import { apiFetch } from "@/lib/api";
import type { SystemSettings } from "@/lib/types";
import { useCurrentUser } from "@/lib/use-current-user";

const empty: SystemSettings = {
  landingTagline: "",
  landingHeadline: "",
  organizationName: "",
  defaultRiskLevels: [],
  emailNotifications: false,
  passwordMinLength: 8,
  sessionTimeoutMinutes: 480,
  mfaEnabled: false,
  maxUploadSizeMb: 25,
  allowedFileTypes: [],
  maintenanceMode: false,
  backupEnabled: false,
  backupFrequency: "daily",
};

export default function SettingsPage() {
  const { user, loading } = useCurrentUser();
  const [settings, setSettings] = useState<SystemSettings>(empty);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const isAdmin = user?.role === "admin";

  function load() {
    return apiFetch<{ settings: SystemSettings }>("/settings").then((data) => {
      setSettings({ ...empty, ...data.settings });
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
      const updated = await apiFetch<{ settings: SystemSettings }>("/settings", {
        method: "PATCH",
        body: JSON.stringify({
          ...settings,
          defaultRiskLevels: (settings.defaultRiskLevels ?? []).join(", "),
          allowedFileTypes: (settings.allowedFileTypes ?? []).join(", "),
        }),
      });
      setSettings({ ...empty, ...updated.settings });
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not save settings");
    } finally {
      setBusy(false);
    }
  }

  async function reset(path: string) {
    setBusy(true);
    try {
      const updated = await apiFetch<{ settings: SystemSettings }>(path, { method: "POST" });
      setSettings({ ...empty, ...updated.settings });
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not reset settings");
    } finally {
      setBusy(false);
    }
  }

  if (loading || !user) {
    return (
      <main>
        <section className="card">
          <p>Loading settings…</p>
        </section>
      </main>
    );
  }

  return (
    <AppShell user={user} title="System settings">
      <section className="card">
        {!isAdmin ? <p>Administrator access is required.</p> : null}
        {error ? <p className="form-error">{error}</p> : null}
        {isAdmin ? (
          <form className="ticket-form" onSubmit={onSubmit}>
            <label>
              Organization name
              <input
                value={settings.organizationName ?? ""}
                onChange={(event) => setSettings({ ...settings, organizationName: event.target.value })}
              />
            </label>
            <label>
              Landing tagline
              <input
                value={settings.landingTagline ?? ""}
                onChange={(event) => setSettings({ ...settings, landingTagline: event.target.value })}
              />
            </label>
            <label>
              Landing headline
              <textarea
                value={settings.landingHeadline ?? ""}
                onChange={(event) => setSettings({ ...settings, landingHeadline: event.target.value })}
              />
            </label>
            <label>
              Default risk levels (comma separated)
              <input
                value={(settings.defaultRiskLevels ?? []).join(", ")}
                onChange={(event) =>
                  setSettings({
                    ...settings,
                    defaultRiskLevels: event.target.value.split(",").map((item) => item.trim()).filter(Boolean),
                  })
                }
              />
            </label>
            <label>
              Password minimum length
              <input
                type="number"
                value={settings.passwordMinLength ?? 8}
                onChange={(event) =>
                  setSettings({ ...settings, passwordMinLength: Number(event.target.value) })
                }
              />
            </label>
            <label>
              Session timeout (minutes)
              <input
                type="number"
                value={settings.sessionTimeoutMinutes ?? 480}
                onChange={(event) =>
                  setSettings({ ...settings, sessionTimeoutMinutes: Number(event.target.value) })
                }
              />
            </label>
            <label>
              Max upload size (MB)
              <input
                type="number"
                value={settings.maxUploadSizeMb ?? 25}
                onChange={(event) =>
                  setSettings({ ...settings, maxUploadSizeMb: Number(event.target.value) })
                }
              />
            </label>
            <label>
              Allowed file types
              <input
                value={(settings.allowedFileTypes ?? []).join(", ")}
                onChange={(event) =>
                  setSettings({
                    ...settings,
                    allowedFileTypes: event.target.value.split(",").map((item) => item.trim()).filter(Boolean),
                  })
                }
              />
            </label>
            <label>
              Backup frequency
              <select
                value={settings.backupFrequency ?? "daily"}
                onChange={(event) => setSettings({ ...settings, backupFrequency: event.target.value })}
              >
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
              </select>
            </label>
            <label className="checkbox">
              <input
                checked={!!settings.emailNotifications}
                onChange={(event) => setSettings({ ...settings, emailNotifications: event.target.checked })}
                type="checkbox"
              />
              Email notifications
            </label>
            <label className="checkbox">
              <input
                checked={!!settings.mfaEnabled}
                onChange={(event) => setSettings({ ...settings, mfaEnabled: event.target.checked })}
                type="checkbox"
              />
              MFA enabled
            </label>
            <label className="checkbox">
              <input
                checked={!!settings.maintenanceMode}
                onChange={(event) => setSettings({ ...settings, maintenanceMode: event.target.checked })}
                type="checkbox"
              />
              Maintenance mode
            </label>
            <label className="checkbox">
              <input
                checked={!!settings.backupEnabled}
                onChange={(event) => setSettings({ ...settings, backupEnabled: event.target.checked })}
                type="checkbox"
              />
              Backups enabled
            </label>
            <div className="actions">
              <button disabled={busy} type="submit">
                Save settings
              </button>
              <button
                className="secondary"
                disabled={busy}
                type="button"
                onClick={() => void reset("/settings/reset-landing")}
              >
                Reset landing
              </button>
              <button
                className="secondary"
                disabled={busy}
                type="button"
                onClick={() => void reset("/settings/reset-ai")}
              >
                Reset AI defaults
              </button>
            </div>
          </form>
        ) : null}
      </section>
    </AppShell>
  );
}
