SHELL := /bin/sh

DOCKER ?= docker
USER_ID := $(shell id -u)
GROUP_ID := $(shell id -g)

.PHONY: help setup demo install scaffold scaffold-api scaffold-web upgrade-api runtime-version build up restart down logs composer artisan npm test lint validate

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

test:
	USER_ID=$(USER_ID) GROUP_ID=$(GROUP_ID) $(DOCKER) compose -p no-excuse-test -f compose.yml -f compose.test.yml run --build --no-deps api

lint:
	$(DOCKER) compose exec api vendor/bin/pint --test
	$(DOCKER) compose exec web npm run build

validate: test lint
