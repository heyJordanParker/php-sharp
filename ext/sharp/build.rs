use std::env;
use std::fs;
use std::path::PathBuf;

// php-sharp commits the pinned bridge's header as ext/sharp/sharp_unit.h. Every build writes it again,
// so sharp.c always compiles against the bridge it links, and CI's generated-files check fails on a difference.
fn main() {
    println!("cargo:rerun-if-changed=build.rs");

    let include = PathBuf::from(env::var("DEP_SHARP_BRIDGE_INCLUDE").expect("the bridge exports its header folder"));
    let target = PathBuf::from(env::var("CARGO_MANIFEST_DIR").expect("cargo sets CARGO_MANIFEST_DIR")).join("sharp_unit.h");
    let content = fs::read(include.join("sharp_unit.h")).expect("the bridge writes sharp_unit.h");
    if fs::read(&target).ok().as_ref() != Some(&content) {
        fs::write(&target, content).expect("ext/sharp is writable");
    }
}
