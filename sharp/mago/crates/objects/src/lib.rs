//! A content-addressed store of objects, as Git's object database keeps them: each object is a blob of bytes, or a tree
//! that names blobs and trees, stored once, under the hash of its kind and bytes. A tree whose few entries change is
//! written again as a new tree beside the objects it keeps, so a store writes only what changed. A ref file names a
//! root tree, and [`Objects::collect`] deletes each object no root reaches, as `git gc` prunes.

use std::collections::BTreeMap;
use std::collections::HashSet;
use std::fmt;
use std::fs;
use std::io;
use std::io::Write as _;
use std::path::Path;
use std::path::PathBuf;
use std::sync::atomic::AtomicU64;
use std::sync::atomic::Ordering;

/// How many objects a store holds before [`Objects::collect_auto`] collects it, as Git's `gc.auto` sets it.
const GC_AUTO: usize = 6700;

/// The fan-out folder [`Objects::collect_auto`] counts to estimate how many objects a store holds, as `git gc --auto`
/// counts `17`.
const SAMPLED_FOLDER: &str = "17";

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

/// A store of objects in a folder, as `.git/objects` holds them: each object in a file of the fan-out folder its ID's
/// first two digits name, named by the rest of its ID.
#[derive(Clone, Debug)]
pub struct Objects {
    folder: PathBuf,
}

impl Objects {
    /// The store in `folder`, which a first write makes.
    #[must_use]
    pub fn at(folder: impl Into<PathBuf>) -> Self {
        Self { folder: folder.into() }
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
    /// Returns the error of reading the object's file, and [`io::ErrorKind::InvalidData`] when it holds no blob.
    pub fn read_blob(&self, id: ObjectId) -> io::Result<Vec<u8>> {
        self.read(Kind::Blob, id)
    }

    /// The tree `id`.
    ///
    /// # Errors
    ///
    /// Returns the error of reading the object's file, and [`io::ErrorKind::InvalidData`] when it holds no tree.
    pub fn read_tree(&self, id: ObjectId) -> io::Result<Tree> {
        Tree::decode(&self.read(Kind::Tree, id)?)
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
        if path.exists() {
            return Ok(id);
        }
        fs::create_dir_all(path.parent().unwrap_or(&self.folder))?;
        let temporary = self.folder.join(format!(
            "{TEMPORARY_PREFIX}{}_{}",
            std::process::id(),
            WRITES.fetch_add(1, Ordering::Relaxed)
        ));
        let mut file = fs::File::create(&temporary)?;
        file.write_all(&header)?;
        file.write_all(content)?;
        drop(file);
        fs::rename(&temporary, &path)?;

        Ok(id)
    }

    /// The content of the object `id`, of `kind`.
    fn read(&self, kind: Kind, id: ObjectId) -> io::Result<Vec<u8>> {
        let mut bytes = fs::read(self.path(id))?;
        let end = bytes.iter().position(|byte| *byte == 0);
        match end {
            Some(end) if bytes[..end] == header(kind, bytes.len() - end - 1)[..end] => {
                bytes.drain(..=end);

                Ok(bytes)
            }
            _ => Err(io::Error::new(io::ErrorKind::InvalidData, format!("object {id} of the store is no {kind:?}"))),
        }
    }

    /// The file of the object `id`.
    fn path(&self, id: ObjectId) -> PathBuf {
        let hex = id.to_string();

        self.folder.join(&hex[..2]).join(&hex[2..])
    }

    /// Deletes each object no tree of `roots` reaches, and each file a write left behind, as `git gc` prunes, while
    /// nothing else writes to the store. Gives how many files it deleted.
    ///
    /// # Errors
    ///
    /// Returns the error of reading a tree, or of listing or deleting a file.
    pub fn collect(&self, roots: &[ObjectId]) -> io::Result<usize> {
        let reached = self.reached(roots)?;

        self.sweep(&reached)
    }

    /// Collects the store as [`Self::collect`] does, once it holds more objects than Git's `gc.auto` and than twice
    /// those `roots` reach, as `git gc --auto` estimates it from the objects of one fan-out folder. Gives how many files
    /// it deleted.
    ///
    /// # Errors
    ///
    /// Returns the error of reading a tree, or of listing or deleting a file.
    pub fn collect_auto(&self, roots: &[ObjectId]) -> io::Result<usize> {
        let sampled = match fs::read_dir(self.folder.join(SAMPLED_FOLDER)) {
            Ok(entries) => entries.count(),
            Err(error) if error.kind() == io::ErrorKind::NotFound => 0,
            Err(error) => return Err(error),
        };
        let estimate = sampled * 256;
        if estimate <= GC_AUTO {
            return Ok(0);
        }
        let reached = self.reached(roots)?;
        if estimate <= 2 * reached.len() {
            return Ok(0);
        }

        self.sweep(&reached)
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

    /// Deletes each object not in `reached`, each fan-out folder it empties, and each temporary file.
    fn sweep(&self, reached: &HashSet<ObjectId>) -> io::Result<usize> {
        let entries = match fs::read_dir(&self.folder) {
            Ok(entries) => entries,
            Err(error) if error.kind() == io::ErrorKind::NotFound => return Ok(0),
            Err(error) => return Err(error),
        };
        let mut deleted = 0;
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
                } else {
                    fs::remove_file(object.path())?;
                    deleted += 1;
                }
            }
            if !kept {
                fs::remove_dir(entry.path())?;
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
    fn an_automatic_collection_waits_until_the_store_holds_more_than_git_s_gc_auto() {
        let folder = tempfile::tempdir().unwrap();
        let objects = Objects::at(folder.path().join("objects"));
        let root = objects.write_tree(&Tree::new()).unwrap();
        let gone = objects.write_blob(b"gone").unwrap();

        assert_eq!(objects.collect_auto(&[root]).unwrap(), 0);
        objects.read_blob(gone).unwrap();
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
