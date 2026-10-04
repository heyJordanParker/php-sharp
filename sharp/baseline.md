# Upstream test baseline

`sharp/bin/test --upstream` runs `Zend/tests`, `ext/reflection`, `ext/tokenizer` and `ext/opcache` against the `sharp/bin/build` binary. This file records its result on the untouched `php-8.5.11` build. A change to PHP# must keep every count and every listed test below unchanged. A new failure, skip or expected failure is a regression until the Architect accepts it here.

The build is a debug, non-thread-safe CLI build with the configure flags in `sharp/bin/build`. It has no CGI binary and no JIT, so tests that need them skip.

## Results

| Platform | Command | Tests | Passed | Skipped | Expected fail | Failed |
| --- | --- | --- | --- | --- | --- | --- |
| macOS 27 arm64, Apple clang 21 | `sharp/bin/test --upstream` | 6827 | 6778 | 47 | 2 | 0 |
| Debian trixie aarch64 in Docker, gcc 14 | `sharp/bin/test --linux --upstream` | 6827 | 6779 | 46 | 2 | 0 |
| CI `ubuntu-latest`, Ubuntu 24.04 x86_64 | `sharp/bin/test --upstream` | 6827 | 6779 | 46 | 2 | 0 |
| CI `macos-latest`, macOS 26 arm64 | `sharp/bin/test --upstream` | 6827 | 6778 | 47 | 2 | 0 |

The Linux rows skip the same tests, and so do the macOS rows.

## Expected failures

macOS and Linux.

| Test | Reason |
| --- | --- |
| `Zend/tests/inheritance/interface_constructor_prototype_002.phpt` | X::__constructor()'s prototype is set to B::__construct() |
| `ext/opcache/tests/opt/verify_return_type.phpt` | Return types cannot be inferred through prototypes |

## Skipped tests

macOS and Linux unless the Platform column says otherwise.

| Test | Reason | Platform |
| --- | --- | --- |
| `Zend/tests/binary-32bit.phpt` | this test is for 32bit platform only | |
| `Zend/tests/bug46701.phpt` | this test is for 32bit platforms only | |
| `Zend/tests/bug62097.phpt` | for system with 32-bit wide longs only | |
| `Zend/tests/bug67436/bug67436.phpt` | Opcache overrides error handler | |
| `Zend/tests/bug70173.phpt` | this test is for 32bit platforms only | |
| `Zend/tests/bug71930.phpt` | Required extension missing: curl | |
| `Zend/tests/comparison/compare_001.phpt` | this test is for 32bit platform only | |
| `Zend/tests/comparison/compare_002.phpt` | this test is for 32bit platform only | |
| `Zend/tests/comparison/compare_003.phpt` | this test is for 32bit platform only | |
| `Zend/tests/comparison/compare_004.phpt` | this test is for 32bit platform only | |
| `Zend/tests/comparison/compare_005.phpt` | this test is for 32bit platform only | |
| `Zend/tests/comparison/compare_006.phpt` | this test is for 32bit platform only | |
| `Zend/tests/concat/concat_003.phpt` | debug version is slow | |
| `Zend/tests/double_to_string.phpt` | this test is for 32bit platform only | |
| `Zend/tests/exceptions/exception_011.phpt` | CGI not available | |
| `Zend/tests/gh11138.phpt` | CGI not available | |
| `Zend/tests/hex_overflow_32bit.phpt` | this test is for 32bit platform only | |
| `Zend/tests/in-de-crement/decrement_001.phpt` | this test is for 32bit platform only | |
| `Zend/tests/in-de-crement/increment_001.phpt` | this test is for 32bit platform only | |
| `Zend/tests/in-de-crement/increment_diagnostic_change_type_do_operator.phpt` | Required extension missing: gmp | |
| `Zend/tests/int_overflow_32bit.phpt` | this test is for 32bit platform only | |
| `Zend/tests/int_underflow_32bit.phpt` | this test is for 32bit platform only | |
| `Zend/tests/magic_methods/bug68412.phpt` | Need Zend MM enabled | |
| `Zend/tests/multibyte/multibyte_encoding_006.phpt` | The mbstring extension cannot be present for this test | |
| `Zend/tests/temporary_cleaning/temporary_cleaning_014.phpt` | Required extension missing: gmp | |
| `Zend/tests/type_coercion/float_to_int/dval_to_lval_32.phpt` | for machines with 32-bit longs | |
| `Zend/tests/type_coercion/float_to_int/explicit_casts_should_not_warn_32bit.phpt` | this test is for 32bit platform only | |
| `Zend/tests/type_coercion/float_to_int/warning_float_does_not_fit_zend_long_strings_32bit.phpt` | this test is for 32bit platform only | |
| `Zend/tests/type_declarations/scalar_return_basic.phpt` | this test is for 32bit platform only | |
| `Zend/tests/type_declarations/scalar_strict.phpt` | this test is for 32bit platform only | |
| `Zend/tests/zend_signed_multiply-32bit.phpt` | Running on 64-bit target | |
| `ext/opcache/tests/api/opcache_preloading_002.phpt` | Environment variable TEST_NON_ROOT_USER is not set | |
| `ext/opcache/tests/blacklist-win32.phpt` | only for Windows | |
| `ext/opcache/tests/bug78189.phpt` | this test is for Windows platforms only | |
| `ext/opcache/tests/file_cache_error.phpt` | Test requires setrlimit(RLIMIT_FSIZE) to work | macOS |
| `ext/opcache/tests/file_cache_error.phpt` | File cache is disabled when JIT is on | Linux |
| `ext/opcache/tests/gh10405.phpt` | Environment variable TEST_NON_ROOT_USER is not set | |
| `ext/opcache/tests/gh18417.phpt` | Opcache DLL not found in extension_dir (Windows-only) | |
| `ext/opcache/tests/gh8466.phpt` | dl_test extension is not built | |
| `ext/opcache/tests/issue0183.phpt` | only for linux | macOS |
| `ext/opcache/tests/jit/bug80426.phpt` | JIT is not available | |
| `ext/opcache/tests/jit/gh21710.phpt` | JIT is not available | |
| `ext/opcache/tests/jit/reg_alloc_003_32bits.phpt` | this test is for 32bit platform only | |
| `ext/opcache/tests/preload_user_002.phpt` | Environment variable TEST_NON_ROOT_USER is not set | |
| `ext/opcache/tests/preload_user_003.phpt` | Test needs root user | |
| `ext/opcache/tests/preload_user_004.phpt` | php-fpm binary not found | |
| `ext/opcache/tests/preload_user_005.phpt` | php-fpm binary not found | |
| `ext/opcache/tests/preload_windows.phpt` | Windows only test | |
