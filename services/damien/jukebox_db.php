<?php
define('JUKEBOX_DB', __DIR__ . '/jukebox.db');

function jukebox_init(): void {
    $db = new SQLite3(JUKEBOX_DB);
    $db->exec('CREATE TABLE IF NOT EXISTS albums (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        artist TEXT NOT NULL,
        album_path TEXT NOT NULL,
        album_name TEXT,
        position INTEGER DEFAULT 0,
        added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(artist, album_path)
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS radios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        url TEXT NOT NULL,
        position INTEGER DEFAULT 0,
        added_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->close();
}
jukebox_init();

function jukebox_list(): array {
    $db = new SQLite3(JUKEBOX_DB);
    $res = $db->query('SELECT artist, album_path, album_name FROM albums ORDER BY position, artist, album_name');
    $albums = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $albums[] = $row;
    }
    $db->close();
    return $albums;
}

function jukebox_add(string $artist, string $album_path, string $album_name): bool {
    $db = new SQLite3(JUKEBOX_DB);
    $stmt = $db->prepare('INSERT OR IGNORE INTO albums (artist, album_path, album_name) VALUES (:a, :p, :n)');
    $stmt->bindValue(':a', $artist, SQLITE3_TEXT);
    $stmt->bindValue(':p', $album_path, SQLITE3_TEXT);
    $stmt->bindValue(':n', $album_name, SQLITE3_TEXT);
    $ok = $stmt->execute() !== false;
    $db->close();
    return $ok;
}

function jukebox_remove(string $artist, string $album_path): bool {
    $db = new SQLite3(JUKEBOX_DB);
    $stmt = $db->prepare('DELETE FROM albums WHERE artist = :a AND album_path = :p');
    $stmt->bindValue(':a', $artist, SQLITE3_TEXT);
    $stmt->bindValue(':p', $album_path, SQLITE3_TEXT);
    $ok = $stmt->execute() !== false;
    $db->close();
    return $ok;
}

function jukebox_is_selected(string $artist, string $album_path): bool {
    $db = new SQLite3(JUKEBOX_DB);
    $stmt = $db->prepare('SELECT COUNT(*) FROM albums WHERE artist = :a AND album_path = :p');
    $stmt->bindValue(':a', $artist, SQLITE3_TEXT);
    $stmt->bindValue(':p', $album_path, SQLITE3_TEXT);
    $count = $stmt->execute()->fetchArray()[0];
    $db->close();
    return $count > 0;
}

// === Radios ===

function radio_list(): array {
    $db = new SQLite3(JUKEBOX_DB);
    $res = $db->query('SELECT id, name, url FROM radios ORDER BY position, name');
    $radios = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) $radios[] = $row;
    $db->close();
    return $radios;
}

function radio_add(string $name, string $url): bool {
    $db = new SQLite3(JUKEBOX_DB);
    $stmt = $db->prepare('INSERT OR IGNORE INTO radios (name, url) VALUES (:n, :u)');
    $stmt->bindValue(':n', $name); $stmt->bindValue(':u', $url);
    $ok = $stmt->execute() !== false;
    $db->close();
    return $ok;
}

function radio_remove(int $id): bool {
    $db = new SQLite3(JUKEBOX_DB);
    $stmt = $db->prepare('DELETE FROM radios WHERE id = :i');
    $stmt->bindValue(':i', $id, SQLITE3_INTEGER);
    $ok = $stmt->execute() !== false;
    $db->close();
    return $ok;
}

function radio_move(int $id, string $dir): void {
    $db = new SQLite3(JUKEBOX_DB);
    $cur = $db->querySingle('SELECT position FROM radios WHERE id=' . $id);
    if ($cur === null) return;
    $other = $dir === 'up'
        ? $db->querySingle('SELECT id, position FROM radios WHERE position < ' . $cur . ' ORDER BY position DESC LIMIT 1', true)
        : $db->querySingle('SELECT id, position FROM radios WHERE position > ' . $cur . ' ORDER BY position ASC LIMIT 1', true);
    if ($other) {
        $db->exec('UPDATE radios SET position=' . $other['position'] . ' WHERE id=' . $id);
        $db->exec('UPDATE radios SET position=' . $cur . ' WHERE id=' . $other['id']);
    }
    $db->close();
}

function album_move(string $artist, string $album_path, string $dir): void {
    $db = new SQLite3(JUKEBOX_DB);
    $cur = $db->querySingle('SELECT position FROM albums WHERE artist=\"' . addslashes($artist) . '\" AND album_path=\"' . addslashes($album_path) . '\"');
    if ($cur === null) return;
    $other = $dir === 'up'
        ? $db->querySingle('SELECT artist, album_path, position FROM albums WHERE position < ' . $cur . ' ORDER BY position DESC LIMIT 1', true)
        : $db->querySingle('SELECT artist, album_path, position FROM albums WHERE position > ' . $cur . ' ORDER BY position ASC LIMIT 1', true);
    if ($other) {
        $db->exec('UPDATE albums SET position=' . $other['position'] . ' WHERE artist=\"' . addslashes($artist) . '\" AND album_path=\"' . addslashes($album_path) . '\"');
        $db->exec('UPDATE albums SET position=' . $cur . ' WHERE artist=\"' . addslashes($other['artist']) . '\" AND album_path=\"' . addslashes($other['album_path']) . '\"');
    }
    $db->close();
}
