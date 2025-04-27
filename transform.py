import pandas as pd
import json
import requests
import time
import os

# the old way
# TMDB API setup
# TMDB_TOKEN = 'token goes here durr'
# TMDB_BASE_URL = 'https://api.themoviedb.org/3'
# HEADERS = {'Authorization': f'Bearer {TMDB_TOKEN}'}

# the new way
from decouple import config
TMDB_TOKEN = config('TMDB_TOKEN')
TMDB_BASE_URL = 'https://api.themoviedb.org/3'
HEADERS = {'Authorization': f'Bearer {TMDB_TOKEN}'}

# Backdrop download setup
BACKDROP_DIR = '/home/dbfilms/htdocs/dbfilms.diesel.baby/data/img/backdrops'
os.makedirs(BACKDROP_DIR, exist_ok=True)

def download_backdrop(backdrop_path):
    if not backdrop_path:
        return None
    filename = backdrop_path.split('/')[-1]
    local_path = os.path.join(BACKDROP_DIR, filename)
    if not os.path.exists(local_path):
        try:
            url = f"https://image.tmdb.org/t/p/w1280{backdrop_path}"
            response = requests.get(url)
            response.raise_for_status()
            with open(local_path, 'wb') as f:
                f.write(response.content)
            print(f"Downloaded backdrop: {filename}")
        except requests.RequestException as e:
            print(f"Error downloading backdrop {filename}: {e}")
            return None
    return f"/data/img/backdrops/{filename}"

def fetch_tmdb_data(tmdb_id, media_type):
    try:
        # Fetch main details and credits
        url = f"{TMDB_BASE_URL}/{media_type}/{tmdb_id}?append_to_response=credits,release_dates,content_ratings"
        response = requests.get(url, headers=HEADERS)
        response.raise_for_status()
        data = response.json()
        
        # Actors (top 3)
        actors = [cast['name'] for cast in data.get('credits', {}).get('cast', [])[:3]]
        
        # Genres
        genres = [genre['name'] for genre in data.get('genres', [])]
        
        # Director (movies) or Created By (TV)
        director = None
        if media_type == 'movie':
            for crew in data.get('credits', {}).get('crew', []):
                if crew['job'] == 'Director':
                    director = crew['name']
                    break
        else:
            creators = [creator['name'] for creator in data.get('created_by', [])[:1]]
            director = creators[0] if creators else None
        
        # Certification
        certification = None
        if media_type == 'movie':
            for release in data.get('release_dates', {}).get('results', []):
                if release['iso_3166_1'] == 'US':
                    certification = next((r['certification'] for r in release['release_dates'] if r['certification']), None)
                    break
        else:
            for rating in data.get('content_ratings', {}).get('results', []):
                if rating['iso_3166_1'] == 'US':
                    certification = rating['rating']
                    break
        
        # Download backdrop
        backdrop_path = download_backdrop(data.get('backdrop_path'))
        
        return {
            'actors': ','.join(actors) if actors else None,
            'genres': ','.join(genres) if genres else None,
            'director': director,
            'tagline': data.get('tagline'),
            'backdrop_path': backdrop_path,
            'certification': certification,
            'original_language': data.get('original_language')
        }
    except requests.RequestException as e:
        print(f"TMDB error for {media_type} {tmdb_id}: {e}")
        return {
            'actors': None, 'genres': None, 'director': None, 'tagline': None,
            'backdrop_path': None, 'certification': None, 'original_language': None
        }
    finally:
        time.sleep(0.1)  # Rate limiting

# Load CSVs
contents = pd.read_csv('contents.csv')
watcheds = pd.read_csv('watcheds.csv')
tags = pd.read_csv('tags.csv')
watched_tags = pd.read_csv('watched_tags.csv')
watched_seasons = pd.read_csv('watched_seasons.csv')

# Join watcheds with contents
merged = watcheds.merge(contents, left_on='content_id', right_on='id', suffixes=('_watched', '_content'))

# Map status to watch_status
def map_status(row):
    if row['type'].lower() == 'movie':
        return {
            'FINISHED': 'watched',
            'WATCHING': 'watching',
            'PLANNED': 'plan_to_watch'
        }.get(row['status_watched'], None)
    else:
        seasons = watched_seasons[watched_seasons['watched_id'] == row['id_watched']]
        if not seasons.empty:
            unique_statuses = seasons['status'].unique()
            if len(unique_statuses) == 1 and unique_statuses[0] == 'FINISHED':
                return 'watched'
            elif 'WATCHING' in unique_statuses:
                return 'watching'
            elif 'PLANNED' in unique_statuses:
                return 'plan_to_watch'
        return {
            'FINISHED': 'watched',
            'WATCHING': 'watching',
            'PLANNED': 'plan_to_watch'
        }.get(row['status_watched'], None)

merged['watch_status'] = merged.apply(map_status, axis=1)

# Handle tags
watched_tags = watched_tags.merge(tags, left_on='tag_id', right_on='id', suffixes=('_wt', '_tag'))
tag_groups = watched_tags.groupby('watched_id')['name'].agg(lambda x: ','.join(x)).reset_index()
merged = merged.merge(tag_groups, left_on='id_watched', right_on='watched_id', how='left')
merged['tags'] = merged['name'].fillna('')

# Format poster_path
merged['poster_path'] = merged['poster_path'].apply(lambda x: f"/data/img/{x.split('/')[-1]}" if pd.notnull(x) and x.strip() else None)

# Fetch TMDB data
tmdb_data = []
for _, row in merged.iterrows():
    media_type = 'movie' if row['type'].lower() == 'movie' else 'tv'
    tmdb_info = fetch_tmdb_data(row['tmdb_id'], media_type)
    tmdb_data.append(tmdb_info)

# Add TMDB data to DataFrame
merged['actors'] = [data['actors'] for data in tmdb_data]
merged['genres'] = [data['genres'] for data in tmdb_data]
merged['director'] = [data['director'] for data in tmdb_data]
merged['tagline'] = [data['tagline'] for data in tmdb_data]
merged['backdrop_path'] = [data['backdrop_path'] for data in tmdb_data]
merged['certification'] = [data['certification'] for data in tmdb_data]
merged['original_language'] = [data['original_language'] for data in tmdb_data]

# Select all contents columns (except id), rename contents.status to content_status
contents_columns = [col if col != 'status' else 'content_status' for col in contents.columns if col != 'id']
result_columns = contents_columns + ['watch_status', 'rating', 'thoughts', 'tags', 'actors', 'genres', 'director', 'tagline', 'backdrop_path', 'certification', 'original_language', 'created_at', 'updated_at']
merged = merged.rename(columns={'status_content': 'content_status'})
result = merged[result_columns].copy()
result['rating'] = result['rating'].apply(lambda x: int(x * 10) if pd.notnull(x) else None)
result['type'] = result['type'].apply(lambda x: 'movie' if x.lower() == 'movie' else 'tv')

# Save to JSON
result.to_json('watcharr_export.json', orient='records', lines=True)

print("Exported to watcharr_export.json")
print(f"Rows: {len(result)}")
