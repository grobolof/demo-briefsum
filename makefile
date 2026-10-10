include .env

# Запустить контейнеры:
up:
	docker compose up -d --build --remove-orphans

# Остановить контейнеры:
stop:
	docker compose stop

# Остановить и удалить контейнеры:
down:
	docker compose down -v

# Очистить кэш Symfony:
cache-clear:
	docker compose exec -it application php bin/console cache:clear

# КОМАНДЫ ДОСТУПНЫ ПРИ УСЛОВИИ ЧТО ПРОЕКТ SYMFONY УЖЕ СОЗДАН:

## Создать файл миграции:
migrations-create:
	docker compose exec -it application php bin/console make:migration

## Накатить все миграции:
migrations-up:
	docker compose exec -it application php bin/console doctrine:migrations:migrate --no-interaction

## Откатить последнюю пачку миграций:
migrations-down:
	docker compose exec -it application php bin/console doctrine:migrations:migrate prev --no-interaction

## Произвольная команда console:
console: ## Пример: make console CMD="debug:router"
	docker compose exec -it application php bin/console $(CMD)

# КОМАНДА ДОСТУПНА ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН ПАКЕТ PHPSTAN:

## Проверить код на ошибки:
phpstan:
	docker compose exec -it application php vendor/bin/phpstan analyse

# КОМАНДЫ ДОСТУПНЫ ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН ПАКЕТ PHPCS:

## Исправить ошибки:
phpcs:
	docker compose exec -it application php vendor/bin/php-cs-fixer fix --allow-risky=yes

## Проверить код на ошибки без исправления:
phpcs-dry:
	docker compose exec -it application php vendor/bin/php-cs-fixer fix --allow-risky=yes --dry-run || true

# КОМАНДЫ ДОСТУПНЫ ПРИ УСЛОВИИ ЧТО УСТАНОВЛЕН ПАКЕТ RECTOR:

## Исправить ошибки:
rector:
	docker compose exec -it application php vendor/bin/rector process

## Проверить код на ошибки без исправления:
rector-dry:
	docker compose exec -it application php vendor/bin/rector process --dry-run || true

# КОМАНДА ДОСТУПНА ПРИ УСЛОВИИ ЧТО ЕСТЬ ТЕСТЫ PHPUNIT:

## Запустить тесты:
test:
	docker compose exec -it application php bin/phpunit
