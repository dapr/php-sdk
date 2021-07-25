
export GITHUB_SHA=latest
export DAPR_VERSION=1.3.0-rc.1

PHIVE=$(shell which phive || echo .phive/phive)

.PHONY: integration-tests
integration-tests: build tools
	docker-compose down -v
	docker-compose up &
	sleep 10
	curl --silent --output /tmp/test-results.json --write-out "%{http_code}" -H 'dapr-api-token: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c' http://localhost:9502/do_tests
	docker-compose down -v
	cat /tmp/test-results.json | jq .

.PHONY: stop
	docker-compose down -v

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

$(PHIVE):
	wget -O phive.phar "https://phar.io/releases/phive.phar"
	wget -O phive.phar.asc "https://phar.io/releases/phive.phar.asc"
	gpg --keyserver hkps://keys.openpgp.org --recv-keys 0x9D8A98B29B2D5D79
	gpg --verify phive.phar.asc phive.phar
	rm phive.phar.asc
	chmod +x phive.phar
	mv phive.phar $(PHIVE)

tools: .phive/phars.xml $(PHIVE)
	rm -rf tools && echo $(PHIVE)
	$(PHIVE) install --trust-gpg-keys 12CE0F1D262429A5,4AA394086372C20A
