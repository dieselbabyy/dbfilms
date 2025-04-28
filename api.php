<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$db = new PDO('sqlite:/home/dbfilms/htdocs/dbfilms.diesel.baby/dbfilms.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sort = $_GET['sort'] ?? 'title';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$offset = (int)($_GET['offset'] ?? 0);
$limit = 10;

$allowed_sorts = ['title', 'release_date', 'updated_at', 'rating'];
$sort = in_array($sort, $allowed_sorts) ? $sort : 'title';
$order = ($sort === 'title') ? 'ASC' : 'DESC';

$where = '';
$params = [];
if ($status && in_array($status, ['watched', 'watching', 'plan_to_watch', ''])) {
    $where = $status ? 'WHERE watch_status = ?' : 'WHERE watch_status IS NULL';
    if ($status) $params[] = $status;
}
if ($search) {
    $where .= ($where ? ' AND ' : 'WHERE ') . 'title LIKE ?';
    $params[] = "%$search%";
}

$query = "SELECT tmdb_id, title, poster_path, COALESCE(rating, 0) AS rating FROM entries $where ORDER BY $sort $order LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Strip /data/img/ prefix
foreach ($entries as &$entry) {
    $entry['poster_path'] = str_replace('/data/img/', '', $entry['poster_path'] ?? '');
    $entry['rating'] = (int)$entry['rating']; // Ensure integer
}
unset($entry);

echo json_encode($entries);
?>