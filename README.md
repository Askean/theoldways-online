# Old Ways, One A Day — static site

One page, no framework, no database, no third-party services. Built from the same series data the
clips use, so the habit list on the site can never drift from the videos.

## Files

| File | Role |
|---|---|
| `index.html` | the page — generated, do not hand-edit (see below) |
| `products/build_media.py` | encodes the 30 web clips (720×1280 CRF 26, faststart), posters and habit stills |
| `products/build_site.py` | builds `index.html` from `out/clips/index.json` + `scripts/series_oldways.json` |
| `assets/style.css` | all the CSS, hand-written, tight and image-led |
| `video/*.mp4` | the 30 clips, playable on-site (no external host, keeps visitors here) |
| `assets/img/habits/*.jpg` | one still per habit, for the 30-card grid |
| `assets/img/posters/*.jpg` | one poster frame per clip, for the playlist thumbnails |
| `assets/img/col_*.jpg` | the collage strip under the hero |
| `subscribe.php` | email capture: validates, honeypot, appends to `data/subscribers.csv` |
| `thank-you.html` | confirmation page (reads `?state=` for the honest edge cases) |
| `starter.pdf` | the free five-day starter offered by the signup forms |
| `.htaccess` | HTTPS + clean URLs + deny access to `data/` |

## Rebuild

```bash
python products/build_media.py     # web clips, posters, stills (skips what exists)
python products/build_site.py      # rewrites index.html + the collage/cover JPEGs
```

Repo weight is ~35 MB, almost all of it the 30 web clips at ~950 KB each. Videos are cached for a
year by `.htaccess`; the HTML never is.

## Deploy (the house convention)

1. Copy this folder to `~/sitegit/repos/<repo>` (one repo per site, e.g. `oldways-site`).
2. `git init`, commit, add origin `https://github.com/Askean/<repo>.git`, push `main`.
3. In hPanel: add the domain, then **Git** → deploy from the GitHub repo's `main` branch.

Never SFTP into `public_html` for content — the GitHub repo is the single source of truth.

## Notes

- The Gumroad link is in `products/build_site.py` (`GUMROAD`). Change it there, rebuild, push.
- `data/subscribers.csv` is created on first signup and is blocked by `.htaccess`. It is **not** in
  git (see `.gitignore`) — the deployed server owns that file.
- The signup emails the starter through PHP `mail()`, which shared hosting often delivers to spam.
  The address is stored regardless, so nobody is lost if a message bounces.
