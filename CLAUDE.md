# CLAUDE.md

Guidance for Claude Code working in this folder.

## Project overview

**VIX** is a daily Slack alerter for the CBOE Volatility Index. It fires once per US trading day, posts the EOD VIX close to the user's Slack with a traffic-light indicator, and surfaces the user's 3-number trading rule:

- **VIX ≥ 30** → buy SPY/QQQ
- **VIX ≥ 45** → buy more
- **VIX ≤ 14** → trim/sell

## Architecture

A single GitHub Actions workflow does everything: fetch, classify, post.

```
.github/workflows/vix-eod.yml
  └── cron: 30 21 * * 1-5 (UTC)
        ├── curl CBOE JSON  → price, change, pct
        ├── bc/printf       → classify into tier, build mrkdwn message
        └── curl Slack      → POST {"text": ..., "mrkdwn": true}
```

Why GH Actions instead of a Claude Code routine: the routine sandbox blocks outbound network calls to public finance APIs (Yahoo and CBOE both 403'd `WebFetch`). GH Actions runners have unrestricted internet, so plain `curl` just works. Same architectural pattern as `../coc-bot` — only difference is GH Actions hosts it instead of `bick.dk`.

## Files

- `.github/workflows/vix-eod.yml` — the entire bot. Bash + curl + jq + bc, ~60 lines.
- `strategy.md` — the 3-number rule, tier table, historical anchors, failure modes. Read this when adjusting thresholds or interpreting alerts.

## Schedule

Cron: `30 21 * * 1-5` UTC = 22:30 CET / 23:30 CEST, weekdays only. US close is 16:00 ET, so 21:30 UTC is always ≥30 min post-close regardless of DST.

GH Actions cron drifts up to ~15 min on free tier. Acceptable for EOD reporting.

## Secrets

- `SLACK_WEBHOOK_URL` — incoming-webhook URL for the target channel (set via `gh secret set SLACK_WEBHOOK_URL --repo juniperbrando/vix --body '<url>'`). Currently reuses the same `test-staver` webhook as `../coc-bot/Bot/properties.php:14`.

## Run / debug

```bash
# Trigger the workflow manually from your machine
gh workflow run vix-eod.yml --repo juniperbrando/vix

# Tail the most recent run
gh run watch --repo juniperbrando/vix

# Sanity-check the data source (no auth, no rate limit at this volume)
curl -sS 'https://cdn.cboe.com/api/global/delayed_quotes/quotes/_VIX.json' | jq '.data | {current_price, price_change, price_change_percent}'

# Sanity-check Slack delivery
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"text":"📊 VIX test","mrkdwn":true}' \
  "$(gh secret list --repo juniperbrando/vix)"  # (URL is encrypted; paste directly when testing)
```

## Legacy

A Claude Code routine (`trig_018nzaGJ1c7DjdSBAdQWpUvt`) was the original implementation but was disabled — see git history for the prompt. The routine architecture is left in place (disabled) at https://claude.ai/code/routines/trig_018nzaGJ1c7DjdSBAdQWpUvt in case the sandbox network restrictions are loosened in the future.
