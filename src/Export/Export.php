<?php

namespace Netivo\Module\WooCommerce\Feed\Export;

use WC_Product;
use XMLWriter;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

abstract class Export {
	protected string $name;

	public function start(): void {
		global $wpdb;
		$current_export = get_option( '_nt_export_' . $this->name );

		if ( ! empty( $current_export ) ) {
			echo 'Export already started <br />';
			exit;
		}

		$parts_directory = WP_CONTENT_DIR . '/uploads/integration/' . $this->name . '/';
		$this->prepare_parts_directory( $parts_directory );

		$sql = "SELECT ID FROM {$wpdb->posts} AS posts
          WHERE (posts.post_type='product' OR posts.post_type='product_variation') AND posts.post_status='publish'";

		$current_export = $wpdb->get_results( $sql, ARRAY_N );
		update_option( '_nt_export_' . $this->name, $current_export );
		update_option( '_nt_export_part_' . $this->name, 0 );
	}

	public function proceed(): void {
		ini_set( 'memory_limit', '1000M' );
		$products_array = get_option( '_nt_export_' . $this->name );
		$part_size      = get_option( '_nt_export_part_size_' . $this->name, 1000 );
		$products       = array_slice( $products_array, 0, $part_size, true );

		$parts_directory = WP_CONTENT_DIR . '/uploads/integration/' . $this->name . '/';

		$part = get_option( '_nt_export_part_' . $this->name, null );

		if ( null === $part ) {
			echo 'Export already finished';
			exit;
		}
		$part_filename = 'part_' . $part . '.xml';

		$xml = new XMLWriter();
		$xml->openMemory();

		echo 'Exporting part ' . $part . '<br />';

		$excluded_product_ids = apply_filters( 'netivo/woocommerce/feed/excluded_product_ids', [] );

		foreach ( $products as $index => $product_id ) {
			if ( in_array( $product_id[0], $excluded_product_ids ) ) {
				unset( $products_array[ $index ] );
				continue;
			}

			$product = wc_get_product( $product_id[0] );

			if ( $product->get_type() === 'variable' ) {
				unset( $products_array[ $index ] );
				continue;
			}

			$this->parse_product_xml( $product, $product_id[0], $xml );

			unset( $products_array[ $index ] );
		}

		$file_put_success = file_put_contents( $parts_directory . $part_filename, $xml->flush( true ) );
		if ( $file_put_success ) {
			update_option( '_nt_export_part_' . $this->name, $part + 1 );
		}

		if ( empty( $products_array ) ) {
			echo 'Export finished';
			$this->finish( $xml, $part + 1 );
		}

		update_option( '_nt_export_' . $this->name, $products_array );
	}

	protected abstract function parse_product_xml( WC_Product $product, string|int $product_id, XMLWriter $xml ): void;

	protected abstract function finish( XMLWriter $xml, string|int $part_count ): void;

	protected function get_brand_by_id( $id ): string {
		if ( empty( $id ) ) {
			return '';
		}

		$term = get_term_by( 'term_taxonomy_id', $id, 'product_brand' );

		return $term ? $term->term_name : '';
	}

	protected function get_brand_array_by_id( $ids ): array {
		if ( empty( $ids ) ) {
			return [];
		}

		$terms = [];

		foreach ( $ids as $id ) {
			$term    = get_term_by( 'term_taxonomy_id', $id, 'product_brand' );
			$terms[] = $term->name;
		}


		return $terms;
	}

	//TODO: Add filters
	protected function get_data_for_product( WC_Product $product, string|int $product_id ): array {
		$product_type = $product->get_type();
		$is_variation = ( $product_type === 'variation' );

		$sku          = $product->get_sku();
		$ean          = $product->get_global_unique_id();
		$product_name = $product->get_name();
		$description  = htmlspecialchars( $product->get_description(), ENT_XML1 );
		$product_link = get_permalink( $product_id );

		$product_price      = ( $product_type === 'package' ) ? $product->get_regular_price() : $product->get_regular_price( 'normal' );
		$product_sale_price = ( $product_type === 'package' ) ? round( (float) $product->get_sale_price(), 2 ) : round( (float) $product->get_sale_price( 'normal' ), 2 );

		$availability = ( $product->is_purchasable() > 0 ) ? 'in stock' : 'out of stock';

		$image_link = wp_get_attachment_image_url( $product->get_image_id(), 'full' );
		$gallery    = [];

		if ( ! $is_variation ) {
			$gallery = $product->get_gallery_image_ids();
			if ( ! empty( $gallery ) ) {
				foreach ( $gallery as &$element ) {
					$element = wp_get_attachment_image_url( $element, 'full' );
				}
			}
		}

		$category = $product->get_category_ids();

		if ( ! empty( $category ) ) {
			$category = get_term( $category[0] );
		} else {
			$category = '';
		}

		$brand_ids = $product->get_brand_ids();
		$brands    = $this->get_brand_array_by_id( $brand_ids );

		$product_real_price = ( $product_sale_price > 0 ) ? $product_sale_price : $product_price;
		$costs              = $this->calculate_shipping_costs( $product, $product_real_price );

		if ( $is_variation ) {
			$parent_id = $product->get_parent_id();
			$parent    = wc_get_product( $parent_id );

			$parent_status = $parent->get_status();

			if ( $parent_status !== 'publish' ) {
				return [];
			}

			$product_link = get_permalink( $parent_id );

			$category = $parent->get_category_ids();
			$category = get_term( $category[0] );

			$brand_ids = $parent->get_brand_ids();
			$brands    = $this->get_brand_array_by_id( $brand_ids );

			if ( empty( $image_link ) ) {
				$image_link = wp_get_attachment_image_url( $parent->get_image_id(), 'full' );
			}

			if ( empty( $description ) ) {
				$description = htmlspecialchars( $parent->get_description(), ENT_XML1 );
			}
		}

		if ( $brands ) {
			$brands = implode( ', ', $brands );
		} else {
			$brands = '';
		}

		return [
			'id'           => $product_id,
			'sku'          => $sku,
			'ean'          => $ean,
			'type'         => $product_type,
			'name'         => $product_name,
			'description'  => $description,
			'category'     => $category->name,
			'brands'       => $brands,
			'link'         => $product_link,
			'price'        => $product_price,
			'sale_price'   => $product_sale_price,
			'real_price'   => $product_real_price,
			'availability' => $availability,
			'image_link'   => $image_link,
			'gallery'      => $gallery,
			'costs'        => $costs,
		];
	}

	protected function prepare_parts_directory( $parts_directory ): void {
		if ( ! is_dir( $parts_directory ) ) {
			mkdir( $parts_directory, 0777, true );
		}

		$files = array_diff( scandir( $parts_directory ), [ '.', '..' ] );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				unlink( $parts_directory . $file );
			}
		}
	}

	protected function calculate_shipping_costs( $product, $price ): array {
		$costs   = [];
		$package = [
			'contents'      => [
				[
					'data'              => $product,
					'quantity'          => 1,
					'line_total'        => $price,
					'line_subtotal'     => $price,
					'line_subtotal_tax' => 0,
					'line_tax'          => 0,
				]
			],
			'destination'   => [
				'country'  => 'PL',
				'state'    => '',
				'postcode' => '',
			],
			'contents_cost' => $price,
		];

		$shipping                  = WC()->shipping->calculate_shipping_for_package( $package );
		$excluded_shipping_methods = apply_filters( 'netivo/woocommerce/feed/excluded_shipping_methods', [], $product );

		foreach ( $shipping['rates'] as $shiping => $shipping_rate ) {
			$shipping_label      = $shipping_rate->get_label();
			$shipping_id         = $shipping_rate->get_id();
			$shipping_meta       = $shipping_rate->get_meta_data();
			$shipping_free_label = $shipping_meta['free_shipping_label'] ?? null;
			$shipping_cost       = $shipping_rate->get_cost();
			if ( in_array( $shipping_id, $excluded_shipping_methods ) || in_array( $shipping_label, $excluded_shipping_methods ) || str_contains( strtolower( $shipping_label ), 'odbiór osobisty' ) ||
			     ( str_contains( strtolower( $shipping_label ), 'inpost' ) && str_contains( strtolower( $shipping_label ), 'paczkomat' ) ) ) {
				continue;
			}

			if ( isset( $shipping_free_label ) && (int) $shipping_cost == 0 && ! str_contains( strtolower( $shipping_label ), strtolower( $shipping_free_label ) ) ) {
				continue;
			}

			$costs[ $shipping_label ] = $shipping_cost;
		}

		return $costs;
	}

}