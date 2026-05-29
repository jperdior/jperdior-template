---
name: init
description: Bootstrap a fresh clone of the project for local development. Checks prerequisites, copies .env.dist, patches /etc/hosts, and starts the stack. Triggers on "init", "bootstrap", "set up locally", "first time setup", "getting started", "how do I run this".
---

# Init

One-shot local dev bootstrap for a fresh clone. Idempotent — safe to run again if something failed midway.

## Workflow

1. **Check prerequisites** — report what's missing, don't abort:
   - `docker info` — Docker running?
   - `docker compose version` — Compose v2.24+?
   - `make --version` — GNU Make available?
   - `git --version` — always present if we got here.

2. **Copy `.env.local`** if it doesn't exist:
   ```sh
   [ -f .env.local ] || cp .env.dist .env.local
   echo "Review .env.local and adjust APP_SECRET / JWT_PASSPHRASE / DB credentials before production use."
   ```

3. **Patch `/etc/hosts`** for Traefik `.localhost` routing:
   ```sh
   sh ops/scripts/init-hosts.sh
   ```
   On macOS 12+ this is a no-op (mDNSResponder resolves `*.localhost` automatically).
   On Linux it adds: `127.0.0.1 api.localhost web.localhost admin.localhost`.

4. **Start the stack**:
   ```sh
   make start
   ```
   The first boot takes 2–5 minutes (image build + `composer install` + migrations).

5. **Verify**:
   ```sh
   curl -s http://api.localhost/api/doc | grep -q openapi \
     && echo "API OK" || echo "API not ready yet — check make logs"
   ```

6. **Report** the service URLs and next steps.

## Output

```
✅ Stack is up.

Service URLs:
  Web app   →  http://web.localhost   (or http://localhost)
  Admin     →  http://admin.localhost
  API       →  http://api.localhost
  API docs  →  http://api.localhost/api/doc
  Traefik   →  http://localhost:8080

Next steps:
  make logs          — tail all container logs
  make api-shell     — shell inside the API container
  make seed-admin EMAIL=you@example.com  — promote a user to admin
  /scaffold-bounded-context              — add a new bounded context
```

## Rules

- Never overwrite an existing `.env.local` — only copy if missing.
- Always print the service URLs at the end, even if the user ran init before.
- If `make start` fails, tail `make logs` for 10 seconds and surface the first ERROR line.
- If `/etc/hosts` already has the entries, skip silently.
