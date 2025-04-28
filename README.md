# dbfilms.diesel.baby

A PHP-based movie database website hosted on a Linux server (`helios`), using SQLite (`dbfilms.db`) to store movie data imported from `watcharr_export.json` (converted from Watcharr CSV via `transform.py`). The site displays movies with TMDB-sourced posters and backdrops, supports sorting, filtering, searching, and enhanced styling, aiming for a user-friendly, feature-rich experience.

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

  
