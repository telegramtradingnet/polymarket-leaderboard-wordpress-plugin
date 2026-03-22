=== Polymarket Leaderboard — Top Traders & Copy Trading ===
Contributors: bounmee
Tags: polymarket, leaderboard, crypto, prediction-markets, trading, defi, wallets
Requires at least: 5.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed a live Polymarket top-wallet leaderboard on any page. Always-fresh API data, 30D/7D toggle, White & Navy Blue themes, category filters, and CopyTrade buttons.

== Description ==

**Polymarket Leaderboard** embeds a live top-10 wallet rankings table on any page or post with a single shortcode.

= Plugin Repositories =

* GitLab: https://gitlab.com/telegramtrading-polymarket-wordpress/polymarket-leaderboard-wordpress-plugin
* SourceForge: https://sourceforge.net/projects/polymarket-wordpress/
* GitHub: https://github.com/telegramtradingnet/polymarket-leaderboard-wordpress-plugin
* Codeberg: https://codeberg.org/telegramtrading/polymarket-leaderboard-wordpress

= Key Features =

**Live Leaderboard**
* Top-10 Polymarket wallets ranked by PNL
* **30D / 7D period toggle** — switch between 30-day and 7-day best wallets directly on the widget, no page reload
* Category filters: All, Politics, Crypto, Sports, Culture, Tech, Finance, Macro
* Wallet address copy-to-clipboard, verified badges, X (Twitter) links
* Why-badge: auto-generated insight per wallet

**Two Visual Themes**
* **White** (default) — clean, light, reads on any background
* **Navy Blue** — full dark-mode treatment (deep navy gradient background, white/light text, all elements adapted for WCAG contrast)
* Theme chosen in Settings; both themes are bulletproof at any viewport width

**No Caching — Always Fresh**
* Every API call fetches live data from the Polymarket API
* No transient or session caching; visitors always see current rankings
* Server-side seed data on first page load (zero loading spinner)

**Optional Extras**
* ⭐ Wallet of the Day — dark-card performance breakdown for the #1 wallet; analysis adapts to selected time period
* ⚖️ Wallet Compare Tool — side-by-side PNL, volume, and efficiency comparison with verdict summary
* ⚡ CopyTrade buttons — link visitors to your copy-trading service

**Technical**
* Server-side AJAX proxy through admin-ajax.php — zero CORS issues
* Pure vanilla JS, no jQuery dependency
* ID-scoped CSS (#ttg-lb-root) — immune to theme overrides in both directions
* Falls back to demo data if the API is unreachable
* WCAG AA contrast on all text in both themes

= Shortcode =

Basic:
`[polymarket_leaderboard]`

With a pre-selected category:
`[polymarket_leaderboard category="CRYPTO"]`

Valid categories: OVERALL · POLITICS · SPORTS · CRYPTO · CULTURE · ECONOMICS · TECH · FINANCE

= API =

This plugin uses the official Polymarket Data API:

    GET https://data-api.polymarket.com/v1/leaderboard
        ?limit=10&offset=0&timePeriod=MONTH&orderBy=PNL&category=OVERALL

Full reference: https://docs.polymarket.com/api-reference/core/get-trader-leaderboard-rankings.md

No API key required. Only public on-chain data is fetched.

= Privacy =

This plugin fetches public data from data-api.polymarket.com only. No visitor data is collected, stored, or transmitted to any third party.

== Installation ==

1. Download the plugin zip.
2. In WordPress Admin go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip and click **Install Now**.
4. Activate the plugin.
5. Add `[polymarket_leaderboard]` to any page or post.
6. Visit **Settings → Polymarket LB** to choose your theme (White or Navy Blue) and enable optional features.

== Frequently Asked Questions ==

= Does this require an API key? =
No. The Polymarket Data API is public and requires no authentication.

= Is the data cached? =
No. Version 3.4.0 removed all caching. Every page load fetches fresh data from the Polymarket API. The server-side seed eliminates any loading spinner on first paint, but the data is always current.

= Can I change the theme after setup? =
Yes. Go to **Settings → Polymarket LB** at any time and switch between White and Navy Blue. The change takes effect immediately on save.

= How does the 30D / 7D toggle work? =
The toggle appears in the widget controls bar. Clicking 7D fires a fresh API request with timePeriod=WEEK. The period label, table header, card stat labels, and Wallet of the Day analysis all update accordingly. No page reload needed.

= Why does the leaderboard sometimes show demo data? =
If the Polymarket API is unreachable (network issues, downtime), the widget falls back to anonymised demo data rather than showing a blank page or error. Refreshing when the API is back will restore live data.

= Does it work with page caching plugins? =
Yes. The AJAX requests that the JS fires are not cached by page caching plugins. The initial server-side seed is rendered as an inline JSON variable; static page caches will cache this seed, but clicking Refresh or switching any filter always fetches live data.

= Is it mobile-friendly? =
Yes. On screens ≤ 768px the table switches to a card layout. Both White and Navy Blue themes are responsive and tested at 320px and up.

== Changelog ==

= 3.4.8 =
* Fix: 30D/7D toggle + Refresh block centred on desktop (was already centred on mobile)

= 3.4.7 =
* Fix: Wallet Compare accordion — icon and both text lines now centre horizontally as a group on desktop and mobile; chevron pinned absolutely to the right edge so it does not affect centring

= 3.4.6 =
* Fix: Compare accordion toggle-left changed to align-items:flex-start so the balance icon and text lines anchor at the top edge on desktop

= 3.4.5 =
* Fix: Compare accordion text lines always stacked vertically on all themes — replaced flex column with display:block!important to prevent WordPress theme overrides collapsing the two lines into one row

= 3.4.4 =
* Fix: "Why this wallet leads today" analysis box moved outside the 1fr/auto grid — now spans full width below the name/stats/buttons row in the Wallet of the Day card on desktop
* Fix: Compare accordion text — replaced <strong> and <span> (styled by WP themes) with plain <div> elements; zero theme interference possible

= 3.4.3 =
* Fix: Table first-row colour bleed — switched from border-collapse:separate + border-spacing to border-collapse:collapse with border-bottom dividers; eliminates any WP theme background leaking through row gaps
* Improvement: Table text sizes increased — wallet name, PNL, volume 13px → 15px; address 11px → 12px; column headers 11px → 12px; row padding increased for better readability

= 3.4.2 =
* Fix: Compare accordion "Side-by-side wallet comparison" and "Pick any two wallets" lines now vertically aligned on desktop — added explicit margin/padding/text-indent resets on the text wrapper

= 3.4.1 =
* Fix: Table header row blue/white split on desktop — added background:transparent!important to thead tr, tbody, tfoot so WordPress theme styles cannot bleed into row spacing gaps
* Fix: 7D volume always showing $0 — PHP normalise() now tries vol → volume → tradedAmount → notional fallback chain; JS shows — instead of $0 when volume is genuinely absent
* Fix: Share on X button on Wallet of the Day — desktop version moved under the wallet address (hidden on mobile); mobile version stays in the header row; both hrefs updated by JS
* Fix: 30D/7D toggle + Refresh block centred on mobile
* Fix: Navy Blue theme — root element now has border-radius:20px, 32px side padding, and a subtle box-shadow so the widget sits as a rounded card rather than flush to the page edges; mobile uses border-radius:14px and 16px padding

= 3.4.0 =
* New: White / Navy Blue theme selector in Settings; full CSS adaptation for navy dark background (tables, cards, controls, filters, compare tool, all states)
* New: 30D / 7D period toggle in the widget controls bar; switches API timePeriod between MONTH and WEEK
* Removed all transient and sessionStorage caching — always live, always fresh
* Period-aware labels: table PNL header, card stat labels, WotD stat label, crawler note, whyBadge copy, and WotD share tweet all reflect the selected period
* Admin page: live API status badge, theme picker with visual swatches, removed stale cache status table

= 3.3.2 =
* Complete CSS rewrite — ID-scoped, immune to theme overrides in both directions
* Heading pseudo-elements suppressed (::before / ::after)
* Action buttons redesigned: View Profile (deep navy) and CopyTrade (vivid indigo)
* WCAG AA compliance on positive PNL colour

= 3.3.0 =
* Repository renamed to polymarket-leaderboard-wordpress
* Setup wizard extended to cover Wallet of the Day and Compare Tool

= 3.2.1 =
* Slug renamed for WP.org guideline compliance
* First-run redirect to setup wizard

= 3.0.0 =
* Initial public release
* Server-side AJAX proxy, 1-hour transient cache, category filters, WOTD, compare tool, mobile card layout

== Upgrade Notice ==

= 3.4.8 =
* Fix: 30D/7D toggle + Refresh block centred on desktop (was already centred on mobile)

= 3.4.7 =
Pure CSS fixes for Compare accordion centring, table row colours, WOTD analysis full width, and 7D volume display. No breaking changes — existing shortcodes continue to work unchanged.
