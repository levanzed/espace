# ESPACE API deployment notes

## Architecture (single Moodle service)

ESPACE uses **one** Moodle external service and **one** per-user token:

| Item | Value |
|------|--------|
| Service name | **ESPACE** |
| Shortname | `espace` |
| Login | `login/token.php` with `service=espace` |
| Allowlist | `local_espace/db/services.php` + `docs/ESPACE_WS_ALLOWLIST.md` |

There is **no** dual-token setup and **no** dependency on `moodle_mobile_app` for the ESPACE app.

## Environment variables

| Variable | Required | Purpose |
|----------|----------|---------|
| `MOODLE_URL` | Yes | Moodle site base URL (no trailing slash) |
| `MOODLE_TOKEN` | Optional | Fallback site token (must be an **ESPACE**-service token if set) |
| `JWT_SECRET_KEY` | Yes | Signs ESPACE JWTs |

```env
MOODLE_URL=https://moodle.example.com
MOODLE_TOKEN=
JWT_SECRET_KEY=change-me
```

Do not commit `.env` or real tokens. Remove any leftover `MOODLE_ESPACE_TOKEN` from older experiments.

## Deploy / upgrade

1. Copy/sync `local/espace/` (plugin ≥ **1.1.5**).
2. **Site administration → Notifications** — upgrade `local_espace`.
3. **External services → ESPACE**
   - Enabled
   - Shortname `espace`
   - Functions match `docs/ESPACE_WS_ALLOWLIST.md`
4. Deploy FastAPI (`auth` uses `service=espace`).
5. **Users must log in again** (old Mobile-service JWTs will fail WS calls).

```bash
cd /opt/containers/espace   # or your compose path
docker compose up -d --build espace-api
```

## Verification (Sprint A slice)

- [ ] Login succeeds with ESPACE service
- [ ] Course list and course contents load
- [ ] Section create / rename / move / hide / show / delete
- [ ] Create Assignment (Activities API)
- [ ] Intro attachment upload (`core_files_upload`)
- [ ] Edit Assignment / save
- [ ] Module hide / show / delete via courseformat

## Growing the allowlist

When enabling grades, forum, quiz, etc., add the required official WS to the ESPACE service allowlist (see `docs/ESPACE_WS_ALLOWLIST.md`) — do not switch back to Mobile or dual tokens.
