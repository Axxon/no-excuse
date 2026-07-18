SHELL := /bin/sh

DOCKER ?= docker
USER_ID := $(shell id -u)
GROUP_ID := $(shell id -g)
DEMO_ENV_FILE ?= .env.demo
DEMO_PROJECT_NAME ?= no-excuse-demo

.PHONY: help setup demo install scaffold scaffold-api scaffold-web upgrade-api runtime-version build up restart down logs composer artisan npm mail-test test lint validate remote-config remote-build demo-prod-build demo-prod-up demo-prod-deploy demo-prod-logs demo-prod-ps

help:
	@echo "no-excuse"
	@echo "  make setup     Build, install, start and initialize the project"
	@echo "  make demo      Load optional demonstration data"
	@echo "  make scaffold  Create the Laravel API and Vue application with Docker"
	@echo "  make upgrade-api Upgrade Composer dependencies with Docker"
	@echo "  make runtime-version Show the selected PHP runtime version"
	@echo "  make build     Build PHP 8.5 and Node 24 images"
	@echo "  make up        Start the development stack"
	@echo "  make restart   Reload services after a server configuration change"
	@echo "  make test      Run the backend suite in the isolated test stack"
	@echo "  make lint      Check PHP formatting and build the typed frontend"
	@echo "  make validate  Run the complete validation rail"
	@echo "  make mail-test EMAIL=you@example.com Send a transport test email"
	@echo "  make remote-config Validate the provider-neutral remote profile"
	@echo "  make remote-build Build the remote images from the local checkout"
	@echo "  make demo-prod-deploy Build, migrate and start the isolated public demo"

setup: build install up
	$(DOCKER) compose exec api php artisan migrate --force

demo:
	$(DOCKER) compose exec api php artisan db:seed --force

install:
	USER_ID=$(USER_ID) GROUP_ID=$(GROUP_ID) $(DOCKER) compose run --no-deps api composer install --no-interaction

scaffold: scaffold-api scaffold-web

scaffold-api:
	@test ! -e api/composer.json || { echo "api already scaffolded"; exit 0; }
	$(DOCKER) run --rm \
		--user "$(USER_ID):$(GROUP_ID)" \
		-e COMPOSER_HOME=/tmp/composer \
		-v "$(CURDIR):/workspace" \
		-w /workspace \
		composer:2 create-project laravel/laravel api "^13.0" --no-interaction

scaffold-web:
	@test ! -e web/package.json || { echo "web already scaffolded"; exit 0; }
	$(DOCKER) run --rm \
		--user "$(USER_ID):$(GROUP_ID)" \
		-e NPM_CONFIG_CACHE=/tmp/npm-cache \
		-v "$(CURDIR):/workspace" \
		-w /workspace \
		node:22-alpine npm create vite@latest web -- --template vue-ts

upgrade-api:
	$(DOCKER) run --rm \
		--user "$(USER_ID):$(GROUP_ID)" \
		-e COMPOSER_HOME=/tmp/composer \
		-v "$(CURDIR)/api:/app" \
		-w /app \
		composer:2 update --with-all-dependencies --no-interaction

runtime-version:
	$(DOCKER) run --rm php:8.5-cli-alpine php -v

build:
	USER_ID=$(USER_ID) GROUP_ID=$(GROUP_ID) $(DOCKER) compose build

up:
	USER_ID=$(USER_ID) GROUP_ID=$(GROUP_ID) $(DOCKER) compose up -d

restart:
	USER_ID=$(USER_ID) GROUP_ID=$(GROUP_ID) $(DOCKER) compose up -d --force-recreate

down:
	$(DOCKER) compose down

logs:
	$(DOCKER) compose logs -f api queue-intake queue-scoring queue-notifications web

composer:
	$(DOCKER) compose exec api composer $(ARGS)

artisan:
	$(DOCKER) compose exec api php artisan $(ARGS)

npm:
	$(DOCKER) compose exec web npm $(ARGS)

mail-test:
	@test -n "$(EMAIL)" || { echo "EMAIL is required"; exit 2; }
	$(DOCKER) compose exec api php artisan mail:test "$(EMAIL)"

test:
	COMPOSE_IGNORE_ORPHANS=true USER_ID=$(USER_ID) GROUP_ID=$(GROUP_ID) $(DOCKER) compose -p no-excuse-test -f compose.yml -f compose.test.yml run --rm --build api php artisan test $(TEST_ARGS)

lint:
	$(DOCKER) compose exec api vendor/bin/pint --test
	$(DOCKER) compose exec web npm run build

validate: test lint

remote-config:
	SOURCE_ARCHIVE_URL=https://example.invalid/no-excuse.tar.gz APP_KEY=base64:validation DB_PASSWORD=validation MAIL_USERNAME=validation MAIL_PASSWORD=validation MAIL_FROM_ADDRESS=validation@example.com $(DOCKER) compose -f compose.remote.yml -f deploy/remote/mailer.override.yml config --quiet
	sh -n deploy/remote/configure-brevo-secret.sh

remote-build:
	SOURCE_ARCHIVE_URL=. APP_KEY=base64:validation DB_PASSWORD=validation $(DOCKER) compose -f compose.remote.yml build

demo-prod-build:
	$(DOCKER) compose -p $(DEMO_PROJECT_NAME) --env-file $(DEMO_ENV_FILE) -f compose.demo.yml build

demo-prod-up:
	$(DOCKER) compose -p $(DEMO_PROJECT_NAME) --env-file $(DEMO_ENV_FILE) -f compose.demo.yml up -d

demo-prod-deploy: demo-prod-build demo-prod-up
	$(DOCKER) compose -p $(DEMO_PROJECT_NAME) --env-file $(DEMO_ENV_FILE) -f compose.demo.yml exec api php artisan migrate --force
	$(DOCKER) compose -p $(DEMO_PROJECT_NAME) --env-file $(DEMO_ENV_FILE) -f compose.demo.yml exec api php artisan optimize

demo-prod-logs:
	$(DOCKER) compose -p $(DEMO_PROJECT_NAME) --env-file $(DEMO_ENV_FILE) -f compose.demo.yml logs -f api queue-intake queue-scoring scheduler web

demo-prod-ps:
	$(DOCKER) compose -p $(DEMO_PROJECT_NAME) --env-file $(DEMO_ENV_FILE) -f compose.demo.yml ps
