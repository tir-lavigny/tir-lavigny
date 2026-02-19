.PHONY: help install


default: help

help: ## Display this help message
	@echo "Usage: make <target>"
	@echo
	@echo "Possible targets:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "    %-20s%s\n", $$1, $$2}'

	


install: ## Install project dependencies
	composer install
	npm ci
	npm run build
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