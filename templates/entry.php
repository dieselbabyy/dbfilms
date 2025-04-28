<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable('/home/dbfilms/htdocs/dbfilms.diesel.baby');
$dotenv->load();
$db = new PDO('sqlite:/home/dbfilms/htdocs/dbfilms.diesel.baby/dbfilms.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch entry and tags
$stmt = $db->prepare('
    SELECT e.*, GROUP_CONCAT(t.name) as tags
    FROM entries e
    LEFT JOIN entry_tags et ON e.id = et.entry_id
    LEFT JOIN tags t ON et.tag_id = t.id
    WHERE e.tmdb_id = ?
    GROUP BY e.id
');
$stmt->execute([$_GET['id'] ?? 0]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

// Strip prefixes from image paths
$poster_path = str_replace('/data/img/', '', $entry['poster_path'] ?? '');
$backdrop_path = str_replace('/data/img/backdrops/', '', $entry['backdrop_path'] ?? '');

// Format release date
$release_date = $entry['release_date'] ? (new DateTime($entry['release_date']))->format('F j, Y') : 'N/A';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($entry['title'] ?? 'Not Found'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <style>
        .hero { 
            background: url('/data/img/backdrops/<?php echo htmlspecialchars($backdrop_path); ?>') no-repeat center; 
            background-size: cover; 
            min-height: 100vh; 
        }
        .hero-body { background: rgba(0, 0, 0, 0.5); }
        .poster { max-width: 200px; }
        .movie-title { font-size: 3rem; font-weight: bold; }
        .info-label { font-weight: bold; }
        .rating { font-size: 1.5rem; }
        .high-rating {
            background: linear-gradient(45deg, #ff6b6b, #ffd700, #4facfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
        }
        @keyframes sparkle {
            0% { text-shadow: 0 0 5px rgba(255, 215, 0, 0.8); }
            50% { text-shadow: 0 0 15px rgba(255, 215, 0, 1), 0 0 20px rgba(255, 255, 255, 0.8); }
            100% { text-shadow: 0 0 5px rgba(255, 215, 0, 0.8); }
        }
        .sparkle { animation: sparkle 1.5s infinite; }
    </style>
</head>
<body>
    <section class="hero is-fullheight">
        <div class="hero-body">
            <div class="container">
                <?php if ($entry): ?>
                    <div class="columns">
                        <div class="column is-3">
                            <img class="poster" src="/data/img/<?php echo htmlspecialchars($poster_path); ?>" alt="Poster">
                        </div>
                        <div class="column is-9">
                            <h1 class="title has-text-white movie-title"><?php echo htmlspecialchars($entry['title']); ?></h1>
                            <p class="subtitle has-text-light"><?php echo htmlspecialchars($entry['overview'] ?? ''); ?></p>
                            <p class="has-text-white rating <?php echo ($entry['rating'] ?? 0) > 90 ? 'high-rating sparkle' : ''; ?>">
                                <span class="info-label">Rating:</span> <?php echo htmlspecialchars($entry['rating'] ?? 'N/A'); ?>/100
                            </p>
                            <p class="has-text-white">
                                <span class="info-label">Genres:</span> <?php echo htmlspecialchars($entry['genres'] ?? 'N/A'); ?>
                            </p>
                            <p class="has-text-white">
                                <span class="info-label">Actors:</span> <?php echo htmlspecialchars($entry['actors'] ?? 'N/A'); ?>
                            </p>
                            <p class="has-text-white">
                                <span class="info-label">Director:</span> <?php echo htmlspecialchars($entry['director'] ?? 'N/A'); ?>
                            </p>
                            <p class="has-text-white">
                                <span class="info-label">Release:</span> <?php echo htmlspecialchars($release_date); ?>
                            </p>
                            <p class="has-text-white">
                                <span class="info-label">Tags:</span> <?php echo htmlspecialchars($entry['tags'] ?? 'None'); ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <h1 class="title has-text-white">Entry Not Found</h1>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
