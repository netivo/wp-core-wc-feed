<?php

namespace Netivo\Module\WooCommerce\Feed\Export;

class Google extends Export {
	protected string $name = 'google';

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
          LEFT JOIN {$wpdb->postmeta} AS meta1 ON meta1.post_id = posts.ID AND meta1.meta_key = '_export_google'
          WHERE posts.post_type='product' AND posts.post_status='publish'";

		$current_export = $wpdb->get_results( $sql, ARRAY_N );
		update_option( '_nt_export_' . $this->name, $current_export );
		update_option( '_nt_export_part_' . $this->name, 0 );
	}

	public function proceed(): void {
		$products_array = get_option( '_nt_export_' . $this->name );
		$products       = array_slice( $products_array, 0, 1000 );

		$parts_directory = WP_CONTENT_DIR . '/uploads/integration/' . $this->name . '/';

		$part          = get_option( '_nt_export_part_' . $this->name );
		$part_filename = 'part_' . $part . '.xml';

		$shipping_methods           = \WC_Shipping_Zones::get_zone( 1 )->get_shipping_methods( true );
		$processed_shipping_methods = $this->process_shipping_methods( $shipping_methods );

		$xml = new \XMLWriter();
		$xml->openMemory();

		foreach ( $products as $index => $product_id ) {
			$product = wc_get_product( $product_id[0] );

			$this->parse_product_xml( $product, $product_id[0], $xml, $processed_shipping_methods );

			unset( $products_array[ $index ] );
		}

		$file_put_success = file_put_contents( $parts_directory . $part_filename, $xml->flush( true ) );
		if ( $file_put_success ) {
			update_option( '_nt_export_part_' . $this->name, $part + 1 );
		}

		if ( empty( $products_array ) ) {
			$this->finish( $xml, $part + 1 );
		}

		update_option( '_nt_export_' . $this->name, $products_array );
	}

	protected function finish( $xml, $part_count ): void {
		if ( file_exists( ABSPATH . '/export_google.xml' ) ) {
			unlink( ABSPATH . '/export_google.xml' );
		}

		$title    = get_option( 'nt_feed_title' . $this->name );
		$site_url = get_option( 'nt_feed_url' );
		$desc     = get_option( 'nt_feed_description' . $this->name );

		$xml->flush(); // wyczyszczenie bufora

		$xml->startDocument( '1.0', 'UTF-8' );
		$xml->setIndent( true );
		$xml->setIndentString( '  ' );
		$xml->startElement( 'rss' );
		$xml->writeAttribute( 'version', '2.0' );
		$xml->writeAttributeNs( 'xmlns', 'g', null, 'http://base.google.com/ns/1.0' );
		$xml->startElement( 'channel' );
		$xml->writeElement( 'title', $title );
		$xml->writeElement( 'link', $site_url );
		$xml->writeElement( 'description', $desc );

		$export_directory = WP_CONTENT_DIR . '/uploads/integration/' . $this->name . '/';
		for ( $i = 0; $i < $part_count; $i ++ ) {
			$export_filename = 'part_' . $i . '.xml';
			$xml->writeRaw( file_get_contents( $export_directory . $export_filename ) );
		}

		$xml->endElement();
		$xml->endElement();
		$xml->endDocument();

		file_put_contents( ABSPATH . '/export_google.xml', $xml->flush() );

		delete_option( '_nt_export_google' );
		delete_option( '_nt_export_google_part' );
	}

	protected function parse_product_xml( $product, $product_id, $xml, $processed_shipping_methods ) {
		$xml->setIndent( true );
		$xml->setIndentString( '      ' );
		$xml->startElement( 'item' );
		{
			$category = $product->get_category_ids();
			$category = get_term( $category[0] );

			$xml->writeElementNs( 'g', 'id', null, $product_id );

			$xml->startElement( 'title' );
			{
				$xml->writeCdata( str_replace( '&', '&amp;', $product->get_name() ) );
			}
			$xml->endElement();

			$xml->writeElement( 'link', get_permalink( $product_id ) );


			$xml->startElement( 'description' );
			{
				$xml->writeCdata( htmlspecialchars( $product->get_description(), ENT_XML1 ) );
			}
			$xml->endElement();

			$xml->writeElementNs( 'g', 'price', null, ( ( $product->get_type() === 'package' ) ? $product->get_regular_price() : $product->get_regular_price( 'normal' ) ) . ' PLN' );
			if ( $product->is_on_sale() ) {
				$xml->writeElementNs( 'g', 'sale_price', null, ( ( $product->get_type() === 'package' ) ? round( (float) $product->get_sale_price(), 2 ) : round( (float) $product->get_sale_price( 'normal' ), 2 ) ) . ' PLN' );
			}
			if ( $product->get_type() == 'meters' ) {
				$mib = $product->get_meta( '_meters_in_box' );
				if ( ! empty( $mib ) ) {
					$xml->writeElementNs( 'g', 'unit_pricing_measure', null, '1sqm' );
					$xml->writeElementNs( 'g', 'unit_pricing_base_measure', null, '1sqm' );
				}
			}

			$xml->writeElementNs( 'g', 'image_link', null, wp_get_attachment_image_url( $product->get_image_id(), 'full' ) );

			$xml->writeElementNs( 'g', 'availability', null, ( $product->get_stock_quantity() > 0 ) ? 'in stock' : 'out of stock' );

			$ean = $product->get_global_unique_id();
			$xml->writeElementNs( 'g', 'gtin', null, $ean );

			$brand_ids = $product->get_brand_ids();
			$brands    = $this->get_brand_array_by_id( $brand_ids );

			$xml->startElementNs( 'g', 'brand', null );
			{
				$xml->writeCdata( str_replace( '&', '&amp;', implode( ',', $brands ) ) );
			}
			$xml->endElement();

			$xml->startElementNs( 'g', 'mpn', null );
			{
				$xml->writeCdata( get_post_meta( $product_id, '_manufacturer_id', true ) );
			}
			$xml->endElement();


			$xml->startElementNs( 'g', 'product_type', null );
			{
				$xml->writeCdata( str_replace( '&', '&amp;', $category->name ) );
			}
			$xml->endElement();

			$weight = (float) $product->get_weight();
			$costs  = [];

			foreach ( $processed_shipping_methods as $method ) {
				$shipping_cost = null;
				if ( $method['type'] === 'table_rate' ) {
					$shipping_method_instance = $method['instance'];
					if ( method_exists( $shipping_method_instance, 'calculate_shipping' ) ) {
						$package = [
							'contents'      => [
								[
									'data'     => $product,
									'quantity' => 1,
								]
							],
							'destination'   => [
								'country' => 'PL',
							],
							'contents_cost' => $product->get_price(),
						];

						$shipping_method_instance->rates = [];
						$shipping_method_instance->calculate_shipping( $package );

						if ( ! empty( $shipping_method_instance->rates ) ) {
							$rate          = reset( $shipping_method_instance->rates );
							$shipping_cost = $rate->cost;
						}
					}

					if ( $shipping_cost === null ) {
						foreach ( $method['rules'] as $rule ) {
							$min = ( ! empty( $rule['min'] ) ) ? (float) $rule['min'] : null;
							$max = ( ! empty( $rule['max'] ) ) ? (float) $rule['max'] : null;
							if ( empty( $min ) ) {
								$min = ( ! empty( $rule['conditions'][0]['min'] ) ) ? (float) $rule['conditions'][0]['min'] : null;
							}
							if ( empty( $max ) ) {
								$max = ( ! empty( $rule['conditions'][0]['max'] ) ) ? (float) $rule['conditions'][0]['max'] : null;
							}
							if ( ( $min === null && $max === null ) || ( $min === null && $weight <= $max ) || ( $max === null && $weight >= $min ) || ( $weight >= $min && $weight <= $max ) ) {
								$shipping_cost = $rule['cost_per_order'];
								break;
							}
						}
					}
					if ( $shipping_cost !== null ) {
						$costs[ $method['title'] ] = $shipping_cost;
					}
				} else {
					$costs[ $method['title'] ] = $method['constant_cost'];
				}
			}
			if ( ! empty( $costs ) ) {
				$min     = min( $costs );
				$service = array_keys( $costs, $min );
				if ( is_array( $service ) ) {
					$service = $service[0];
				}
				$xml->startElementNs( 'g', 'shipping', null );
				{
					$xml->writeElementNs( 'g', 'country', null, 'PL' );
					$xml->writeElementNs( 'g', 'service', null, $service );
					$xml->writeElementNs( 'g', 'price', null, sprintf( '%.02f PLN', $min ) );
				}
				$xml->endElement();
			}
		}
		$xml->endElement();
	}

	protected function process_shipping_methods( $shipping_methods ): array {
		$processed_shipping_methods = [];

		$excluded_shipping_methods = apply_filters( '_nt_export_excluded_shipping_' . $this->name, [] );

		foreach ( $shipping_methods as $method ) {
			if ( in_array( $method->id, $excluded_shipping_methods ) || str_contains( $method->get_title(), 'Paczkomat InPost' ) ) {
				continue;
			}

			$processed_method = [
				'id'            => $method->id,
				'title'         => $method->get_title(),
				'instance'      => $method,
				'type'          => 'standard',
				'rules'         => [],
				'constant_cost' => null,
			];

			if ( class_exists( '\WPDesk\FS\TableRate\ShippingMethodSingle' ) && is_a( $method, \WPDesk\FS\TableRate\ShippingMethodSingle::class ) ) {
				$rules = $method->get_instance_option( 'method_rules', [] );
				if ( ! empty( $rules ) ) {
					$processed_method['type']  = 'table_rate';
					$processed_method['rules'] = $rules;
				}
			} else {
				$processed_method['constant_cost'] = (float) $method->get_instance_option( 'cost', 0 );
			}

			$processed_shipping_methods[] = $processed_method;
		}

		return $processed_shipping_methods;
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
}