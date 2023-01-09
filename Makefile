cnf ?= .env
include $(cnf)
export $(shell sed 's/=.*//' $(cnf))
RUN_APP = docker exec $(APP_NAME)-web-server-1
RUN_DB = docker exec $(APP_NAME)-mysql-server-1

.PHONY: help

help: ## This help.
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

build: ## Build the containers
	docker compose build

start: ## Start the containers
	docker compose up -d

stop: ## Stop the containers
	docker compose down

migrate: ## Execute laravel migrations
	$(RUN_APP) php artisan migrate

composer: ## Execute composer commands using c parameter ´c=install´
	$(RUN_APP) composer $(c)

artisan: ## Execute composer commands using c parameter ´c=install´
	$(RUN_APP) php artisan $(c)

recreate-env:
	$(RUN_APP) php artisan migrate:rollback \
	&& $(RUN_APP) php artisan migrate \
	&& $(RUN_APP) php artisan db:seed --class=PermissionsSeeder  \
	&& $(RUN_APP) php artisan db:seed --class=RolSeeder \
	&& $(RUN_APP) php artisan db:seed --class=UserSeeder