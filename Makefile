ENV_FILE ?= $(if $(wildcard .env.local),.env.local,.env)
COMPOSE = docker compose --env-file $(ENV_FILE)

export LOGIN PASSWORD

.PHONY: help env init up down restart build pull ps logs health \
        migrations migrations-diff create-admin backend-shell frontend-shell \
        docker-up docker-down docker-build docker-pull

help:
	@printf '%s\n' \
		'make init                         Start services and run migrations' \
		'make up | down | restart          Manage services' \
		'make build | pull | ps | logs     Build, pull DB image and inspect services' \
		'make migrations                   Apply database migrations' \
		'make migrations-diff              Generate a database migration' \
		'make create-admin                 Create admin with interactive prompts' \
		'make create-admin LOGIN=... PASSWORD=...  Create admin from arguments' \
		'make health                       Check backend and frontend health' \
		'make backend-shell | frontend-shell  Open a service shell'

env:
	@test -f "$(ENV_FILE)" || { printf 'Create %s from .env.example and set secrets first.\n' "$(ENV_FILE)" >&2; exit 1; }

init: env
	@$(MAKE) up
	@$(MAKE) migrations

up: env
	$(COMPOSE) up --build -d

down:
	$(COMPOSE) down --remove-orphans

restart: down
	@$(MAKE) up

build: env
	$(COMPOSE) build

pull: env
	$(COMPOSE) pull db

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs -f backend frontend

health:
	curl --fail --show-error http://localhost:8000/api/v1/health
	curl --fail --show-error http://localhost:3000/api/health

migrations:
	$(COMPOSE) exec -T backend php bin/console doctrine:migrations:migrate --no-interaction

migrations-diff:
	$(COMPOSE) exec -T backend php bin/console doctrine:migrations:diff --no-interaction

create-admin:
	@if [ -n "$$LOGIN" ] || [ -n "$$PASSWORD" ]; then \
		if [ -z "$$LOGIN" ] || [ -z "$$PASSWORD" ]; then \
			printf '%s\n' 'Set both LOGIN and PASSWORD.' >&2; exit 1; \
		fi; \
		$(COMPOSE) up --build -d backend && \
		$(COMPOSE) exec -T backend php bin/console app:create-admin "$$LOGIN" "$$PASSWORD"; \
	else \
		$(COMPOSE) up --build -d backend && \
		$(COMPOSE) exec backend php bin/console app:create-admin; \
	fi

backend-shell:
	$(COMPOSE) exec backend sh

frontend-shell:
	$(COMPOSE) exec frontend sh

docker-up: up
docker-down: down
docker-build: build
docker-pull: pull
