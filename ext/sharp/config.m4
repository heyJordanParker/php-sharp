AC_PATH_PROG([RUSTUP], [rustup])
AS_VAR_IF([RUSTUP],, [AC_MSG_ERROR([ext/sharp builds with the Rust toolchain in ext/sharp/rust-toolchain.toml. Install rustup.])])
PHP_SUBST([RUSTUP])

PHP_NEW_EXTENSION([sharp], [sharp.c sharp_table.c], [no],, [-Werror=switch])
PHP_ADD_MAKEFILE_FRAGMENT

dnl The Rust library links into libphp and each SAPI binary after ext/sharp's objects, as DTrace's objects do in
dnl build/php.m4. EXTRA_LIBS would put the build folder's path in php-config --libs, so a program that embeds libphp,
dnl as FrankenPHP does, would link it again and fail where the build folder is gone.
PHP_GLOBAL_OBJS="$PHP_GLOBAL_OBJS ext/sharp/target/release/libsharp.a"
