.PHONY: help install \
	docker-dev-build docker-dev-setup docker-dev-up docker-dev-down docker-dev-logs docker-dev-shell docker-dev-artisan \
	docker-prod-build docker-prod-setup docker-prod-up docker-prod-deploy docker-prod-down docker-prod-logs docker-prod-shell docker-prod-artisan docker-prod-ssl


default: help

help: ## Display this help message
	@echo "Usage: make <target>"
	@echo
	@echo "Possible targets:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "    %-20s%s\n", $$1, $$2}'




install: ## Install project dependencies
	composer install
	pnpm install --frozen-lockfile
	pnpm run build
	@echo "Installation complete"

setup-dev: ## Set up the development environment (includes installation)
setup-dev:	install
	@echo "Setting up development environment..."
	[ ! -f .env ] && cp .env.example .env || true
	touch database/telescope.sqlite
	touch database/pulse.sqlite
	php artisan migrate
	php artisan key:generate
	@echo "Development environment setup complete"

delete-dependencies: ## Delete project dependencies
	@echo "Deleting project dependencies..."
	rm -rf vendor node_modules
	@echo "Dependencies deleted"

seed: ## Seed the database with sample data
	@echo "Seeding the database with sample data..."
	php artisan db:seed
	@echo "Database seeding complete"

# ==============================================================================
# Docker - Development
# ==============================================================================

docker-dev-build: ## Build the dev Docker image
	docker compose build

docker-dev-setup: ## First-time dev setup: build, start, generate key, migrate & seed
	@echo "Setting up Docker dev environment..."
	touch database/database.sqlite database/pulse.sqlite database/telescope.sqlite
	[ ! -f .env ] && cp .env.example .env || true
	docker compose up -d --build
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate --seed
	@echo "Dev environment ready → http://localhost:8000"

docker-dev-up: ## Start the dev stack
	docker compose up -d

docker-dev-down: ## Stop the dev stack
	docker compose down

docker-dev-logs: ## Follow app logs (dev)
	docker compose logs -f app

docker-dev-shell: ## Open a shell in the dev app container
	docker compose exec app sh

docker-dev-artisan: ## Run an Artisan command in dev - usage: make docker-dev-artisan CMD="migrate"
	docker compose exec app php artisan $(CMD)

# ==============================================================================
# Docker - Production
# ==============================================================================

docker-prod-build: ## Build the prod Docker image (multi-stage, assets included)
	docker compose -f docker-compose.prod.yml build app

docker-prod-setup: ## First-time prod setup: init SSL then start the full stack
	@[ -f .env.prod ] || { echo "ERROR: .env.prod not found - copy .env.prod.example and fill in the values."; exit 1; }
	@[ -n "$(EMAIL)" ] || { echo "ERROR: EMAIL is required - usage: make docker-prod-setup EMAIL=your@email.com"; exit 1; }
	chmod +x docker/ssl/init-ssl.sh
	./docker/ssl/init-ssl.sh $(EMAIL)
	docker compose -f docker-compose.prod.yml up -d
	@echo "Production stack up → https://tir-lavigny.ch"

docker-prod-up: ## Start the prod stack
	docker compose -f docker-compose.prod.yml up -d

docker-prod-deploy: ## Pull latest code, rebuild image and restart (subsequent deployments)
	git pull
	docker compose -f docker-compose.prod.yml build app
	docker compose -f docker-compose.prod.yml up -d

docker-prod-down: ## Stop the prod stack
	docker compose -f docker-compose.prod.yml down

docker-prod-logs: ## Follow app logs (prod)
	docker compose -f docker-compose.prod.yml logs -f app

docker-prod-shell: ## Open a shell in the prod app container
	docker compose -f docker-compose.prod.yml exec app sh

docker-prod-artisan: ## Run an Artisan command in prod - usage: make docker-prod-artisan CMD="migrate"
	docker compose -f docker-compose.prod.yml exec app php artisan $(CMD)

docker-prod-ssl: ## Renew / re-issue the SSL certificate - usage: make docker-prod-ssl EMAIL=your@email.com
	@[ -n "$(EMAIL)" ] || { echo "ERROR: EMAIL is required - usage: make docker-prod-ssl EMAIL=your@email.com"; exit 1; }
	chmod +x docker/ssl/init-ssl.sh
	./docker/ssl/init-ssl.sh $(EMAIL)
