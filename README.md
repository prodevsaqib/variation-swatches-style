# Variation Swatches Style

Renders WooCommerce variable product attributes as color swatches or button
swatches instead of plain `<select>` dropdowns, and automatically falls back
to a clean, styled dropdown once an attribute has more than 3 options — so
color pickers stay compact and long lists (flavors, sizes, etc.) stay usable.

## Features

- **Color swatches** — circular swatches for any attribute named "Color" (or
  "Colour"), colored via:
  1. A color picked manually with the native WordPress color picker (see
     **Usage** below — the picker lives in a different place depending on
     whether the attribute is global or per-product).
  2. Color code metadata saved on the variation (`_zilon_color_name` /
     `_zilon_color_value`), if your import pipeline provides it.
  3. The option's own name, when it's a valid CSS color keyword (e.g. "Red").
- **Button swatches** — for any other attribute with 3 or fewer options.
- **Styled dropdown** — as soon as *any* attribute on a product has more than
  3 options, every attribute on that product renders as a styled dropdown
  instead, so the selectors look consistent.
- **Out-of-stock indication** — a swatch is crossed out when its value, in
  combination with whatever else is currently selected, only matches
  out-of-stock variations.
- Keeps WooCommerce's native `<select>` in the DOM (hidden) so all of
  WooCommerce's own variation/price/stock JavaScript keeps working unmodified.
- **Settings page** (**WooCommerce → Swatches Style**) to control the dropdown
  option limit, whether it also applies to color attributes, swatch shapes
  (circle/square, rounded/square buttons), the accent color used for the
  selected state, and whether the selected-value label and out-of-stock
  indicator are shown at all — with a live preview.

## Requirements

- WordPress + WooCommerce (variable products)

## Installation

1. Download or clone this repository into `wp-content/plugins/`.
2. Activate **Onlinehub Variation Swatches** from the Plugins screen.

## Usage

Swatches render automatically on the single product page for any variable
product — no configuration needed.

To pick exact color codes for a "Color" attribute, where you do that depends
on whether the attribute is **global** (a shared taxonomy, reused across
products) or **custom** (defined on one product only):

- **Global "Color" attribute** — go to **Products → Attributes → Color →
  Configure terms**. Each term (e.g. "Gold", "Navy Blue") gets its own color
  field with a picker, on both the "Add new" and "Edit" term screens. The
  color is saved once per term and reused by every product that uses it.
- **Custom "Color" attribute** (added directly on a product's Attributes tab,
  not a global taxonomy) — edit the product, open **Product Data → Attributes**,
  and expand the "Color" attribute row. A color-picker field appears inline
  for each value, right under "Used for variations". Values are saved per
  product, since custom attributes aren't shared.

In both cases, save/update after picking colors — the swatch reads them on
the next page load.

## Settings

Go to **WooCommerce → Swatches Style** to control how swatches render
site-wide, with a live preview that updates as you change values:

- **Dropdown fallback** — the option-count limit before an attribute becomes
  a dropdown, whether that limit also applies to color attributes, and a
  comma-separated list of attribute names to leave as the plain WooCommerce
  dropdown entirely (e.g. "Length, Material").
- **Appearance** — color swatch shape (circle/square), button shape
  (rounded/square corners), swatch size (small/medium/large), and the accent
  color used to highlight the selected value.
- **Behavior** — toggle the "Attribute : Value" selected-value label, the
  color-name hover tooltip, the out-of-stock indicator, and its style
  (diagonal line, faded, or hidden entirely).
- **Reset to Defaults** — one click to clear all of the above back to their
  defaults.
- Both **Save Changes** and **Reset to Defaults** run over AJAX (no page
  reload) and confirm with a toast notification; the page still degrades to
  a normal full-page submit if JavaScript is unavailable.

## License

GPL-2.0-or-later
