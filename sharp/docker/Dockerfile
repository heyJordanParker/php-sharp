ARG BASE_IMAGE=public.ecr.aws/docker/library/debian:trixie
ARG GOLANG_IMAGE=public.ecr.aws/docker/library/golang:1.27-trixie

FROM ${GOLANG_IMAGE} AS golang

FROM ${BASE_IMAGE}

# procps gives sharp/bin/build-mago's prune pgrep and ps, which find a process running from the build folder. ccache
# compiles the engine in CI, which sets CC and CXX to it, and a build without them compiles as before. Composer runs on
# Debian's php-cli, so php-bcmath and php-zip give it the ext-bcmath and ext-zip heyjordanparker/php-sharp-composer
# requires. cmake builds the libzip mago's engine links from its archive, and mago's engine runs readline on libedit
# and links libxml2's archive, which needs liblzma's.
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      autoconf \
      bison \
      build-essential \
      ca-certificates \
      ccache \
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

# The pinned tools a run would otherwise download, as the compilers above are: PHP-Parser at the version
# build/gen_stub.php pins, at its SHA-256, which sharp/bin/check generated links where gen_stub.php reads it, and the
# Lean sharp/mago/crates/sharp-lean/lean/lean-toolchain pins, as elan's toolchain in ELAN_HOME, where mago finds it.
# Every user reads and writes ELAN_HOME, so a container run with sharp/docker/run's --user finds it. A pin that moves
# without this file fails the run that reads it: sharp/bin/check generated for PHP-Parser, sharp/bin/test for Lean.
ARG PHP_PARSER_VERSION=5.6.1
ARG PHP_PARSER_SHA256=5d49e1b6a01fb55232d40dbcd1c7ae5b1de25f7de328b003cc50a81dc530a309
ARG LEAN_TOOLCHAIN=leanprover/lean4:v4.34.1
ENV ELAN_HOME=/opt/elan
RUN curl -fsSL --retry 3 -o /tmp/php-parser.tar.gz "https://github.com/nikic/PHP-Parser/archive/v$PHP_PARSER_VERSION.tar.gz" \
 && echo "$PHP_PARSER_SHA256  /tmp/php-parser.tar.gz" | sha256sum -c \
 && mkdir "/opt/PHP-Parser-$PHP_PARSER_VERSION" \
 && tar -xzf /tmp/php-parser.tar.gz -C "/opt/PHP-Parser-$PHP_PARSER_VERSION" --strip-components 1 \
 && rm /tmp/php-parser.tar.gz \
 && elan toolchain install "$LEAN_TOOLCHAIN" \
 && "$ELAN_HOME/toolchains/$(echo "$LEAN_TOOLCHAIN" | sed 's|/|--|; s|:|---|')/bin/lake" --version \
 && chmod -R a+rwX "$ELAN_HOME"

# sharp/bin/hosts builds FrankenPHP with the Go its v1.13.5 release names, as sharp/docker/runtime/frankenphp.Dockerfile
# does.
COPY --from=golang /usr/local/go /usr/local/go
ENV PATH=/usr/local/go/bin:$PATH \
    GOTOOLCHAIN=local
