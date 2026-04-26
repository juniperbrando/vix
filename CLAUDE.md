# CLAUDE.md

Guidance for Claude Code working in this folder.

## Project overview

**VIX** is a daily Slack alerter for the CBOE Volatility Index. It fires once per US trading day, posts the EOD VIX close to the user's Slack with a traffic-light indicator, and surfaces the user's 3-number trading rule:

- **VIX ≥ 30** → buy SPY/QQQ
- **VIX ≥ 45** → buy more
- **VIX ≤ 14** → trim/sell

## Architecture

There is no application code. The "bot" is a Claude Code **routine** (scheduled remote agent). Each scheduled invocation spins up an agent that:

1. Fetches VIX from Yahoo Finance via the **`WebFetch`** tool (the sandbox's curl host allowlist blocks Yahoo, so curl is not an option).
2. Maps the close to a tier (see `strategy.md`).
3. Posts the message to Slack `#vix` via the **Slack MCP connector's `Send message` tool** (the sandbox also blocks curl to `hooks.slack.com`, so an incoming webhook is not an option).

```
schedule cron (UTC 30 21 * * 1-5)
  └── Claude agent runs `routine_prompt.md`
        ├── WebFetch → Yahoo Finance JSON
        ├── classify → tier table
        └── Slack MCP → Send message to #vix
```

## Files

- `strategy.md` — the 3-number rule, tier table, historical anchors, failure modes. Read this when adjusting thresholds or interpreting alerts.
- `routine_prompt.md` — the exact prompt registered as the routine. Self-contained — change here, then re-register / update via the `schedule` skill.

## Routine

- ID: `trig_018nzaGJ1c7DjdSBAdQWpUvt`
- Manage: https://claude.ai/code/routines/trig_018nzaGJ1c7DjdSBAdQWpUvt
- Cron: `30 21 * * 1-5` UTC = 22:30 CET / 23:30 CEST, weekdays only. US market close is 16:00 ET (20:00 UTC EDT / 21:00 UTC EST), so 21:30 UTC is always ≥30 min post-close regardless of DST.
- Required attached connectors: **Slack** (per-routine, via Edit modal → Connectors tab).
- Required attached repo: this one (`juniperbrando/vix`) — its only purpose is to satisfy the routine's "must have a source" requirement.
