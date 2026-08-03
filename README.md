# Card-o-Bot

Standalone trading-card creator: Cardy Chat v2 (OpenAI) paints character art, you optionally draw on editable layers, then save and download a framed card. Collection gallery lists every card for view + re-download.

## Stack

- **PHP 8.2** Apache app (`public/`)
- **MySQL** (`database/schema.sql`)
- **OpenAI** chat (`/v1/responses`) + images (`/v1/images/generations`)
- **Optional ML sidecar** (`ml-service/`) — FastAPI + PyTorch sentence-transformers

## Quick local PHP smoke

```bash
cd cardobot-app
cp .env.example .env
# fill OPENAI_API_KEY + MySQL creds
php -S localhost:8080 -t public
```

Apply schema:

```bash
mysql -u USER -p DB < database/schema.sql
```

## Docker (web)

```bash
docker build -t cardobot .
docker run --rm -p 8080:8080 --env-file .env -e PORT=8080 -e UPLOAD_ROOT=/data/uploads -v cardobot_uploads:/data/uploads cardobot
```

## ML sidecar (optional)

```bash
cd ml-service
docker build -t cardobot-ml .
docker run --rm -p 8000:8000 -e ML_SERVICE_TOKEN=dev -e PORT=8000 -v cardobot_ml:/data/ml cardobot-ml
```

In web `.env`:

```env
ML_SERVICE_URL=http://host.docker.internal:8000
ML_SERVICE_TOKEN=dev
```

If unset, chat/paint/draw still work; memory hints and local safety no-op.

## Railway deploy

1. Create a GitHub repo from this folder and connect Railway.
2. **Web service:** Dockerfile at repo root. Root directory = repo root. Set `PORT` automatically.
3. Add **MySQL** plugin. Map:
   - `CARDOBOT_DB_HOST` ← `MYSQLHOST`
   - `CARDOBOT_DB_PORT` ← `MYSQLPORT`
   - `CARDOBOT_DB_NAME` ← `MYSQLDATABASE`
   - `CARDOBOT_DB_USER` ← `MYSQLUSER`
   - `CARDOBOT_DB_PASS` ← `MYSQLPASSWORD`
4. Volume mount `/data` for uploads (`UPLOAD_ROOT=/data/uploads`).
5. Set `APP_URL=https://YOUR-SERVICE.up.railway.app`, `OPENAI_API_KEY`, optional Google OAuth + admin vars.
6. Run schema once (Railway MySQL shell or a one-off).
7. Optional second service from `ml-service/Dockerfile`; set `ML_SERVICE_URL` to the private URL and shared `ML_SERVICE_TOKEN`.

### Pointing cardobot.com at Railway

**Temporary:** On Bluehost, redirect `cardobot.com` / `www` to the Railway URL (301).

**Cutover:**

1. Add custom domain in Railway.
2. Set DNS CNAME (or A/AAAA per Railway docs) for `cardobot.com` / `www`.
3. Set `APP_URL=https://cardobot.com`.
4. Google Cloud OAuth: add origins + redirect `https://cardobot.com/api/google-callback.php` (and www).
5. Keep Bluehost tree as archive; do not dual-maintain.

## Product flow

1. Login (password or Google).
2. Cardy Chat v2 inside console chrome: gather → **confirm** → paint.
3. Reveal: **Save card** (no drawing required), **Draw on it** (optional layers), **Download PNG**, or revise.
4. Profile → My Collection: view, download, delete all saved cards.

## Key paths

| Path | Role |
|---|---|
| `public/index.php` | Hybrid console + chat + studio |
| `public/api/chat.php` | Chat v2 |
| `public/api/render-image.php` | Async image gen |
| `public/api/export-card.php` | Save framed PNG |
| `public/api/download-card.php` | View/download |
| `public/assets/js/drawing-engine.js` | Editable layers |
| `public/assets/js/card-studio.js` | Frame composite |
| `ml-service/` | Embeddings sidecar |

## Env reference

See [`.env.example`](.env.example). Never commit real secrets.
