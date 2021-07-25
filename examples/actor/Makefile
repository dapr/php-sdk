SHELL := /bin/bash

include .env
export $(shell sed 's/=.*//' .env)
export GIT_SHA=$(shell git rev-parse HEAD)

.PHONY: deps
deps: service/actor-http service/actor-service service/client-http service/client-service
	@echo 'all done: run `make start` to get started'

.PHONY: service/actor-service
service/actor-service: images/opcache.ini images/fpm.conf
	docker build --pull --build-arg SERVICE=actor -f ../images/service.Dockerfile --target production -t php-actor-service:${GIT_SHA} .

.PHONY: service/actor-http
service/actor-http: images/Caddyfile
	docker build --pull --build-arg SERVICE=actor -f ../images/caddy.Dockerfile --target base -t php-actor-http:${GIT_SHA} .

.PHONY: service/client-service
service/client-service: images/opcache.ini images/opcache.ini
	docker build --pull --build-arg SERVICE=client -f ../images/service.Dockerfile --target production -t php-client-service:${GIT_SHA} .

.PHONY: service/client-http
service/client-http: images/Caddyfile
	docker build --pull --build-arg SERICE=client -f ../images/caddy.Dockerfile --target base -t ${DOCKER_USER}/php-client-http:${GIT_SHA} .

.PHONY: start
start:
	DOCKER_BUILDKIT=1 COMPOSE_DOCKER_CLI_BUILD=1 docker-compose -f docker-compose.yml -f ../docker-compose.common.yml up -d

.PHONY: stop
stop:
	docker-compose -f docker-compose.yml -f ../docker-compose.common.yml stop

.PHONY: clean
clean: stop
	docker-compose -f docker-compose.yml -f ../docker-compose.common.yml rm -f
	rm -rf images

.PHONY: push
push: deps
	docker-compose push

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
