# ArrCal

Unified calendar dashboard for **Radarr** (movies) and **Sonarr** (TV episodes). One page to see what's coming out this week — no more tab-switching between two UIs.

---

## Install

### Docker Hub

```bash
docker pull nipakke/arrcal
```

### Run with Docker

```bash
docker run -d \
  --name arrcal \
  -p 8080:80 \
  -e RADARR_URL=http://host.docker.internal:7878 \
  -e RADARR_API_KEY=your-radarr-key \
  -e SONARR_URL=http://host.docker.internal:8989 \
  -e SONARR_API_KEY=your-sonarr-key \
  nipakke/arrcal
```

### Run with Docker Compose

```yaml
services:
  arrcal:
    image: nipakke/arrcal
    container_name: arrcal
    ports:
      - "8080:80"
    environment:
      - RADARR_URL=http://radarr:7878
      - RADARR_API_KEY=changeme
      - SONARR_URL=http://sonarr:8989
      - SONARR_API_KEY=changeme
      - CACHE_TTL=300
    restart: unless-stopped
```

Add Radarr and Sonarr as sibling services or point to existing instances.

---

## Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `RADARR_URL` | — | Radarr instance URL for API calls (e.g. `http://radarr:7878`) |
| `RADARR_PUBLIC_URL` | `RADARR_URL` | Public URL for clickable links; use when internal != external URL |
| `RADARR_API_KEY` | — | Radarr API key |
| `SONARR_URL` | — | Sonarr instance URL for API calls (e.g. `http://sonarr:8989`) |
| `SONARR_PUBLIC_URL` | `SONARR_URL` | Public URL for clickable links; use when internal != external URL |
| `SONARR_API_KEY` | — | Sonarr API key |
| `CACHE_TTL` | `300` | Server-side cache TTL in seconds |
| `APP_ENV` | `production` | `local` for dev mode |
| `PORT` | `80` | HTTP server port (`8080` for local dev) |
| `TRUST_PROXY` | `disabled` | Proxy trust mode (`auto`, `1`, `cloudflare`, `traefik`) |

### Public vs Private URLs

In containerised or proxied setups, the URL ArrCal uses to reach the *arr API (e.g. `http://radarr:7878`) may differ from the URL users should open in their browser (e.g. `https://radarr.example.com`). Set `{SERVICE}_PUBLIC_URL` when they differ:

```bash
# Internal: ArrCal uses this for API calls
RADARR_URL=http://radarr:7878
# Public: sent to the frontend for clickable links
RADARR_PUBLIC_URL=https://radarr.example.com
```

When `PUBLIC_URL` is not set, it defaults to the internal `URL` — fully backward compatible.

### Multi-Instance

Use numbered variables for multiple Radarr or Sonarr instances. Each also supports an optional `PUBLIC_URL`:

```
RADARR_URL=http://localhost:7878
RADARR_API_KEY=key1
RADARR_2_URL=http://localhost:7879
RADARR_2_API_KEY=key2
RADARR_2_LABEL=4K Movies
RADARR_2_PUBLIC_URL=https://radarr-4k.example.com

SONARR_URL=http://localhost:8989
SONARR_API_KEY=key1
SONARR_2_URL=http://localhost:8990
SONARR_2_API_KEY=key2
SONARR_2_LABEL=Anime
SONARR_2_PUBLIC_URL=https://sonarr-anime.example.com
```

---

## Development

```bash
pnpm install
pnpm dev            # PHP server + Vite HMR, concurrently
```

Open **http://localhost:5173** — Vite proxies `/api/*` to PHP.

### Radarr + Sonarr (dev dependencies)

```bash
docker compose -f docker-compose.dev.yml up -d
```

Radarr on `:7878`, Sonarr on `:8989`. Configure API keys in `.env` (see `.env.example`).

### Other commands

| Command | What it does |
|---------|-------------|
| `pnpm build:frontend` | Build SPA, copy to `public/` |
| `pnpm --filter arrcal-frontend build` | Just the frontend |
| `vendor/bin/pest` | PHP tests |

---

## API

```
GET /api/calendar?month=2026-06
```

Returns a 42-cell monthly calendar grid with entries from all configured Radarr and Sonarr instances, merged and sorted by date. Response shape:

```json
{
  "calendar": [{ "date": "2026-06-07", "day": 7, "isCurrentMonth": true, "entries": [...] }],
  "currentMonth": "2026-06",
  "prevMonth": "2026-05",
  "nextMonth": "2026-07",
  "monthName": "June 2026"
}
```

Each entry has a semantic `status` field (`downloaded`, `missing`, `upcoming`, `unmonitored`, `error`) — **no CSS classes**. The frontend maps status to daisyUI badge styles.

---

## License

MIT © 2026

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
