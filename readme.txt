=== MetaPress AI ===
Contributors: binjuhor
Tags: seo, ai, yoast, open graph, metadata
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/license/mit

Generate editable SEO, Open Graph, and X/Twitter metadata with AI for WordPress content.

== Description ==

MetaPress AI generates three metadata suggestions for posts, pages, and selected custom post types. Editors review and apply a suggestion before saving it to Yoast SEO metadata fields.

Post content is sent to OpenAI only when an authorized editor clicks Generate. An OpenAI API account is required and API usage may incur charges.

== Installation ==

1. Activate MetaPress AI.
2. Open Settings > MetaPress AI.
3. Configure an OpenAI API key and select enabled post types.
4. Edit a post and use the MetaPress AI panel.

For production, define `METAPRESS_AI_OPENAI_API_KEY` in wp-config.php instead of storing the key in WordPress.

== Changelog ==

= 1.0.0 =
* Initial release.
