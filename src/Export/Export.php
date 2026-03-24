<?php

namespace Netivo\Module\WooCommerce\Feed\Export;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

abstract class Export {
	protected string $name;

	public abstract function start(): void;

	public abstract function proceed(): void;

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