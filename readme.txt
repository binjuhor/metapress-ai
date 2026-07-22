=== MetaPress AI ===
Contributors: binjuhor
Tags: seo, ai, yoast, open graph, metadata
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: MIT
License URI: https://opensource.org/license/mit

Generate editable SEO, Open Graph, and X/Twitter metadata with AI for WordPress content.

== Description ==

MetaPress AI generates SEO and social metadata for posts, pages, and selected custom post types. Editors can review three suggestions in the editor or select multiple items in a WordPress list table and run Generate SEO metadata with AI. Bulk generation processes items sequentially and applies the first validated suggestion.

Supported providers are OpenAI, DeepSeek, Google Gemini, Anthropic Claude, and Ollama. Post content is sent only to the active provider when an authorized editor starts generation. Provider API usage may incur charges.

== Installation ==

1. Activate MetaPress AI.
2. Open Settings > MetaPress AI.
3. Select an AI provider, configure its API key and model, and select enabled post types.
4. Edit a post and use the MetaPress AI panel.

For production, API keys may be defined in wp-config.php as `METAPRESS_AI_OPENAI_API_KEY`, `METAPRESS_AI_DEEPSEEK_API_KEY`, `METAPRESS_AI_GEMINI_API_KEY`, or `METAPRESS_AI_CLAUDE_API_KEY`.

== Changelog ==

= 1.1.0 =
* Add bulk generation for enabled post types.
* Add OpenAI, DeepSeek, Gemini, Claude, and Ollama providers.

= 1.0.0 =
* Initial release.
