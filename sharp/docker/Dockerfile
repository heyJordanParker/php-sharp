FROM public.ecr.aws/docker/library/debian:trixie

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
