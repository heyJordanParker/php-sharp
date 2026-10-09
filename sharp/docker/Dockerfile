FROM public.ecr.aws/docker/library/debian:trixie@sha256:913f6706df59a68922d1dd08f78c2476560a8d367897200a6005b00e5f67c2d5

RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      autoconf \
      bison \
      build-essential \
      ca-certificates \
      curl \
      git \
      php-cli \
      pkg-config \
      re2c \
      rustup \
      libicu-dev \
      libonig-dev \
      libpq-dev \
      libsodium-dev \
      libsqlite3-dev \
      libxml2-dev \
      libzip-dev \
 && rm -rf /var/lib/apt/lists/*
