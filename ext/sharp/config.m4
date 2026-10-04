AC_PATH_PROG([RUSTUP], [rustup])
AS_VAR_IF([RUSTUP],, [AC_MSG_ERROR([ext/sharp builds with the Rust toolchain in ext/sharp/rust-toolchain.toml. Install rustup.])])
PHP_SUBST([RUSTUP])

PHP_NEW_EXTENSION([sharp], [sharp.c], [no],, [-Werror=switch])
PHP_ADD_MAKEFILE_FRAGMENT

EXTRA_LIBS="$EXTRA_LIBS $abs_builddir/ext/sharp/target/release/libsharp.a"
