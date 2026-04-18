# Visionary API

Thanks for checking out the Visionary API! If you use it to create a project, please let us know, we'd love to try it!

Visionary exposes a simple JSON API via `api.php`. Use the `action` parameter to call specific operations, and send JSON payloads for POST requests.

## Base endpoint

`POST api.php?action=<action>`
`GET api.php?action=<action>&param=value`

- Responses are JSON.
- Successful responses include `success: true` and usually `data`.
- Errors include `success: false` and `error`.
- Non-GET requests require the `X-CSRF-Token` header. The site frontend injects this automatically.

## Common usage

### Fetch current user

`GET api.php?action=current_user`

Example:

```js
const res = await fetch('api.php?action=current_user', { credentials: 'same-origin' });
const payload = await res.json();
console.log(payload.data);
```

### List ideas

`GET api.php?action=list&sort=date&limit=20&offset=0`

Query parameters:
- `sort`: `date`, `likes`, `messages`
- `limit`: page size
- `offset`: pagination offset

### Get a single idea

`GET api.php?action=idea&id=123`

### Create an idea

`POST api.php?action=create`

Request body:

```json
{
  "title": "My new project idea",
  "description": "This app will ...",
  "tags": "web,ai"
}
```

### Post a chat message on an idea

`POST api.php?action=post_message`

Request body:

```json
{
  "idea_id": 123,
  "message": "Looks great!",
  "attachment_url": "uploads/example.png"
}
```

### Upload a file

`POST api.php?action=upload`

Form data:
- `file`: file upload field

Response includes an `url` that can be used as `attachment_url` when posting chat messages.

### Like or unlike an idea

`POST api.php?action=like_idea`
`POST api.php?action=unlike_idea`

Request body:

```json
{ "idea_id": 123 }
```

### Follow a user

`POST api.php?action=follow`

Request body:

```json
{ "user_id": 42 }
```

### Notifications

- `GET api.php?action=notifications_list`
- `GET api.php?action=notifications_count`
- `POST api.php?action=notifications_mark_read` with `{ "id": 42 }`
- `POST api.php?action=notifications_mark_all_read`

### Profile

`GET api.php?action=profile&username=Alice`

### Site stats

`GET api.php?action=site_stats`

### Leaderboard (WIP)

`GET api.php?action=leaderboard&period=week`

Supported periods: `week`, `month`, `alltime`.

### Developer workflow

- Claim an idea: `POST api.php?action=claim`
- Complete an idea: `POST api.php?action=complete`

Request body:

```json
{ "id": 123 }
```

### Direct messages

- `POST api.php?action=dm_send` to message another user
- `POST api.php?action=admin_dm_send` for admin messaging

Payload:

```json
{
  "to_user_id": 42,
  "message": "Hi there!"
}
```

## Notes

- The frontend already handles CSRF token injection for fetch requests.
- The `upload` endpoint accepts common image files, text, and PDF uploads.
