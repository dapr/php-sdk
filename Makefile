
export GITHUB_SHA=latest
export DAPR_VERSION=1.3.0-rc.1

.PHONY: integration-tests
integration-tests: build
	docker-compose down -v
	docker-compose up &
	sleep 10
	curl --silent --output /tmp/test-results.json --write-out "%{http_code}" http://localhost:9502/do_tests
	docker-compose down -v
	cat /tmp/test-results.json | jq .

composer.lock: composer.json
	composer update

vendor/autoload.php: composer.lock
	composer install
	touch vendor/autoload.php

.PHONY: build
build: build-caddy build-tests

.PHONY: build-tests
build-tests: vendor/autoload.php
	docker build -t tests:latest -f images/tests.Dockerfile .

.PHONY: build-caddy
build-caddy: vendor/autoload.php
	docker build -t caddy:latest -f images/caddy.Dockerfile .
