import Link from "next/link";
import { ApiStatus } from "./components/ApiStatus";

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? "/api/v1";
const basePath = process.env.NEXT_PUBLIC_BASE_PATH ?? "";
const bladeUrl = process.env.NEXT_PUBLIC_BLADE_URL ?? "http://localhost:8080/login";

export default function HomePage() {
  return (
    <main>
      <section className="card">
        <h1>RMS Next.js</h1>
        <p>
          Next.js at <code>/app</code> owns reporter, workflow, and admin mutations
          over <code>{apiBase}</code>. Blade remains available as a parallel console.
        </p>
        <ApiStatus />
        <dl className="meta">
          <div>
            <dt>Phase</dt>
            <dd>16 / slice 3 (admin users, settings, audit)</dd>
          </div>
          <div>
            <dt>Edge path</dt>
            <dd>{basePath || "/"}</dd>
          </div>
        </dl>
        <div className="actions">
          <Link href="/login">Sign in</Link>
          <Link className="secondary" href="/tickets/new">
            New report
          </Link>
          <Link className="secondary" href="/tickets">
            Tickets
          </Link>
          <a className="secondary" href={bladeUrl}>
            Blade login
          </a>
        </div>
      </section>
    </main>
  );
}
