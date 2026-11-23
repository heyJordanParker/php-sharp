ARG BASE_IMAGE=public.ecr.aws/docker/library/debian:trixie

# The pinned tools a build would otherwise download, the one place each is pinned, so every image ships the tools its
# tests ran with: PHP-Parser at the version build/gen_stub.php pins, in /opt/PHP-Parser-<version>, and the Lean
# sharp/mago/crates/sharp-lean/lean/lean-toolchain pins, as elan's toolchain in /opt/elan, unpacked from the release
# archive whose SHA-256 sharp/mago/crates/sharp-lean/src/library.rs pins for the platform, as elan unpacks it. The
# image holds nothing else, so a build that copies them pulls only them. sharp/docker/Dockerfile copies /opt as its
# ELAN_HOME and its PHP-Parser, and sharp/docker/runtime/frankenphp.Dockerfile copies the Lean into Mago's cache folder
# and links the PHP-Parser where imagick's gen_stub.php reads it. The download runs on the build's own platform for
# every target platform, so one builder makes the image of both with no emulation. A pin that moves without this file
# fails the build that reads it: sharp/bin/check and frankenphp.Dockerfile for PHP-Parser, and sharp/bin/test and
# frankenphp.Dockerfile for Lean.
FROM --platform=$BUILDPLATFORM ${BASE_IMAGE} AS download

RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ca-certificates curl zstd \
 && rm -rf /var/lib/apt/lists/*

ARG TARGETARCH
ARG PHP_PARSER_VERSION=5.6.1
ARG PHP_PARSER_SHA256=5d49e1b6a01fb55232d40dbcd1c7ae5b1de25f7de328b003cc50a81dc530a309
ARG LEAN_TOOLCHAIN=leanprover/lean4:v4.34.1
RUN set -eux; \
	curl -fsSL --retry 3 -o /tmp/php-parser.tar.gz "https://github.com/nikic/PHP-Parser/archive/v$PHP_PARSER_VERSION.tar.gz"; \
	echo "$PHP_PARSER_SHA256  /tmp/php-parser.tar.gz" | sha256sum -c; \
	mkdir "/opt/PHP-Parser-$PHP_PARSER_VERSION"; \
	tar -xzf /tmp/php-parser.tar.gz -C "/opt/PHP-Parser-$PHP_PARSER_VERSION" --strip-components 1; \
	version=${LEAN_TOOLCHAIN##*:v}; \
	case "$TARGETARCH" in \
		amd64) archive="lean-$version-linux"; sha256=47bf4bbd78f70c2e9670598ab7124d92b6efb7330ff33e5fbb4030f6fd72e4e4 ;; \
		arm64) archive="lean-$version-linux_aarch64"; sha256=fdb974c2cdb4627e090d5d4007b913e09d13c4868720fb5594e22808b3de9e37 ;; \
		*) echo "Lean ships no Linux archive for $TARGETARCH." >&2; exit 1 ;; \
	esac; \
	curl -fsSL --retry 3 -o /tmp/lean.tar.zst "https://github.com/leanprover/lean4/releases/download/v$version/$archive.tar.zst"; \
	echo "$sha256  /tmp/lean.tar.zst" | sha256sum -c; \
	toolchain="/opt/elan/toolchains/$(echo "$LEAN_TOOLCHAIN" | sed 's|/|--|; s|:|---|')"; \
	mkdir -p "$toolchain"; \
	tar --zstd --no-same-owner -xf /tmp/lean.tar.zst -C "$toolchain" --strip-components 1; \
	chmod -R a+rwX /opt/elan; \
	rm /tmp/php-parser.tar.gz /tmp/lean.tar.zst

FROM scratch

COPY --from=download /opt/ /opt/
