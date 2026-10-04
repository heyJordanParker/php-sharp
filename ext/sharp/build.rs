use std::env;
use std::fs;
use std::path::PathBuf;

fn main() {
    println!("cargo:rerun-if-env-changed=SHARP_HEADER_DIR");

    let include = PathBuf::from(env::var("DEP_SHARP_BRIDGE_INCLUDE").expect("the bridge exports its header folder"));
    let destination = PathBuf::from(env::var("SHARP_HEADER_DIR").expect("Makefile.frag sets SHARP_HEADER_DIR"));

    for entry in fs::read_dir(&include).expect("the bridge header folder exists") {
        let header = entry.expect("the bridge header folder is readable").path();
        let target = destination.join(header.file_name().expect("a header has a file name"));
        let content = fs::read(&header).expect("the bridge header is readable");
        if fs::read(&target).ok().as_ref() != Some(&content) {
            fs::write(&target, content).expect("the build folder is writable");
        }
    }
}
