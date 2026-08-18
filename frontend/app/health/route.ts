export async function GET() {
  return Response.json({
    status: "ok",
    service: "rms-frontend",
    framework: "nextjs",
    version: "14",
    phase: 16,
    slice: 3,
    migration: "complete",
    apiBase: process.env.NEXT_PUBLIC_API_URL ?? "/api/v1",
    basePath: process.env.NEXT_PUBLIC_BASE_PATH ?? "",
    auth: "sanctum-bearer",
    pages: [
      "login",
      "dashboard",
      "tickets",
      "tickets/new",
      "notifications",
      "departments",
      "positions",
      "users",
      "settings",
      "audit-logs",
    ],
  });
}
