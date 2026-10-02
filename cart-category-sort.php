<?php
/**
 * Plugin Name:       YS | Cart Category Sort
 * Plugin URI:        https://github.com/ysaintlary/cart-category-sort
 * Description:       Trie les lignes du panier WooCommerce selon un ordre fixe de groupes définis par catégorie ou par produit.
 * Version: 1.2.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Yves Saint-Lary
 * Author URI:        https://ysaintlary.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       cart-category-sort
 * Domain Path:       /languages
 *
 * WC requires at least: 8.0
 * WC tested up to:      9.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCS_VERSION', '1.2.0' );

require_once __DIR__ . '/lib/wp-plugin-base/wp-plugin-base-runtime-updater.php';

/*
 * ──────────────────────────────────────────────
 *  Tableau des groupes de tri
 *
 *  Chaque entrée définit un groupe avec :
 *    - 'rang'       : position dans le panier (1 = en haut)
 *    - 'type'       : 'category' ou 'product'
 *    - 'ids'        : identifiants de catégories ou de produits
 *
 *  Le groupe « Autres » (rang 6) est implicite :
 *  tout produit hors groupes 1 à 5 y est placé.
 *
 *  Pour modifier sans toucher au plugin, utiliser
 *  le filtre 'ccs_sort_groups'.
 * ──────────────────────────────────────────────
 */
$ccs_default_groups = array(
	array(
		'rang' => 1,
		'nom'  => 'Sleeve',
		'type' => 'category',
		'ids'  => array( 45 ),
	),
	array(
		'rang' => 2,
		'nom'  => 'Tin box',
		'type' => 'product',
		'ids'  => array( 3779 ),
	),
	array(
		'rang' => 3,
		'nom'  => 'Bouchées',
		'type' => 'category',
		'ids'  => array( 75 ),
	),
	array(
		'rang' => 4,
		'nom'  => 'Barres de chocolat',
		'type' => 'category',
		'ids'  => array( 44 ),
	),
	array(
		'rang' => 5,
		'nom'  => 'Display',
		'type' => 'category',
		'ids'  => array( 40, 61, 68 ),
	),
);

/* Rang attribué aux produits qui ne correspondent à aucun groupe. */
define( 'CCS_OTHER_GROUP_RANK', 99 );


/* ─── Compatibilité HPOS et blocs Panier / Commande ─── */

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__ );
	}
} );


/* ─── Fonctions utilitaires ─── */

/**
 * Retourne le tableau des groupes, filtrable.
 *
 * @return array
 */
function ccs_get_sort_groups() {
	global $ccs_default_groups;
	return apply_filters( 'ccs_sort_groups', $ccs_default_groups );
}

/**
 * Détermine le rang du groupe pour un produit donné.
 *
 * Le résultat est mis en cache dans une variable statique
 * pour éviter des requêtes répétées pendant la même requête PHP.
 *
 * @param int $product_id Identifiant du produit (ou du parent pour une variation).
 * @return int Rang du groupe, ou CCS_OTHER_GROUP_RANK si aucun groupe ne correspond.
 */
function ccs_get_product_group( $product_id ) {
	static $cache = array();

	if ( isset( $cache[ $product_id ] ) ) {
		return $cache[ $product_id ];
	}

	$groups = ccs_get_sort_groups();

	/* Trier les groupes par rang pour respecter la priorité en cas de conflit. */
	usort( $groups, function ( $a, $b ) {
		return $a['rang'] - $b['rang'];
	} );

	$category_ids = null;

	foreach ( $groups as $group ) {
		if ( 'product' === $group['type'] ) {
			if ( in_array( $product_id, $group['ids'], true ) ) {
				$cache[ $product_id ] = $group['rang'];
				return $group['rang'];
			}
		} elseif ( 'category' === $group['type'] ) {
			/* Charger les catégories du produit une seule fois. */
			if ( null === $category_ids ) {
				$terms = get_the_terms( $product_id, 'product_cat' );
				$category_ids = ( $terms && ! is_wp_error( $terms ) )
					? wp_list_pluck( $terms, 'term_id' )
					: array();
			}
			if ( array_intersect( $category_ids, $group['ids'] ) ) {
				$cache[ $product_id ] = $group['rang'];
				return $group['rang'];
			}
		}
	}

	$cache[ $product_id ] = CCS_OTHER_GROUP_RANK;
	return CCS_OTHER_GROUP_RANK;
}

/**
 * Vérifie si le panier contient des produits liés
 * (Product Bundles ou Composite Products).
 *
 * @param array $cart_contents Contenu du panier.
 * @return bool
 */
function ccs_has_linked_products( $cart_contents ) {
	foreach ( $cart_contents as $item ) {
		if ( isset( $item['bundled_by'] ) || isset( $item['bundled_items'] ) ) {
			return true;
		}
		if ( isset( $item['composite_parent'] ) || isset( $item['composite_children'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Trie les lignes du panier selon les groupes définis.
 *
 * Tri stable : les produits d'un même groupe conservent
 * leur ordre d'ajout au panier.
 */
function ccs_sort_cart() {
	if ( ! did_action( 'woocommerce_init' ) ) {
		return;
	}

	$cart = WC()->cart;
	if ( ! $cart || empty( $cart->cart_contents ) ) {
		return;
	}

	if ( ccs_has_linked_products( $cart->cart_contents ) ) {
		return;
	}

	/* Calculer le rang, le nom et l'index d'origine de chaque ligne. */
	$positions = array();
	$index     = 0;
	foreach ( $cart->cart_contents as $key => $item ) {
		$product    = $item['data'] ?? null;
		$product_id = $item['product_id'] ?? 0;

		/* Pour une variation, utiliser l'identifiant du produit parent. */
		if ( $product instanceof \WC_Product_Variation ) {
			$product_id = $product->get_parent_id();
		}

		$name = $product instanceof \WC_Product ? $product->get_name() : '';

		$positions[ $key ] = array(
			'group' => ccs_get_product_group( $product_id ),
			'name'  => mb_strtolower( $name ),
			'index' => $index++,
		);
	}

	/* Tri stable par rang de groupe, puis alphabétique, puis par ordre d'ajout. */
	uksort( $cart->cart_contents, function ( $a, $b ) use ( $positions ) {
		$diff = $positions[ $a ]['group'] - $positions[ $b ]['group'];
		if ( 0 !== $diff ) {
			return $diff;
		}
		$cmp = strnatcasecmp( $positions[ $a ]['name'], $positions[ $b ]['name'] );
		if ( 0 !== $cmp ) {
			return $cmp;
		}
		return $positions[ $a ]['index'] - $positions[ $b ]['index'];
	} );
}


/* ─── Accrochage aux événements du panier ─── */

/* Tri au chargement du panier depuis la session (couvre le bloc Panier,
   le mini-panier, la page de commande et les requêtes Store API
   de mise à jour de quantité ou de suppression). */
add_action( 'woocommerce_cart_loaded_from_session', 'ccs_sort_cart', 100 );

/* Tri immédiat après ajout d'un produit, pour que la réponse
   Store API soit déjà dans le bon ordre. */
add_action( 'woocommerce_add_to_cart', 'ccs_sort_cart', 100 );
