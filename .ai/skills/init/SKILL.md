---
name: init
description: Bootstrap a fresh clone of the project for local development. Checks prerequisites, copies .env.dist, and starts the stack. Triggers on "init", "bootstrap", "set up locally", "first time setup", "getting started", "how do I run this".
---

# Init

One-shot local dev bootstrap for a fresh clone. Idempotent — safe to run again if something failed midway.

> **Note:** This skill is the AI-guided alternative to `make init`. It cannot patch `/etc/hosts` because that requires `sudo`. If you are on Linux and need `.localhost` routing, run `sudo make init` from the terminal first — it handles hosts patching and then instructs you to run `make start`.

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

3. **Start the stack**:
   ```sh
   make start
   ```
   The first boot takes 2–5 minutes (image build + `composer install` + migrations).

4. **Verify**:
   ```sh
   curl -s http://api.localhost/api/doc | grep -q openapi \
     && echo "API OK" || echo "API not ready yet — check make logs"
   ```

5. **Report** the service URLs and next steps.

## Output

```
✅ Stack is up.

Service URLs:
  Web app   →  http://web.localhost
  Admin     →  http://admin.localhost
  API       →  http://api.localhost
  API docs  →  http://api.localhost/api/doc
  Traefik   →  http://localhost:8080

Next steps:
  make logs          — tail all container logs
  make api-shell     — shell inside the API container
  make seed-admin EMAIL=you@example.com  — promote a user to admin

  When you're ready to personalize the project name and docs, say
  "customize my project" or run /customize-project.
```

## Rules

- Never overwrite an existing `.env.local` — only copy if missing.
- Always print the service URLs at the end, even if the user ran init before.
- If `make start` fails, tail `make logs` for 10 seconds and surface the first ERROR line.
- Do not attempt to patch `/etc/hosts` — that requires sudo and must be done via `make init`.
