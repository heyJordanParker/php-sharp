$(builddir)/sharp.lo: $(builddir)/sharp_bridge.h $(builddir)/sharp_build_id.h $(builddir)/target/release/libsharp.a $(srcdir)/sharp_native.h

$(builddir)/sharp_bridge.h: $(builddir)/target/release/libsharp.a

$(builddir)/target/release/libsharp.a: sharp-always
	+cd $(srcdir) && cargo=`$(RUSTUP) which cargo` && PATH="`dirname "$$cargo"`:$$PATH" CC="$(CC)" CFLAGS="$(CFLAGS_CLEAN)" SHARP_HEADER_DIR=$(top_builddir)/$(builddir) "$$cargo" build --locked --release --target-dir $(top_builddir)/$(builddir)/target

$(builddir)/sharp_build_id.h: $(builddir)/target/release/libsharp.a sharp-always
	@id=`git -C $(srcdir) rev-parse HEAD`; \
	if test -z "$$id" || test -n "`git -C $(srcdir) status --porcelain --untracked-files=no`"; then id="$$id+`date -u +%Y%m%dT%H%M%SZ`"; fi; \
	id="$$id+`cksum < $(builddir)/target/release/libsharp.a | tr ' ' -`"; \
	echo "#define SHARP_BUILD_ID \"$$id\"" > $@.tmp; \
	if cmp -s $@.tmp $@; then rm $@.tmp; else mv $@.tmp $@; fi

.PHONY: sharp-always
