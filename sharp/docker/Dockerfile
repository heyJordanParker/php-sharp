ARG BASE_IMAGE=public.ecr.aws/docker/library/debian:trixie
ARG GOLANG_IMAGE=public.ecr.aws/docker/library/golang:1.27-trixie

# The tools image sharp/docker/tools/Dockerfile builds, which sharp/docker/run builds and tags php-sharp-tools, and
# base-images.yml's DEVELOPMENT job replaces with --build-context tools=…, the image it builds in the same job.
FROM php-sharp-tools AS tools

FROM ${GOLANG_IMAGE} AS golang

FROM ${BASE_IMAGE}

# procps gives sharp/bin/build-mago's prune pgrep and ps, which find a process running from the build folder.
# Composer runs on Debian's php-cli, so php-bcmath and php-zip give it the ext-bcmath and ext-zip
# heyjordanparker/php-sharp-composer requires. cmake builds the libzip mago's engine links from its archive, and mago's
# engine runs readline on libedit and links libxml2's archive, which needs liblzma's. valgrind counts what SPEED
# measures. actionlint checks each workflow's scripts with shellcheck when it finds one.
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      autoconf \
      bison \
      build-essential \
      ca-certificates \
      cmake \
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
      shellcheck \
      unzip \
      valgrind \
      libargon2-dev \
      libcurl4-openssl-dev \
      libedit-dev \
      libicu-dev \
      liblzma-dev \
      libonig-dev \
      libpq-dev \
      libreadline-dev \
      libsodium-dev \
      libsqlite3-dev \
      libssl-dev \
      libxml2-dev \
      libzip-dev \
      zlib1g-dev \
 && rm -rf /var/lib/apt/lists/*

# The pinned tools: the tools image's PHP-Parser, which sharp/bin/check links where gen_stub.php reads it, its
# actionlint, which sharp/bin/check workflows runs, and its Lean, as elan's toolchain in ELAN_HOME, where mago finds it,
# and the Rust ext/sharp/rust-toolchain.toml pins, in
# RUSTUP_HOME, which every cargo of the engine and of sharp/bin/build-mago runs. Every user reads and writes ELAN_HOME,
# and reads RUSTUP_HOME, so a container run with sharp/docker/run's --user finds both. A Rust pin that moves without
# this file fails rustup, which can't install the new toolchain into a RUSTUP_HOME the run's user can't write.
ARG RUST_TOOLCHAIN=1.97.0
ENV ELAN_HOME=/opt/elan RUSTUP_HOME=/opt/rustup PATH=/opt/actionlint:$PATH
COPY --from=tools /opt/ /opt/
RUN "$ELAN_HOME"/toolchains/*/bin/lake --version \
 && rustup toolchain install "$RUST_TOOLCHAIN" --profile minimal \
 && rustup run "$RUST_TOOLCHAIN" cargo --version \
 && chmod -R a+rX,go-w "$RUSTUP_HOME"

# sharp/bin/hosts builds FrankenPHP with the Go its v1.13.5 release names, as sharp/docker/runtime/frankenphp.Dockerfile
# does.
COPY --from=golang /usr/local/go /usr/local/go
ENV PATH=/usr/local/go/bin:$PATH \
    GOTOOLCHAIN=local
