<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use Tmdb\Client;
use Tmdb\Helper\ImageHelper;
use Tmdb\Configuration;

$db = new PDO('sqlite:/home/dbfilms/htdocs/dbfilms.diesel.baby/dbfilms.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get existing tags
$tags_stmt = $db->query("SELECT id, name FROM tags ORDER BY name");
$existing_tags = $tags_stmt->fetchAll(PDO::FETCH_ASSOC);

$search_query = $_GET['query'] ?? '';
$results = [];
if ($search_query) {
    $client = new Client(['api_key' => $_ENV['TMDB_API_KEY']]);
    $response = $client->getSearchApi()->searchMovies($search_query, ['language' => 'en-US']);
    $results = $response['results'] ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tmdb_id'])) {
    // Begin transaction
    $db->beginTransaction();
    try {
        // Insert new entry
        $tmdb_id = $_POST['tmdb_id'];
        $client = new Client(['api_key' => $_ENV['TMDB_API_KEY']]);
        $movie = $client->getMoviesApi()->getMovie($tmdb_id, ['language' => 'en-US']);
        
        // Download images
        $config = new Configuration($client->getConfiguration());
        $imageHelper = new ImageHelper($config);
        $poster_path = $movie['poster_path'] ? "/data/img/" . basename($movie['poster_path']) : null;
        $backdrop_path = $movie['backdrop_path'] ? "/data/img/backdrops/" . basename($movie['backdrop_path']) : null;
        
        if ($movie['poster_path']) {
            $poster_url = $imageHelper->getUrl($movie['poster_path'], 'w500');
            file_put_contents("/home/dbfilms/htdocs/dbfilms.diesel.baby$poster_path", file_get_contents($poster_url));
        }
        if ($movie['backdrop_path']) {
            $backdrop_url = $imageHelper->getUrl($movie['backdrop_path'], 'w1280');
            file_put_contents("/home/dbfilms/htdocs/dbfilms.diesel.baby$backdrop_path", file_get_contents($backdrop_url));
        }

        // Insert entry
        $stmt = $db->prepare("
            INSERT INTO entries (
                tmdb_id, type, title, poster_path, backdrop_path, overview, release_date,
                genres, actors, director, tagline, certification, runtime, budget, revenue,
                imdb_id, content_status, popularity, vote_average, vote_count, original_language,
                rating, notes, watch_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $genres = implode(', ', array_column($movie['genres'] ?? [], 'name'));
        $actors = implode(', ', array_column($movie['credits']['cast'] ?? [], 'name', 0, 3));
        $director = '';
        foreach ($movie['credits']['crew'] ?? [] as $crew) {
            if ($crew['job'] === 'Director') {
                $director = $crew['name'];
                break;
            }
        }

        $rating = isset($_POST['rating']) && is_numeric($_POST['rating']) ? (int)$_POST['rating'] : 0;
        if ($rating < 0 || $rating > 100) $rating = 0;

        $stmt->execute([
            $movie['id'],
            'movie',
            $movie['title'] ?? 'Unknown',
            $poster_path,
            $backdrop_path,
            $movie['overview'] ?? null,
            $movie['release_date'] ?? null,
            $genres,
            $actors,
            $director,
            $movie['tagline'] ?? null,
            $movie['releases']['countries'][0]['certification'] ?? null,
            $movie['runtime'] ?? null,
            $movie['budget'] ?? null,
            $movie['revenue'] ?? null,
            $movie['imdb_id'] ?? null,
            $movie['status'] ?? null,
            $movie['popularity'] ?? null,
            $movie['vote_average'] ?? null,
            $movie['vote_count'] ?? null,
            $movie['original_language'] ?? null,
            $rating,
            $_POST['notes'] ?? null,
            $_POST['watch_status'] ?? null,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);

        // Handle tags
        $selected_tags = $_POST['tags'] ?? [];
        $new_tag = trim($_POST['new_tag'] ?? '');
        
        // Insert new tag if provided
        if ($new_tag) {
            $tag_stmt = $db->prepare("INSERT OR IGNORE INTO tags (name) VALUES (?)");
            $tag_stmt->execute([$new_tag]);
            $tag_id = $db->lastInsertId();
            if ($tag_id == 0) {
                // Tag already exists, get its ID
                $tag_id = $db->query("SELECT id FROM tags WHERE name = " . $db->quote($new_tag))->fetchColumn();
            }
            $selected_tags[] = $tag_id;
        }

        // Insert tag associations
        $tag_stmt = $db->prepare("INSERT OR IGNORE INTO entry_tags (entry_tmdb_id, tag_id) VALUES (?, ?)");
        foreach ($selected_tags as $tag_id) {
            $tag_stmt->execute([$tmdb_id, $tag_id]);
        }

        $db->commit();
        header('Location: /templates/entry.php?id=' . $tmdb_id);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        echo "Error: " . $e->getMessage();
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Add Movie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <style>
        .card { margin-bottom: 1.5rem; }
        .card-img { max-height: 300px; object-fit: cover; }
        .section { min-height: 100vh; }
        .tags-field { max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Add New Movie</h1>
            <form method="GET" class="mb-5">
                <div class="field has-addons">
                    <div class="control">
                        <input class="input" type="text" name="query" placeholder="Search for a movie" value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    <div class="control">
                        <button class="button is-primary" type="submit">Search</button>
                    </div>
                </div>
            </form>
            <?php if ($results): ?>
                <div class="columns is-multiline">
                    <?php foreach ($results as $movie): ?>
                        <div class="column is-one-third">
                            <div class="card">
                                <div class="card-image">
                                    <figure class="image">
                                        <img class="card-img" src="https://image.tmdb.org/t/p/w500<?php echo htmlspecialchars($movie['poster_path'] ?? ''); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                                    </figure>
                                </div>
                                <div class="card-content">
                                    <p class="title is-5"><?php echo htmlspecialchars($movie['title']); ?></p>
                                    <p><?php echo htmlspecialchars($movie['release_date'] ?? ''); ?></p>
                                    <form method="POST">
                                        <input type="hidden" name="tmdb_id" value="<?php echo htmlspecialchars($movie['id']); ?>">
                                        <div class="field">
                                            <label class="label">Watch Status</label>
                                            <div class="control">
                                                <div class="select">
                                                    <select name="watch_status">
                                                        <option value="">Select Status</option>
                                                        <option value="watched">Watched</option>
                                                        <option value="watching">Watching</option>
                                                        <option value="plan_to_watch">Plan to Watch</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label class="label">Rating (0–100)</label>
                                            <div class="control">
                                                <input class="input" type="number" name="rating" min="0" max="100" placeholder="e.g., 85">
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label class="label">Tags</label>
                                            <div class="control tags-field">
                                                <?php foreach ($existing_tags as $tag): ?>
                                                    <label class="checkbox">
                                                        <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>">
                                                        <?php echo htmlspecialchars($tag['name']); ?>
                                                    </label><br>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label class="label">New Tag</label>
                                            <div class="control">
                                                <input class="input" type="text" name="new_tag" placeholder="e.g., b-movie-schlock">
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label class="label">Notes</label>
                                            <div class="control">
                                                <textarea class="textarea" name="notes" placeholder="Your notes"></textarea>
                                            </div>
                                        </div>
                                        <div class="field">
                                            <div class="control">
                                                <button class="button is-primary" type="submit">Add Movie</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</body>
</html>