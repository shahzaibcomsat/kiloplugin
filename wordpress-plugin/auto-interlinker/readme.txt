=== Auto Interlinker ===
Contributors: yourname
Tags: internal links, interlinking, SEO, automatic links, content linking
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically reads your articles and interlinks them on relevant keywords to improve SEO and user navigation.

== Description ==

**Auto Interlinker** is a powerful WordPress plugin that automatically analyzes your post content, extracts relevant keywords, and inserts contextual internal links between related articles — all without any manual effort.

### How It Works

1. **Keyword Extraction** — When a post is published or updated, the plugin automatically extracts the most relevant keywords and phrases from the title, content, excerpt, tags, and categories.
2. **Keyword Index** — All extracted keywords are stored in a database index, mapping each keyword to its source post.
3. **Automatic Interlinking** — When a visitor views a post, the plugin scans the content for keywords that match other posts in the index and automatically inserts hyperlinks.
4. **Smart Filtering** — The plugin avoids self-links, duplicate links, and links inside existing `<a>`, `<code>`, `<pre>`, or `<script>` tags.

### Features

* ✅ Automatic keyword extraction from post content, title, tags, and categories
* ✅ Multi-word phrase detection (bigrams and trigrams)
* ✅ Configurable maximum links per post
* ✅ Option to link each keyword only once per post
* ✅ Support for multiple post types (posts, pages, custom post types)
* ✅ Custom keyword management — add or remove keywords per post
* ✅ Link log to track all inserted interlinks
* ✅ Bulk reprocess all posts with one click
* ✅ Hourly cron job for background processing
* ✅ Open links in new tab option
* ✅ Nofollow attribute option
* ✅ Exclude specific posts from interlinking
* ✅ Transient caching for performance
* ✅ Clean admin UI with dashboard, settings, keywords, and log pages

### Settings

* **Enable/Disable** — Master toggle for the entire plugin
* **Post Types** — Choose which post types to scan and interlink
* **Max Links Per Post** — Limit the number of interlinks per article
* **Max Keywords Per Post** — Control how many keywords are indexed per post
* **Minimum Keyword Length** — Filter out short/common words
* **Link Once** — Only link the first occurrence of each keyword
* **Open in New Tab** — Add `target="_blank"` to all interlinks
* **Nofollow** — Add `rel="nofollow"` to all interlinks
* **Exclude Post IDs** — Prevent specific posts from being linked

== Installation ==

1. Upload the `auto-interlinker` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Auto Interlinker → Dashboard** and click **Reprocess All Posts** to build the initial keyword index.
4. Configure settings under **Auto Interlinker → Settings**.

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. The keyword index is built in the background (on post save or via cron). The interlinking engine uses a 5-minute transient cache to avoid repeated database queries on each page view.

= Can I add custom keywords? =

Yes! Go to **Auto Interlinker → Keywords**, enter a post ID and keyword, and click **Add Keyword**. Custom keywords are marked separately and won't be overwritten by automatic extraction.

= How do I prevent a post from being linked? =

Add the post ID to the **Exclude Post IDs** field in Settings.

= Will it link to the same post multiple times? =

No. The plugin ensures each target post is only linked once per source post per page view.

= Does it modify my post content in the database? =

No. Links are injected dynamically via the `the_content` filter and are never saved to the database. Your original content remains untouched.

== Screenshots ==

1. Dashboard with stats and quick actions
2. Settings page
3. Keywords index with accordion view
4. Link log

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
