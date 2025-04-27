<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$db = new PDO('sqlite:dbfilms.db');
$db->exec('DROP TABLE IF EXISTS entries; DROP TABLE IF EXISTS tags; DROP TABLE IF EXISTS entry_tags; DROP TABLE IF EXISTS admin;');
$db->exec(file_get_contents('dbfilms_schema.sql'));
$json_data = file_get_contents('watcharr_export.json');
$json = json_decode($json_data, true);
if ($json === null) {
    die("Error decoding JSON: " . json_last_error_msg());
}
if (!is_array($json)) {
    die("JSON is not an array of entries.");
}
$stmt = $db->prepare('
    INSERT INTO entries (
        tmdb_id, type, title, poster_path, backdrop_path, overview, release_date,
        genres, actors, director, tagline, certification, runtime, budget, revenue,
        imdb_id, content_status, popularity, vote_average, vote_count, original_language,
        rating, notes, watch_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
');
$tag_stmt = $db->prepare('INSERT OR IGNORE INTO tags (name) VALUES (?)');
$entry_tag_stmt = $db->prepare('INSERT INTO entry_tags (entry_id, tag_id) SELECT ?, id FROM tags WHERE name = ?');
foreach ($json as $entry) {
    $stmt->execute([
        $entry['tmdb_id'],
        $entry['type'],
        $entry['title'],
        $entry['poster_path'] ?? null,
        $entry['backdrop_path'] ?? null,
        $entry['overview'] ?? null,
        $entry['release_date'] ?? null,
        $entry['genres'] ?? null,
        $entry['actors'] ?? null,
        $entry['director'] ?? null,
        $entry['tagline'] ?? null,
        $entry['certification'] ?? null,
        $entry['runtime'] ?? null,
        $entry['budget'] ?? null,
        $entry['revenue'] ?? null,
        $entry['imdb_id'] ?? null,
        $entry['content_status'] ?? null,
        $entry['popularity'] ?? null,
        $entry['vote_average'] ?? null,
        $entry['vote_count'] ?? null,
        $entry['original_language'] ?? null,
        $entry['rating'] ?? null,
        $entry['thoughts'] ?? null,
        $entry['watch_status'] ?? null
    ]);
    $entry_id = $db->lastInsertId();
    foreach (explode(',', $entry['tags'] ?? '') as $tag) {
        if ($tag) {
            $tag_stmt->execute([$tag]);
            $entry_tag_stmt->execute([$entry_id, $tag]);
        }
    }
}
echo "Import complete.\n";
?>
