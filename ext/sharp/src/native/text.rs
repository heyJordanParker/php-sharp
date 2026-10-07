use super::sharp_native_str;

#[unsafe(no_mangle)]
pub unsafe extern "C" fn sharp_native_Text_Text_slug(title: sharp_native_str) -> sharp_native_str {
    slug(unsafe { title.bytes() }).into()
}

fn slug(title: &[u8]) -> String {
    let mut slug = String::with_capacity(title.len());
    let mut separator = false;
    for chunk in title.utf8_chunks() {
        for character in chunk.valid().chars() {
            let mut ascii = [0];
            let bytes = if character.is_ascii() {
                character.encode_utf8(&mut ascii).as_bytes()
            } else {
                deunicode::deunicode_char(character)
                    .unwrap_or("-")
                    .as_bytes()
            };
            push_slug_bytes(&mut slug, bytes, &mut separator);
        }
        separator |= !chunk.invalid().is_empty();
    }
    slug
}

fn push_slug_bytes(slug: &mut String, bytes: &[u8], separator: &mut bool) {
    for &byte in bytes {
        if byte.is_ascii_alphanumeric() {
            if *separator && !slug.is_empty() {
                slug.push('-');
            }
            *separator = false;
            slug.push(byte.to_ascii_lowercase() as char);
        } else {
            *separator = true;
        }
    }
}
