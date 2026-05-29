# jperdior-template

Spec-driven, AI-engineered monorepo template:

- **API**: PHP 8.4 + Symfony 7.4, DDD + Hexagonal + CQRS via Symfony Messenger.
- **Frontend**: Next.js 15 (App Router) + TypeScript + Tailwind + shadcn/ui — `apps/web` (public) and `apps/admin` (back-office).
- **Auth**: JWT (Lexik + Gesdinet refresh-token rotation) out of the box.
- **Persistence**: PostgreSQL 16, Doctrine 3 (XML mapping).
- **AI harness**: ported from open-mercato — specs, skills, code-review CI gate, PR automation.
- **Multi-tenancy**: NOT in core. Opt-in via the `packages/tenancy-php` package when you need it.

## Quickstart

```sh
git clone <this repo> my-new-project
cd my-new-project
cp .env.dist .env.local
make start
```

After ~30 s:
- API: http://localhost:8080 (Swagger UI at `/api/doc`)
- Web: http://localhost:3000
- Admin: http://localhost:3001

## Where things live

```
apps/                       one Symfony API + Next.js web + Next.js admin
packages/                   shared PHP + TS libraries (composer path repos / pnpm workspaces)
ops/                        all deployment artefacts (Docker, K8s skeleton, CI scripts)
.ai/                        specs, skills, qa harness, lessons — the AI engineering harness
docs/                       architecture, getting-started, multitenancy, auth, ops, ai-workflow
AGENTS.md                   task router for AI agents and humans
Makefile                    single entry point: make start | test | lint | shell
```

## Common commands

| What                       | Command                  |
|---------------------------|--------------------------|
| Start stack                | `make start`             |
| Stop stack                 | `make stop`              |
| Tail logs                  | `make logs`              |
| Open shell in API          | `make api-shell`         |
| Run all tests              | `make test`              |
| Lint everything            | `make lint`              |
| Apply migrations           | `make migrate`           |
| Generate migration diff    | `make migrate-diff`      |
| Regenerate TS API client   | `make gen-api`           |
| Seed an admin user         | `make seed-admin`        |

## Adding a new bounded context

```sh
# Drives the .ai/skills/scaffold-bounded-context skill via Claude / Cursor
# Generates apps/api/code/src/<Context>/{Domain,Application,Infrastructure,Presentation}
```

See [docs/adding-a-bounded-context.md](docs/adding-a-bounded-context.md).

## Documentation

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — DDD + Hexagonal + CQRS deep-dive
- [docs/getting-started.md](docs/getting-started.md) — first 30 minutes
- [docs/auth.md](docs/auth.md) — JWT flow, refresh rotation, frontend cookie strategy
- [docs/multitenancy.md](docs/multitenancy.md) — 5-step opt-in
- [docs/ops.md](docs/ops.md) — Docker dev, K8s production, env reference
- [docs/ai-workflow.md](docs/ai-workflow.md) — driving Claude/Cursor with the harness

MIT licensed. See [LICENSE](LICENSE).
