# VIX strategy — the three numbers

## The rule

> $VIX hits **30** → Buy stocks
> $VIX hits **45+** → Buy even more
> $VIX hits **14** → Sell / trim
> Repeat endlessly.

Instruments: SPY / QQQ (cash equities, no leverage).

## Why these levels

The VIX measures 30-day implied volatility on S&P 500 options. It is mean-reverting — it does not trend like a stock. Long-run median sits around **17–19**. Anything outside that band is a regime, not a quote.

- **≤ 14** — complacency. Options are underpriced; the market has stopped paying for protection. Historically a poor place to add risk; a fine place to trim.
- **20–30** — elevated. Real worry, but not yet panic. Wait.
- **≥ 30** — fear. Implied vol has roughly doubled. SPX is usually 8–15% off recent highs. Historically the *start* of risk being repriced cheaply.
- **≥ 45** — panic / forced-selling. Rare. Liquidations dominate price. Almost every print here has been a within-12-months buying opportunity (with one big asterisk — see failure modes).

## Tier table (used by the daily Slack alert)

| VIX close | Tier | Emoji | Action |
|---|---|---|---|
| ≤ 14 | Complacency | 🔴 | TRIM / sell |
| 14 – 20 | Calm | 🟢 | Hold |
| 20 – 30 | Elevated | 🟡 | Watch |
| 30 – 45 | Fear | 🟠 | **BUY** SPY/QQQ |
| ≥ 45 | Panic | 🚨 | **BUY MORE** |

## Historical anchors

| Event | VIX peak | Notes |
|---|---|---|
| GFC (Oct 2008) | ~80 | All-time intraday high until 2020. |
| Flash Crash (May 2010) | ~46 | Spike-and-fade in days. |
| Eurozone (Aug 2011) | ~48 | Multi-month elevated regime. |
| Volmageddon (Feb 2018) | ~50 | XIV blew up; VIX collapsed back inside a week. |
| Q4 2018 selloff | ~36 | Powell pivot bottom. |
| COVID (Mar 2020) | ~85 | New all-time high; SPX -34%. |
| 2022 bear (multiple) | ~36 | VIX never broke 40 despite -25% SPX — **the strategy underperformed in 2022**. |
| SVB (Mar 2023) | ~30 | Brief spike. |

## Failure modes — read this before betting the farm

1. **2022-style grinding bears.** A slow -25% drawdown can happen *without* VIX ever crossing 45, or even staying above 30 for long. Buying every 30 print on the way down means averaging into a falling market. The strategy assumes panic-style vol; trending bears starve it.
2. **Long calm regimes.** 2017 had VIX under 14 for months. Trimming early gave up large gains. The 14 signal is "right eventually" but can be very early.
3. **Vol-of-vol intraday spikes.** Intraday VIX can poke above 30 then close at 24. Using EOD close (this routine does) avoids most of that, but be aware of the gap.
4. **Single-name vol ≠ index vol.** Buying individual stocks on a VIX print can still go badly if the stock has idiosyncratic risk. Stick to index ETFs unless you have a separate thesis.
5. **45+ is rare.** Only ~6 events in 30 years. Don't expect to deploy that bullet often.

## Position sizing (not encoded in the bot)

Suggested mental model — adjust to taste:

- VIX 30 trigger → deploy 1/3 of dry powder
- VIX 35 → another 1/3
- VIX 45+ → final 1/3

Trimming on 14: scale out toward neutral, never go net short on a 14 print alone.

## What VIX is *not*

- Not a directional indicator. High VIX doesn't mean "go down further" — it means "options are expensive."
- Not a timing tool with day-level precision. It identifies regimes, not bottoms.
- Not predictive. It is implied (forward-looking) vol but ultimately backward-anchored to recent realized vol plus a risk premium.
