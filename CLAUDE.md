# CLAUDE.md

This file provides guidance to Claude Code when working with code in this repository.

## What this project is

A **Minitel terminal emulator** served as a web application. It runs AI-powered Minitel services (assistant, horoscope, confessional, classified ads, jukebox) via the MiniPavi WebSocket gateway, backed by the Anthropic API.

This is a fork of [ludosevilla/minipavi](https://github.com/ludosevilla/minipavi) (GPL v2), with custom additions:
- `services/damien/` — AI-powered Minitel service modules (3615 DAMIEN)
- `emulminitel/kiosk.php` — Fullscreen kiosk emulator with auto-detecting WebSocket URL
- `start-minipavi.sh` — Startup script for Raspberry Pi kiosk deployment
- Web interfaces: EQ controller, Jukebox manager, MPD proxy

## Architecture

```
Browser (Minitel emulator)
  → WebSocket (wss://host/ws)
    → nginx proxy
      → MiniPavi PHP gateway (127.0.0.1:8182, CLI process)
        → HTTP fetch service
          → nginx → PHP-FPM → services/damien/*.php
```

### Components

| Component | Description |
|-----------|-------------|
| `minipavi.php` | WebSocket gateway (CLI, not CGI — needs systemd or equivalent) |
| `emulminitel/` | HTML/JS Minitel emulator (static files served by nginx) |
| `services/damien/` | PHP Minitel modules (served via PHP-FPM) |
| `services/` | Web interfaces (EQ, jukebox manager, MPD proxy) |
| `lib/` | MiniPavi PHP library |

## Deployment targets

| Target | URL | Notes |
|--------|-----|-------|
| **VPS Géraldine** | https://minitel.boursaux.fr | Production web deployment |
| **Andre (Raspberry Pi 5)** | `ws://127.0.0.1:8182/` | Local kiosk with Chromium |

## Key commands

```bash
# Start gateway locally (for testing)
php minipavi.php --cfgfile minipavi-base.conf

# Start PHP built-in server for services (for local dev)
php -S 127.0.0.1:8091 -t services

# Test a service via MiniPavi JSON protocol
curl -s -X POST http://127.0.0.1:8091/damien/ \
  -H 'Content-Type: application/json' -H 'User-Agent: MiniPAVI/1.0' \
  -d '{"PAVI":{"uniqueId":"test","remoteAddr":"127.0.0.1","typesocket":"WS","versionminitel":"1","content":[],"context":"","fctn":"CNX","webmedia":""}}'
```

### Deploy to VPS

```bash
# Automatic: push to main → GitHub Actions → rsync to VPS
git push origin main

# Manual fallback (from Mac):
rsync -avz --delete --no-owner --no-group \
  --exclude='.git' --exclude='.github' --exclude='.env' \
  --exclude='logs/' --exclude='stats/' --exclude='recordings/' \
  -e "ssh -p 22022" \
  ./ damien@100.122.99.119:/var/www/minitel.boursaux.fr/

# Restart gateway after manual deploy
ssh -p 22022 damien@100.122.99.119 "sudo systemctl restart minipavi-gateway"
```

## Environment & secrets

### `.env` file (never committed, never deployed)

```bash
export ANTHROPIC_API_KEY="sk-ant-api03-..."
```

Sourced by:
- `start-minipavi.sh` on Andre (for PHP built-in server)
- systemd `EnvironmentFile` on VPS (for gateway service)

See `.env.example` for the template.

### `minipavi-base.conf`

Machine-specific config (ports, keys, service URLs). Excluded from rsync. See `minipavi-base.conf.example`.

**Key config values per environment:**

| Setting | Andre (Pi) | Géraldine (VPS) |
|---------|-----------|-----------------|
| `<durl>` | `http://127.0.0.1:8091/damien/` | `http://127.0.0.1/damien/` |
| `<wsport>` | 8182 | 8182 |
| `<httpport>` | 8080 | 8080 |

## PHP service module pattern

Every Minitel module follows this structure (use `confessionnal.php` as the cleanest template):

1. `require_once` `MiniPaviCli.php` and dependencies
2. `use MiniPavi\MiniPaviCli;`
3. `error_reporting(E_ERROR); ini_set('display_errors', 0);`
4. Wrap entire logic in `try { ... } catch (Exception $e) {} exit;`
5. Call `MiniPaviCli::start()` to parse the incoming MiniPavi POST request
6. Check `MiniPaviCli::$fctn`: exit on `'FIN'`, redirect to menu on `'SOMMAIRE'`
7. Unserialize context: `$context = unserialize(MiniPaviCli::$context)`
8. State machine via `$context['step']`
9. Build Videotex output
10. Send via `MiniPaviCli::send($vdt, $nextUrl, serialize($context), $echo, $cmd, $directCall)`

## Anthropic API (`anthropic.php`)

- Model: `claude-haiku-4-5-20251001` (hardcoded)
- API key from env var `ANTHROPIC_API_KEY`
- Direct `curl` to `https://api.anthropic.com/v1/messages`
- Key functions: `anthropic_call()`, `anthropic_text()`, `anthropic_parse_json()`, `anthropic_ascii()`, `anthropic_wrap()`

## Critical gotchas

- **`MSK_AUTOSEND` is undefined** on the installed MiniPavi version. Always use numeric value `256`.
- **`MiniPaviCli::$content` is a PHP array**, not a string. Extract with: `$input = is_array($rawContent) ? trim(implode('', $rawContent)) : trim((string)$rawContent);`
- **Double-height titles**: must write same content on row N and N+1.
- **Never use raw MPD sockets** in PHP — they hang. Use `mpc` CLI via `exec()` or `shell_exec()`.
- **Accented characters** must be wrapped with `MiniPaviCli::toG2()` for Videotex encoding.
- **Jukebox** uses `_skip` flag and `$modeChanged` to prevent double-trigger on navigation.
