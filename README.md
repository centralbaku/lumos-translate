# Lumos Translate

A Polylang-style multilingual plugin for WordPress that lets you translate **everything** — from pages to hard-coded strings in your theme.

## What can be translated

| Content | How |
|---|---|
| Pages, posts, custom post types | "Languages" box in the editor → **+ Add translation** (duplicates content, meta, terms as a draft) |
| Categories, tags, custom taxonomies | Language selector on the term form; link translations by term ID |
| Menus | Each menu location gets a per-language copy (`Primary (Français)`); menu links auto-point to translated pages |
| Theme & plugin gettext strings (`__()`, `_e()`, `esc_html__()`, `_x()`, `_n()`…) | Auto-discovered while browsing the site, or via **Theme Scanner** |
| **Hard-coded text** in theme templates (never wrapped in `__()`) | Theme Scanner extracts literal HTML text & attributes; replaced in the final HTML output |
| Site title, tagline, any option, Customizer theme mods | Settings tab → list option / theme_mod names |
| Widgets | Titles and text widget content |
| Anything else | `lumos_t( 'text' )` / `lumos_e( 'text' )`, or add a custom string in the Strings tab |

## URLs
Default language has no prefix; others use `/fr/…`. `hreflang` tags and `<html lang>` / `dir="rtl"` are added automatically.

## Switcher
- Shortcode: `[lumos_switcher style="list|dropdown" flags="1" names="1"]`
- Widget: **Lumos Language Switcher**
- PHP: `<?php lumos_tr_switcher(); ?>`

## Polylang compatibility
`pll__`, `pll_e`, `pll_register_string`, `pll_current_language`, `pll_get_post`, `pll_home_url`, `pll_languages_list` are provided (only if Polylang is not active).

## Install
Copy the folder to `wp-content/plugins/lumos-translate`, activate, then go to **Lumos Translate → Languages**.

## Import / export
Strings tab → export/import JSON (all strings with all translations).
