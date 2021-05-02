SHELL := /bin/bash

include .env
export $(shell sed 's/=.*//' .env)

.PHONY: deps
deps: images/fpm.conf images/opcache.ini images/xdebug.ini images/Caddyfile docker-compose.yml
	DOCKER_BUILDKIT=1 COMPOSE_DOCKER_CLI_BUILD=1 docker-compose build

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
