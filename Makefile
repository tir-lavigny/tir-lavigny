.PHONY: help install \
	docker-dev-build docker-dev-setup docker-dev-up docker-dev-down docker-dev-logs docker-dev-shell docker-dev-artisan \


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
	touch database/database.sqlite database/telescope.sqlite
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

