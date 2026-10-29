# No `# syntax=` line: it makes BuildKit pull the docker/dockerfile frontend from Docker Hub with no login (#86), so
# the image builds with BuildKit's own Dockerfile frontend. The WORKFLOWS job of .github/workflows/sharp.yml fails on
# one under sharp/docker.
#
# docker-library/php 8.5/trixie/zts at f6048b5f6e3c2202ee082665a4ccc0d90de291a3, built from this repository's source
# instead of the php.net tarball, then FrankenPHP v1.13.1's Dockerfile stages on top of it, as FrankenPHP builds on
# php:8.5-zts-trixie.
#

# The images are pinned by their digests, and .github/workflows/base-images.yml moves the digests on a branch that an
# issue names, for review. CI sets REGISTRY to ghcr.io/heyjordanparker, where base-images.yml copies the images under
# the same digests.
ARG REGISTRY=public.ecr.aws/docker/library
ARG BASE_IMAGE=${REGISTRY}/debian:trixie-slim@sha256:f6b08b09a05725f9431502d3b5714f5c4576e82c1a84fa1debe0c24a7e0c3ec4
ARG GOLANG_IMAGE=${REGISTRY}/golang:1.27-trixie@sha256:c65ecfe613b539ac74c62a4c3b2c27e8f8a6076384c8f27e8a3c0f3be17cc385
FROM ${BASE_IMAGE} AS source

RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		autoconf \
		bison \
		make \
		re2c \
		xz-utils \
	; \
	apt-get dist-clean

COPY . /usr/src/php

RUN set -eux; \
	cd /usr/src/php; \
	./buildconf --force; \
	scripts/dev/genfiles; \
	rm -rf autom4te.cache; \
	cd /usr/src; \
	tar -cJf php.tar.xz --owner=0 --group=0 --numeric-owner --sort=name php

FROM ${BASE_IMAGE} AS php-base

# prevent Debian's PHP packages from being installed
# https://github.com/docker-library/php/pull/542
RUN set -eux; \
	{ \
		echo 'Package: php*'; \
		echo 'Pin: release *'; \
		echo 'Pin-Priority: -1'; \
	} > /etc/apt/preferences.d/no-debian-php

# dependencies required for running "phpize"
# (see persistent deps below)
ENV PHPIZE_DEPS \
		autoconf \
		dpkg-dev \
		file \
		g++ \
		gcc \
		libc-dev \
		make \
		pkg-config \
		re2c

# persistent / runtime deps
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		$PHPIZE_DEPS \
		ca-certificates \
		curl \
		xz-utils \
	; \
	apt-get dist-clean

ENV PHP_INI_DIR /usr/local/etc/php
RUN set -eux; \
	mkdir -p "$PHP_INI_DIR/conf.d"; \
# allow running as an arbitrary user (https://github.com/docker-library/php/issues/743)
	[ ! -d /var/www/html ]; \
	mkdir -p /var/www/html; \
	chown www-data:www-data /var/www/html; \
	chmod 1777 /var/www/html

# Apply stack smash protection to functions using local buffers and alloca()
# Make PHP's main executable position-independent (improves ASLR security mechanism, and has no performance impact on x86_64)
# Enable optimization (-O2)
# Enable linker optimization (this sorts the hash buckets to improve cache locality, and is non-default)
# https://github.com/docker-library/php/issues/272
# -D_LARGEFILE_SOURCE and -D_FILE_OFFSET_BITS=64 (https://www.php.net/manual/en/intro.filesystem.php)
ENV PHP_CFLAGS="-fstack-protector-strong -fpic -fpie -O2 -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64"
ENV PHP_CPPFLAGS="$PHP_CFLAGS"
ENV PHP_LDFLAGS="-Wl,-O1 -pie"

ENV PHP_VERSION 8.5.12

COPY --from=source /usr/src/php.tar.xz /usr/src/

COPY sharp/docker/runtime/docker-php-source /usr/local/bin/

RUN set -eux; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		git \
		libargon2-dev \
		libcurl4-openssl-dev \
		libonig-dev \
		libreadline-dev \
		libsodium-dev \
		libsqlite3-dev \
		libssl-dev \
		libxml2-dev \
		rustup \
		zlib1g-dev \
	; \
	\
	export \
		CFLAGS="$PHP_CFLAGS" \
		CPPFLAGS="$PHP_CPPFLAGS" \
		LDFLAGS="$PHP_LDFLAGS" \
# https://github.com/php/php-src/blob/d6299206dd828382753453befd1b915491b741c6/configure.ac#L1496-L1511
		PHP_BUILD_PROVIDER='https://github.com/heyJordanParker/php-sharp' \
		PHP_UNAME='Linux - Docker' \
	; \
	docker-php-source extract; \
	cd /usr/src/php; \
	gnuArch="$(dpkg-architecture --query DEB_BUILD_GNU_TYPE)"; \
	debMultiarch="$(dpkg-architecture --query DEB_BUILD_MULTIARCH)"; \
# https://bugs.php.net/bug.php?id=74125
	if [ ! -d /usr/include/curl ]; then \
		ln -sT "/usr/include/$debMultiarch/curl" /usr/local/include/curl; \
	fi; \
	case "$gnuArch" in \
		'i686-linux-'*) \
			# php zend jit assumes SSE registers, xmm0-7, are available (and "Pentium4"/SSE2 is a reasonable baseline)
			# https://github.com/php/php-src/pull/22832
			# so, allow it and use SSE/SSE2 floating-point unit instead of the legacy stack
			export \
				CFLAGS="$CFLAGS -msse2 -mfpmath=sse" \
				CPPFLAGS="$CPPFLAGS -msse2 -mfpmath=sse" \
			; \
			;; \
	esac; \
	test "$PHP_INI_DIR" != "${PHP_INI_DIR%/php}"; \
	./configure \
		--build="$gnuArch" \
		--sysconfdir="${PHP_INI_DIR%/php}" \
		--with-config-file-path="$PHP_INI_DIR" \
		--with-config-file-scan-dir="$PHP_INI_DIR/conf.d" \
		\
# make sure invalid --configure-flags are fatal errors instead of just warnings
		--enable-option-checking=fatal \
# a compiler warning fails the release build, as php-src's own CI builds
		--enable-werror \
		\
# https://github.com/docker-library/php/issues/439
		--with-mhash \
		\
# https://github.com/docker-library/php/issues/822
		--with-pic \
		\
# --enable-mbstring is included here because otherwise there's no way to get pecl to use it properly (see https://github.com/docker-library/php/issues/195)
		--enable-mbstring \
# --enable-mysqlnd is included here because it's harder to compile after the fact than extensions are (since it's a plugin for several extensions, not an extension in itself)
		--enable-mysqlnd \
# https://wiki.php.net/rfc/argon2_password_hash
		--with-password-argon2 \
# https://wiki.php.net/rfc/libsodium
		--with-sodium=shared \
# always build against system sqlite3 (https://github.com/php/php-src/commit/6083a387a81dbbd66d6316a3a12a63f06d5f7109)
		--with-pdo-sqlite=/usr \
		--with-sqlite3=/usr \
		\
		--with-curl \
		--with-iconv \
		--with-openssl \
		--with-readline \
		--with-zlib \
		\
# https://github.com/docker-library/php/pull/1259
		--enable-phpdbg \
		--enable-phpdbg-readline \
		\
# in PHP 7.4+, the pecl/pear installers are officially deprecated (requiring an explicit "--with-pear")
		--with-pear \
		\
		--with-libdir="lib/$debMultiarch" \
		\
# https://github.com/docker-library/php/pull/939#issuecomment-730501748
		--enable-embed \
		\
		--enable-zts \
# https://externals.io/message/118859
		--disable-zend-signals \
	; \
	make -j "$(nproc)"; \
	find -type f -name '*.a' -delete; \
	make install; \
	find \
		/usr/local \
		-type f \
		-perm '/0111' \
		-exec sh -euxc ' \
			strip --strip-all "$@" || : \
		' -- '{}' + \
	; \
	make clean; \
	\
# https://github.com/docker-library/php/issues/692 (copy default example "php.ini" files somewhere easily discoverable)
	cp -v php.ini-* "$PHP_INI_DIR/"; \
	\
	cd /; \
	docker-php-source delete; \
	rm -rf ~/.cargo ~/.rustup; \
	\
# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
	apt-mark auto '.*' > /dev/null; \
	[ -z "$savedAptMark" ] || apt-mark manual $savedAptMark; \
	find /usr/local -type f -executable -exec ldd '{}' ';' \
		| awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
		| sort -u \
		| xargs -rt dpkg-query --search \
# https://manpages.debian.org/trixie/dpkg/dpkg-query.1.en.html#S (we ignore diversions and it'll be really unusual for more than one package to provide any given .so file)
		| awk 'sub(":$", "", $1) { print $1 }' \
		| sort -u \
		| xargs -r apt-mark manual \
	; \
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	apt-get dist-clean; \
	\
# update pecl channel definitions https://github.com/docker-library/php/issues/443
	pecl update-channels; \
	rm -rf /tmp/pear ~/.pearrc; \
	\
# smoke test
	php --version; \
	test "$(php -r 'echo PHP_VERSION;')" = "$PHP_VERSION"; \
	test "$(php -r 'echo PHP_ZTS;')" = 1; \
	php --ri sharp

COPY sharp/docker/runtime/docker-php-ext-* sharp/docker/runtime/zts/docker-php-entrypoint /usr/local/bin/

# sodium was built as a shared module (so that it can be replaced later if so desired), so let's enable it too (https://github.com/docker-library/php/issues/598)
RUN docker-php-ext-enable sodium

ENTRYPOINT ["docker-php-entrypoint"]

FROM scratch AS frankenphp-source

# v1.13.1 takes golang.org/x/net v0.61.0, which fixes CVE-2026-78669 in v1.13.0's v0.59.0. The source is fetched by
# the commit its v1.13.1 tag names, so a moved tag builds nothing else.
ADD https://github.com/php/frankenphp.git#99d4e119ca2e5a90a69d8dbee5d5c9a8016416c5 /

FROM ${GOLANG_IMAGE} AS golang-base

#
# FrankenPHP v1.13.1's Dockerfile, with e-dant/watcher and install-php-extensions at fixed releases, each checked
# against its SHA-256.
#

FROM php-base AS common

WORKDIR /app

RUN apt-get update && \
	apt-get -y --no-install-recommends install \
		mailcap \
		libcap2-bin \
	&& \
	apt-get clean && \
	# Remove files with non-deterministic content (embedded timestamps and mtimes) to keep image digests reproducible
	rm -rf /var/lib/apt/lists/* /var/log/apt /var/log/dpkg.log /var/log/alternatives.log /var/cache/ldconfig/aux-cache

RUN set -eux; \
	mkdir -p \
		/app/public \
		/config/caddy \
		/data/caddy \
		/etc/caddy \
		/etc/frankenphp; \
	sed -i 's/php/frankenphp run/g' /usr/local/bin/docker-php-entrypoint

COPY --link --from=frankenphp-source /caddy/frankenphp/Caddyfile /etc/caddy/Caddyfile
COPY --link --from=frankenphp-source /package/content/index.php /app/public/index.php
RUN ln /etc/caddy/Caddyfile /etc/frankenphp/Caddyfile && \
	curl -sSLf \
		-o /usr/local/bin/install-php-extensions \
		https://github.com/mlocati/docker-php-extension-installer/releases/download/2.12.0/install-php-extensions && \
	echo '3f49c71fa66c79b8b2b96bc0ce92885dafd7946f3b5a46f545b390d6f1617e2c  /usr/local/bin/install-php-extensions' | sha256sum -c && \
	chmod +x /usr/local/bin/install-php-extensions

CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
HEALTHCHECK CMD curl -f http://localhost:2019/metrics || exit 1

# See https://caddyserver.com/docs/conventions#file-locations for details
ENV XDG_CONFIG_HOME=/config
ENV XDG_DATA_HOME=/data

EXPOSE 80
EXPOSE 443
EXPOSE 443/udp
EXPOSE 2019

FROM common AS builder

ARG FRANKENPHP_VERSION='1.13.1'
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

COPY --from=golang-base /usr/local/go /usr/local/go

ENV PATH=/usr/local/go/bin:$PATH
ENV GOTOOLCHAIN=local

# This is required to link the FrankenPHP binary to the PHP binary
RUN apt-get update && \
	apt-get -y --no-install-recommends install \
	cmake \
	git \
	libargon2-dev \
	libbrotli-dev \
	libcurl4-openssl-dev \
	libonig-dev \
	libreadline-dev \
	libsodium-dev \
	libsqlite3-dev \
	libssl-dev \
	libxml2-dev \
	zlib1g-dev \
	&& \
	apt-get clean

# Install e-dant/watcher (necessary for file watching), at the release FrankenPHP v1.13.1's go.mod pins
WORKDIR /usr/local/src/watcher
RUN set -eux; \
	curl -fsSL -o /tmp/watcher.tar.gz https://github.com/e-dant/watcher/archive/refs/tags/0.14.5.tar.gz; \
	echo '190bbe5e19923083db2beb3aa68f4e99a563c8f685d8037b0536780b0116c4d3  /tmp/watcher.tar.gz' | sha256sum -c; \
	tar xzf /tmp/watcher.tar.gz --strip-components 1; \
	rm /tmp/watcher.tar.gz; \
	# -Wno-error=use-after-free: GCC 12 on Bookworm i386 emits a spurious warning in libstdc++ basic_string.h
	cmake -S . -B build -DCMAKE_BUILD_TYPE=Release -DCMAKE_CXX_FLAGS="-Wno-error=use-after-free"; \
	cmake --build build; \
	cmake --install build; \
	ldconfig

WORKDIR /go/src/app
COPY --link --from=frankenphp-source / ./
RUN go mod download

WORKDIR /go/src/app/caddy
RUN go mod download

# See https://github.com/docker-library/php/blob/master/8.5/trixie/zts/Dockerfile#L57-L59 for PHP values
ENV CGO_CFLAGS="-DFRANKENPHP_VERSION=$FRANKENPHP_VERSION $PHP_CFLAGS"
ENV CGO_CPPFLAGS=$PHP_CPPFLAGS
ENV CGO_LDFLAGS="-L/usr/local/lib -lssl -lcrypto -lreadline -largon2 -lcurl -lonig -lz $PHP_LDFLAGS"

WORKDIR /go/src/app/caddy/frankenphp
RUN GOBIN=/usr/local/bin \
	../../go.sh install -ldflags "-w -s -X 'github.com/caddyserver/caddy/v2.CustomVersion=FrankenPHP $FRANKENPHP_VERSION PHP $PHP_VERSION Caddy' -X 'github.com/caddyserver/caddy/v2.CustomBinaryName=frankenphp' -X 'github.com/caddyserver/caddy/v2/modules/caddyhttp.ServerHeader=FrankenPHP Caddy'" -buildvcs=false && \
	setcap cap_net_bind_service=+ep /usr/local/bin/frankenphp && \
	cp Caddyfile /etc/frankenphp/Caddyfile && \
	frankenphp version && \
	frankenphp build-info

FROM common AS runner

ENV GODEBUG=cgocheck=0

# copy watcher shared library
COPY --from=builder /usr/local/lib/libwatcher* /usr/local/lib/
# fix for the file watcher on arm
RUN apt-get update && \
	apt-get install -y --no-install-recommends libstdc++6 && \
	apt-get clean && \
	ldconfig && \
	# Remove files with non-deterministic content (embedded timestamps and mtimes) to keep image digests reproducible
	rm -rf /var/lib/apt/lists/* /var/log/apt /var/log/dpkg.log /var/log/alternatives.log /var/cache/ldconfig/aux-cache

COPY --from=builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp
RUN setcap cap_net_bind_service=+ep /usr/local/bin/frankenphp && \
	frankenphp version && \
	frankenphp build-info && \
	php --ri sharp
