use std::io;
use std::path::Path;

use mago_prelude::Prelude;

pub fn main() -> io::Result<()> {
    println!("cargo:rustc-env=TARGET={}", std::env::var("TARGET").unwrap());
    println!("cargo:rerun-if-changed=crates/prelude/assets");
    println!("cargo:rerun-if-changed=build.rs");

    // The release's version, which release.yml sets, or else the root package's 0.0.0, as rustc takes CFG_RELEASE.
    // Every crate keeps version 0.0.0, which cargo hashes into its symbols, so a release's symbols are its profile's
    // wherever their code is the same (#1164).
    let release = std::env::var("MAGO_RELEASE")
        .ok()
        .filter(|release| !release.is_empty())
        .unwrap_or_else(|| std::env::var("CARGO_PKG_VERSION").unwrap());
    let parts: Vec<&str> = release.split('.').collect();
    assert!(
        parts.len() == 3 && parts.iter().all(|part| !part.is_empty() && part.bytes().all(|byte| byte.is_ascii_digit())),
        "MAGO_RELEASE is {release}, where a version is <major>.<minor>.<patch>."
    );
    println!("cargo:rustc-env=MAGO_RELEASE={release}");
    println!("cargo:rustc-env=MAGO_RELEASE_MAJOR={}", parts[0]);
    println!("cargo:rustc-env=MAGO_RELEASE_MINOR={}", parts[1]);
    println!("cargo:rerun-if-env-changed=MAGO_RELEASE");

    let out_dir = std::env::var("OUT_DIR").expect("OUT_DIR environment variable not set");

    Prelude::write(&Path::new(&out_dir).join("prelude.bin"))
}
