ARG BASE_IMAGE=public.ecr.aws/docker/library/debian:trixie

# The pinned files a build would otherwise download from github.com, the one place each is pinned, so every image ships
# the tools its tests ran with and no CI job downloads one mid-run, where a network fault would fail the job. /opt holds
# the tools: PHP-Parser at the version build/gen_stub.php pins, in /opt/PHP-Parser-<version>, the Lean
# sharp/mago/crates/sharp-lean/lean/lean-toolchain pins, as elan's toolchain in /opt/elan, unpacked from the release
# archive whose SHA-256 sharp/mago/crates/sharp-lean/src/library.rs pins for the platform, as elan unpacks it, and the
# actionlint sharp/bin/check workflows runs, in /opt/actionlint. sharp/docker/Dockerfile copies /opt as its ELAN_HOME,
# its PHP-Parser and its actionlint, and sharp/docker/runtime/frankenphp.Dockerfile copies the Lean into Mago's cache
# folder and links the PHP-Parser where imagick's gen_stub.php reads it. /runtime holds what only frankenphp.Dockerfile
# builds from: the PEAR installer, install-php-extensions, e-dant/watcher's source, and the sources of FrankenPHP and of
# the extensions the image builds. The download runs on the build's own platform for every target platform, so one
# builder makes the image of both with no emulation. A pin that moves without this file fails the build that reads it:
# sharp/bin/check and frankenphp.Dockerfile for PHP-Parser, and sharp/bin/test and frankenphp.Dockerfile for Lean.
FROM --platform=$BUILDPLATFORM ${BASE_IMAGE} AS download

RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ca-certificates curl zstd \
 && rm -rf /var/lib/apt/lists/*

ARG TARGETARCH
ARG PHP_PARSER_VERSION=5.6.1
ARG PHP_PARSER_SHA256=5d49e1b6a01fb55232d40dbcd1c7ae5b1de25f7de328b003cc50a81dc530a309
ARG LEAN_TOOLCHAIN=leanprover/lean4:v4.34.1
ARG ACTIONLINT_VERSION=1.7.12
RUN set -eux; \
	curl -fsSL --retry 3 -o /tmp/php-parser.tar.gz "https://github.com/nikic/PHP-Parser/archive/v$PHP_PARSER_VERSION.tar.gz"; \
	echo "$PHP_PARSER_SHA256  /tmp/php-parser.tar.gz" | sha256sum -c; \
	mkdir "/opt/PHP-Parser-$PHP_PARSER_VERSION"; \
	tar -xzf /tmp/php-parser.tar.gz -C "/opt/PHP-Parser-$PHP_PARSER_VERSION" --strip-components 1; \
	version=${LEAN_TOOLCHAIN##*:v}; \
	case "$TARGETARCH" in \
		amd64) archive="lean-$version-linux"; sha256=47bf4bbd78f70c2e9670598ab7124d92b6efb7330ff33e5fbb4030f6fd72e4e4; \
			actionlint_sha256=8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8 ;; \
		arm64) archive="lean-$version-linux_aarch64"; sha256=fdb974c2cdb4627e090d5d4007b913e09d13c4868720fb5594e22808b3de9e37; \
			actionlint_sha256=325e971b6ba9bfa504672e29be93c24981eeb1c07576d730e9f7c8805afff0c6 ;; \
		*) echo "Lean ships no Linux archive for $TARGETARCH." >&2; exit 1 ;; \
	esac; \
	curl -fsSL --retry 3 -o /tmp/actionlint.tar.gz \
		"https://github.com/rhysd/actionlint/releases/download/v$ACTIONLINT_VERSION/actionlint_${ACTIONLINT_VERSION}_linux_$TARGETARCH.tar.gz"; \
	echo "$actionlint_sha256  /tmp/actionlint.tar.gz" | sha256sum -c; \
	mkdir /opt/actionlint; \
	tar -xzf /tmp/actionlint.tar.gz -C /opt/actionlint actionlint; \
	curl -fsSL --retry 3 -o /tmp/lean.tar.zst "https://github.com/leanprover/lean4/releases/download/v$version/$archive.tar.zst"; \
	echo "$sha256  /tmp/lean.tar.zst" | sha256sum -c; \
	toolchain="/opt/elan/toolchains/$(echo "$LEAN_TOOLCHAIN" | sed 's|/|--|; s|:|---|')"; \
	mkdir -p "$toolchain"; \
	tar --zstd --no-same-owner -xf /tmp/lean.tar.zst -C "$toolchain" --strip-components 1; \
	chmod -R a+rwX /opt/elan; \
	rm /tmp/php-parser.tar.gz /tmp/lean.tar.zst /tmp/actionlint.tar.gz

# The PEAR installer from the PEAR project's own repository at a fixed commit, which make install reads from PHP's
# pear/ folder in place of downloading it, and install-php-extensions and e-dant/watcher at the releases
# frankenphp.Dockerfile builds with, where FrankenPHP takes the latest: watcher at the one FrankenPHP v1.13.5's go.mod
# pins. Each is checked against its SHA-256.
ARG PEAR_INSTALLER_COMMIT=72aa5f771f56aaf826641b21159453f993e44450
ARG PEAR_INSTALLER_SHA256=a71349da29f1a6e3f0792695b167e582375a93c70c0e9d35e7c4675697c5f039
ARG INSTALL_PHP_EXTENSIONS_VERSION=2.13.2
ARG INSTALL_PHP_EXTENSIONS_SHA256=27fa8d80bd4e22feb9ddcadff1ca40c8f3e8ea942eb2dd8f446ed1afd2f2f0ab
ARG WATCHER_VERSION=0.14.5
ARG WATCHER_SHA256=190bbe5e19923083db2beb3aa68f4e99a563c8f685d8037b0536780b0116c4d3
RUN set -eux; \
	mkdir -p /runtime/watcher; \
	curl -fsSL --retry 3 -o /runtime/install-pear-nozlib.phar \
		"https://raw.githubusercontent.com/pear/pearweb_phars/$PEAR_INSTALLER_COMMIT/install-pear-nozlib.phar"; \
	echo "$PEAR_INSTALLER_SHA256  /runtime/install-pear-nozlib.phar" | sha256sum -c; \
	curl -fsSL --retry 3 -o /runtime/install-php-extensions \
		"https://github.com/mlocati/docker-php-extension-installer/releases/download/$INSTALL_PHP_EXTENSIONS_VERSION/install-php-extensions"; \
	echo "$INSTALL_PHP_EXTENSIONS_SHA256  /runtime/install-php-extensions" | sha256sum -c; \
	chmod 0755 /runtime/install-php-extensions; \
	curl -fsSL --retry 3 -o /tmp/watcher.tar.gz "https://github.com/e-dant/watcher/archive/refs/tags/$WATCHER_VERSION.tar.gz"; \
	echo "$WATCHER_SHA256  /tmp/watcher.tar.gz" | sha256sum -c; \
	tar -xzf /tmp/watcher.tar.gz -C /runtime/watcher --strip-components 1 --no-same-owner; \
	rm /tmp/watcher.tar.gz

# FrankenPHP and the PECL extensions the image builds, each fetched by the commit its release tag names, so a moved tag
# builds nothing else and an outage of pecl.php.net can't stop a release. FrankenPHP v1.13.5 takes golang.org/x/net
# v0.62.0, which fixes CVE-2026-78661 in v1.13.4's v0.61.0. igbinary 3.2.17RC1 is its first release that builds on PHP
# 8.5. A git source brings phpredis's liblzf submodule, which GitHub's tag archive leaves out. excimer, the -dev image's,
# at 1.2.6, its first release that runs on a thread-safe engine, as FrankenPHP's, and its last with the package.xml
# install-php-extensions builds a source folder from: 1.2.7 removed it for PIE and changed no code.
ADD https://github.com/php/frankenphp.git#a3663e9e2cc4c258018a28ee068e50cc12f3ed2d /runtime/frankenphp
ADD https://github.com/igbinary/igbinary.git#edda7101adf583df047d028a154abf3bf04ced61 /runtime/igbinary
ADD https://github.com/Imagick/imagick.git#70087bab33eab913e99ac77d64d04d1a2fd0b7b0 /runtime/imagick
ADD https://github.com/phpredis/phpredis.git#df4fab2de7fc327c54c94a13af2b9542e4fbd720 /runtime/phpredis
ADD https://github.com/wikimedia/mediawiki-php-excimer.git#cc87c1e297cc39398ca786c41a486a3c1f17c8c3 /runtime/excimer

FROM scratch

COPY --from=download /opt/ /opt/
COPY --from=download /runtime/ /runtime/
