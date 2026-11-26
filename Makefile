.PHONY: help install restore-db \
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
	php artisan key:generate
	php artisan members:key
	php artisan migrate
	@echo "Development environment setup complete"

restore-db: ## Restore local MariaDB from database/backups - usage: make restore-db FILE=db-YYYYMMDD-HHMMSS.sql.gz
	@test -n "$(FILE)" || { echo "Usage: make restore-db FILE=db-YYYYMMDD-HHMMSS.sql.gz"; exit 1; }
	@case "$(FILE)" in */*|*..*) echo "Invalid file name: $(FILE)"; exit 1;; esac
	@test -f "database/backups/$(FILE)" || { echo "File not found: database/backups/$(FILE)"; exit 1; }
	@case "$(FILE)" in *.sql.gz) ;; *) echo "Expected a .sql.gz dump."; exit 1;; esac
	@DB_NAME=$$(sed -n 's/^DB_DATABASE=//p' .env 2>/dev/null | tail -n 1); \
	[ -n "$$DB_NAME" ] || DB_NAME=laravel; \
	printf 'This will DROP and recreate local database "%s" from %s.\n' "$$DB_NAME" "$(FILE)"; \
	if [ "$(YES)" != "1" ]; then \
		printf 'Type "yes" to continue: '; \
		read -r answer; \
		[ "$$answer" = "yes" ] || { echo "Aborted."; exit 1; }; \
	fi; \
	SOURCE_DB=$$(gzip -cd "database/backups/$(FILE)" | awk '\
		/^-- Current Database: `/ { db=$$0; sub(/^-- Current Database: `/, "", db); sub(/`$$/, "", db); next } \
		/^CREATE TABLE `migrations`/ { print db; exit } \
	'); \
	[ -n "$$SOURCE_DB" ] || { echo "Could not detect the application database in the dump."; exit 1; }; \
	echo "Source database: $$SOURCE_DB"; \
	docker compose up -d --wait db; \
	docker compose exec -T db sh -c 'mariadb -u root -p"$$MARIADB_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS \`$$MARIADB_DATABASE\`; CREATE DATABASE \`$$MARIADB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"'; \
	{ \
		printf 'SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n'; \
		gzip -cd "database/backups/$(FILE)" | awk -v source="$$SOURCE_DB" '\
			/^-- Current Database: `/ { current=$$0; sub(/^-- Current Database: `/, "", current); sub(/`$$/, "", current); in_section=(current==source); next } \
			in_section && /^CREATE DATABASE / { next } \
			in_section && /^USE `/ { next } \
			in_section { print } \
		'; \
		printf 'SET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n'; \
	} | docker compose exec -T db sh -c 'mariadb --default-character-set=utf8mb4 -u root -p"$$MARIADB_ROOT_PASSWORD" "$$MARIADB_DATABASE"'; \
	echo "Database $$DB_NAME restored from $(FILE)."

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
	docker compose exec app php artisan members:key
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

