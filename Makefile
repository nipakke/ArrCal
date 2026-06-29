.PHONY: run build test lint format clean

run:
	APP_ENV=local php bin/server

build:
	composer install --no-dev --optimize-autoloader

test:
	./vendor/bin/pest

lint:
	composer lint
format:
	composer format

clean:
	rm -rf cache/
