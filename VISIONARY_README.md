# Visionary — Minimal PHP Ideas Board

A tiny web app built with HTML, CSS, JS and PHP that lets people post ideas and developers claim or complete them. Uses SQLite for storage so there's no extra DB to configure.

Files added:
- `index.php` — Frontend UI
- `api.php` — Simple JSON API (list, create, claim, complete)
- `init_db.php` — Create DB and insert sample ideas
- `css/style.css` — Styles
- `js/app.js` — Frontend logic
- `data/visionary.db` — created at runtime

API endpoints (JSON):
- `GET api.php?action=list` — returns all ideas
- `POST api.php?action=create` — body JSON `{title, description, author_name}`
- `POST api.php?action=claim` — body JSON `{id, developer_name}`
- `POST api.php?action=complete` — body JSON `{id}`

New auth endpoints/pages:
- `signup.php` — create account (choose role: poster / dev / both)
- `login.php` — username/password login or GitHub OAuth
- `logout.php` — log out
- `github_callback.php` — GitHub OAuth handler (configure `GITHUB_CLIENT_ID` and `GITHUB_CLIENT_SECRET`)

GitHub OAuth setup:
1. Create an OAuth app at https://github.com/settings/developers with the Authorization callback URL set to `http://localhost:8000/github_callback.php` (adjust if you use a different host/port).
2. Set environment variables `GITHUB_CLIENT_ID` and `GITHUB_CLIENT_SECRET`, or edit `config.php` directly.

Notes:
- The app auto-creates `data/visionary.db` when first used.
- This is intentionally minimal — let me know if you want user accounts, comments, or email notifications.
