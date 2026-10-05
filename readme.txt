=== Cart Category Sort ===
Contributors: ysaintlary
Tags: cart, sort, woocommerce, category, order
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Sorts WooCommerce cart items into fixed groups defined by product category or product ID.

== Description ==

Cart Category Sort organises cart line items into a predefined order of groups, based on product categories or specific product IDs.

The sort is applied server-side on the cart contents array, so the Cart block, mini-cart, Checkout block, and resulting orders all display items in the same order.

= Features =

* Server-side sorting — no JavaScript, no extra markup
* Works with the Cart and Checkout blocks (Store API)
* Preserves add-to-cart order within each group
* Skips sorting when Product Bundles or Composite Products items are detected
* Filterable group configuration via the `ccs_sort_groups` PHP filter
* HPOS compatible

== Installation ==

1. Upload the `cart-category-sort` folder to `/wp-content/plugins/`
2. Activate the plugin through the "Plugins" menu in WordPress
3. Cart items are automatically sorted — no configuration screen needed

To customise the groups, use the `ccs_sort_groups` filter in your theme or a custom plugin.

== Changelog ==

= 1.1.0 =
* Feature - Tri alphabétique des produits au sein de chaque groupe.


= 1.0.1 =
* Fix - fix: normalize dependabot.yml line endings for cross-platform CI.
* Fix - fix: normalize line endings to LF via .gitattributes.
* Add - ci: switch to GitHub automation profile, add CI workflows.
* Add - feat: initial plugin setup with wp-plugin-base v1.10.0 (local profile).
* Dev - Initial plugin files: cart-category-sort.php, readme.txt, .wp-plugin-base.env.


= 1.0.0 =
* Initial release
