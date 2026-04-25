# CLAUDE.md

Guidance for Claude Code working in this folder.

## Project overview

**VIX** is a daily Slack alerter for the CBOE Volatility Index. It fires once per US trading day, posts the EOD VIX close to the user's Slack with a traffic-light indicator, and surfaces the user's 3-number trading rule:

- **VIX ≥ 30** → buy SPY/QQQ
- **VIX ≥ 45** → buy more
- **VIX ≤ 14** → trim/sell

## Architecture

There is no application code. The "bot" is a Claude Code **routine** (scheduled remote agent) registered via the `schedule` skill. Each scheduled invocation spins up an agent that:

1. Curls VIX from Yahoo Finance (`query1.finance.yahoo.com/v8/finance/chart/%5EVIX`).
2. Maps the close to a tier (see `strategy.md`).
3. Posts a `mrkdwn` message to a Slack incoming webhook.

```
schedule cron (UTC 30 21 * * 1-5)
  └── Claude agent runs `routine_prompt.md`
        ├── fetch → Yahoo Finance
        ├── classify → tier table
        └── post → Slack webhook (reused from coc-bot)
```

## Files

- `strategy.md` — the 3-number rule, tier table, historical anchors, failure modes. Read this when adjusting thresholds or interpreting alerts.
- `routine_prompt.md` — the exact prompt registered as the routine. Self-contained — change here, then re-register with the `schedule` skill.

## Slack webhook

Reuses the `test-staver` webhook already used by `../coc-bot/Bot/properties.php:14`:

```
https://hooks.slack.com/services/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
```

Message format mirrors coc-bot — JSON POST with `mrkdwn: true`.

## Schedule

Cron: `30 21 * * 1-5` UTC = 22:30 CET / 23:30 CEST, weekdays only. US market close is 16:00 ET (20:00 UTC EDT / 21:00 UTC EST), so 21:30 UTC is always ≥30 min post-close regardless of DST.

To re-register or modify: invoke the `schedule` skill with the contents of `routine_prompt.md` as the prompt. The routine name is `vix-eod-daily`.

## Manual run / debug

```bash
# Fetch raw VIX
curl -sS 'https://query1.finance.yahoo.com/v8/finance/chart/%5EVIX?interval=1d&range=5d' \
  | jq '.chart.result[0].meta | {price: .regularMarketPrice, prev: .chartPreviousClose}'

# Test-post to Slack
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"text":"📊 VIX test","mrkdwn":true}' \
  'https://hooks.slack.com/services/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'
```
