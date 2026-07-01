---
name: init
description: Bootstrap a fresh clone of the project for local development. Checks prerequisites, copies .env.local, personalizes the project. Triggers on "init", "bootstrap", "set up locally", "first time setup", "getting started", "how do I run this".
---

# Init

Onboarding wizard for a fresh clone. Checks the environment, sets up config, and personalizes the template so every future AI session knows what you're building.

> **Note:** This skill is the AI-guided alternative to `make init`. It cannot patch `/etc/hosts` because that requires `sudo`. If you are on Linux and need `.localhost` routing, run `sudo make init` from the terminal first — it patches hosts and installs skills.

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

3. **Personalize the project**: run `/customize-project` to rename placeholder package names and add your project description to `AGENTS.md`. This is the right time — before the first image build.

4. **Explain the two stack modes**:
   - **Headless test stack** (default for development) — auto-starts on `make lint` / `make test` / `make build-web`. Per-worktree, port-free, parallel-safe. No `make start` needed for development.
   - **Full dev stack** (for browser testing) — `make start` brings up Traefik + nginx + Postgres + Redis + Mailpit with host ports. Needed only when you want to see the app in a browser.

5. **Report next steps**.

## Output

```
✅ Project initialized.

Config:
  .env.local: created (review APP_SECRET, JWT_PASSPHRASE, DB credentials)
  Project personalized: yes / no

Ready to work:
  make test               — run tests (auto-starts headless stack)
  make lint               — run quality checks
  /new-feature feat-<slug>  — create a worktree + branch for your first feature
  /spec-writing             — design your first feature spec-first (recommended)

Need a browser preview?
  make start              — full dev stack with host ports (single instance)
```

## Rules

- Never overwrite an existing `.env.local` — only copy if missing.
- Never run `make start` from this skill — that's a separate user decision.
- Do not attempt to patch `/etc/hosts` — that requires sudo and must be done via `make init`.
