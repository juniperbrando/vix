# CLAUDE.md

Guidance for Claude Code working in this folder.

## Project overview

**VIX** is a Slack alerter for the CBOE Volatility Index. It checks VIX once per US trading day at 15:00 New York time (1 h before US close, ~21:00 Danish time, ~15 min delayed quote) so the user can still act the same session, but only posts to the user's Slack when (a) VIX moves into a *different tier* of the tier table in `strategy.md`, or (b) CNN's Fear & Greed Index *enters* Extreme Fear (≤ 25) as an early warning that the VIX buy trigger may be near. The post shows the tier change, the action to take, and the full tier table with the current row marked. It also shows CNN's Fear & Greed Index as context (0 = extreme fear, 100 = extreme greed, i.e. the opposite scale of VIX). F&G is never part of the tier logic. A second line says whether F&G confirms the VIX signal (Fear/Panic + F&G ≤ 25, or Complacency + F&G ≥ 75), does not confirm it, or is at an extreme while VIX is not. It encodes the user's 3-number trading rule:

- **VIX ≥ 30** → buy SPY/QQQ
- **VIX ≥ 45** → buy more
- **VIX ≤ 14** → trim/sell

## Architecture

A single GitHub Actions workflow does everything: fetch, classify, compare with last state, post on change, persist state. A cron on bick.dk fires it via `workflow_dispatch` at 15:00 NY. GH's own `schedule:` (21:30 UTC) stays as a post-close backstop — it starts 5–8 h late, so it can't be the main trigger, but it catches final-hour tier changes and a dead bick.dk cron (it only posts if the close tier differs from the stored one).

```
bick.dk cron (0 20,21 * * 1-5 Danish time) → scheduler/trigger.php → fires only at 15:xx New York time, Mon–Fri
  └── POST api.github.com/.../workflows/vix-eod.yml/dispatches
GH cron 30 21 * * 1-5 (UTC, backstop on the close, starts hours late)
  └──
.github/workflows/vix-eod.yml
        ├── actions/checkout  → repo incl. state/last_tier + state/last_fg_zone
        ├── curl CBOE JSON    → price, change, pct (browser headers; falls back to FRED VIXCLS, ~1 day lag)
        ├── curl CNN F&G      → Fear & Greed score (context line only, best-effort)
        ├── bc                → classify into tier
        ├── compare           → post if tier changed OR F&G entered Extreme Fear, else exit 0
        ├── printf            → headline + VIX + as-of time + action + tier table + (———) F&G line/verdict
        ├── curl Slack        → POST {"text": ..., "mrkdwn": true}
        └── git commit+push   → state/ (written every run, committed only when changed)
```

Why GH Actions instead of a Claude Code routine: the routine sandbox blocks outbound network calls to public finance APIs (Yahoo and CBOE both 403'd `WebFetch`). GH Actions runners have unrestricted internet, so plain `curl` just works. Same architectural pattern as `../coc-bot` — only difference is GH Actions hosts it instead of `bick.dk`.

## Files

- `scheduler/trigger.php` — runs on bick.dk; dispatches the workflow at 15:00 NY time. Token lives in `scheduler/config.php` (gitignored; template in `config.example.php`): fine-grained PAT, repo `juniperbrando/vix` only, Actions read/write. `php trigger.php --now` fires immediately.
- `.github/workflows/vix-eod.yml` — the entire bot. Bash + curl + jq + bc, ~120 lines.
- `state/last_tier`, `state/last_fg_zone` — VIX tier and F&G zone (`extreme_fear` | `normal`) seen on the last run, committed back by the workflow (`vix-bot`). Delete `last_tier` to force a "first run" post; edit either to simulate a change on the next run. Pull before you push, since the bot commits to `main`.
- `strategy.md` — the 3-number rule, tier table, historical anchors, failure modes. Read this when adjusting thresholds or interpreting alerts.

## Schedule

One run per weekday at 15:00 America/New_York (= 21:00 Danish time, 20:00 during the few weeks each spring/autumn when US and EU DST differ). The time check lives in `trigger.php`; the bick.dk cron runs at both candidate hours (`0 20,21 * * 1-5` in Danish time, `0 19,20` in UTC) and the script fires only on the right one. CBOE quotes are ~15 min delayed, so the post reflects ~14:45 NY; the post shows the quote's "as of" time. CBOE's endpoint does not show extended-hours VIX — checks before 9:30 NY would only see yesterday's close. A tier change in the final hour is caught by the GH-cron backstop on the close (arrives late at night / early morning Danish time). US holidays: the quote is stale → no tier change → no post.

## Secrets

- `SLACK_WEBHOOK_URL` — incoming-webhook URL for the target channel (set via `gh secret set SLACK_WEBHOOK_URL --repo juniperbrando/vix --body '<url>'`). Currently points at `#dollarbills` in the `12-angrymen` workspace (Slack app "VIX bot", incoming-webhook scope only). Free plan caps the workspace at 10 apps.

## Run / debug

```bash
# Trigger the workflow manually from your machine (posts only if tier changed / F&G entered Extreme Fear)
gh workflow run vix-eod.yml --repo juniperbrando/vix

# Force a Slack post regardless of changes (e.g. to check formatting)
gh workflow run vix-eod.yml --repo juniperbrando/vix -f force=true

# Tail the most recent run
gh run watch --repo juniperbrando/vix

# Sanity-check the data source (no auth, no rate limit at this volume)
curl -sS 'https://cdn-api.cboe.com/api/global/delayed_quotes/quotes/_VIX.json' | jq '.data | {current_price, price_change, price_change_percent}'

# Sanity-check CNN Fear & Greed (answers 418 "I'm a teapot" without browser-like headers)
curl -sS -A 'Mozilla/5.0' -H 'Accept: application/json' -H 'Referer: https://edition.cnn.com/markets/fear-and-greed' -H 'Origin: https://edition.cnn.com' 'https://production.dataviz.cnn.io/index/fearandgreed/current' | jq '{score, rating}'

# Sanity-check Slack delivery
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"text":"📊 VIX test","mrkdwn":true}' \
  "$(gh secret list --repo juniperbrando/vix)"  # (URL is encrypted; paste directly when testing)
```

## Legacy

A Claude Code routine (`trig_018nzaGJ1c7DjdSBAdQWpUvt`) was the original implementation but was disabled — see git history for the prompt. The routine architecture is left in place (disabled) at https://claude.ai/code/routines/trig_018nzaGJ1c7DjdSBAdQWpUvt in case the sandbox network restrictions are loosened in the future.
