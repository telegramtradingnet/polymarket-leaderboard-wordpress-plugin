# Polymarket Leaderboard for WordPress

Embed a **live Polymarket top-wallet leaderboard** on any WordPress page with a single shortcode. Pulls the top 10 traders ranked by 30-day PNL from the official Polymarket API — server-side, cached, and fully theme-isolated.

[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
![Version](https://img.shields.io/badge/version-3.3.2-green)
![WordPress](https://img.shields.io/badge/WordPress-5.5%2B-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)

**[→ Live Demo](https://telegramtrading.net/best-polymarket-smart-wallets-copytrading/)**

[![Polymarket Leaderboard WordPress Plugin](https://telegramtrading.net/wp-content/uploads/2026/03/polymarket-leaderboard-wordpress.png)](https://telegramtrading.net/best-polymarket-smart-wallets-copytrading/)

---

## What it looks like

The widget renders a ranked leaderboard table with category filters, wallet addresses, 30-day PNL, volume, and action buttons — automatically, every hour, from the official Polymarket Data API.

| Feature | Desktop | Mobile |
|---|---|---|
| Leaderboard | Full table with rank badges | Card layout |
| Wallet of the Day | Performance breakdown card | Stacked layout |
| Compare Tool | Side-by-side with bar charts | Single column |

---

## Features

### 🏆 Leaderboard
- Live top-10 Polymarket wallets ranked by 30-day PNL
- Category filters — All, Politics, Crypto, Sports, Culture, Tech, Finance, Macro
- Wallet address copy, X (Twitter) profile links, verified badges
- Why-badge: auto-generated insight per wallet (PNL/Vol ratio, rank context)
- Zero first-paint spinner — data seeded server-side on page load

### ⭐ Wallet of the Day *(optional)*
- Spotlights the #1 ranked wallet with a full dark-card performance breakdown
- Auto-generated analysis: PNL/volume efficiency, category edge, profit context
- Share to X button pre-filled with wallet stats
- One-click wallet address copy

### ⚖️ Wallet Compare Tool *(optional)*
- Side-by-side comparison of any two wallets from the current leaderboard
- Metrics: 30-day PNL, volume, PNL/volume efficiency ratio, rank
- Visual bar charts for at-a-glance comparison
- Verdict summary with winner and efficiency analysis

### 🛡️ Technical
- **Server-side AJAX proxy** — all API calls go through `admin-ajax.php`, zero CORS issues on any host
- **1-hour transient cache** per category, 60-second retry on API error
- **Theme-isolated CSS** — scoped under `#ttg-lb-root` with ID-level specificity; your theme cannot break the widget, and the widget cannot break your theme
- **Pure vanilla JS** — no jQuery, no external JS dependencies
- Compatible with all page caching plugins (WP Rocket, W3 Total Cache, LiteSpeed, etc.)
- Falls back to anonymised demo data if the Polymarket API is unreachable
- WCAG AA contrast on all PNL colour values

---

## Quick Start

Place the shortcode on any page or post:

```
[polymarket_leaderboard]
```

Pre-select a default category on load:

```
[polymarket_leaderboard category="CRYPTO"]
```

**Valid categories:** `OVERALL` · `POLITICS` · `SPORTS` · `CRYPTO` · `CULTURE` · `ECONOMICS` · `TECH` · `FINANCE`

---

## Installation

1. Download the latest zip from [Releases](../../releases/latest)
2. In WordPress Admin: **Plugins → Add New → Upload Plugin** — upload the zip
3. Activate the plugin
4. Add `[polymarket_leaderboard]` to any page
5. Visit **Settings → Polymarket LB** to enable the optional Wallet of the Day and Compare Tool

---

## How It Works

```
Page load
  └── shortcode seeds data server-side (window.TTG_SEED)
        └── no spinner on first paint

Category filter click
  └── AJAX → admin-ajax.php → Polymarket API
        └── cached 1h per category in WP Transients
              └── falls back to demo data on error
```

The Polymarket API endpoint used:

```
GET https://data-api.polymarket.com/v1/leaderboard
    ?limit=10&offset=0&timePeriod=MONTH&orderBy=PNL&category=OVERALL
```

Full spec: [docs.polymarket.com](https://docs.polymarket.com/api-reference/core/get-trader-leaderboard-rankings.md)

---

## Admin Settings

**Settings → Polymarket LB** lets you:

- Enable / disable **Wallet of the Day**
- Enable / disable **Wallet Compare Tool**
- View cache status per category
- Clear all cached data

---

## Privacy

This plugin fetches **public** data from `https://data-api.polymarket.com` only. No visitor data is collected, stored, or transmitted. No tracking pixels, no analytics, no beacons.

---

## Changelog

### 3.3.2 — Theme Isolation & UI Redesign
- Complete CSS rewrite — all styles scoped under `#ttg-lb-root` with ID-level specificity, immune to theme overrides in both directions
- Heading `::before`/`::after` pseudo-elements suppressed — no theme decorative bars
- Action buttons redesigned: View Profile (deep navy) and ⚡ CopyTrade (vivid indigo)
- All interactive elements hardened against theme colour overrides
- Table cell padding, row shadows, and border-collapse protected against theme resets
- WCAG AA compliance on PNL positive colour

### 3.3.0
- Renamed repository to `polymarket-leaderboard-wordpress`
- Setup wizard covers Wallet of the Day and Compare Tool

### 3.2.1 — 3.0.0
- Slug renamed for WP.org guideline 17 compliance
- First-run setup wizard, opt-in settings for all optional features
- Full CSS bundle self-contained, Google Fonts via `wp_enqueue_style`
- Initial release: proxy, cache, filters, WOTD, compare, mobile cards

---

## Related

- 📖 [How to copy trade on Polymarket](https://telegramtrading.net/polymarket-copy-trading-tutorial/)
- 📊 [Polymarket smart wallet analysis & guides](https://telegramtrading.net/category/polymarket/)

---

## License

GPL-2.0+ — free to use, modify, and redistribute.
