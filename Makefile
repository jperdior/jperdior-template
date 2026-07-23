PWD := $(shell pwd)
UNAME := $(shell uname)
PROJECT_NAME := jperdior
API_CONTAINER   := api
WORKER_CONTAINER := worker
WEB_CONTAINER   := web
ADMIN_CONTAINER := admin
ENV_FILE := $(if $(wildcard .env.local),.env.local,.env.dist)
DOCKER_COMPOSE := docker compose --env-file $(ENV_FILE) -p ${PROJECT_NAME} -f ${PWD}/ops/docker/docker-compose.base.yml -f ${PWD}/ops/docker/docker-compose.dev.yml
DOCKER_COMPOSE_ASYNC := $(DOCKER_COMPOSE) --profile async
# Headless CI-gate stack: per-worktree project name + no host ports, so lint/test/build
# run in any number of worktrees in parallel without `make start`. See docker-compose.test.yml.
# `tr '+' '-'` guards against worktree dirnames containing `+` (e.g. a stray legacy
# `feat/<slug>` branch, which some tooling renders as `feat+<slug>` on disk) — `+` is not
# a valid Docker Compose project-name character.
TEST_PROJECT_NAME := $(PROJECT_NAME)-test-$(shell echo $(notdir $(PWD)) | tr '+' '-')
DOCKER_COMPOSE_TEST := docker compose --env-file $(ENV_FILE) -p $(TEST_PROJECT_NAME) -f ${PWD}/ops/docker/docker-compose.base.yml -f ${PWD}/ops/docker/docker-compose.test.yml

# Isolated web-e2e stack: dev-shaped (live source mounts + `next dev`) but under its own
# per-worktree project name with no host ports and a disposable DB, so `make test-e2e`
# needs no `make start` and never touches the dev database. See docker-compose.e2e.yml.
E2E_PROJECT_NAME := $(PROJECT_NAME)-e2e-$(shell echo $(notdir $(PWD)) | tr '+' '-')
DOCKER_COMPOSE_E2E_STACK := docker compose --env-file $(ENV_FILE) -p $(E2E_PROJECT_NAME) -f ${PWD}/ops/docker/docker-compose.base.yml -f ${PWD}/ops/docker/docker-compose.dev.yml -f ${PWD}/ops/docker/docker-compose.e2e.yml
EXEC := exec -T

# JS workspace gates run standalone in an ephemeral node container — they need no
# postgres/api, only the cached per-worktree node_modules volumes. `run --rm --no-deps`
# reuses those volumes. js-workspace-install.sh skips `pnpm install` (and its slow
# lockfile supply-chain verification) when the lockfile is unchanged, and the persisted
# corepack_cache volume avoids re-downloading pnpm — so an unchanged tree pays only
# container startup, not a full reinstall, on every lint/test/build.
JS_RUN        := ${DOCKER_COMPOSE_TEST} run --rm --no-deps -T
WEB_INSTALL   := sh /repo/ops/ci/scripts/js-workspace-install.sh web
ADMIN_INSTALL := sh /repo/ops/ci/scripts/js-workspace-install.sh admin

# PHP static-analysis gates (phpstan / php-cs-fixer / deptrac) likewise run standalone in
# an ephemeral api container — they need no postgres, only the cached per-worktree
# api_vendor + api_var volumes. `run --rm --no-deps` overrides the long-running startup
# command (which would create/migrate the DB) with the gate command. PHP_INSTALL is a fast
# no-op once api_vendor is populated; cache:warmup recompiles the dev container XML that
# phpstan's symfony extension reads (kernel compilation needs no DB connection). Only the
# PHP *test* gates (phpunit) need a live DB, hence `up-test`.
PHP_RUN       := ${DOCKER_COMPOSE_TEST} run --rm --no-deps -T ${API_CONTAINER}
PHP_INSTALL   := composer install --no-interaction --no-progress && php bin/console cache:warmup

.EXPORT_ALL_VARIABLES:

.PHONY: help test lint
help: ## Show this help
ifeq ($(UNAME), Linux)
	@grep -P '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-22s\033[0m %s\n", $$1, $$2}'
else
	@awk -F ':.*##' '$$0 ~ FS {printf "%22s%s\n", $$1 ":", $$2}' \
		$(MAKEFILE_LIST) | grep -v '@awk' | sort
endif

# ----- Init (first-time setup) -----

init: ## Bootstrap a fresh clone: copy .env.local, patch /etc/hosts, install skills
	@[ -f .env.local ] || cp .env.dist .env.local
	@sh ops/scripts/init-hosts.sh
	@sh scripts/install-skills.sh --target claude
	@echo ""
	@echo "Setup complete. Run 'make start' to build and start the stack."

# ----- Lifecycle -----

start: _ensure-volume-mountpoints build up wait ## Build, start, and wait until web/api/admin serve HTTP

start-logs: start logs ## Same as `make start`, then tail logs

_ensure-volume-mountpoints: ## Pre-create Docker named-volume mount points as the host user
	@mkdir -p node_modules apps/web/.next apps/admin/.next apps/api/config/jwt \
	          .pnpm-store \
	          apps/web/node_modules apps/admin/node_modules \
	          packages/api-client-ts/node_modules packages/ui-react/node_modules \
	          packages/auth-server-ts/node_modules \
	          apps/api/vendor apps/api/var apps/api/public/bundles

build: ## Build all container images
	@${DOCKER_COMPOSE} build

up: ## Start containers in the background
	@${DOCKER_COMPOSE} up -d

wait: ## Block until web, api and admin respond over HTTP
	@TRAEFIK_PORT=$$(grep -s '^TRAEFIK_PORT=' $(ENV_FILE) | cut -d= -f2-) \
	 sh ops/scripts/wait-for-stack.sh

stop: ## Stop and remove containers
	@${DOCKER_COMPOSE} down --remove-orphans

restart: stop start ## Restart the stack

# ----- Headless CI-gate stack (parallel-safe, no host ports, per-worktree) -----

up-test: _ensure-volume-mountpoints ## Start the headless PHP test stack (postgres + api); installs deps; no host ports
	@${DOCKER_COMPOSE_TEST} up -d postgres api
	@sh ops/scripts/wait-for-test-stack.sh

stop-test: ## Stop and remove this worktree's headless test stack
	@${DOCKER_COMPOSE_TEST} down --remove-orphans

start-async: ## Start full stack + RabbitMQ + worker (set MESSENGER_TRANSPORT_DSN in .env.local first)
	@${DOCKER_COMPOSE_ASYNC} up -d
	@${DOCKER_COMPOSE_ASYNC} logs -f --tail=100

logs: ## Tail container logs
	@${DOCKER_COMPOSE} logs -f --tail=100

logs-ci: ## Dump container logs without following (for CI failure output)
	@${DOCKER_COMPOSE} logs --tail=300

ps: ## Show container status
	@${DOCKER_COMPOSE} ps

traefik: ## Open Traefik dashboard in the browser
	@open http://localhost:8080 2>/dev/null || xdg-open http://localhost:8080

# ----- Shells -----

api-shell: up-test ## Open a shell inside the API container (headless test stack)
	@${DOCKER_COMPOSE_TEST} exec ${API_CONTAINER} sh

worker-shell: ## Open a shell inside the worker container (requires make start-async)
	@${DOCKER_COMPOSE_ASYNC} exec ${WORKER_CONTAINER} sh

db-shell: up-test ## Open a psql shell (headless test stack)
	@${DOCKER_COMPOSE_TEST} exec postgres psql -U $${POSTGRES_USER:-app} $${POSTGRES_DB:-app}

# ----- Composer -----

composer-install: up-test ## composer install inside the API container
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} composer install

composer-require: up-test ## make composer-require PACKAGE=vendor/pkg
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} composer require ${PACKAGE}

# ----- Database / Doctrine -----

migrate: up-test ## Run pending migrations
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} php bin/console doctrine:migrations:migrate --no-interaction

migrate-diff: up-test ## Generate a migration diff against current entities
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} php bin/console doctrine:migrations:diff

db-create: up-test ## Create the database
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} php bin/console doctrine:database:create --if-not-exists

db-reset: up-test ## Drop and recreate the database (DANGEROUS)
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} php bin/console doctrine:database:drop --force --if-exists
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} php bin/console doctrine:database:create
	@${MAKE} migrate

# ----- Tests -----

test: up-test test-shared-kernel test-api test-web ## Run the full test matrix

test-shared-kernel: _ensure-volume-mountpoints ## PHPUnit for packages/shared-kernel-php — standalone, no postgres
	@${PHP_RUN} sh -c 'cd /app/packages/shared-kernel-php && composer install --no-interaction --no-progress && php vendor/bin/phpunit'

test-api: up-test ## Run PHP unit + functional tests
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} php vendor/bin/phpunit ${ARG}

test-unit: up-test ## Run PHP unit tests only
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} php vendor/bin/phpunit --testsuite Unit ${ARG}

test-functional: up-test ## Run PHP functional tests only
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} php vendor/bin/phpunit --testsuite Functional ${ARG}

test-web: _ensure-volume-mountpoints ## Run JS unit tests (packages + web + admin) — standalone, no postgres/api
	@${JS_RUN} ${WEB_CONTAINER}   sh -c '${WEB_INSTALL} && pnpm -C packages/auth-server-ts test && pnpm -C packages/api-client-ts test && pnpm -C apps/web test'
	@${JS_RUN} ${ADMIN_CONTAINER} sh -c '${ADMIN_INSTALL} && pnpm -C apps/admin test'

# ----- Lint / static analysis -----

lint: _ensure-volume-mountpoints ## Lint everything — standalone, no postgres/api stack
	@$(MAKE) lint-shared-kernel lint-api lint-web

lint-shared-kernel: _ensure-volume-mountpoints ## PHPStan for packages/shared-kernel-php — standalone, no postgres
	@${PHP_RUN} sh -c '${PHP_INSTALL} && \
		php vendor/bin/phpstan analyse -c /app/packages/shared-kernel-php/phpstan.dist.neon --no-progress --memory-limit=512M'

lint-api: _ensure-volume-mountpoints ## PHPStan + php-cs-fixer dry-run + deptrac (apps/api) — standalone, no postgres
	@${PHP_RUN} sh -c '${PHP_INSTALL} && \
		php vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit=512M ${ARG} && \
		php vendor/bin/php-cs-fixer fix --dry-run --diff && \
		php vendor/bin/deptrac analyse --no-progress'

lint-fix: _ensure-volume-mountpoints ## Fix PHP code style — standalone, no postgres
	@${PHP_RUN} sh -c '${PHP_INSTALL} && php vendor/bin/php-cs-fixer fix'

lint-web: _ensure-volume-mountpoints ## Typecheck + ESLint on JS workspaces — standalone, no postgres/api
	@${JS_RUN} ${WEB_CONTAINER}   sh -c '${WEB_INSTALL} && pnpm -r --filter "./apps/web" --filter "./packages/*" typecheck && pnpm -C apps/web lint'
	@${JS_RUN} ${ADMIN_CONTAINER} sh -c '${ADMIN_INSTALL} && pnpm -C apps/admin typecheck && pnpm -C apps/admin lint'

# ----- Build -----

build-api: up-test ## Build the API for production
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} composer install --no-dev --optimize-autoloader

build-web: _ensure-volume-mountpoints ## Build web + admin for production — standalone, no postgres/api
	@${JS_RUN} ${WEB_CONTAINER}   sh -c '${WEB_INSTALL} && pnpm -C apps/web build'
	@${JS_RUN} ${ADMIN_CONTAINER} sh -c '${ADMIN_INSTALL} && pnpm -C apps/admin build'

# ----- OpenAPI / TS client -----

# The dump boots the kernel to read routes/attributes — no DB connection. If a future
# bundle makes it require one, change the dependency back to `up-test` and use
# `${DOCKER_COMPOSE_TEST} ${EXEC}` — the CI openapi-drift job inherits either path.
# tmp+mv keeps the committed openapi.json intact if the dump fails midway.
gen-api: _ensure-volume-mountpoints ## Regenerate committed openapi.json + TS client types — standalone, no postgres/api
	@${PHP_RUN} sh -c '${PHP_INSTALL} && php bin/console nelmio:apidoc:dump --format=json > openapi.json.tmp && mv openapi.json.tmp openapi.json'
	@${JS_RUN} ${WEB_CONTAINER} sh -c '${WEB_INSTALL} && pnpm -C packages/api-client-ts gen'

# ----- JWT keys -----

jwt-keys: up-test ## Generate JWT key pair into apps/api/config/jwt/
	@${DOCKER_COMPOSE_TEST} ${EXEC} ${API_CONTAINER} php bin/console lexik:jwt:generate-keypair --skip-if-exists

# ----- Seeders -----

seed-admin: ## Promote a user to ROLE_ADMIN (EMAIL=foo@bar.com)
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console app:user:promote-admin ${EMAIL}

# ----- Clean -----

clean: ## Remove containers, volumes, and generated artefacts (dev + test stacks)
	@${DOCKER_COMPOSE} down --remove-orphans --volumes
	@${DOCKER_COMPOSE_TEST} down --remove-orphans --volumes
	@rm -rf apps/*/node_modules apps/*/.next packages/*/node_modules .turbo .pnpm-store

# ----- Isolated web-e2e stack (Playwright; standalone, no `make start` needed) -----

test-e2e: _ensure-volume-mountpoints ## Run web Playwright e2e against an isolated, disposable stack (own DB reset from scratch each run; no `make start` needed)
	@$(DOCKER_COMPOSE_E2E_STACK) up -d postgres redis api web
	@sh ops/scripts/wait-for-e2e-stack.sh
	@echo "e2e stack: resetting database from scratch…"
	@$(DOCKER_COMPOSE_E2E_STACK) exec -T postgres psql -U $${POSTGRES_USER:-app} -d $${POSTGRES_DB:-app} -c 'DROP SCHEMA public CASCADE; CREATE SCHEMA public;' >/dev/null
	@$(DOCKER_COMPOSE_E2E_STACK) exec -T api php bin/console doctrine:migrations:migrate --no-interaction -q
	@$(DOCKER_COMPOSE_E2E_STACK) --profile e2e run --rm playwright

stop-e2e: ## Stop/remove this worktree's isolated web-e2e stack; drops its disposable DB volume (kept caches survive)
	@$(DOCKER_COMPOSE_E2E_STACK) --profile e2e down --remove-orphans
	@docker volume rm $(E2E_PROJECT_NAME)_postgres_data >/dev/null 2>&1 || true
