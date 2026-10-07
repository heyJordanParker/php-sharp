#![allow(non_camel_case_types, non_snake_case)]

mod text;

use std::ptr;
use std::slice;

#[repr(C)]
pub struct sharp_native_str {
    ptr: *const u8,
    len: usize,
}

impl sharp_native_str {
    unsafe fn bytes<'a>(&self) -> &'a [u8] {
        unsafe { slice::from_raw_parts(self.ptr, self.len) }
    }
}

impl From<String> for sharp_native_str {
    fn from(string: String) -> Self {
        let bytes = Box::into_raw(string.into_bytes().into_boxed_slice());
        Self {
            ptr: bytes.cast::<u8>(),
            len: bytes.len(),
        }
    }
}

#[unsafe(no_mangle)]
pub unsafe extern "C" fn sharp_native_str_free(string: sharp_native_str) {
    drop(unsafe {
        Box::from_raw(ptr::slice_from_raw_parts_mut(
            string.ptr.cast_mut(),
            string.len,
        ))
    });
}
