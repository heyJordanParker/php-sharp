//! A content-addressed store of objects, as Git's object database keeps them: each object is a blob of bytes, or a tree
//! that names blobs and trees, stored once, under the hash of its kind and bytes. A tree whose few entries change is
//! written again as a new tree beside the objects it keeps, so a store writes only what changed. A ref file names a
//! root tree, and [`Objects::collect`] deletes each loose object no root reaches, as `git prune` does, and packs the
//! objects the roots reach into one file once the loose ones are many, as `git gc --auto` does, so a reader opens one
//! pack in place of a file for each object.

// A pack and its index are files of fixed byte order, as a compiled file is.
#![allow(clippy::big_endian_bytes, clippy::little_endian_bytes)]

use std::collections::BTreeMap;
use std::collections::HashSet;
use std::fmt;
use std::fs;
use std::io;
use std::io::Write as _;
use std::path::Path;
use std::path::PathBuf;
use std::sync::Arc;
use std::sync::Mutex;
use std::sync::PoisonError;
use std::sync::atomic::AtomicU64;
use std::sync::atomic::Ordering;

/// The start of the name of a file [`Objects`] writes before it renames it to its object's name.
const TEMPORARY_PREFIX: &str = "tmp_obj_";

/// The files this process wrote, so each write's temporary file has a name of its own.
static WRITES: AtomicU64 = AtomicU64::new(0);

/// The name of an object: the BLAKE3 hash of its kind, its length and its bytes.
#[derive(Clone, Copy, PartialEq, Eq, Hash, PartialOrd, Ord)]
pub struct ObjectId([u8; 32]);

impl ObjectId {
    /// The ID that `hex` spells in 64 lowercase hexadecimal digits, or none.
    #[must_use]
    pub fn from_hex(hex: &str) -> Option<Self> {
        let digits = hex.as_bytes();
        if digits.len() != 64 {
            return None;
        }
        let mut id = [0; 32];
        for (byte, [high, low]) in id.iter_mut().zip(digits.as_chunks::<2>().0) {
            *byte = (digit(*high)? << 4) | digit(*low)?;
        }

        Some(Self(id))
    }
}

/// The value of `byte`, a lowercase hexadecimal digit, or none.
fn digit(byte: u8) -> Option<u8> {
    match byte {
        b'0'..=b'9' => Some(byte - b'0'),
        b'a'..=b'f' => Some(byte - b'a' + 10),
        _ => None,
    }
}

impl fmt::Display for ObjectId {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        self.0.iter().try_for_each(|byte| write!(f, "{byte:02x}"))
    }
}

impl fmt::Debug for ObjectId {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        write!(f, "ObjectId({self})")
    }
}

/// The kind of an object.
#[derive(Clone, Copy, Debug, PartialEq, Eq)]
pub enum Kind {
    /// Bytes.
    Blob,
    /// A [`Tree`].
    Tree,
}

impl Kind {
    /// The word an object's header names its kind by, as Git's does.
    fn word(self) -> &'static [u8] {
        match self {
            Kind::Blob => b"blob",
            Kind::Tree => b"tree",
        }
    }

    /// The mode a tree's entry names an object of this kind by, as Git's trees do.
    fn mode(self) -> &'static [u8] {
        match self {
            Kind::Blob => b"100644",
            Kind::Tree => b"40000",
        }
    }
}

/// A tree: the kind and ID of the object each name names, in name order.
#[derive(Clone, Debug, Default, PartialEq, Eq)]
pub struct Tree(BTreeMap<Vec<u8>, (Kind, ObjectId)>);

impl Tree {
    /// A tree that names nothing.
    #[must_use]
    pub fn new() -> Self {
        Self::default()
    }

    /// Names the object `id`, of `kind`, `name`, which holds no `/` and no NUL byte, in place of what it named.
    pub fn insert(&mut self, name: impl Into<Vec<u8>>, kind: Kind, id: ObjectId) {
        self.0.insert(name.into(), (kind, id));
    }

    /// The kind and ID of the object `name` names.
    #[must_use]
    pub fn get(&self, name: &[u8]) -> Option<(Kind, ObjectId)> {
        self.0.get(name).copied()
    }

    /// Each name, with the kind and ID of the object it names, in name order.
    pub fn iter(&self) -> impl Iterator<Item = (&[u8], Kind, ObjectId)> {
        self.0.iter().map(|(name, (kind, id))| (name.as_slice(), *kind, *id))
    }

    /// How many names the tree holds.
    #[must_use]
    pub fn len(&self) -> usize {
        self.0.len()
    }

    /// Whether the tree names nothing.
    #[must_use]
    pub fn is_empty(&self) -> bool {
        self.0.is_empty()
    }

    /// The tree's bytes, as Git encodes a tree: each entry's mode, a space, its name, a NUL byte and its object's ID.
    fn encode(&self) -> Vec<u8> {
        let mut bytes = Vec::with_capacity(self.0.len() * 48);
        for (name, (kind, id)) in &self.0 {
            bytes.extend_from_slice(kind.mode());
            bytes.push(b' ');
            bytes.extend_from_slice(name);
            bytes.push(0);
            bytes.extend_from_slice(&id.0);
        }

        bytes
    }

    /// The tree `bytes` encode, as [`Self::encode`] encodes it.
    ///
    /// # Errors
    ///
    /// Returns [`io::ErrorKind::InvalidData`] when `bytes` encode no tree.
    fn decode(mut bytes: &[u8]) -> io::Result<Self> {
        let invalid = || io::Error::new(io::ErrorKind::InvalidData, "an object of the store is no tree");
        let mut tree = Self::new();
        while !bytes.is_empty() {
            let space = bytes.iter().position(|byte| *byte == b' ').ok_or_else(invalid)?;
            let kind = match &bytes[..space] {
                mode if mode == Kind::Blob.mode() => Kind::Blob,
                mode if mode == Kind::Tree.mode() => Kind::Tree,
                _ => return Err(invalid()),
            };
            bytes = &bytes[space + 1..];
            let end = bytes.iter().position(|byte| *byte == 0).ok_or_else(invalid)?;
            let name = bytes[..end].to_vec();
            let id = bytes.get(end + 1..end + 33).ok_or_else(invalid)?;
            tree.insert(name, kind, ObjectId(id.try_into().map_err(|_| invalid())?));
            bytes = &bytes[end + 33..];
        }

        Ok(tree)
    }
}

/// How many loose objects a store holds before [`Objects::collect`] packs them, as Git's `gc.auto` sets it.
const GC_AUTO: usize = 6700;

/// The folder of a store that holds its packs, as `.git/objects/pack` does.
const PACK_FOLDER: &str = "pack";

/// The bytes that start a pack, as they start Git's.
const PACK_SIGNATURE: &[u8; 4] = b"PACK";

/// The bytes that start a pack's index, as they start Git's version 2 index.
const INDEX_SIGNATURE: &[u8; 4] = b"\xfftOc";

/// The version of a pack and of its index, as Git's.
const PACK_VERSION: u32 = 2;

/// A pack of a store, as Git's packfile holds a store's objects in one file, with an index of where each one starts.
///
/// It is Git's format without its compression and its deltas, with 32-byte BLAKE3 IDs, 64-bit offsets, and its numbers
/// little-endian, as the compiled files' headers keep theirs.
#[derive(Debug)]
struct Pack {
    file: fs::File,
    /// The ID of each object the pack holds, in ID order, with the offset of its entry in the pack.
    entries: Vec<(ObjectId, u64)>,
    /// The pack's file and its index's file.
    paths: [PathBuf; 2],
}

impl Pack {
    /// The pack whose index is the file at `index`, beside the pack of the same name.
    fn open(index: &Path) -> io::Result<Self> {
        let invalid = || io::Error::new(io::ErrorKind::InvalidData, format!("{} is no index", index.display()));
        let bytes = fs::read(index)?;
        let version = PACK_VERSION.to_le_bytes();
        if bytes.get(..4) != Some(INDEX_SIGNATURE.as_slice()) || bytes.get(4..8) != Some(version.as_slice()) {
            return Err(invalid());
        }
        let count = usize::try_from(read_u32(&bytes, 8 + 255 * 4).ok_or_else(invalid)?).map_err(|_| invalid())?;
        let ids = 8 + 256 * 4;
        let offsets = ids + count * 32;
        let mut entries = Vec::with_capacity(count);
        for entry in 0..count {
            let id = bytes.get(ids + entry * 32..ids + entry * 32 + 32).ok_or_else(invalid)?;
            let offset = bytes.get(offsets + entry * 8..offsets + entry * 8 + 8).ok_or_else(invalid)?;
            entries.push((
                ObjectId(id.try_into().map_err(|_| invalid())?),
                u64::from_le_bytes(offset.try_into().map_err(|_| invalid())?),
            ));
        }
        let pack = index.with_extension("pack");

        Ok(Self { file: fs::File::open(&pack)?, entries, paths: [pack, index.to_path_buf()] })
    }

    /// The kind and content of the object `id`, when the pack holds it.
    fn read(&self, id: ObjectId) -> io::Result<Option<(Kind, Vec<u8>)>> {
        let Ok(found) = self.entries.binary_search_by_key(&id, |(entry, _)| *entry) else {
            return Ok(None);
        };
        let offset = self.entries[found].1;
        // Every entry is followed by at least the pack's 32-byte trailer, so its whole header is in the next 16 bytes.
        let mut header = [0; 16];
        read_at(&self.file, &mut header, offset)?;
        let invalid = || io::Error::new(io::ErrorKind::InvalidData, format!("object {id} of a pack is damaged"));
        let (kind, size, header_length) = parse_entry_header(&header).ok_or_else(invalid)?;
        let mut content = vec![0; usize::try_from(size).map_err(|_| invalid())?];
        read_at(&self.file, &mut content, offset + to_u64(header_length))?;

        Ok(Some((kind, content)))
    }
}

/// Fills `buffer` from `file` at `offset`, leaving the file's position as it was, so the readers of a pack share it.
fn read_at(file: &fs::File, buffer: &mut [u8], offset: u64) -> io::Result<()> {
    #[cfg(unix)]
    {
        std::os::unix::fs::FileExt::read_exact_at(file, buffer, offset)
    }
    #[cfg(windows)]
    {
        let mut read = 0;
        while read < buffer.len() {
            match std::os::windows::fs::FileExt::seek_read(file, &mut buffer[read..], offset + to_u64(read))? {
                0 => return Err(io::ErrorKind::UnexpectedEof.into()),
                count => read += count,
            }
        }
        Ok(())
    }
}

/// `count` as a `u64`, which holds every length a store writes.
fn to_u64(count: usize) -> u64 {
    u64::try_from(count).unwrap_or(u64::MAX)
}

/// The little-endian `u32` of `bytes` at `at`.
fn read_u32(bytes: &[u8], at: usize) -> Option<u32> {
    Some(u32::from_le_bytes(bytes.get(at..at + 4)?.try_into().ok()?))
}

/// The header of a pack's entry, as Git writes it: the kind's number, 2 for a tree and 3 for a blob, and the content's
/// size in groups of 7 bits, the first group of 4, each byte but the last with its high bit set.
fn entry_header(kind: Kind, size: usize) -> Vec<u8> {
    let number: u8 = match kind {
        Kind::Tree => 2,
        Kind::Blob => 3,
    };
    let mut size = to_u64(size);
    let mut byte = (number << 4) | u8::try_from(size & 0x0f).unwrap_or_default();
    size >>= 4;
    let mut header = Vec::with_capacity(10);
    while size > 0 {
        header.push(byte | 0x80);
        byte = u8::try_from(size & 0x7f).unwrap_or_default();
        size >>= 7;
    }
    header.push(byte);

    header
}

/// The kind, the content's size and the header's length of the entry whose header `bytes` start with.
fn parse_entry_header(bytes: &[u8]) -> Option<(Kind, u64, usize)> {
    let first = *bytes.first()?;
    let kind = match (first >> 4) & 0x07 {
        2 => Kind::Tree,
        3 => Kind::Blob,
        _ => return None,
    };
    let mut size = u64::from(first & 0x0f);
    let mut shift = 4;
    let mut length = 1;
    let mut byte = first;
    while byte & 0x80 != 0 {
        byte = *bytes.get(length)?;
        size |= u64::from(byte & 0x7f) << shift;
        shift += 7;
        length += 1;
    }

    Some((kind, size, length))
}

/// A store of objects in a folder, as `.git/objects` holds them.
///
/// Each loose object is in a file of the fan-out folder its ID's first two digits name, named by the rest of its ID, and
/// the others are in the packs of its [`PACK_FOLDER`].
#[derive(Debug)]
pub struct Objects {
    folder: PathBuf,
    /// The store's packs, read on the first read that needs them.
    packs: Mutex<Option<Arc<[Pack]>>>,
}

impl Objects {
    /// The store in `folder`, which a first write makes.
    #[must_use]
    pub fn at(folder: impl Into<PathBuf>) -> Self {
        Self { folder: folder.into(), packs: Mutex::default() }
    }

    /// The store's folder.
    #[must_use]
    pub fn folder(&self) -> &Path {
        &self.folder
    }

    /// Writes `bytes` as a blob, unless the store holds that blob already, and gives its ID.
    ///
    /// # Errors
    ///
    /// Returns the error of writing the object's file.
    pub fn write_blob(&self, bytes: &[u8]) -> io::Result<ObjectId> {
        self.write(Kind::Blob, bytes)
    }

    /// Writes `tree`, unless the store holds that tree already, and gives its ID.
    ///
    /// # Errors
    ///
    /// Returns the error of writing the object's file.
    pub fn write_tree(&self, tree: &Tree) -> io::Result<ObjectId> {
        self.write(Kind::Tree, &tree.encode())
    }

    /// The bytes of the blob `id`.
    ///
    /// # Errors
    ///
    /// Returns the error of reading the object, and [`io::ErrorKind::InvalidData`] when it is no blob.
    pub fn read_blob(&self, id: ObjectId) -> io::Result<Vec<u8>> {
        self.read(Kind::Blob, id)
    }

    /// The tree `id`.
    ///
    /// # Errors
    ///
    /// Returns the error of reading the object, and [`io::ErrorKind::InvalidData`] when it is no tree.
    pub fn read_tree(&self, id: ObjectId) -> io::Result<Tree> {
        Tree::decode(&self.read(Kind::Tree, id)?)
    }

    /// The store's packs, as their indexes name them.
    fn packs(&self) -> io::Result<Arc<[Pack]>> {
        let mut packs = self.packs.lock().unwrap_or_else(PoisonError::into_inner);
        if let Some(packs) = packs.as_ref() {
            return Ok(Arc::clone(packs));
        }
        let mut loaded = Vec::new();
        match fs::read_dir(self.folder.join(PACK_FOLDER)) {
            Ok(entries) => {
                for entry in entries {
                    let path = entry?.path();
                    if path.extension().is_some_and(|extension| extension == "idx") {
                        loaded.push(Pack::open(&path)?);
                    }
                }
            }
            Err(error) if error.kind() == io::ErrorKind::NotFound => {}
            Err(error) => return Err(error),
        }
        let loaded: Arc<[Pack]> = loaded.into();
        *packs = Some(Arc::clone(&loaded));

        Ok(loaded)
    }

    /// Writes `content` as an object of `kind`, unless the store holds it, and gives its ID: its header, the kind's
    /// word, a space, the content's length and a NUL byte, then the content, written into a file of its own that is then
    /// renamed to the object's name, so a reader finds the whole object or none.
    fn write(&self, kind: Kind, content: &[u8]) -> io::Result<ObjectId> {
        let header = header(kind, content.len());
        let mut hasher = blake3::Hasher::new();
        hasher.update(&header);
        hasher.update(content);
        let id = ObjectId(*hasher.finalize().as_bytes());
        let path = self.path(id);
        if self.packs()?.iter().any(|pack| pack.entries.binary_search_by_key(&id, |(entry, _)| *entry).is_ok())
            || path.exists()
        {
            return Ok(id);
        }
        fs::create_dir_all(path.parent().unwrap_or(&self.folder))?;
        let temporary = self.temporary();
        let mut file = fs::File::create(&temporary)?;
        file.write_all(&header)?;
        file.write_all(content)?;
        drop(file);
        fs::rename(&temporary, &path)?;

        Ok(id)
    }

    /// A new file's name in the store's folder, for a write to rename once it is whole.
    fn temporary(&self) -> PathBuf {
        self.folder.join(format!("{TEMPORARY_PREFIX}{}_{}", std::process::id(), WRITES.fetch_add(1, Ordering::Relaxed)))
    }

    /// The content of the object `id`, of `kind`, from a pack that holds it, as Git looks in its packs first, or else
    /// from its loose file.
    fn read(&self, kind: Kind, id: ObjectId) -> io::Result<Vec<u8>> {
        let no_kind = || io::Error::new(io::ErrorKind::InvalidData, format!("object {id} of the store is no {kind:?}"));
        for pack in self.packs()?.iter() {
            if let Some((found, content)) = pack.read(id)? {
                return if found == kind { Ok(content) } else { Err(no_kind()) };
            }
        }
        let mut bytes = fs::read(self.path(id))?;
        let end = bytes.iter().position(|byte| *byte == 0);
        match end {
            Some(end) if bytes[..end] == header(kind, bytes.len() - end - 1)[..end] => {
                bytes.drain(..=end);

                Ok(bytes)
            }
            _ => Err(no_kind()),
        }
    }

    /// The kind and content of the object `id`, a blob or a tree.
    fn read_any(&self, id: ObjectId) -> io::Result<(Kind, Vec<u8>)> {
        match self.read(Kind::Blob, id) {
            Ok(content) => Ok((Kind::Blob, content)),
            Err(error) if error.kind() == io::ErrorKind::InvalidData => Ok((Kind::Tree, self.read(Kind::Tree, id)?)),
            Err(error) => Err(error),
        }
    }

    /// The file of the object `id`.
    fn path(&self, id: ObjectId) -> PathBuf {
        let hex = id.to_string();

        self.folder.join(&hex[..2]).join(&hex[2..])
    }

    /// Deletes each loose object no tree of `roots` reaches, and each file a write left behind, as `git prune` does,
    /// and once more loose objects than Git's `gc.auto` remain, packs every object the roots reach into one pack and
    /// deletes the loose objects and the other packs, as `git gc --auto` repacks, while nothing else writes to the
    /// store. Gives how many files it deleted.
    ///
    /// # Errors
    ///
    /// Returns the error of reading an object, or of listing, writing or deleting a file.
    pub fn collect(&self, roots: &[ObjectId]) -> io::Result<usize> {
        let reached = self.reached(roots)?;
        let (mut deleted, loose) = self.sweep(&reached)?;
        if loose > GC_AUTO {
            deleted += self.repack(&reached)?;
        }

        Ok(deleted)
    }

    /// Each object the trees `roots` reach, themselves included.
    fn reached(&self, roots: &[ObjectId]) -> io::Result<HashSet<ObjectId>> {
        let mut reached: HashSet<ObjectId> = roots.iter().copied().collect();
        let mut trees = roots.to_vec();
        while let Some(tree) = trees.pop() {
            for (_, kind, id) in self.read_tree(tree)?.iter() {
                if reached.insert(id) && kind == Kind::Tree {
                    trees.push(id);
                }
            }
        }

        Ok(reached)
    }

    /// Deletes each loose object not in `reached`, each fan-out folder it empties, and each temporary file. Gives how
    /// many files it deleted, and how many loose objects remain.
    fn sweep(&self, reached: &HashSet<ObjectId>) -> io::Result<(usize, usize)> {
        let entries = match fs::read_dir(&self.folder) {
            Ok(entries) => entries,
            Err(error) if error.kind() == io::ErrorKind::NotFound => return Ok((0, 0)),
            Err(error) => return Err(error),
        };
        let (mut deleted, mut loose) = (0, 0);
        for entry in entries {
            let entry = entry?;
            let name = entry.file_name().to_string_lossy().into_owned();
            if name.starts_with(TEMPORARY_PREFIX) {
                fs::remove_file(entry.path())?;
                deleted += 1;
                continue;
            }
            if name.len() != 2 || !entry.file_type()?.is_dir() {
                continue;
            }
            let mut kept = false;
            for object in fs::read_dir(entry.path())? {
                let object = object?;
                let id = ObjectId::from_hex(&format!("{name}{}", object.file_name().to_string_lossy()));
                if id.is_some_and(|id| reached.contains(&id)) {
                    kept = true;
                    loose += 1;
                } else {
                    fs::remove_file(object.path())?;
                    deleted += 1;
                }
            }
            if !kept {
                fs::remove_dir(entry.path())?;
            }
        }

        Ok((deleted, loose))
    }

    /// Writes every object of `reached` into one new pack, then deletes the store's loose objects and its other packs.
    /// Gives how many files it deleted.
    fn repack(&self, reached: &HashSet<ObjectId>) -> io::Result<usize> {
        let mut ids: Vec<ObjectId> = reached.iter().copied().collect();
        ids.sort_unstable();
        let mut pack = Vec::new();
        pack.extend_from_slice(PACK_SIGNATURE);
        pack.extend_from_slice(&PACK_VERSION.to_le_bytes());
        pack.extend_from_slice(&u32::try_from(ids.len()).unwrap_or(u32::MAX).to_le_bytes());
        let mut offsets = Vec::with_capacity(ids.len());
        for id in &ids {
            let (kind, content) = self.read_any(*id)?;
            offsets.push(to_u64(pack.len()));
            pack.extend(entry_header(kind, content.len()));
            pack.extend_from_slice(&content);
        }
        let trailer = *blake3::hash(&pack).as_bytes();
        pack.extend_from_slice(&trailer);
        let mut index = Vec::with_capacity(8 + 256 * 4 + ids.len() * 40 + 64);
        index.extend_from_slice(INDEX_SIGNATURE);
        index.extend_from_slice(&PACK_VERSION.to_le_bytes());
        for first in 0..=255u8 {
            let below = ids.partition_point(|id| id.0[0] <= first);
            index.extend_from_slice(&u32::try_from(below).unwrap_or(u32::MAX).to_le_bytes());
        }
        for id in &ids {
            index.extend_from_slice(&id.0);
        }
        for offset in &offsets {
            index.extend_from_slice(&offset.to_le_bytes());
        }
        index.extend_from_slice(&trailer);
        let checksum = *blake3::hash(&index).as_bytes();
        index.extend_from_slice(&checksum);

        let folder = self.folder.join(PACK_FOLDER);
        fs::create_dir_all(&folder)?;
        let name = format!("pack-{}", ObjectId(trailer));
        let (pack_path, index_path) = (folder.join(format!("{name}.pack")), folder.join(format!("{name}.idx")));
        for (path, bytes) in [(&pack_path, &pack), (&index_path, &index)] {
            let temporary = self.temporary();
            fs::write(&temporary, bytes)?;
            fs::rename(&temporary, path)?;
        }
        let old = self.packs()?;
        *self.packs.lock().unwrap_or_else(PoisonError::into_inner) = None;
        let mut deleted = self.sweep(&HashSet::default())?.0;
        for pack in old.iter() {
            for path in &pack.paths {
                if *path != pack_path && *path != index_path {
                    fs::remove_file(path)?;
                    deleted += 1;
                }
            }
        }

        Ok(deleted)
    }
}

/// An object's header: the word of its `kind`, a space, the `length` of its content and a NUL byte.
fn header(kind: Kind, length: usize) -> Vec<u8> {
    let mut header = kind.word().to_vec();
    header.push(b' ');
    header.extend_from_slice(length.to_string().as_bytes());
    header.push(0);

    header
}

/// The object the ref file at `path` names, as a file of `.git/refs` names a commit, or none when the file is missing
/// or names none.
#[must_use]
pub fn read_ref(path: &Path) -> Option<ObjectId> {
    ObjectId::from_hex(fs::read_to_string(path).ok()?.trim_end())
}

/// Points the ref file at `path` at `id`, by renaming a file written beside it over it, so a reader finds the ID it named
/// or `id`.
///
/// # Errors
///
/// Returns the error of writing or renaming the file.
pub fn write_ref(path: &Path, id: ObjectId) -> io::Result<()> {
    let mut temporary = path.as_os_str().to_owned();
    temporary.push(".lock");
    fs::write(&temporary, format!("{id}\n"))?;

    fs::rename(&temporary, path)
}

#[cfg(test)]
#[allow(clippy::unwrap_used)]
mod tests {
    use super::*;

    #[test]
    fn a_blob_is_stored_once_under_its_hash_and_reads_back() {
        let folder = tempfile::tempdir().unwrap();
        let objects = Objects::at(folder.path().join("objects"));

        let first = objects.write_blob(b"bytes").unwrap();
        let second = objects.write_blob(b"bytes").unwrap();

        assert_eq!(first, second);
        assert_ne!(first, objects.write_blob(b"other bytes").unwrap());
        assert_eq!(objects.read_blob(first).unwrap(), b"bytes");
        objects.read_tree(first).unwrap_err();
    }

    #[test]
    fn a_tree_names_its_objects_in_name_order_and_reads_back() {
        let folder = tempfile::tempdir().unwrap();
        let objects = Objects::at(folder.path().join("objects"));
        let blob = objects.write_blob(b"bytes").unwrap();
        let mut inner = Tree::new();
        inner.insert("leaf", Kind::Blob, blob);
        let inner_id = objects.write_tree(&inner).unwrap();
        let mut tree = Tree::new();
        tree.insert("b", Kind::Blob, blob);
        tree.insert("a", Kind::Tree, inner_id);

        let id = objects.write_tree(&tree).unwrap();

        let read = objects.read_tree(id).unwrap();
        assert_eq!(read, tree);
        assert_eq!(read.iter().map(|(name, ..)| name.to_vec()).collect::<Vec<_>>(), [b"a".to_vec(), b"b".to_vec()]);
        assert_eq!(read.get(b"a"), Some((Kind::Tree, inner_id)));
    }

    #[test]
    fn a_collection_deletes_each_object_no_root_reaches() {
        let folder = tempfile::tempdir().unwrap();
        let objects = Objects::at(folder.path().join("objects"));
        let kept = objects.write_blob(b"kept").unwrap();
        let nested = objects.write_blob(b"nested").unwrap();
        let gone = objects.write_blob(b"gone").unwrap();
        let mut inner = Tree::new();
        inner.insert("nested", Kind::Blob, nested);
        let inner_id = objects.write_tree(&inner).unwrap();
        let mut root = Tree::new();
        root.insert("kept", Kind::Blob, kept);
        root.insert("inner", Kind::Tree, inner_id);
        let root_id = objects.write_tree(&root).unwrap();
        fs::write(objects.folder().join(format!("{TEMPORARY_PREFIX}1_1")), b"left").unwrap();

        let deleted = objects.collect(&[root_id]).unwrap();

        assert_eq!(deleted, 2);
        objects.read_blob(gone).unwrap_err();
        for id in [kept, nested] {
            objects.read_blob(id).unwrap();
        }
        for id in [inner_id, root_id] {
            objects.read_tree(id).unwrap();
        }
    }

    #[test]
    fn a_collection_packs_the_reached_objects_once_the_loose_ones_outnumber_git_s_gc_auto() {
        let folder = tempfile::tempdir().unwrap();
        let objects = Objects::at(folder.path().join("objects"));
        let mut tree = Tree::new();
        for number in 0..=GC_AUTO {
            tree.insert(number.to_string(), Kind::Blob, objects.write_blob(number.to_string().as_bytes()).unwrap());
        }
        let root = objects.write_tree(&tree).unwrap();
        let gone = objects.write_blob(b"gone").unwrap();

        objects.collect(&[root]).unwrap();

        let files: Vec<PathBuf> = fs::read_dir(objects.folder()).unwrap().map(|entry| entry.unwrap().path()).collect();
        assert_eq!(files, [objects.folder().join(PACK_FOLDER)]);
        assert_eq!(fs::read_dir(objects.folder().join(PACK_FOLDER)).unwrap().count(), 2);
        let reopened = Objects::at(objects.folder());
        assert_eq!(reopened.read_tree(root).unwrap(), tree);
        for (name, _, id) in tree.iter() {
            assert_eq!(reopened.read_blob(id).unwrap(), name);
        }
        reopened.read_blob(gone).unwrap_err();
        assert_eq!(reopened.write_blob(b"7").unwrap(), tree.get(b"7").unwrap().1);
        assert_eq!(fs::read_dir(objects.folder()).unwrap().count(), 1);
    }

    #[test]
    fn a_ref_names_the_object_it_was_last_pointed_at() {
        let folder = tempfile::tempdir().unwrap();
        let objects = Objects::at(folder.path().join("objects"));
        let path = folder.path().join("analysis");
        let first = objects.write_blob(b"first").unwrap();
        let second = objects.write_blob(b"second").unwrap();

        assert_eq!(read_ref(&path), None);
        write_ref(&path, first).unwrap();
        write_ref(&path, second).unwrap();

        assert_eq!(read_ref(&path), Some(second));
        assert_eq!(ObjectId::from_hex(&second.to_string()), Some(second));
    }
}
