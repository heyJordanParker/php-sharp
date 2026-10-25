ARG BASE_IMAGE=public.ecr.aws/docker/library/debian:trixie
ARG GOLANG_IMAGE=public.ecr.aws/docker/library/golang:1.27-trixie

FROM ${GOLANG_IMAGE} AS golang

FROM ${BASE_IMAGE}

RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      autoconf \
      bison \
      build-essential \
      ca-certificates \
      composer \
      curl \
      elan \
      git \
      libfcgi-bin \
      php-cli \
      php-xml \
      pkg-config \
      re2c \
      rustup \
      unzip \
      libcurl4-openssl-dev \
      libicu-dev \
      libonig-dev \
      libpq-dev \
      libsodium-dev \
      libsqlite3-dev \
      libxml2-dev \
      libzip-dev \
 && rm -rf /var/lib/apt/lists/*

# sharp/bin/hosts builds FrankenPHP with the Go its v1.13.1 release names, as sharp/docker/runtime/frankenphp.Dockerfile
# does, and serves the fixture through php-fpm with libfcgi-bin's cgi-fcgi.
COPY --from=golang /usr/local/go /usr/local/go
ENV PATH=/usr/local/go/bin:$PATH \
    GOTOOLCHAIN=local
