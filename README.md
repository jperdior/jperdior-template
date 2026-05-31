# jperdior-template

[![CI](https://github.com/jperdior/jperdior-template/actions/workflows/ci.yml/badge.svg)](https://github.com/jperdior/jperdior-template/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://php.net)
[![Symfony 7.4](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony&logoColor=white)](https://symfony.com)
[![Next.js 15](https://img.shields.io/badge/Next.js-15-000000?logo=next.js&logoColor=white)](https://nextjs.org)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/jperdior/jperdior-template/pulls)

My personal project template. Clone it, rename it, start writing features.

It comes with my preferred stack (PHP 8.4 + Symfony 7.4, DDD + Hexagonal + CQRS, Next.js 15 frontends) and a `User` bounded context already in place — sign-up, JWT auth, role management. The architecture conventions and CI are set up from the start, so you're not doing that per project.

The `.ai/` harness is the other main thing: specs, skills, and review gates that give an AI agent enough context to contribute meaningfully without going off-rails.

---

## Stack

| Layer | Technology |
|-------|-----------|
| API | PHP 8.4 + Symfony 7.4 |
| Architecture | DDD + Hexagonal + CQRS, modular monolith |
| Auth | Lexik JWT + Gesdinet refresh-token rotation |
| Persistence | PostgreSQL 16, Doctrine 3 (XML mapping) |
| Queue | Symfony Messenger — sync by default, RabbitMQ-ready via `--profile async` |
| Cache / Locks | Redis 7 |
| Public frontend | Next.js 15 App Router, TypeScript strict, Tailwind, shadcn/ui |
| Admin panel | Next.js 15, same stack, gated to `ROLE_ADMIN` |
| API client | Auto-generated TypeScript from OpenAPI spec |
| Containers | Docker Compose v2 + Traefik (local routing) |

---

## Quickstart

```sh
git clone <this repo> my-new-project
cd my-new-project
sudo make init   # sudo needed to patch /etc/hosts
make start       # build images and start the stack (2–5 min first time)
```

Once the stack is up, **personalize the project** (rename placeholders, add your project description to `AGENTS.md`) by saying **"customize my project"** or running `/customize-project` in Claude Code.

Then:

| URL | Service |
|-----|---------|
| `http://api.localhost/api/doc` | Swagger UI |
| `http://web.localhost` | Next.js public app |
| `http://admin.localhost` | Next.js admin panel |
| `http://localhost:8080` | Traefik dashboard |

Create the first admin account:

```sh
make seed-admin EMAIL=you@example.com
```

See [docs/getting-started.md](docs/getting-started.md) for the full walkthrough.

---

## Common commands

| Command | What it does |
|---------|-------------|
| `make start` | Build images, start stack, tail logs |
| `make stop` | Stop containers |
| `make lint` | PHPStan + cs-fixer + deptrac + tsc + eslint (all in containers) |
| `make test` | PHPUnit (unit + functional) + JS tests (all in containers) |
| `make migrate-diff` | Generate a Doctrine migration from entity changes |
| `make gen-api` | Regenerate the TypeScript API client from OpenAPI spec |
| `make seed-admin EMAIL=x` | Promote a user to `ROLE_ADMIN` |

Run `make help` for the full list.

---

## Documentation

| Guide | What's in it |
|-------|-------------|
| [docs/getting-started.md](docs/getting-started.md) | From clone to first endpoint |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | DDD + Hexagonal + CQRS rationale, the four layers, the three buses |
| [docs/auth.md](docs/auth.md) | JWT flow, refresh rotation, frontend cookie strategy |
| [docs/adding-a-bounded-context.md](docs/adding-a-bounded-context.md) | Step-by-step guide for new contexts |
| [docs/ai-workflow.md](docs/ai-workflow.md) | Spec-first AI-driven development with the `.ai/` harness |
| [docs/ops.md](docs/ops.md) | Docker setup, environment variables, CI pipeline |
| [AGENTS.md](AGENTS.md) | Task router — the first file an AI agent reads before any coding |

---

MIT licensed. See [LICENSE](LICENSE).
