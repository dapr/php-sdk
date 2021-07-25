SHELL := /bin/bash

export GIT_SHA=$(shell git rev-parse HEAD)
include .env
export $(shell sed 's/=.*//' .env)

.PHONY: deps
deps: php-secrets-service php-secrets-http docker-compose.yml php-secret-client-http php-secret-client-service

.PHONY: php-secrets-service
php-secrets-service: images/fpm.conf images/opcache.ini
	docker build -f ../images/service.Dockerfile -t php-secrets-service:$(GIT_SHA) --pull --build-arg SERVICE=secrets --target production .

.PHONY: php-secrets-http
php-secrets-http: images/Caddyfile
	docker build -f ../images/caddy.Dockerfile -t php-secrets-http:$(GIT_SHA) --pull --build-arg SERVICE=secrets --target base .

.PHONY: php-secret-client-service
php-secret-client-service: images/fpm.conf images/opcache.ini
	docker build -f ../images/service.Dockerfile -t php-secret-client-service:$(GIT_SHA) --pull --build-arg SERVICE=client --target production .

.PHONY: php-secret-client-http
php-secret-client-http: images/Caddyfile
	docker build -f ../images/caddy.Dockerfile -t php-secret-client-http:$(GIT_SHA) --pull --build-arg SERVICE=client --target base .

.PHONY: start
start:
	DOCKER_BUILDKIT=1 COMPOSE_DOCKER_CLI_BUILD=1 docker-compose -f docker-compose.yml -f ../docker-compose.common.yml up -d

.PHONY: stop
stop:
	docker-compose -f docker-compose.yml -f ../docker-compose.common.yml stop

.PHONY: logs
logs:
	docker-compose -f docker-compose.yml -f ../docker-compose.common.yml logs

.PHONY: clean
clean: stop
	docker-compose -f docker-compose.yml -f ../docker-compose.common.yml rm -f
	rm -rf images

.PHONY: push
push: deps
	docker-compose push

.PHONY: deploy
deploy:
	kubectl apply -f deployments/simple-secret.yml
	kubectl apply -f deployments/some-secrets.yml
	envsubst < deployments/secret-service.yml | kubectl apply -f -
	envsubst < deployments/client-service.yml | kubectl apply -f -

.PHONY: deploy-delete
deploy-delete:
	kubectl delete -f deployments/simple-secret.yml
	kubectl delete -f deployments/some-secrets.yml
	envsubst < deployments/secret-service.yml | kubectl delete -f -
	envsubst < deployments/client-service.yml | kubectl delete -f -

images/fpm.conf: ../images/fpm.conf
	mkdir -p images
	cp ../images/fpm.conf images/fpm.conf
	touch images/fpm.conf

images/opcache.ini: ../images/opcache.ini
	mkdir -p images
	cp ../images/opcache.ini images/opcache.ini
	touch images/opcache.ini

images/xdebug.ini: ../images/xdebug.ini
	mkdir -p images
	cp ../images/xdebug.ini images/xdebug.ini
	touch images/xdebug.ini

images/Caddyfile: ../images/Caddyfile
	mkdir -p images
	cp ../images/Caddyfile images/Caddyfile
	touch images/Caddyfile
