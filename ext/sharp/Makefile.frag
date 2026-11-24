$(builddir)/sharp.lo: $(srcdir)/sharp_unit.h $(srcdir)/sharp_native.h $(builddir)/sharp_build_id.h $(builddir)/sharp_version.h $(top_srcdir)/Zend/zend_language_parser.h

# The crates the library depends on build from vendor/, which `cargo vendor` writes, so the engine's build reaches no
# network. The source replacement is on the command line, since an untracked .cargo/config.toml holds a worktree's own
# [patch], as .gitignore says.
$(builddir)/target/release/libsharp.a: sharp-always
	+cd $(srcdir) && cargo=`$(RUSTUP) which cargo` && PATH="`dirname "$$cargo"`:$$PATH" CC="$(CC)" CFLAGS="$(CFLAGS_CLEAN)" "$$cargo" build --locked --release --config 'source.crates-io.replace-with="vendored-sources"' --config 'source.vendored-sources.directory="vendor"' --target-dir $(top_builddir)/$(builddir)/target

# The engine's build ID names the commit, the change to the engine's sources since it, and the Rust library it links, so
# a build of the same sources writes the same ID, and nothing that includes it compiles again. An edit outside the
# engine's sources, as under sharp/, docs/ or a tests folder, leaves it as it was. A tree with no commit to name takes
# the time instead, so two of its builds never share an ID.
SHARP_ENGINE_SOURCES = Zend main ext sapi TSRM build configure.ac ':(exclude,glob)**/tests/**'
$(builddir)/sharp_build_id.h: $(builddir)/target/release/libsharp.a sharp-always
	@id=`git -C $(top_srcdir) rev-parse HEAD`; \
	if test -z "$$id"; then id="+`date -u +%Y%m%dT%H%M%SZ`"; \
	elif ! git -C $(top_srcdir) diff --quiet HEAD -- $(SHARP_ENGINE_SOURCES); then \
		id="$$id+`git -C $(top_srcdir) diff HEAD -- $(SHARP_ENGINE_SOURCES) | cksum | tr ' ' -`"; \
	fi; \
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
