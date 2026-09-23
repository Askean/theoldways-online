# Old Ways, One A Day — static site

One page, no framework, no database, no third-party services. Built from the same series data the
clips use, so the habit list on the site can never drift from the videos.

## Files

| File | Role |
|---|---|
| `index.html` | the page — generated, do not hand-edit (see below) |
| `products/build_site.py` | builds `index.html` from `scripts/series_oldways.json` + `products/gumroad_product.json` |
| `assets/style.css` | all the CSS, hand-written |
| `assets/img/*.jpg` | web-sized JPEGs derived from the ComfyUI stills |
| `subscribe.php` | email capture: validates, honeypot, appends to `data/subscribers.csv`, mails the starter |
| `thank-you.html` | confirmation page (reads `?state=` for the honest edge cases) |
| `starter.pdf` | the free five-day starter offered by the signup form |
| `.htaccess` | HTTPS + clean URLs + deny access to `data/` |

## Rebuild

```bash
python products/build_site.py     # rewrites index.html and the image derivatives
```

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
