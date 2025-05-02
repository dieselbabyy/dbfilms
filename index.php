<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$db = new PDO('sqlite:/home/dbfilms/htdocs/dbfilms.diesel.baby/dbfilms.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$allowed_sorts = ['title', 'release_date', 'created_at', 'rating'];
$sort = in_array($sort, $allowed_sorts) ? $sort : 'created_at';
$order = in_array($order, ['ASC', 'DESC']) ? $order : 'DESC';

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

$stmt = $db->prepare("SELECT tmdb_id, title, poster_path, COALESCE(rating, 0) AS rating, watch_status, created_at, updated_at FROM entries $where ORDER BY $sort $order LIMIT 10");
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Strip /data/img/ prefix
foreach ($entries as &$entry) {
    $entry['poster_path'] = str_replace('/data/img/', '', $entry['poster_path'] ?? '');
}
unset($entry);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Movie Database</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap">
    <style>
        body, .section {
            background-color: #1c2526;
            color: #e0e0e0;
            font-family: 'Inter', sans-serif;
        }
        .container, .card {
            background-color: #2a3439;
            color: #e0e0e0;
        }
        .title {
            color: #ffffff;
            font-size: 3rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .title.is-5 {
            font-size: 1.25rem;
            font-weight: 400;
            color: #ffffff;
        }
        .input, .button {
            background-color: #3a4449;
            color: #e0e0e0;
            border-color: #4a5459;
        }
        .input::placeholder {
            color: #a0a0a0;
        }
        .dropdown-menu, .dropdown-content {
            background-color: #2a3439;
            border: 1px solid #4a5459;
        }
        .dropdown-item {
            color: #e0e0e0;
        }
        .dropdown-item.is-active {
            background-color: #3a4449;
            color: #ffffff;
        }
        .card {
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        .card-img {
            max-height: 300px;
            object-fit: cover;
        }
        .card-content {
            min-height: 100px;
        }
        #loader {
            text-align: center;
            padding: 2rem;
            min-height: 100px;
            font-size: 1.2rem;
            background: #3a4449;
            margin: 2rem auto;
            border: 1px solid #4a5459;
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        #loader.hidden {
            display: none;
        }
        .star-rating {
            color: #ffd700;
        }
        .section {
            min-height: 100vh;
        }
        .spacer {
            height: 5000px;
        }
        .status-icon {
            display: inline-flex;
            align-items: center;
            vertical-align: middle;
            margin-left: 0.5rem;
        }
        .icon-watched, .icon-watching, .icon-plan {
            width: 1.5rem;
            height: 1.5rem;
        }
        .status-watched .icon-watched {
            fill: #00ff00;
        }
        .status-watching .icon-watching {
            fill: #ff9900;
        }
        .status-plan .icon-plan {
            fill: #cccccc;
        }
        .dropdown.is-right .dropdown-menu {
            right: 0;
            left: auto;
        }
        .dropdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .fa-check {
            margin-left: 5px;
        }
        .is-hidden {
            display: none;
        }
        .footer {
            background-color: #1a1f21;
            padding: 2rem 1rem;
            color: #e0e0e0;
            font-size: 0.9rem;
            text-align: center;
            border-top: 6px solid #00b7eb;
        }
        .footer .content {
            margin-top: 1rem;
        }
        .footer a {
            color: #00ff00;
        }
        .footer a:hover {
            color: #ffffff;
        }
        .footer .github-icon {
            width: 1rem;
            height: 1rem;
            fill: #ffffff;
            vertical-align: middle;
            margin-right: 0.25rem;
        }
        @media (max-width: 768px) {
            .icon-watched, .icon-watching, .icon-plan {
                width: 1.2rem;
                height: 1.2rem;
            }
            .field {
                width: 100%;
            }
            .title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Movie Database</h1>
            <form class="mb-5" method="GET">
                <div class="field is-grouped mb-5" style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="field" style="width: 60%; margin: 0 auto;">
                        <div class="control has-icons-left">
                            <input class="input" type="text" name="search" placeholder="Search movies" value="<?php echo htmlspecialchars($search); ?>">
                            <span class="icon is-left">
                                <svg class="icon-search" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path fill="#e0e0e0" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="dropdown is-right" id="filterDropdown">
                        <div class="dropdown-trigger">
                            <button class="button" aria-haspopup="true" aria-controls="dropdown-menu">
                                <span>Filter/sort results by:</span>
                                <span class="icon is-small">
                                    <svg class="icon-filter" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                        <path fill="#e0e0e0" d="M10 18h4v-2h-4zM3 6v2h18V6zm3 7h12v-2H6z"/>
                                    </svg>
                                </span>
                            </button>
                        </div>
                        <div class="dropdown-menu" id="dropdown-menu" role="menu">
                            <div class="dropdown-content">
                                <div class="dropdown-item">
                                    <p><strong>Sort By</strong></p>
                                    <a href="#" class="dropdown-item sort-option <?php echo $sort === 'created_at' ? 'is-active' : ''; ?>" data-sort="created_at">
                                        Date Added <i class="fas fa-check <?php echo $sort === 'created_at' ? '' : 'is-hidden'; ?>"></i>
                                        <span class="sort-direction"><?php echo $order === 'DESC' ? '↓' : '↑'; ?></span>
                                    </a>
                                    <a href="#" class="dropdown-item sort-option <?php echo $sort === 'title' ? 'is-active' : ''; ?>" data-sort="title">
                                        Title <i class="fas fa-check <?php echo $sort === 'title' ? '' : 'is-hidden'; ?>"></i>
                                        <span class="sort-direction"><?php echo $order === 'ASC' ? '↑' : '↓'; ?></span>
                                    </a>
                                    <a href="#" class="dropdown-item sort-option <?php echo $sort === 'release_date' ? 'is-active' : ''; ?>" data-sort="release_date">
                                        Release Date <i class="fas fa-check <?php echo $sort === 'release_date' ? '' : 'is-hidden'; ?>"></i>
                                        <span class="sort-direction"><?php echo $order === 'DESC' ? '↓' : '↑'; ?></span>
                                    </a>
                                    <a href="#" class="dropdown-item sort-option <?php echo $sort === 'rating' ? 'is-active' : ''; ?>" data-sort="rating">
                                        Rating <i class="fas fa-check <?php echo $sort === 'rating' ? '' : 'is-hidden'; ?>"></i>
                                        <span class="sort-direction"><?php echo $order === 'DESC' ? '↓' : '↑'; ?></span>
                                    </a>
                                </div>
                                <hr class="dropdown-divider">
                                <div class="dropdown-item">
                                    <p><strong>Filter By Status</strong></p>
                                    <a href="#" class="dropdown-item status-option <?php echo $status === '' ? 'is-active' : ''; ?>" data-status="">
                                        All Statuses <i class="fas fa-check <?php echo $status === '' ? '' : 'is-hidden'; ?>"></i>
                                    </a>
                                    <a href="#" class="dropdown-item status-option <?php echo $status === 'watched' ? 'is-active' : ''; ?>" data-status="watched">
                                        Watched <i class="fas fa-check <?php echo $status === 'watched' ? '' : 'is-hidden'; ?>"></i>
                                    </a>
                                    <a href="#" class="dropdown-item status-option <?php echo $status === 'watching' ? 'is-active' : ''; ?>" data-status="watching">
                                        Watching <i class="fas fa-check <?php echo $status === 'watching' ? '' : 'is-hidden'; ?>"></i>
                                    </a>
                                    <a href="#" class="dropdown-item status-option <?php echo $status === 'plan_to_watch' ? 'is-active' : ''; ?>" data-status="plan_to_watch">
                                        Plan to Watch <i class="fas fa-check <?php echo $status === 'plan_to_watch' ? '' : 'is-hidden'; ?>"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <div class="columns is-multiline" id="movie-grid">
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $poster_path = str_replace('/data/img/', '', $entry['poster_path'] ?? '');
                    $rating = isset($entry['rating']) ? (int)$entry['rating'] : 0;
                    $stars = round($rating / 20);
                    $watch_status = $entry['watch_status'] ?? '';
                    $status_icon = '';
                    $status_class = '';
                    $status_text = '';
                    if ($watch_status === 'watched') {
                        $status_icon = '<span class="status-icon icon-watched"><svg class="icon-watched" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m14.05 18.375l4.975-4.975L17.6 12l-3.55 3.55l-1.4-1.425l-1.425 1.425zM7 15h3v-2H7zm0-3h7v-2H7zm0-3h7V7H7zm5 13q-2.075 0-3.9-.788t-3.175-2.137T2.788 15.9T2 12t.788-3.9t2.137-3.175T8.1 2.788T12 2t3.9.788t3.175 2.137T21.213 8.1T22 12t-.788 3.9t-2.137 3.175t-3.175 2.138T12 22m0-2q3.35 0 5.675-2.325T20 12t-2.325-5.675T12 4T6.325 6.325T4 12t2.325 5.675T12 20m0-8" /></svg></span>';
                        $status_class = 'status-watched';
                        $status_text = 'Watched on ' . ($entry['updated_at'] ?? 'Unknown');
                    } elseif ($watch_status === 'watching') {
                        $status_icon = '<span class="status-icon icon-watching"><svg class="icon-watching" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m10.775 16.475l4.6-3.05q.225-.15.225-.425t-.225-.425l-4.6-3.05q-.25-.175-.513-.038T10 9.926v6.15q0 .3.263.438t.512-.038M10 3q-.425 0-.712-.288T9 2t.288-.712T10 1h4q.425 0 .713.288T15 2t-.288.713T14 3zm2 19q-1.85 0-3.488-.712T5.65 19.35t-1.937-2.863T3 13t.713-3.488T5.65 6.65t2.863-1.937T12 4q1.55 0 2.975.5t2.675 1.45l.7-.7q.275-.275.7-.275t.7.275t.275.7t-.275.7l-.7.7Q20 8.6 20.5 10.025T21 13q0 1.85-.713 3.488T18.35 19.35t-2.863 1.938T12 22m0-2q2.9 0 4.95-2.05T19 13t-2.05-4.95T12 6T7.05 8.05T5 13t2.05 4.95T12 20m0-7" /></svg></span>';
                        $status_class = 'status-watching';
                        $status_text = 'Currently watching, started on ' . ($entry['created_at'] ?? 'Unknown');
                    } elseif ($watch_status === 'plan_to_watch') {
                        $status_icon = '<span class="status-icon icon-plan"><svg class="icon-plan" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 8h14V6H5zm0 0V6zm0 14q-.825 0-1.412-.587T3 20V6q0-.825.588-1.412T5 4h1V3q0-.425.288-.712T7 2t.713.288T8 3v1h8V3q0-.425.288-.712T17 2t.713.288T18 3v1h1q.825 0 1.413.588T21 6v4.675q0 .425-.288.713t-.712.287t-.712-.288t-.288-.712V10H5v10h5.8q.425 0 .713.288T11.8 21t-.288.713T10.8 22zm13 1q-2.075 0-3.537-1.463T13 18t1.463-3.537T18 13t3.538 1.463T23 18t-1.463 3.538T18 23m.5-5.2v-2.3q0-.2-.15-.35T18 15t-.35.15t-.15.35v2.275q0 .2.075.388t.225.337l1.525 1.525q.15.15.35.15t.35-.15t.15-.35t-.15-.35z" /></svg></span>';
                        $status_class = 'status-plan';
                        $status_text = 'Added to watchlist on ' . ($entry['created_at'] ?? 'Unknown');
                    }
                    ?>
                    <div class="column is-one-third">
                        <div class="card">
                            <div class="card-image">
                                <figure class="image">
                                    <img class="card-img" src="/data/img/<?php echo htmlspecialchars($poster_path); ?>" alt="<?php echo htmlspecialchars($entry['title']); ?>">
                                </figure>
                            </div>
                            <div class="card-content">
                                <p class="title is-5">
                                    <a href="/templates/entry.php?id=<?php echo htmlspecialchars($entry['tmdb_id']); ?>">
                                        <?php echo htmlspecialchars($entry['title']); ?>
                                    </a>
                                    <?php if ($status_icon): ?>
                                        <?php echo $status_icon; ?>
                                    <?php endif; ?>
                                </p>
                                <?php if ($watch_status === 'watched' && $rating > 0): ?>
                                    <p class="star-rating"><?php echo str_repeat('★', $stars) . str_repeat('☆', 5 - $stars); ?> (<?php echo $rating; ?>/100)</p>
                                <?php else: ?>
                                    <p class="star-rating"> </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="loader">Loading...</div>
            <div class="spacer"></div>
        </div>
    </section>
    <footer class="footer">
        <div class="content has-text-centered">
            <p>
                © 2025 diesel.baby •
                <a href="https://github.com/dieselbabyy/dbfilms" target="_blank">
                    <svg class="github-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 17.773 3.633 17.296 3.633 17.296c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                    </svg>
                    dbfilms on github
                </a>
            </p>
            <p>
                This product uses the <a href="https://www.themoviedb.org/" target="_blank">TMDB API</a> but is not endorsed or certified by TMDB.
                <br>
                <a href="https://www.themoviedb.org/" target="_blank">
                    <img src="/data/img/tmdb-logo.png" alt="TMDB Logo" style="width:273px; margin-top: 1rem;">
                </a>
            </p>
            <p>
                Font credits: [To be added]
            </p>
        </div>
    </footer>
    <script>
        let offset = 10;
        let loading = false;
        let currentSort = '<?php echo $sort; ?>';
        let currentOrder = '<?php echo $order; ?>';
        let currentStatus = '<?php echo addslashes($status); ?>';
        let currentSearch = '<?php echo addslashes($search); ?>';

        const filterDropdown = document.getElementById('filterDropdown');
        const sortOptions = document.querySelectorAll('.sort-option');
        const statusOptions = document.querySelectorAll('.status-option');

        filterDropdown.addEventListener('click', (e) => {
            if (e.target.closest('.dropdown-trigger')) {
                filterDropdown.classList.toggle('is-active');
            }
        });

        sortOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.preventDefault();
                const newSort = option.dataset.sort;
                const isSameSort = newSort === currentSort;
                currentSort = newSort;
                currentOrder = isSameSort ? (currentOrder === 'ASC' ? 'DESC' : 'ASC') : (newSort === 'title' ? 'ASC' : 'DESC');
                
                sortOptions.forEach(opt => {
                    opt.classList.toggle('is-active', opt.dataset.sort === currentSort);
                    opt.querySelector('.fa-check').classList.toggle('is-hidden', opt.dataset.sort !== currentSort);
                    opt.querySelector('.sort-direction').textContent = currentOrder === 'ASC' ? '↑' : '↓';
                });
                
                updateUrl();
            });
        });

        statusOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.preventDefault();
                currentStatus = option.dataset.status;
                
                statusOptions.forEach(opt => {
                    opt.classList.toggle('is-active', opt.dataset.status === currentStatus);
                    opt.querySelector('.fa-check').classList.toggle('is-hidden', opt.dataset.status !== currentStatus);
                });
                
                updateUrl();
            });
        });

        function updateUrl() {
            const newSearch = document.querySelector('input[name="search"]').value;
            window.location.href = `?sort=${currentSort}&order=${currentOrder}&status=${encodeURIComponent(currentStatus)}&search=${encodeURIComponent(newSearch)}`;
        }

        function loadMoreMovies() {
            if (loading) return;
            loading = true;
            const loader = document.getElementById('loader');
            loader.classList.remove('hidden');
            console.log(`Loading more movies: offset=${offset}, sort=${currentSort}, order=${currentOrder}, status=${currentStatus}, search=${currentSearch}`);

            fetch(`/api.php?sort=${currentSort}&order=${currentOrder}&status=${encodeURIComponent(currentStatus)}&search=${encodeURIComponent(currentSearch)}&offset=${offset}`, { method: 'GET' })
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
                        const rating = entry.rating !== null ? parseInt(entry.rating) : 0;
                        const stars = Math.round(rating / 20);
                        let status_icon = '';
                        let status_class = '';
                        let status_text = '';
                        if (entry.watch_status === 'watched') {
                            status_icon = '<span class="status-icon icon-watched"><svg class="icon-watched" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m14.05 18.375l4.975-4.975L17.6 12l-3.55 3.55l-1.4-1.425l-1.425 1.425zM7 15h3v-2H7zm0-3h7v-2H7zm0-3h7V7H7zm5 13q-2.075 0-3.9-.788t-3.175-2.137T2.788 15.9T2 12t.788-3.9t2.137-3.175T8.1 2.788T12 2t3.9.788t3.175 2.137T21.213 8.1T22 12t-.788 3.9t-2.137 3.175t-3.175 2.138T12 22m0-2q3.35 0 5.675-2.325T20 12t-2.325-5.675T12 4T6.325 6.325T4 12t2.325 5.675T12 20m0-8" /></svg></span>';
                            status_class = 'status-watched';
                            status_text = 'Watched on ' + (entry.updated_at || 'Unknown');
                        } else if (entry.watch_status === 'watching') {
                            status_icon = '<span class="status-icon icon-watching"><svg class="icon-watching" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m10.775 16.475l4.6-3.05q.225-.15.225-.425t-.225-.425l-4.6-3.05q-.25-.175-.513-.038T10 9.926v6.15q0 .3.263.438t.512-.038M10 3q-.425 0-.712-.288T9 2t.288-.712T10 1h4q.425 0 .713.288T15 2t-.288.713T14 3zm2 19q-1.85 0-3.488-.712T5.65 19.35t-1.937-2.863T3 13t.713-3.488T5.65 6.65t2.863-1.937T12 4q1.55 0 2.975.5t2.675 1.45l.7-.7q.275-.275.7-.275t.7.275t.275.7t-.275.7l-.7.7Q20 8.6 20.5 10.025T21 13q0 1.85-.713 3.488T18.35 19.35t-2.863 1.938T12 22m0-2q2.9 0 4.95-2.05T19 13t-2.05-4.95T12 6T7.05 8.05T5 13t2.05 4.95T12 20m0-7" /></svg></span>';
                            status_class = 'status-watching';
                            status_text = 'Currently watching, started on ' + (entry.created_at || 'Unknown');
                        } else if (entry.watch_status === 'plan_to_watch') {
                            status_icon = '<span class="status-icon icon-plan"><svg class="icon-plan" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 8h14V6H5zm0 0V6zm0 14q-.825 0-1.412-.587T3 20V6q0-.825.588-1.412T5 4h1V3q0-.425.288-.712T7 2t.713.288T8 3v1h8V3q0-.425.288-.712T17 2t.713.288T18 3v1h1q.825 0 1.413.588T21 6v4.675q0 .425-.288.713t-.712.287t-.712-.288t-.288-.712V10H5v10h5.8q.425 0 .713.288T11.8 21t-.288.713T10.8 22zm13 1q-2.075 0-3.537-1.463T13 18t1.463-3.537T18 13t3.538 1.463T23 18t-1.463 3.538T18 23m.5-5.2v-2.3q0-.2-.15-.35T18 15t-.35.15t-.15.35v2.275q0 .2.075.388t.225.337l1.525 1.525q.15.15.35.15t.35-.15t.15-.35t-.15-.35z" /></svg></span>';
                            status_class = 'status-plan';
                            status_text = 'Added to watchlist on ' + (entry.created_at || 'Unknown');
                        }
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
                                    <p class="title is-5">
                                        <a href="/templates/entry.php?id=${entry.tmdb_id}">${entry.title}</a>
                                        ${status_icon}
                                    </p>
                                    ${entry.watch_status === 'watched' && rating > 0 ? `<p class="star-rating">${'★'.repeat(stars)}${'☆'.repeat(5 - stars)} (${rating}/100)</p>` : '<p class="star-rating"> </p>'}
                                </div>
                            </div>
                        `;
                        grid.appendChild(div);
                    });
                    offset += data.length;
                    loading = false;
                    if (data.length < 10) {
                        loader.classList.add('hidden');
                        console.log('No more data to load');
                    }
                })
                .catch(error => {
                    console.error('Error loading more movies:', error);
                    loading = false;
                    loader.classList.add('hidden');
                });
        }

        const observer = new IntersectionObserver(entries => {
            const entry = entries[0];
            console.log('Observer triggered:', entry.isIntersecting);
            if (entry.isIntersecting && !loading) {
                loadMoreMovies();
            }
        }, { threshold: 0.1, rootMargin: '1500px' });

        console.log('Observing loader element');
        const loader = document.getElementById('loader');
        if (!loader) {
            console.error('Loader element not found!');
        } else {
            observer.observe(loader);
        }

        window.addEventListener('load', () => {
            if (!loader) return;
            const rect = loader.getBoundingClientRect();
            if (rect.top <= window.innerHeight && rect.bottom >= 0 && !loading) {
                console.log('Loader in view on load, triggering loadMoreMovies');
                loadMoreMovies();
            }
        });

        window.addEventListener('scroll', () => {
            if (!loader) return;
            const rect = loader.getBoundingClientRect();
            if (rect.top <= window.innerHeight && rect.bottom >= 0 && !loading) {
                console.log('Loader in view on scroll, triggering loadMoreMovies');
                loadMoreMovies();
            }
        });
    </script>
</body>
</html>