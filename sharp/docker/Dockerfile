ARG BASE_IMAGE=public.ecr.aws/docker/library/debian:trixie
ARG GOLANG_IMAGE=public.ecr.aws/docker/library/golang:1.27-trixie

FROM ${GOLANG_IMAGE} AS golang

FROM ${BASE_IMAGE}

# procps gives sharp/bin/build-mago's prune pgrep and ps, which find a process running from the build folder. ccache
# compiles the engine in CI, which sets CC and CXX to it, and a build without them compiles as before. Composer runs on
# Debian's php-cli, so php-bcmath and php-zip give it the ext-bcmath and ext-zip heyjordanparker/php-sharp-composer
# requires.
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      autoconf \
      bison \
      build-essential \
      ca-certificates \
      ccache \
      composer \
      curl \
      elan \
      git \
      php-bcmath \
      php-cli \
      php-xml \
      php-zip \
      pkg-config \
      procps \
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

# sharp/bin/hosts builds FrankenPHP with the Go its v1.13.5 release names, as sharp/docker/runtime/frankenphp.Dockerfile
# does.
COPY --from=golang /usr/local/go /usr/local/go
ENV PATH=/usr/local/go/bin:$PATH \
    GOTOOLCHAIN=local
