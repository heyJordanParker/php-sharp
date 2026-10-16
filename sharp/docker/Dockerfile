ARG BASE_IMAGE=public.ecr.aws/docker/library/debian:trixie
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
      php-cli \
      php-xml \
      pkg-config \
      re2c \
      rustup \
      unzip \
      libicu-dev \
      libonig-dev \
      libpq-dev \
      libsodium-dev \
      libsqlite3-dev \
      libxml2-dev \
      libzip-dev \
 && rm -rf /var/lib/apt/lists/*
