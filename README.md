# dbfilms.diesel.baby

A PHP-based movie database website hosted on a linux VPS using nginx/cloudpanel, using SQLite (`dbfilms.db`) to store movie data imported from `watcharr_export.json` (converted from Watcharr CSV via the customized `transform.py`). The site displays movies with TMDB-sourced posters and backdrops, supports sorting, filtering, searching, and enhanced styling, aiming for a user-friendly, feature-rich experience.

## Current State (as of April 27, 2025)

### Database Schema
- **`entries` table**:
  ```sql
  CREATE TABLE entries (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      tmdb_id TEXT NOT NULL UNIQUE,
      type TEXT NOT NULL CHECK(type IN ('movie', 'tv')),
      title TEXT NOT NULL,
      poster_path TEXT,
      backdrop_path TEXT,
      overview TEXT,
      release_date TEXT,
      genres TEXT,
      actors TEXT,
      director TEXT,
      tagline TEXT,
      certification TEXT,
      runtime INTEGER,
      budget INTEGER,
      revenue INTEGER,
      imdb_id TEXT,
      content_status TEXT,
      popularity REAL,
      vote_average REAL,
      vote_count INTEGER,
      original_language TEXT,
      rating INTEGER CHECK(rating >= 0 AND rating <= 100),
      notes TEXT,
      watch_status TEXT CHECK(watch_status IN ('watched', 'watching', 'plan_to_watch', NULL)),
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );```

- **Related tables**: `tags`, `entry_tags` for tags (e.g., `B-Movie Schlock`).
- **Image paths**: `/data/img/filename.jpg` (posters), `/data/img/backdrops/filename.jpg` (backdrops).
- **Entries**: ~670 in `dbfilms.db`.

### Key Files
- **`index.php`**: Homepage with a grid of 10 movies, sorting (`title`, `release_date`, `updated_at`, `rating`), filtering by `watch_status` (`watched`, `watching`, `plan_to_watch`, `NULL`), search by title, and star ratings (0-100 to 0-5 stars). Uses Bulma CSS.
- **`api.php`**: JSON API for infinite scroll, supporting `sort`, `status`, `search`, `offset` parameters.
- **`templates/entry.php`**: Movie detail page with poster, backdrop, large title, overview, rating (gradient/sparkle for >90/100), genres, actors, director, release date (`June 14, 2002`), tags (via `GROUP_CONCAT`), and bold labels.
- **`import.php`**: Imports `watcharr_export.json` into `dbfilms.db`, downloading images.
- **`tmapi`**: Bash script for TMDB API queries (e.g., `tmapi --append images 2501`).
- **`.gitignore`**: Excludes `vendor/`, `.env`, `dbfilms.db`, `data/img/`, `logs/`, `transform.py`, `update_json.py`, `validate_json.py`.
- **Python scripts** (`transform.py`, `update_json.py`, `validate_json.py`): Local-only, not in GitHub. `transform.py` converts Watcharr CSV to JSON for cleaner import.

### Accomplishments
- Fixed image path issues (removed duplicate `/data/img/` prefixes).
- Added sorting, filtering, search, and star ratings to `index.php`.
- Enhanced `entry.php` with formatted release date, larger title, prominent rating, bold labels, and tags.
- Downloaded TMDB images for movies (e.g., *The Bourne Identity* `2501`, *Pulp Fiction* `680`).
- Pushed updates to GitHub, excluding `vendor/` and Python scripts.

### Outstanding Issue
- **Infinite Scroll**: Not working on `index.php`. `#loader` div remains `hidden`, and IntersectionObserver doesn’t trigger. No console errors; `api.php` returns valid data. Debug logs added to track `#loader` position and observer activity.

### Environment
- **Server**: Linux, nginx, PHP-FPM, PHP 8.2, PHP-TMDB, Python, SQLite.
- **Paths**: `/home/dbfilms/htdocs/dbfilms.diesel.baby` (web root), `/home/dbfilms/logs/` (logs).
- **Images**: `/home/dbfilms/htdocs/dbfilms.diesel.baby/data/img/`, `/data/img/backdrops/`.
- **GitHub**: Repository being updated at dieselbabyy/dbfilms.

## Updated Game Plan
1. **Fix Infinite Scroll**:
   - Use console logs to verify `#loader` position (`top`, `bottom`, `window.innerHeight`).
   - Add spacer div or adjust CSS to ensure `#loader` enters viewport.
   - Tweak IntersectionObserver (`threshold`, `rootMargin`) or rely on scroll listener.
   - Test manual `loadMoreMovies` to confirm API and DOM updates.

2. **Enhance Search**:
   - Already implemented in `index.php` (title search). Expand to search by `genres`, `actors`, or `director` if needed.

3. **Discovery Function**:
   - Use `php-tmdb` wrapper to fetch similar movies/TV shows from TMDB API.
   - Display recommendations on `entry.php` (e.g., "Similar to *The Bourne Identity*").
   - Reference TMDB’s similar movies endpoint (`/movie/{tmdb_id}/similar`).

4. **Web-Based Form/UI**:
   - Create `add_entry.php` for searching TMDB, adding entries to `dbfilms.db`, and editing tags/ratings.
   - Use `php-tmdb` for search and data retrieval.
   - Include fields for `watch_status`, `rating`, `tags`, and auto-download images.

5. **Improved Styling**:
   - Apply layout based on user-provided screenshots.
   - Enhance `index.php` (card spacing, hover effects) and `entry.php` (gradient/sparkle tweaks, layout).

6. **Automatic Trailer Videos**:
   - Use TMDB’s videos endpoint (`/movie/{tmdb_id}/videos`) to embed YouTube trailers on `entry.php`.
   - Implement with `<iframe>` or Video.js player.

7. **Collections Pages**:
   - Create `collections.php` (e.g., `/collection/b-movie-schlock`) to list movies by tag, similar to `index.php` grid.
   - Use `entry_tags` and `tags` tables for filtering.

8. **Genre Pages**:
   - Create `genre.php` (e.g., `/genre/action`, `/genre/comedy`) to show:
     - Database entries with matching genres (`watched`, `watching`, `plan_to_watch`).
     - Random TMDB entries for that genre using `php-tmdb` (`/discover/movie?with_genres=<id>`).
   - Dynamically generate based on TMDB genre IDs.

9. **Individual Entry Pages**:
   - Implement URL schema: `/movies/2501-the-bourne-identity`, `/tv/123-series-name`.
   - Use Nginx rewrite rules to map `/movies/{id}-{slug}` to `entry.php?id={id}`.
   - Generate slugs from `title` during `import.php` or web UI updates.
   - Store slugs in `entries` table (new column `slug`).
   - Regenerate pages automatically via web UI updates.
   - Convert existing ~670 entries last, after finalizing design and features.

10. **Optimize Database**:
    - Normalize `poster_path`, `backdrop_path` to filenames only.
    - Update `import.php`, `entry.php`, `index.php` accordingly.

11. **Maintenance**:
    - Regular GitHub pushes (core files only, exclude `vendor/`, Python scripts).
    - Use `tmapi` for TMDB data verification.
    - Monitor logs (`/home/dbfilms/logs/php/error.log`, `/home/dbfilms/logs/nginx/error.log`).
  
