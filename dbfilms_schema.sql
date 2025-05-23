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
);
CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE entry_tags (
    entry_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (entry_id, tag_id),
    FOREIGN KEY (entry_id) REFERENCES entries(id),
    FOREIGN KEY (tag_id) REFERENCES tags(id)
);
CREATE TABLE admin (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL
);
