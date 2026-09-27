# CLAUDE.md

Guidance for Claude Code working in this folder.

## Project overview

**VIX** is a Slack alerter for the CBOE Volatility Index. It checks the EOD VIX close once per US trading day, but only posts to the user's Slack when (a) the close moves into a *different tier* of the tier table in `strategy.md`, or (b) CNN's Fear & Greed Index *enters* Extreme Fear (≤ 25) as an early warning that the VIX buy trigger may be near. The post shows the tier change, the action to take, and the full tier table with the current row marked. It also shows CNN's Fear & Greed Index as context (0 = extreme fear, 100 = extreme greed, i.e. the opposite scale of VIX). F&G is never part of the tier logic. A second line says whether F&G confirms the VIX signal (Fear/Panic + F&G ≤ 25, or Complacency + F&G ≥ 75), does not confirm it, or is at an extreme while VIX is not. It encodes the user's 3-number trading rule:

- **VIX ≥ 30** → buy SPY/QQQ
- **VIX ≥ 45** → buy more
- **VIX ≤ 14** → trim/sell

## Architecture

A single GitHub Actions workflow does everything: fetch, classify, compare with last state, post on change, persist state.

```
.github/workflows/vix-eod.yml
  └── cron: 30 21 * * 1-5 (UTC)
        ├── actions/checkout  → repo incl. state/last_tier + state/last_fg_zone
        ├── curl CBOE JSON    → price, change, pct
        ├── curl CNN F&G      → Fear & Greed score (context line only, best-effort)
        ├── bc                → classify into tier
        ├── compare           → post if tier changed OR F&G entered Extreme Fear, else exit 0
        ├── printf            → headline + VIX close + F&G line/verdict + action + tier table
        ├── curl Slack        → POST {"text": ..., "mrkdwn": true}
        └── git commit+push   → state/ (written every run, committed only when changed)
```

Why GH Actions instead of a Claude Code routine: the routine sandbox blocks outbound network calls to public finance APIs (Yahoo and CBOE both 403'd `WebFetch`). GH Actions runners have unrestricted internet, so plain `curl` just works. Same architectural pattern as `../coc-bot` — only difference is GH Actions hosts it instead of `bick.dk`.

## Files

- `.github/workflows/vix-eod.yml` — the entire bot. Bash + curl + jq + bc, ~120 lines.
- `state/last_tier`, `state/last_fg_zone` — VIX tier and F&G zone (`extreme_fear` | `normal`) seen on the last run, committed back by the workflow (`vix-bot`). Delete `last_tier` to force a "first run" post; edit either to simulate a change on the next run. Pull before you push, since the bot commits to `main`.
- `strategy.md` — the 3-number rule, tier table, historical anchors, failure modes. Read this when adjusting thresholds or interpreting alerts.

## Schedule

Cron: `30 21 * * 1-5` UTC = 22:30 CET / 23:30 CEST, weekdays only. US close is 16:00 ET, so 21:30 UTC is always ≥30 min post-close regardless of DST.

GH Actions cron drifts up to ~15 min on free tier. Acceptable for EOD reporting.

## Secrets

- `SLACK_WEBHOOK_URL` — incoming-webhook URL for the target channel (set via `gh secret set SLACK_WEBHOOK_URL --repo juniperbrando/vix --body '<url>'`). Currently reuses the same `test-staver` webhook as `../coc-bot/Bot/properties.php:14`.

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
