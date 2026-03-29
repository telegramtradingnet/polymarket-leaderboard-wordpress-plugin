# Polymarket Leaderboard for WordPress

Embed a **live Polymarket top-wallet leaderboard** on any WordPress page with a single shortcode. Pulls the top 10 traders ranked by 30-day or 7-day PNL from the official Polymarket API — server-side, always fresh (no caching), and fully theme-isolated.

[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
![Version](https://img.shields.io/badge/version-3.6.1-green)
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

### 3.4.7 — Compare Accordion Centring
- Fix: balance icon + both text lines now centred horizontally as a group on desktop and mobile; chevron pinned absolutely to right edge so it doesn't affect the centring

### 3.4.6
- Fix: Compare accordion toggle-left `align-items:flex-start` so icon and text lines anchor at top edge on desktop

### 3.4.5
- Fix: Compare accordion text always stacks vertically on all themes — `display:block!important` on both lines prevents WordPress theme overrides from collapsing them into one row

### 3.4.4 — WOTD Analysis Full Width & Compare Text Fix
- Fix: "Why this wallet leads today" box moved outside the `1fr/auto` grid — now spans full width below the name/stats/buttons row on desktop
- Fix: Compare accordion text replaced `<strong>` / `<span>` (subject to WP theme padding/indent) with plain `<div>` elements — zero theme interference

### 3.4.3 — Table Row Colour Fix & Bigger Text
- Fix: First-row background bleed eliminated — switched from `border-collapse:separate` + `border-spacing` to `border-collapse:collapse` with `border-bottom` dividers; no WP theme background can leak through row gaps
- Improvement: Table text sizes increased — wallet name, PNL, volume 13px → 15px; address/headers 11px → 12px; row padding increased

### 3.4.2 — Compare Accordion Alignment
- Fix: "Side-by-side wallet comparison" and "Pick any two wallets" lines vertically aligned — explicit `margin`, `padding`, `text-indent` resets on text wrapper

### 3.4.1 — Bug Fixes & Polish
- Fix: Table header blue/white split on desktop — `background:transparent!important` on `thead tr`, `tbody`, `tfoot`
- Fix: 7D volume always $0 — PHP `normalise()` now tries `vol → volume → tradedAmount → notional`; JS shows `—` when volume is absent
- Fix: Share on X on Wallet of the Day — desktop copy sits under wallet address; mobile copy stays in header; both hrefs updated by JS
- Fix: 30D/7D toggle + Refresh block centred on mobile
- Fix: Navy Blue theme — `border-radius:20px`, 32px side padding, `box-shadow` so widget sits as a rounded card; mobile 14px radius / 16px padding

### 3.4.0 — Themes, 7D Toggle & Live API
- **New: White / Navy Blue theme** selector in Settings → Polymarket LB; all CSS fully adapted for navy dark background — tables, cards, controls, filter pills, compare tool, loading states, WCAG AA on all text
- **New: 30D / 7D period toggle** on the widget controls bar — switches between MONTH and WEEK timePeriod; affects leaderboard, WotD analysis text, compare labels, and all card stat labels
- **No caching** — removed all transient caching; every API request is now live and fresh
- Admin page redesigned: live API status badge, theme picker card with visual swatches

### 3.3.2 — Theme Isolation & UI Redesign
- Complete CSS rewrite — all styles scoped under `#ttg-lb-root` with ID-level specificity
- Heading `::before`/`::after` pseudo-elements suppressed
- Action buttons redesigned: View Profile (deep navy) and ⚡ CopyTrade (vivid indigo)
- WCAG AA compliance on PNL positive colour

### 3.3.0 — 3.0.0
- Renamed repository to `polymarket-leaderboard-wordpress`
- Setup wizard covers Wallet of the Day and Compare Tool
- Slug renamed for WP.org guideline compliance
- Initial release: proxy, cache, filters, WOTD, compare, mobile cards

---

## Related

- 📖 [How to copy trade on Polymarket](https://telegramtrading.net/polymarket-copy-trading-tutorial/)
- 📊 [Polymarket smart wallet analysis & guides](https://telegramtrading.net/category/polymarket/)

---

## License

GPL-2.0+ — free to use, modify, and redistribute.
