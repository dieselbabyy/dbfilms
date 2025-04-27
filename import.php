<?php
require 'vendor/autoload.php';

// Debug log file
$debug_log = '/home/dbfilms/htdocs/dbfilms.diesel.baby/import_debug.log';
function log_debug($message, $is_error = false) {
    global $debug_log;
    $prefix = $is_error ? '[ERROR] ' : '';
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - $prefix$message\n", FILE_APPEND);
}

// Connect to database
try {
    $db = new PDO('sqlite:/home/dbfilms/htdocs/dbfilms.diesel.baby/data/dbfilms.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    log_debug("Connected to database.");
} catch (Exception $e) {
    log_debug("Database connection failed: " . $e->getMessage(), true);
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Ensure tables exist
$db->exec("CREATE TABLE IF NOT EXISTS entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tmdb_id TEXT NOT NULL UNIQUE,
    type TEXT NOT NULL CHECK(type IN ('movie', 'tv')),
    title TEXT NOT NULL,
    poster_url TEXT,
    rating INTEGER CHECK(rating >= 0 AND rating <= 100),
    notes TEXT,
    watch_status TEXT CHECK(watch_status IN ('watched', 'watching', 'plan_to_watch', NULL)),
    release_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS entry_tags (
    entry_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (entry_id, tag_id),
    FOREIGN KEY (entry_id) REFERENCES entries(id),
    FOREIGN KEY (tag_id) REFERENCES tags(id)
)");
log_debug("Tables ensured.");

// Clear existing data
$db->exec("DELETE FROM entries");
$db->exec("DELETE FROM tags");
$db->exec("DELETE FROM entry_tags");
log_debug("Cleared existing data.");

// Read SQL dump
$dump_file = '/home/dbfilms/htdocs/dbfilms.diesel.baby/data/watcharr_dump.sql';
if (!file_exists($dump_file)) {
    log_debug("Error: watcharr_dump.sql not found.", true);
    die("Error: watcharr_dump.sql not found.\n");
}
$dump = file_get_contents($dump_file);
$lines = preg_split('/;\s*(?:\n|$)/', $dump, -1, PREG_SPLIT_NO_EMPTY);
log_debug("Read " . count($lines) . " lines from watcharr_dump.sql.");

// Temporary storage
$contents = []; // id => [tmdb_id, title, type, poster_path, release_date]
$watcheds = [];
$tags = [];
$watched_tags = [];
$watched_seasons = [];
$errors = [];
$debug_ids = ['6', '557', '539'];
$watcheds_raw_count = 0;
$tags_raw_count = 0;

// Parse INSERT statements
foreach ($lines as $line_num => $line) {
    $line = trim($line);
    if (!preg_match('/^INSERT\s+INTO\s+`?(\w+)`?\s+VALUES\s*(.*)/i', $line, $matches)) {
        log_debug("Skipped line $line_num: No INSERT match - " . substr($line, 0, 50));
        continue;
    }
    $table = strtolower($matches[1]);
    $values = $matches[2];

    log_debug("Found INSERT for table $table at line $line_num");

    // Split values into rows
    $rows = [];
    $current_row = '';
    $in_quotes = false;
    $paren_count = 0;
    for ($i = 0; $i < strlen($values); $i++) {
        $char = $values[$i];
        if ($char === "'" && ($i === 0 || $values[$i - 1] !== '\\')) {
            $in_quotes = !$in_quotes;
        }
        if (!$in_quotes) {
            if ($char === '(') {
                $paren_count++;
            } elseif ($char === ')') {
                $paren_count--;
            }
        }
        $current_row .= $char;
        if ($paren_count === 0 && !$in_quotes && $char === ')') {
            $rows[] = trim($current_row, '()');
            $current_row = '';
        }
    }

    log_debug("Processing $table: " . count($rows) . " rows");
    $successful_rows = 0;
    foreach ($rows as $row_num => $row) {
        try {
            // Preprocess row to handle complex fields
            $row = str_replace("\\'", "''", $row);
            $row = preg_replace("/replace\('(.*?)','\\\n',char\(10\)\)/", '$1', $row);
            // Escape commas outside quotes
            $clean_row = '';
            $in_quotes = false;
            for ($i = 0; $i < strlen($row); $i++) {
                if ($row[$i] === "'" && ($i === 0 || $row[$i - 1] !== '\\')) {
                    $in_quotes = !$in_quotes;
                }
                if ($row[$i] === ',' && !$in_quotes) {
                    $clean_row .= '\,';
                } else {
                    $clean_row .= $row[$i];
                }
            }
            $row_data = str_getcsv($clean_row, ',', "'");

            $row_id = $row_data[0] ?? 'unknown';
            // Log raw row for debug IDs, errors, or first 5 watcheds/tags
            if (in_array($row_id, $debug_ids) || ($table === 'watcheds' && $watcheds_raw_count < 5) || ($table === 'tags' && $tags_raw_count < 5)) {
                log_debug("Raw row $row_num in $table (id $row_id): " . substr(implode(',', $row_data), 0, 100) . "...");
                if ($table === 'watcheds') $watcheds_raw_count++;
                if ($table === 'tags') $tags_raw_count++;
            }

            switch ($table) {
                case 'contents':
                    if (count($row_data) < 8) {
                        $errors[] = "Invalid contents row at line $line_num, row $row_num, id $row_id: " . implode(',', $row_data);
                        log_debug("Invalid contents row at line $line_num, row $row_num, id $row_id: " . implode(',', $row_data), true);
                        continue 2;
                    }
                    $contents[$row_data[0]] = [
                        'tmdb_id' => $row_data[1] ?? '',
                        'title' => $row_data[2] ?? 'Unknown Title',
                        'type' => $row_data[4] === 'MOVIE' ? 'movie' : 'tv',
                        'poster_path' => $row_data[5] !== 'NULL' ? $row_data[5] : null,
                        'release_date' => $row_data[7] !== 'NULL' ? $row_data[7] : null
                    ];
                    log_debug("Parsed contents id {$row_data[0]}, tmdb_id {$row_data[1]}");
                    $successful_rows++;
                    break;
                case 'watcheds':
                    if (count($row_data) < 6) {
                        $errors[] = "Invalid watcheds row at line $line_num, row $row_num, id $row_id (columns: " . count($row_data) . "): " . implode(',', $row_data);
                        log_debug("Invalid watcheds row at line $line_num, row $row_num, id $row_id (columns: " . count($row_data) . "): " . implode(',', $row_data), true);
                        continue 2;
                    }
                    if (!isset($row_data[9])) {
                        $errors[] = "Missing content_id for watcheds id $row_id at line $line_num, row $row_num";
                        log_debug("Missing content_id for watcheds id $row_id at line $line_num, row $row_num", true);
                        continue 2;
                    }
                    if ($row_data[3] !== 'NULL' && $row_data[3] !== '') {
                        log_debug("Skipped watcheds id $row_id: deleted_at is {$row_data[3]}");
                        continue 2;
                    }
                    $watcheds[] = [
                        'id' => $row_data[0] ?? '',
                        'content_id' => $row_data[9] ?? '',
                        'rating' => isset($row_data[5]) && $row_data[5] !== 'NULL' ? (int)($row_data[5] * 10) : null,
                        'status' => isset($row_data[4]) && $row_data[4] !== 'NULL' ? $row_data[4] : null,
                        'thoughts' => isset($row_data[6]) && $row_data[6] !== 'NULL' ? ($row_data[6] ?? '') : null,
                        'created_at' => isset($row_data[1]) && $row_data[1] !== 'NULL' ? $row_data[1] : null,
                        'updated_at' => isset($row_data[2]) && $row_data[2] !== 'NULL' ? $row_data[2] : null
                    ];
                    log_debug("Parsed watcheds id {$row_data[0]}, content_id {$row_data[9]}, status " . (isset($row_data[4]) ? $row_data[4] : 'NULL'));
                    $successful_rows++;
                    break;
                case 'tags':
                    if (count($row_data) < 5) {
                        $errors[] = "Invalid tags row at line $line_num, row $row_num, id $row_id (columns: " . count($row_data) . "): " . implode(',', $row_data);
                        log_debug("Invalid tags row at line $line_num, row $row_num, id $row_id (columns: " . count($row_data) . "): " . implode(',', $row_data), true);
                        continue 2;
                    }
                    if ($row_data[3] !== 'NULL' && $row_data[3] !== '') {
                        log_debug("Skipped tags id $row_id: deleted_at is {$row_data[3]}");
                        continue 2;
                    }
                    $tags[$row_data[0]] = $row_data[5] ?? '';
                    log_debug("Parsed tag id {$row_data[0]}, name " . ($row_data[5] ?? 'NULL'));
                    $successful_rows++;
                    break;
                case 'watched_tags':
                    if (count($row_data) < 2) {
                        $errors[] = "Invalid watched_tags row at line $line_num, row $row_num: " . implode(',', $row_data);
                        log_debug("Invalid watched_tags row at line $line_num, row $row_num: " . implode(',', $row_data), true);
                        continue 2;
                    }
                    $watched_tags[] = [
                        'tag_id' => $row_data[0] ?? '',
                        'watched_id' => $row_data[1] ?? ''
                    ];
                    log_debug("Parsed watched_tags tag_id {$row_data[0]}, watched_id {$row_data[1]}");
                    $successful_rows++;
                    break;
                case 'watched_seasons':
                    if (count($row_data) < 8) {
                        $errors[] = "Invalid watched_seasons row at line $line_num, row $row_num, id $row_id: " . implode(',', $row_data);
                        log_debug("Invalid watched_seasons row at line $line_num, row $row_num, id $row_id: " . implode(',', $row_data), true);
                        continue 2;
                    }
                    $deleted_at = $row_data[3] ?? 'NULL';
                    log_debug("watched_seasons id {$row_data[0]}, watched_id {$row_data[5]}, status {$row_data[7]}, deleted_at {$deleted_at}");
                    $watched_seasons[] = [
                        'watched_id' => $row_data[5],
                        'status' => $row_data[7] ?? ''
                    ];
                    log_debug("Parsed watched_seasons watched_id {$row_data[5]}, status {$row_data[7]}");
                    $successful_rows++;
                    break;
                default:
                    log_debug("Unknown table $table at line $line_num");
                    continue 2;
            }
        } catch (Exception $e) {
            $errors[] = "Error parsing row in $table at line $line_num, row $row_num, id $row_id: " . $e->getMessage();
            log_debug("Error parsing row in $table at line $line_num, row $row_num, id $row_id: " . $e->getMessage(), true);
        }
    }
    log_debug("Processed $table: $successful_rows successful, " . (count($rows) - $successful_rows) . " failed");
}

log_debug("Parsed: " . count($contents) . " contents, " . count($watcheds) . " watcheds, " . count($tags) . " tags, " . count($watched_tags) . " watched_tags, " . count($watched_seasons) . " watched_seasons entries.");

// Start transaction
try {
    $db->beginTransaction();

    // Import entries
    $entry_stmt = $db->prepare("INSERT OR IGNORE INTO entries (tmdb_id, type, title, poster_url, rating, notes, watch_status, release_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $inserted_entries = 0;
    foreach ($watcheds as $watched) {
        try {
            $content_id = $watched['content_id'];
            if (!isset($contents[$content_id])) {
                $errors[] = "Missing content_id {$content_id} for watched_id {$watched['id']}";
                log_debug("Missing content_id {$content_id} for watched_id {$watched['id']}", true);
                continue;
            }

            $content = $contents[$content_id];
            $status = null;
            if ($content['type'] === 'movie') {
                $status = match (strtolower($watched['status'] ?? '')) {
                    'finished' => 'watched',
                    'watching' => 'watching',
                    'planned' => 'plan_to_watch',
                    default => null
                };
            } else {
                $seasons = array_filter($watched_seasons, fn($s) => $s['watched_id'] == $watched['id']);
                if ($seasons) {
                    $unique_statuses = array_unique(array_column($seasons, 'status'));
                    if (count($unique_statuses) == 1 && $unique_statuses[0] === 'FINISHED') {
                        $status = 'watched';
                    } elseif (in_array('WATCHING', $unique_statuses)) {
                        $status = 'watching';
                    } elseif (in_array('PLANNED', $unique_statuses)) {
                        $status = 'plan_to_watch';
                    }
                }
                if (!$status) {
                    $status = match (strtolower($watched['status'] ?? '')) {
                        'finished' => 'watched',
                        'watching' => 'watching',
                        'planned' => 'plan_to_watch',
                        default => null
                    };
                }
            }

            $entry_stmt->execute([
                (string)$content['tmdb_id'],
                $content['type'],
                $content['title'],
                $content['poster_path'] ? "https://image.tmdb.org/t/p/w500/{$content['poster_path']}" : null,
                $watched['rating'],
                $watched['thoughts'],
                $status,
                $content['release_date'],
                $watched['created_at'],
                $watched['updated_at']
            ]);
            log_debug("Inserted entry for watched_id {$watched['id']}, tmdb_id {$content['tmdb_id']}");
            $inserted_entries++;
        } catch (Exception $e) {
            $errors[] = "Error inserting entry for watched_id {$watched['id']}: " . $e->getMessage();
            log_debug("Error inserting entry for watched_id {$watched['id']}: " . $e->getMessage(), true);
        }
    }

    // Import tags
    $tag_stmt = $db->prepare("INSERT OR IGNORE INTO tags (id, name) VALUES (?, ?)");
    $inserted_tags = 0;
    foreach ($tags as $id => $name) {
        try {
            $tag_stmt->execute([$id, $name ?: 'Unnamed Tag']);
            log_debug("Inserted tag id {$id}, name {$name}");
            $inserted_tags++;
        } catch (Exception $e) {
            $errors[] = "Error inserting tag {$name}: " . $e->getMessage();
            log_debug("Error inserting tag {$name}: " . $e->getMessage(), true);
        }
    }

    // Import entry_tags
    $entry_tag_stmt = $db->prepare("INSERT OR IGNORE INTO entry_tags (entry_id, tag_id) VALUES (?, ?)");
    $inserted_entry_tags = 0;
    foreach ($watched_tags as $wt) {
        try {
            $watched_id = $wt['watched_id'];
            $tag_id = $wt['tag_id'];

            $content_id = null;
            foreach ($watcheds as $w) {
                if ($w['id'] == $watched_id) {
                    $content_id = $w['content_id'];
                    break;
                }
            }

            if (!$content_id || !isset($contents[$content_id])) {
                $errors[] = "Skipping entry_tag for watched_id {$watched_id}, tag_id {$tag_id}: Content not found";
                log_debug("Skipping entry_tag for watched_id {$watched_id}, tag_id {$tag_id}: Content not found", true);
                continue;
            }

            $tmdb_id = $contents[$content_id]['tmdb_id'];
            $stmt = $db->prepare("SELECT id FROM entries WHERE tmdb_id = ?");
            $stmt->execute([$tmdb_id]);
            $entry_id = $stmt->fetchColumn();

            if ($entry_id && isset($tags[$tag_id])) {
                $entry_tag_stmt->execute([$entry_id, $tag_id]);
                log_debug("Inserted entry_tag for entry_id {$entry_id}, tag_id {$tag_id}");
                $inserted_entry_tags++;
            } else {
                $errors[] = "Skipping entry_tag for watched_id {$watched_id}, tag_id {$tag_id}: Entry or tag not found";
                log_debug("Skipping entry_tag for watched_id {$watched_id}, tag_id {$tag_id}: Entry or tag not found", true);
            }
        } catch (Exception $e) {
            $errors[] = "Error inserting entry_tag for watched_id {$wt['watched_id']}: " . $e->getMessage();
            log_debug("Error inserting entry_tag for watched_id {$wt['watched_id']}: " . $e->getMessage(), true);
        }
    }

    // Commit transaction
    $db->commit();
    log_debug("Transaction committed.");
} catch (Exception $e) {
    $db->rollBack();
    $errors[] = "Transaction failed: " . $e->getMessage();
    log_debug("Transaction failed: " . $e->getMessage(), true);
}

// Log summary
log_debug("Import Summary:");
log_debug("- Parsed: " . count($contents) . " contents, " . count($watcheds) . " watcheds, " . count($tags) . " tags, " . count($watched_tags) . " watched_tags, " . count($watched_seasons) . " watched_seasons");
log_debug("- Inserted: $inserted_entries entries, $inserted_tags tags, $inserted_entry_tags entry_tags");
log_debug("- Errors: " . count($errors));
if ($errors) {
    log_debug("Top " . min(10, count($errors)) . " errors:");
    foreach (array_slice($errors, 0, 10) as $i => $error) {
        log_debug("  [$i] $error");
    }
}

// Output summary to console
echo "Import Summary:\n";
echo "- Parsed: " . count($contents) . " contents, " . count($watcheds) . " watcheds, " . count($tags) . " tags, " . count($watched_tags) . " watched_tags, " . count($watched_seasons) . " watched_seasons\n";
echo "- Inserted: $inserted_entries entries, $inserted_tags tags, $inserted_entry_tags entry_tags\n";
echo "- Errors: " . count($errors) . "\n";
if ($errors) {
    echo "Check import_debug.log for details.\n";
} else {
    echo "Import completed successfully.\n";
}
