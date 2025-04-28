<?php
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

$stmt = $db->prepare("SELECT tmdb_id, title, poster_path, rating FROM entries $where ORDER BY $sort $order LIMIT 10");
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Movie Database</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <style>
        .card { margin-bottom: 1.5rem; }
        .card-img { max-height: 300px; object-fit: cover; }
        #loader { 
            text-align: center; 
            padding: 2rem; 
            min-height: 100px; 
            font-size: 1.2rem; 
            background: #e0e0e0; 
            margin: 2rem 0; 
            border: 1px solid #ccc; 
        }
        #loader.hidden { display: none; }
        .star-rating { color: #FFD700; }
        .section { min-height: 100vh; } /* Ensure enough scrollable space */
    </style>
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Movies</h1>
            <form class="mb-5" method="GET">
                <div class="field has-addons">
                    <div class="control">
                        <input class="input" type="text" name="search" placeholder="Search movies" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="control">
                        <button class="button is-primary" type="submit">Search</button>
                    </div>
                </div>
            </form>
            <div class="field is-grouped mb-5">
                <div class="control">
                    <div class="select">
                        <select id="sort" onchange="updateSort()">
                            <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Alphabetical</option>
                            <option value="release_date" <?php echo $sort === 'release_date' ? 'selected' : ''; ?>>Release Date</option>
                            <option value="updated_at" <?php echo $sort === 'updated_at' ? 'selected' : ''; ?>>Recent Activity</option>
                            <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Rating</option>
                        </select>
                    </div>
                </div>
                <div class="control">
                    <div class="select">
                        <select id="status" onchange="updateSort()">
                            <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="watched" <?php echo $status === 'watched' ? 'selected' : ''; ?>>Watched</option>
                            <option value="watching" <?php echo $status === 'watching' ? 'selected' : ''; ?>>Currently Watching</option>
                            <option value="plan_to_watch" <?php echo $status === 'plan_to_watch' ? 'selected' : ''; ?>>Plan to Watch</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="columns is-multiline" id="movie-grid">
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $poster_path = str_replace('/data/img/', '', $entry['poster_path'] ?? '');
                    $stars = round(($entry['rating'] ?? 0) / 20);
                    ?>
                    <div class="column is-one-third">
                        <div class="card">
                            <div class="card-image">
                                <figure class="image">
                                    <img class="card-img" src="/data/img/<?php echo htmlspecialchars($poster_path); ?>" alt="<?php echo htmlspecialchars($entry['title']); ?>">
                                </figure>
                            </div>
                            <div class="card-content">
                                <p class="title is-5"><a href="/templates/entry.php?id=<?php echo htmlspecialchars($entry['tmdb_id']); ?>"><?php echo htmlspecialchars($entry['title']); ?></a></p>
                                <p class="star-rating"><?php echo str_repeat('★', $stars) . str_repeat('☆', 5 - $stars); ?> (<?php echo $entry['rating'] ?? 'N/A'; ?>/100)</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="loader" class="hidden">Loading...</div>
        </div>
    </section>
    <script>
        let offset = 10;
        let loading = false;
        const sort = '<?php echo $sort; ?>';
        const status = '<?php echo addslashes($status); ?>';
        const search = '<?php echo addslashes($search); ?>';

        function updateSort() {
            const newSort = document.getElementById('sort').value;
            const newStatus = document.getElementById('status').value;
            const newSearch = document.querySelector('input[name="search"]').value;
            window.location.href = `?sort=${newSort}&status=${encodeURIComponent(newStatus)}&search=${encodeURIComponent(newSearch)}`;
        }

        function loadMoreMovies() {
            if (loading) return;
            loading = true;
            const loader = document.getElementById('loader');
            loader.classList.remove('hidden');
            console.log(`Loading more movies: offset=${offset}, sort=${sort}, status=${status}, search=${search}`);

            fetch(`/api.php?sort=${sort}&status=${encodeURIComponent(status)}&search=${encodeURIComponent(search)}&offset=${offset}`, { method: 'GET' })
                .then(response => {
                    console.log('Fetch response status:', response.status);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    console.log('Fetched data:', data);
                    if (!data.length) {
                        loader.classList.add('hidden');
                        console.log('No more data to load');
                        return;
                    }
                    const grid = document.getElementById('movie-grid');
                    data.forEach(entry => {
                        const stars = Math.round((entry.rating || 0) / 20);
                        const div = document.createElement('div');
                        div.className = 'column is-one-third';
                        div.innerHTML = `
                            <div class="card">
                                <div class="card-image">
                                    <figure class="image">
                                        <img class="card-img" src="/data/img/${entry.poster_path}" alt="${entry.title}">
                                    </figure>
                                </div>
                                <div class="card-content">
                                    <p class="title is-5"><a href="/templates/entry.php?id=${entry.tmdb_id}">${entry.title}</a></p>
                                    <p class="star-rating">${'★'.repeat(stars)}${'☆'.repeat(5 - stars)} (${entry.rating || 'N/A'}/100)</p>
                                </div>
                            </div>
                        `;
                        grid.appendChild(div);
                    });
                    offset += data.length;
                    loading = false;
                    loader.classList.add('hidden', data.length < 10);
                    console.log(`Loaded ${data.length} movies, new offset: ${offset}`);
                })
                .catch(error => {
                    console.error('Error loading more movies:', error);
                    loading = false;
                    loader.classList.add('hidden');
                });
        }

        const observer = new IntersectionObserver(entries => {
            const entry = entries[0];
            console.log('Observer triggered:', entry.isIntersecting, 'Bounding rect:', entry.boundingClientRect, 'Root bounds:', entry.rootBounds);
            if (entry.isIntersecting && !loading) {
                loadMoreMovies();
            }
        }, { threshold: 0.1, rootMargin: '300px' });

        console.log('Observing loader element');
        const loader = document.getElementById('loader');
        if (!loader) {
            console.error('Loader element not found!');
        } else {
            observer.observe(loader);
        }

        // Fallback: Check if loader is in view on load
        window.addEventListener('load', () => {
            if (!loader) return;
            const rect = loader.getBoundingClientRect();
            console.log('Loader on load - Top:', rect.top, 'Window height:', window.innerHeight, 'Bottom:', rect.bottom);
            if (rect.top < window.innerHeight && rect.bottom > 0 && !loading) {
                console.log('Loader in view on load, triggering loadMoreMovies');
                loadMoreMovies();
            }
        });

        // Fallback: Manual scroll check
        window.addEventListener('scroll', () => {
            if (!loader) return;
            const rect = loader.getBoundingClientRect();
            console.log('Scroll - Loader top:', rect.top, 'Window height:', window.innerHeight, 'Bottom:', rect.bottom);
            if (rect.top < window.innerHeight && rect.bottom > 0 && !loading) {
                console.log('Loader in view on scroll, triggering loadMoreMovies');
                loadMoreMovies();
            }
        });
    </script>
</body>
</html>
