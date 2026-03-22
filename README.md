# Polymarket Leaderboard for WordPress

Embed a **live Polymarket top-wallet leaderboard** on any WordPress page with a single shortcode. Pulls the top 10 traders ranked by 30-day or 7-day PNL from the official Polymarket API — server-side, always fresh (no caching), and fully theme-isolated.

[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
![Version](https://img.shields.io/badge/version-3.4.0-green)
![WordPress](https://img.shields.io/badge/WordPress-5.5%2B-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)

**[→ Live Demo](https://telegramtrading.net/best-polymarket-smart-wallets-copytrading/)**

[![Polymarket Leaderboard WordPress Plugin](https://telegramtrading.net/wp-content/uploads/2026/03/polymarket-leaderboard-wordpress.png)](https://telegramtrading.net/best-polymarket-smart-wallets-copytrading/)

---

## 🔗 Plugin Repositories

| Host | URL |
|---|---|
| **GitLab** | [gitlab.com/telegramtrading-polymarket-wordpress/polymarket-leaderboard-wordpress-plugin](https://gitlab.com/telegramtrading-polymarket-wordpress/polymarket-leaderboard-wordpress-plugin) |
| **SourceForge** | [sourceforge.net/projects/polymarket-wordpress](https://sourceforge.net/projects/polymarket-wordpress/) |
| **GitHub** | [github.com/telegramtradingnet/polymarket-leaderboard-wordpress-plugin](https://github.com/telegramtradingnet/polymarket-leaderboard-wordpress-plugin) |
| **Codeberg** | [codeberg.org/telegramtrading/polymarket-leaderboard-wordpress](https://codeberg.org/telegramtrading/polymarket-leaderboard-wordpress) |

---

## What it looks like

The widget renders a ranked leaderboard table with category filters, a 30D/7D period toggle, wallet addresses, PNL, volume, and action buttons — in either a clean White or rich Navy Blue theme.

| Feature | Desktop | Mobile |
|---|---|---|
| Leaderboard | Full table with rank badges | Card layout |
| Wallet of the Day | Performance breakdown card | Stacked layout |
| Compare Tool | Side-by-side with bar charts | Single column |
| Period Toggle | 30D / 7D switch in controls bar | Same |

---

## Features

### 🏆 Leaderboard
- Live top-10 Polymarket wallets ranked by PNL
- **30D / 7D toggle** — switch between 30-day and 7-day best wallets directly on the widget
- Category filters — All, Politics, Crypto, Sports, Culture, Tech, Finance, Macro
- Wallet address copy, X (Twitter) profile links, verified badges
- Why-badge: auto-generated insight per wallet (PNL/Vol ratio, rank context)
- Zero first-paint spinner — data seeded server-side on page load

### 🎨 Themes
- **White** (default) — clean light design, reads perfectly on any background
- **Navy Blue** — dark gradient background (#06122a → #091c3d) with white text; all elements fully adapted for contrast and readability (tables, cards, filters, compare tool, loading states)
- Theme is chosen in **Settings → Polymarket LB** and applied globally to all shortcode instances

### ⭐ Wallet of the Day *(optional)*
- Spotlights the #1 ranked wallet with a full dark-card performance breakdown
- Auto-generated analysis adapts to the currently selected time period (30D or 7D)
- Share to X button pre-filled with wallet stats
- One-click wallet address copy

### ⚖️ Wallet Compare Tool *(optional)*
- Side-by-side comparison of any two wallets from the current leaderboard
- Metrics: PNL, volume, PNL/volume efficiency ratio, rank
- Visual bar charts for at-a-glance comparison
- Verdict summary with winner and efficiency analysis

### 🛡️ Technical
- **No caching** — always fetches fresh data from the Polymarket API on every request
- **Server-side AJAX proxy** — all API calls go through `admin-ajax.php`, zero CORS issues on any host
- **Theme-isolated CSS** — scoped under `#ttg-lb-root` with ID-level specificity; your theme cannot break the widget, and the widget cannot break your theme
- **Pure vanilla JS** — no jQuery, no external JS dependencies
- Falls back to anonymised demo data if the Polymarket API is unreachable
- WCAG AA contrast on all PNL colour values (both themes)
- Bulletproof layout on mobile (card view) and desktop (table view) in both themes

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

1. Download the latest zip from the repositories listed above
2. In WordPress Admin: **Plugins → Add New → Upload Plugin** — upload the zip
3. Activate the plugin
4. Add `[polymarket_leaderboard]` to any page
5. Visit **Settings → Polymarket LB** to:
   - Choose your widget theme (White or Navy Blue)
   - Enable the optional Wallet of the Day and Compare Tool

---

## How It Works

```
Page load
  └── shortcode seeds fresh data server-side (window.TTG_SEED, 30D)
        └── no spinner on first paint

Period toggle click (30D ↔ 7D)
  └── AJAX → admin-ajax.php → Polymarket API (timePeriod=MONTH|WEEK)
        └── fresh data always, no cache

Category filter click
  └── AJAX → admin-ajax.php → Polymarket API
        └── fresh data always, no cache
              └── falls back to demo data on error
```

The Polymarket API endpoints used:

```
GET https://data-api.polymarket.com/v1/leaderboard
    ?limit=10&offset=0&timePeriod=MONTH&orderBy=PNL&category=OVERALL   ← 30D

GET https://data-api.polymarket.com/v1/leaderboard
    ?limit=10&offset=0&timePeriod=WEEK&orderBy=PNL&category=OVERALL    ← 7D
```

Full spec: [docs.polymarket.com](https://docs.polymarket.com/api-reference/core/get-trader-leaderboard-rankings.md)

---

## Admin Settings

**Settings → Polymarket LB** lets you:

- Choose **Widget Theme** — White or Navy Blue
- Enable / disable **Wallet of the Day**
- Enable / disable **Wallet Compare Tool**
- View API & shortcode usage reference

---

## Privacy

This plugin fetches **public** data from `https://data-api.polymarket.com` only. No visitor data is collected, stored, or transmitted. No tracking pixels, no analytics, no beacons.

---

## Changelog

### 3.4.0 — Themes, 7D Toggle & Live API
- **New: White / Navy Blue theme** selector in Settings → Polymarket LB; all CSS fully adapted for navy dark background — tables, cards, controls, filter pills, compare tool, loading states, WCAG AA on all text
- **New: 30D / 7D period toggle** on the widget controls bar — switches between MONTH and WEEK timePeriod; affects leaderboard, WotD analysis text, compare labels, and all card stat labels
- **No caching** — removed all transient caching; every API request is now live and fresh
- Removed sessionStorage client-side cache; the server-side seed is still used for the zero-spinner first paint (always fresh data fetched at page load)
- Admin page redesigned: live API status badge, theme picker card with visual swatches, removed stale cache status table
- Period label in crawler note updates dynamically with toggle
- Table PNL column header and WotD stat label update with selected period

### 3.3.2 — Theme Isolation & UI Redesign
- Complete CSS rewrite — all styles scoped under `#ttg-lb-root` with ID-level specificity
- Heading `::before`/`::after` pseudo-elements suppressed
- Action buttons redesigned: View Profile (deep navy) and ⚡ CopyTrade (vivid indigo)
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
