# syntax=docker/dockerfile:labs
FROM caddy AS base
ARG BASE
COPY images/Caddyfile /etc/caddy/Caddyfile
COPY $BASE /tests
