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
EXEC := exec -T

.EXPORT_ALL_VARIABLES:

.PHONY: help
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

start: _ensure-volume-mountpoints build up logs ## Build, start, and tail logs

_ensure-volume-mountpoints: ## Pre-create Docker named-volume mount points as the host user
	@mkdir -p node_modules apps/web/.next apps/admin/.next apps/api/config/jwt \
	          .pnpm-store \
	          apps/web/node_modules apps/admin/node_modules \
	          packages/api-client-ts/node_modules packages/ui-react/node_modules \
	          apps/api/vendor apps/api/var apps/api/public/bundles

build: ## Build all container images
	@${DOCKER_COMPOSE} build

up: ## Start containers in the background
	@${DOCKER_COMPOSE} up -d

stop: ## Stop and remove containers
	@${DOCKER_COMPOSE} down --remove-orphans

restart: stop start ## Restart the stack

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

api-shell: ## Open a shell inside the API container
	@${DOCKER_COMPOSE} exec ${API_CONTAINER} sh

worker-shell: ## Open a shell inside the worker container (requires make start-async)
	@${DOCKER_COMPOSE_ASYNC} exec ${WORKER_CONTAINER} sh

db-shell: ## Open a psql shell
	@${DOCKER_COMPOSE} exec postgres psql -U $${POSTGRES_USER:-app} $${POSTGRES_DB:-app}

# ----- Composer -----

composer-install: ## composer install inside the API container
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} composer install

composer-require: ## make composer-require PACKAGE=vendor/pkg
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} composer require ${PACKAGE}

# ----- Database / Doctrine -----

migrate: ## Run pending migrations
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console doctrine:migrations:migrate --no-interaction

migrate-diff: ## Generate a migration diff against current entities
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console doctrine:migrations:diff

db-create: ## Create the database
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console doctrine:database:create --if-not-exists

db-reset: ## Drop and recreate the database (DANGEROUS)
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console doctrine:database:drop --force --if-exists
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console doctrine:database:create
	@${MAKE} migrate

# ----- Tests -----

test: test-api test-web ## Run the full test matrix

test-api: ## Run PHP unit + functional tests
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/phpunit ${ARG}

test-unit: ## Run PHP unit tests only
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/phpunit --testsuite Unit ${ARG}

test-functional: ## Run PHP functional tests only
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/phpunit --testsuite Functional ${ARG}

test-web: ## Run JS unit tests (web + admin containers)
	@${DOCKER_COMPOSE} ${EXEC} ${WEB_CONTAINER}   pnpm -C apps/web test
	@${DOCKER_COMPOSE} ${EXEC} ${ADMIN_CONTAINER} pnpm -C apps/admin test

# ----- Lint / static analysis -----

lint: lint-shared-kernel lint-api lint-web ## Lint everything

lint-shared-kernel: ## PHPStan for packages/shared-kernel-php (runs inside API container)
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/phpstan analyse -c /app/packages/shared-kernel-php/phpstan.dist.neon --no-progress --memory-limit=512M

lint-api: ## PHPStan + php-cs-fixer dry-run + deptrac (apps/api)
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit=512M ${ARG}
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/php-cs-fixer fix --dry-run --diff
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/deptrac analyse --no-progress

lint-fix: ## Fix PHP code style
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/php-cs-fixer fix

lint-web: ## Typecheck + ESLint on JS workspaces (runs inside web/admin containers)
	@${DOCKER_COMPOSE} ${EXEC} ${WEB_CONTAINER}   pnpm -r --filter './apps/web' --filter './packages/*' typecheck
	@${DOCKER_COMPOSE} ${EXEC} ${ADMIN_CONTAINER} pnpm -C apps/admin typecheck
	@${DOCKER_COMPOSE} ${EXEC} ${WEB_CONTAINER}   pnpm -C apps/web lint
	@${DOCKER_COMPOSE} ${EXEC} ${ADMIN_CONTAINER} pnpm -C apps/admin lint

# ----- Build -----

build-api: ## Build the API for production
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} composer install --no-dev --optimize-autoloader

build-web: ## Build web + admin for production (runs inside web/admin containers)
	@${DOCKER_COMPOSE} ${EXEC} ${WEB_CONTAINER}   pnpm -C apps/web build
	@${DOCKER_COMPOSE} ${EXEC} ${ADMIN_CONTAINER} pnpm -C apps/admin build

# ----- OpenAPI / TS client -----

gen-api: ## Regenerate TS client from API OpenAPI spec
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console nelmio:apidoc:dump --format=json > apps/api/openapi.json
	@${DOCKER_COMPOSE} ${EXEC} ${WEB_CONTAINER} pnpm -C packages/api-client-ts gen

# ----- JWT keys -----

jwt-keys: ## Generate JWT key pair into apps/api/config/jwt/
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console lexik:jwt:generate-keypair --skip-if-exists

# ----- Seeders -----

seed-admin: ## Promote a user to ROLE_ADMIN (EMAIL=foo@bar.com)
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console app:user:promote-admin ${EMAIL}

# ----- Clean -----

clean: ## Remove containers, volumes, and generated artefacts
	@${DOCKER_COMPOSE} down --remove-orphans --volumes
	@rm -rf apps/*/node_modules apps/*/.next packages/*/node_modules .turbo .pnpm-store
