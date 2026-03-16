.PHONY: up down test lint analyse check install composer-install

DOCKER_COMPOSE = docker compose
APP_SERVICE    = app
COMPOSER_SERVICE = composer

up:
	$(DOCKER_COMPOSE) up -d mysql redis
	@echo "Services started: mysql, redis"

down:
	$(DOCKER_COMPOSE) down

install:
	$(DOCKER_COMPOSE) run --rm $(COMPOSER_SERVICE) install --no-progress --prefer-dist --optimize-autoloader

test:
	$(DOCKER_COMPOSE) run --rm $(APP_SERVICE) vendor/bin/phpunit --colors=always

lint:
	$(DOCKER_COMPOSE) run --rm $(APP_SERVICE) vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes

lint-fix:
	$(DOCKER_COMPOSE) run --rm $(APP_SERVICE) vendor/bin/php-cs-fixer fix --allow-risky=yes

analyse:
	$(DOCKER_COMPOSE) run --rm $(APP_SERVICE) vendor/bin/phpstan analyse --memory-limit=256M

check: lint analyse test

build-phar:
	$(DOCKER_COMPOSE) run --rm $(APP_SERVICE) php -d phar.readonly=0 build/build-phar.php

kphp-check:
	docker build -f Dockerfile.check -t lphenom-check .

