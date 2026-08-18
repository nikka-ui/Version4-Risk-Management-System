const BLADE_BASE = process.env.NEXT_PUBLIC_BLADE_URL?.replace(/\/login\/?$/, "") ?? "http://localhost:8080";

export function bladeConsoleUrl(role: string): string {
  const paths: Record<string, string> = {
    supervisor: "/supervisor",
    dept_head: "/dept",
    rm_officer: "/officer",
    executive: "/executive",
    president: "/president",
    admin: "/admin",
    employee: "/dashboard",
  };

  return `${BLADE_BASE}${paths[role] ?? "/dashboard"}`;
}

export function roleLabel(role: string): string {
  const labels: Record<string, string> = {
    supervisor: "Ticket Reporter",
    dept_head: "Department Head",
    rm_officer: "Risk Management Officer",
    executive: "Executive Committee",
    president: "President",
    admin: "System Administrator",
    employee: "Employee",
  };

  return labels[role] ?? role;
}
