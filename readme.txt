=== Polymarket Leaderboard – Live Top Wallets for WordPress ===
Contributors: bounmee
Tags: polymarket, prediction markets, leaderboard, trading, wallets
Requires at least: 5.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.3.2
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed the live Polymarket top-wallet leaderboard on any WordPress page with one shortcode. Server-side API proxy, category filters, 1-hour cache.

== Description ==

**Polymarket Leaderboard** embeds a live top-wallet leaderboard from the official Polymarket Data API on any WordPress page using a single shortcode.

The plugin fetches the top 10 Polymarket traders ranked by 30-day PNL and renders them in a fast, responsive widget. All API calls are proxied server-side through WordPress — no CORS issues on any host. Data is cached per category for one hour.

**Leaderboard & Data:**

* Live top-10 wallets ranked by 30-day PNL
* Category filters: All, Politics, Crypto, Sports, Culture, Tech, Finance, Macro
* Wallet rank badges, verified badges, X (Twitter) profile links
* Volume and PNL displayed in readable format ($12.4K, $1.2M)
* Auto-refreshes on category switch; manual refresh button

**Wallet of the Day** *(optional — enable in Settings)*

* Spotlights the #1 ranked wallet with a performance breakdown card
* Auto-generated analysis: PNL/volume efficiency, category edge, rank
* Share to X button pre-filled with wallet stats
* One-click copy of wallet address

**Wallet Compare Tool** *(optional — enable in Settings)*

* Pick any two wallets from the current leaderboard
* Side-by-side comparison: 30-day PNL, volume, PNL/volume ratio, rank
* Bar charts for at-a-glance visual comparison
* Verdict summary with winner and efficiency analysis

**Technical:**

* Server-side AJAX proxy — no CORS issues on any host
* Transient cache: 1 hour per category, 60-second retry on API error
* Zero first-paint spinner — leaderboard seeded server-side on page load
* Mobile responsive: full table on desktop, card layout on small screens
* Pure vanilla JS — no jQuery or external JS dependencies
* Compatible with all page caching plugins (WP Rocket, W3 Total Cache, etc.)
* Admin cache status panel under Settings → Polymarket LB
* Falls back to anonymised demo data if the Polymarket API is unreachable
* Fully theme-isolated CSS — scoped under a unique ID, immune to theme interference

---

**Shortcode:**

`[polymarket_leaderboard]`

With a default category pre-selected:

`[polymarket_leaderboard category="CRYPTO"]`

Valid values: `OVERALL` `POLITICS` `SPORTS` `CRYPTO` `CULTURE` `ECONOMICS` `TECH` `FINANCE`

---

**External API:**

Connects to `https://data-api.polymarket.com/v1/leaderboard` for public leaderboard data only. No user data is sent. See Privacy Policy below.

== Installation ==

1. Download the zip and upload the `polymarket-leaderboard-wordpress` folder to `/wp-content/plugins/`, or install via **Plugins → Add New → Upload Plugin**.
2. Activate via **Plugins → Installed Plugins**.
3. Add `[polymarket_leaderboard]` to any page or post.
4. Visit **Settings → Polymarket LB** to optionally enable the Wallet of the Day card and the wallet compare tool, and to manage the API cache.

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. Scripts and API calls only fire on pages where `[polymarket_leaderboard]` is placed. Nothing loads globally.

= What happens if the Polymarket API is down? =

The widget automatically falls back to anonymised demo data. Cached responses protect against brief outages.

= Can I set a default category? =

Yes. `[polymarket_leaderboard category="POLITICS"]` pre-selects a category on load. Valid values: OVERALL, POLITICS, SPORTS, CRYPTO, CULTURE, ECONOMICS, TECH, FINANCE.

= Does it work with page caching plugins? =

Yes. Leaderboard data is seeded server-side on first load and AJAX refreshes happen client-side, so WP Rocket, W3 Total Cache, and similar are fully compatible.

= Can I place this on multiple pages? =

Yes. The transient cache is shared — the Polymarket API is hit at most once per category per hour regardless of how many pages use the shortcode.

= Will the widget break my theme's styles? =

No. All widget CSS is scoped under a unique `#ttg-lb-root` ID and uses ID-level specificity throughout. It cannot affect your theme's styles, and your theme cannot override the widget's styles.

= What data does this plugin collect? =

None. The plugin only fetches public data from the Polymarket API. No visitor data is collected or transmitted anywhere.

== Privacy Policy ==

This plugin does not collect, store, or transmit any personal data about your visitors.

On page load the plugin makes a server-side request to the public Polymarket Data API (`https://data-api.polymarket.com`) to retrieve leaderboard data. The request contains no user information — only query parameters for category and result limit. The response is cached in WordPress Transients and served to all visitors.

No analytics, tracking pixels, or third-party beacons are included.

== Screenshots ==

1. Leaderboard table with category filters, rank badges, and wallet details
2. Wallet of the Day spotlight card with auto-generated performance analysis
3. Side-by-side wallet compare tool with bar charts
4. Admin settings panel — enable optional features, manage cache

== Changelog ==

= 3.3.2 =
* Complete CSS isolation rewrite — all styles scoped under `#ttg-lb-root` with ID-level specificity, preventing any theme from overriding widget styles or injecting decorative borders/bars on headings
* Action buttons redesigned: "View Profile" (deep navy) and "⚡ CopyTrade" (vivid indigo) with guaranteed white text
* H2 heading bars removed — `::before` / `::after` pseudo-elements suppressed
* Added 30px top padding and 50px bottom padding to widget wrapper
* Table cell padding, row shadows, and border-collapse protected against theme resets
* Filter pill active state, rank badges, PNL colours, and all interactive states locked with ID-scoped selectors

= 3.3.0 =
* Renamed repository to polymarket-leaderboard-wordpress
* Setup wizard covers Wallet of the Day and Compare Tool

= 3.2.1 =
* Slug renamed for WP.org guideline 17 compliance
* Removed inline HTML credit from public page output

= 3.2.0 =
* First-run setup wizard on activation
* Settings page: opt-in controls for all optional features

= 3.1.0 =
* Full CSS bundle self-contained, Google Fonts via wp_enqueue_style
* uninstall.php, License URI, escaping improvements

= 3.0.0 =
* Initial release: server-side proxy, transient cache, category filters
* Wallet of the Day, compare tool, mobile cards, demo data fallback

== Upgrade Notice ==

= 3.3.2 =
CSS hardening release. All widget styles are now fully isolated from your theme. Recommended upgrade for all users.
