$(builddir)/sharp.lo: $(srcdir)/sharp_unit.h $(srcdir)/sharp_native.h $(builddir)/sharp_build_id.h $(builddir)/sharp_version.h $(top_srcdir)/Zend/zend_language_parser.h

$(builddir)/target/release/libsharp.a: sharp-always
	+cd $(srcdir) && cargo=`$(RUSTUP) which cargo` && PATH="`dirname "$$cargo"`:$$PATH" CC="$(CC)" CFLAGS="$(CFLAGS_CLEAN)" "$$cargo" build --locked --release --target-dir $(top_builddir)/$(builddir)/target

$(builddir)/sharp_build_id.h: $(builddir)/target/release/libsharp.a sharp-always
	@id=`git -C $(srcdir) rev-parse HEAD`; \
	if test -z "$$id" || test -n "`git -C $(srcdir) status --porcelain --untracked-files=no`"; then id="$$id+`date -u +%Y%m%dT%H%M%SZ`"; fi; \
	id="$$id+`cksum < $(builddir)/target/release/libsharp.a | tr ' ' -`"; \
	echo "#define SHARP_BUILD_ID \"$$id\"" > $@.tmp; \
	if cmp -s $@.tmp $@; then rm $@.tmp; else mv $@.tmp $@; fi

# PHP# and Mago release at one version, the root package's in sharp/mago/Cargo.toml.
$(builddir)/sharp_version.h: $(top_srcdir)/sharp/mago/Cargo.toml
	@version=`sed -n '/^version = /{s/^version = "\(.*\)"$$/\1/p;q;}' $(top_srcdir)/sharp/mago/Cargo.toml`; \
	if test -z "$$version"; then echo "$(top_srcdir)/sharp/mago/Cargo.toml names no version." >&2; exit 1; fi; \
	echo "#define PHP_SHARP_VERSION \"$$version\"" > $@.tmp; \
	if cmp -s $@.tmp $@; then rm $@.tmp; else mv $@.tmp $@; fi

.PHONY: sharp-always
