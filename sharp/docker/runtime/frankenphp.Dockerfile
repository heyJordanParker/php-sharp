# No `# syntax=` line: it makes BuildKit pull the docker/dockerfile frontend from Docker Hub with no login (#86), so
# the image builds with BuildKit's own Dockerfile frontend. The WORKFLOWS job of .github/workflows/sharp.yml fails on
# one under sharp/docker.
#
# docker-library/php 8.5/trixie/zts at f6048b5f6e3c2202ee082665a4ccc0d90de291a3, built from this repository's source
# instead of the php.net tarball, then FrankenPHP v1.13.5's Dockerfile stages on top of it, as FrankenPHP builds on
# php:8.5-zts-trixie.
#

# The images are pinned by their digests, and .github/workflows/base-images.yml moves the digests on a branch that an
# issue names, for review. CI sets REGISTRY to ghcr.io/heyjordanparker, where base-images.yml copies the images under
# the same digests.
ARG REGISTRY=public.ecr.aws/docker/library
ARG BASE_IMAGE=${REGISTRY}/debian:trixie-slim@sha256:f6b08b09a05725f9431502d3b5714f5c4576e82c1a84fa1debe0c24a7e0c3ec4
# Go 1.27.4 builds FrankenPHP, which fixes CVE-2026-97054 in 1.27.3's net/http.
ARG GOLANG_IMAGE=${REGISTRY}/golang:1.27-trixie@sha256:f18c3d73a184fb0582828510722e723b63a93f7188d11dd223d9be6f6880e65d

# The tools image sharp/docker/tools/Dockerfile builds, the one place that pins each tool a build would otherwise
# download, so the image ships the PHP-Parser and the Lean its tests ran with. A local build tags it php-sharp-tools,
# as sharp/README.md shows, and the IMAGE job of .github/workflows/sharp.yml replaces this stage with
# --build-context tools=…, the image base-images.yml pushes under the blob hash of sharp/docker/tools/Dockerfile.
FROM php-sharp-tools AS tools

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
	# PHP#: remove once the Debian base image ships apt 3.0.4, the CVE-2026-109518 fix.
	apt-get install -y --no-install-recommends --only-upgrade apt libapt-pkg7.0; \
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
		libicu-dev \
		libonig-dev \
		libreadline-dev \
		libsodium-dev \
		libsqlite3-dev \
		libssl-dev \
		libxml2-dev \
		libzip-dev \
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
# PHP#: the options are sharp/docker/runtime/configure-options's, one per line, so $options splits into one word each.
# Its own line, so its failure stops the build.
	options="$(sharp/docker/runtime/configure-options)"; \
	./configure \
		--build="$gnuArch" \
		--sysconfdir="${PHP_INI_DIR%/php}" \
		--with-config-file-path="$PHP_INI_DIR" \
		--with-config-file-scan-dir="$PHP_INI_DIR/conf.d" \
		$options \
	; \
	make -j "$(nproc)"; \
	find -type f -name '*.a' -delete; \
# PHP#: the PEAR installer comes from the PEAR project's own repository at a fixed commit, so an outage of pear.php.net,
# which make install downloads it from by default, can't stop a release.
	make install \
		PEAR_INSTALLER_URL=https://raw.githubusercontent.com/pear/pearweb_phars/72aa5f771f56aaf826641b21159453f993e44450/install-pear-nozlib.phar \
	; \
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

# v1.13.5 takes golang.org/x/net v0.62.0, which fixes CVE-2026-78661 in v1.13.4's v0.61.0. The source is fetched by
# the commit its v1.13.5 tag names, so a moved tag builds nothing else.
ADD https://github.com/php/frankenphp.git#a3663e9e2cc4c258018a28ee068e50cc12f3ed2d /

# The PECL extensions the image installs, each fetched by the commit its release tag names, so an outage of
# pecl.php.net can't stop a release. igbinary 3.2.17RC1 is its first release that builds on PHP 8.5.
FROM scratch AS igbinary-source
ADD https://github.com/igbinary/igbinary.git#edda7101adf583df047d028a154abf3bf04ced61 /

FROM scratch AS imagick-source
ADD https://github.com/Imagick/imagick.git#70087bab33eab913e99ac77d64d04d1a2fd0b7b0 /

# A git source brings phpredis's liblzf submodule, which GitHub's tag archive leaves out.
FROM scratch AS phpredis-source
ADD https://github.com/phpredis/phpredis.git#df4fab2de7fc327c54c94a13af2b9542e4fbd720 /

# The -dev image's. excimer 1.2.6 is its first release that runs on a thread-safe engine, as FrankenPHP's, and its last
# with the package.xml install-php-extensions builds a source folder from: 1.2.7 removed it for PIE and changed no code.
FROM scratch AS excimer-source
ADD https://github.com/wikimedia/mediawiki-php-excimer.git#cc87c1e297cc39398ca786c41a486a3c1f17c8c3 /

FROM ${GOLANG_IMAGE} AS golang-base

#
# FrankenPHP v1.13.5's Dockerfile, with e-dant/watcher and install-php-extensions at fixed releases, each checked
# against its SHA-256. Its brotli build and the runner's libbrotli copy run only on Bookworm, which ships libbrotli
# 1.0.9, so they are left out: trixie ships 1.1.0.
#

FROM php-base AS common

WORKDIR /app

RUN apt-get update && \
	apt-get -y --no-install-recommends install \
		mailcap \
		libcap2-bin \
	&& \
	# TODO: remove once the php base images ship the CVE-2026-111497 fix
	apt-get -y --no-install-recommends install --only-upgrade libbrotli1 && \
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
		https://github.com/mlocati/docker-php-extension-installer/releases/download/2.13.2/install-php-extensions && \
	echo '27fa8d80bd4e22feb9ddcadff1ca40c8f3e8ea942eb2dd8f446ed1afd2f2f0ab  /usr/local/bin/install-php-extensions' | sha256sum -c && \
	chmod +x /usr/local/bin/install-php-extensions

# PHP#: the extensions beside PHP's own that link outside libraries and that framework images ship, so an app's image
# builds none of them again on each release: the PostgreSQL drivers, gd, imagick, and redis with igbinary as its
# serializer, which install-php-extensions installs first and builds redis against. It keeps each one's runtime
# libraries and removes its build packages. The sources mount for this step alone, so none stays in the image.
# imagick's `make install` generates its arginfo headers again with the gen_stub.php phpize copies into
# /tmp/pear/temp/imagick/build, which downloads the PHP-Parser it pins when that folder lacks it. So the folder links
# the tools stage's copy of that version first, and pecl deletes the link with its build folder.
RUN --mount=type=bind,from=igbinary-source,target=/usr/src/igbinary,rw \
	--mount=type=bind,from=imagick-source,target=/usr/src/imagick,rw \
	--mount=type=bind,from=phpredis-source,target=/usr/src/phpredis,rw \
	--mount=type=bind,from=tools,source=/opt,target=/opt/tools \
	version=$(sed -n 's/^ *[$]version = "\(.*\)";$/\1/p' /usr/local/lib/php/build/gen_stub.php) && \
	if [ ! -d "/opt/tools/PHP-Parser-$version" ]; then \
		echo "The tools image carries no PHP-Parser $version, which build/gen_stub.php pins. Pin it in sharp/docker/tools/Dockerfile." >&2; \
		exit 1; \
	fi && \
	mkdir -p /tmp/pear/temp/imagick/build && \
	ln -s "/opt/tools/PHP-Parser-$version" /tmp/pear/temp/imagick/build/ && \
	install-php-extensions gd pdo_pgsql pgsql /usr/src/igbinary /usr/src/imagick /usr/src/phpredis && \
	# Remove files with non-deterministic content (embedded timestamps and mtimes) to keep image digests reproducible
	rm -rf /var/lib/apt/lists/* /var/log/apt /var/log/dpkg.log /var/log/alternatives.log /var/cache/ldconfig/aux-cache

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

ARG FRANKENPHP_VERSION='1.13.5'
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
	libicu-dev \
	libonig-dev \
	libreadline-dev \
	libsodium-dev \
	libsqlite3-dev \
	libssl-dev \
	libxml2-dev \
	libzip-dev \
	zlib1g-dev \
	&& \
	apt-get clean

# Install e-dant/watcher (necessary for file watching), at the release FrankenPHP v1.13.5's go.mod pins
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
# A fetch from proxy.golang.org can fail on the network alone, as 0.6.25's arm64 build failed with "stream error:
# stream ID 1; INTERNAL_ERROR; received from peer", so the download retries, as .github/actions/setup-lean retries
# Lean's. go.sum checks every module it fetched, and a retry fetches only the modules still missing.
RUN for attempt in 1 2 3; do \
		if go mod download && (cd caddy && go mod download); then \
			exit 0; \
		fi; \
		if [[ $attempt != 3 ]]; then \
			echo "Retrying the Go module download in $((attempt * 15)) seconds (retry $attempt of 2)" >&2; \
			sleep $((attempt * 15)); \
		fi; \
	done; \
	echo "The Go module download failed 3 times. The output above names the cause." >&2; \
	exit 1

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

# Mago's cache folder is /var/cache/mago for every user. A copy into a folder that exists keeps the folder's mode, so a
# project's image that copies the -dev image's Lean with COPY --from=…-dev /var/cache/mago /var/cache/mago gets a folder
# www-data writes in, where COPY makes a folder it creates root's with mode 755.
ENV XDG_CACHE_HOME=/var/cache
RUN mkdir -m 0777 /var/cache/mago

# The Lean the law checker runs, where mago reads it: `lean/<archive>/` in Mago's cache folder, named as Lean's release
# archive for the platform is, which sharp/mago/crates/sharp-lean/src/library.rs pins. It is the tools stage's elan
# toolchain of the Lean version sharp/mago/crates/sharp-lean/lean/lean-toolchain pins, unpacked from that archive, so a
# new Lean version fails here until sharp/docker/tools/Dockerfile pins it too. Every user reads and writes the folder,
# so a container run with --user finds the Lean and builds the library beside it.
FROM ${BASE_IMAGE} AS lean

ARG TARGETARCH
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

COPY sharp/mago/crates/sharp-lean/lean/lean-toolchain /tmp/lean-toolchain
RUN --mount=type=bind,from=tools,source=/opt,target=/opt/tools \
	set -eux; \
	toolchain=$(< /tmp/lean-toolchain); \
	version=${toolchain##*:v}; \
	case "$TARGETARCH" in \
		amd64) archive="lean-$version-linux" ;; \
		arm64) archive="lean-$version-linux_aarch64" ;; \
		*) echo "Lean ships no Linux archive for $TARGETARCH." >&2; exit 1 ;; \
	esac; \
	installed="/opt/tools/elan/toolchains/$(sed 's|/|--|; s|:|---|' <<< "$toolchain")"; \
	if [[ ! -d $installed ]]; then \
		echo "The tools image carries no Lean $toolchain, which lean-toolchain pins. Pin it in sharp/docker/tools/Dockerfile." >&2; \
		exit 1; \
	fi; \
	mkdir -p /cache/mago/lean; \
	cp -a "$installed" "/cache/mago/lean/$archive"; \
	"/cache/mago/lean/$archive/bin/lake" --version; \
	chmod -R a+rwX /cache/mago

# ghcr.io/heyjordanparker/php-sharp:<version>-dev: the runtime image with the law checker's Lean installed, so a compile
# with laws in it downloads nothing, and excimer, the sampling profiler a framework app's development image installs.
# Mago's cache folder is /var/cache/mago for every user.
FROM runner AS dev

# PHP#: excimer, built as common builds imagick, so an app's development image builds it again on no release.
RUN --mount=type=bind,from=excimer-source,target=/usr/src/excimer,rw \
	install-php-extensions /usr/src/excimer && \
	# Remove files with non-deterministic content (embedded timestamps and mtimes) to keep image digests reproducible
	rm -rf /var/lib/apt/lists/* /var/log/apt /var/log/dpkg.log /var/log/alternatives.log /var/cache/ldconfig/aux-cache

# The folder's contents, so the copy keeps the mode of mago/ too.
COPY --link --from=lean /cache/ /var/cache/

# The runtime image stays the stage a build without --target makes.
FROM runner
