PWD := $(shell pwd)
UNAME := $(shell uname)
PROJECT_NAME := jperdior
API_CONTAINER := api
WORKER_CONTAINER := worker
ENV_FILE := $(if $(wildcard .env.local),.env.local,.env.dist)
DOCKER_COMPOSE := docker compose --env-file $(ENV_FILE) -p ${PROJECT_NAME} -f ${PWD}/ops/docker/docker-compose.base.yml -f ${PWD}/ops/docker/docker-compose.dev.yml
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

init: ## Bootstrap a fresh clone: copy .env.local, patch /etc/hosts, start stack
	@[ -f .env.local ] || cp .env.dist .env.local
	@sh ops/scripts/init-hosts.sh
	@${MAKE} start

# ----- Lifecycle -----

start: build up logs ## Build, start, and tail logs

build: ## Build all container images
	@${DOCKER_COMPOSE} build

up: ## Start containers in the background
	@${DOCKER_COMPOSE} up -d

stop: ## Stop and remove containers
	@${DOCKER_COMPOSE} down --remove-orphans

restart: stop start ## Restart the stack

logs: ## Tail container logs
	@${DOCKER_COMPOSE} logs -f --tail=100

ps: ## Show container status
	@${DOCKER_COMPOSE} ps

traefik: ## Open Traefik dashboard in the browser
	@open http://localhost:8080 2>/dev/null || xdg-open http://localhost:8080

# ----- Shells -----

api-shell: ## Open a shell inside the API container
	@${DOCKER_COMPOSE} exec ${API_CONTAINER} sh

worker-shell: ## Open a shell inside the worker container
	@${DOCKER_COMPOSE} exec ${WORKER_CONTAINER} sh

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

setup-test-db: ## Create and migrate the test database
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console doctrine:database:create --env=test --if-not-exists
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console doctrine:migrations:migrate --env=test --no-interaction

test: test-api test-web ## Run the full test matrix

test-api: ## Run PHP unit + functional tests
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/phpunit ${ARG}

test-unit: ## Run PHP unit tests only
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/phpunit --testsuite Unit ${ARG}

test-functional: ## Run PHP functional tests only
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/phpunit --testsuite Functional ${ARG}

test-web: ## Run JS unit tests (web + admin)
	@pnpm -r --filter "./apps/web..." --filter "./apps/admin..." test

test-e2e: ## Run Playwright integration tests
	@pnpm -C apps/web exec playwright test

# ----- Lint / static analysis -----

lint: lint-api lint-web ## Lint everything

lint-api: ## PHPStan + php-cs-fixer dry-run + deptrac
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit=512M ${ARG}
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/php-cs-fixer fix --dry-run --diff
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/deptrac analyse --no-progress

lint-fix: ## Fix PHP code style
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php vendor/bin/php-cs-fixer fix

lint-web: ## Typecheck + ESLint on JS workspaces
	@pnpm -r --filter "./apps/web..." --filter "./apps/admin..." typecheck
	@pnpm -r --filter "./apps/web..." --filter "./apps/admin..." lint

# ----- Build -----

build-api: ## Build the API for production
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} composer install --no-dev --optimize-autoloader

build-web: ## Build web + admin for production
	@pnpm -r --filter "./apps/web..." --filter "./apps/admin..." build

# ----- OpenAPI / TS client -----

gen-api: ## Regenerate TS client from API OpenAPI spec
	@${DOCKER_COMPOSE} ${EXEC} ${API_CONTAINER} php bin/console nelmio:apidoc:dump --format=json > apps/api/openapi.json
	@pnpm -C packages/api-client-ts gen

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
