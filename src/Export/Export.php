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

		foreach( $ids as $id ) {
			$terms[] = get_term_by( 'term_taxonomy_id', $id, 'product_brand' );
		}


		return $terms;
	}

}