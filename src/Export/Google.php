<?php

namespace Netivo\Module\WooCommerce\Feed\Export;

use XMLWriter;

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

	protected function finish( $xml, $part_count ): void {
		if ( file_exists( ABSPATH . '/export_google.xml' ) ) {
			unlink( ABSPATH . '/export_google.xml' );
		}

		$title    = get_option( 'nt_feed_title' );
		$site_url = get_option( 'nt_feed_url' );
		$desc     = get_option( 'nt_feed_description' );

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

		delete_option( '_nt_export_' . $this->name );
		delete_option( '_nt_export_part_' . $this->name );
	}

	protected function parse_product_xml( $product, $product_id, $xml ) {
		$product_type = $product->get_type();
		$is_variation = ( $product_type === 'variation' );

		$image_link   = wp_get_attachment_image_url( $product->get_image_id(), 'full' );
		$description  = htmlspecialchars( $product->get_description(), ENT_XML1 );
		$product_link = get_permalink( $product_id );

		$category = $product->get_category_ids();
		if ( ! empty( $category ) ) {
			$category = get_term( $category[0] );
		} else {
			$category = '';
		}
		$brand_ids = $product->get_brand_ids();
		$brands    = $this->get_brand_array_by_id( $brand_ids );

		if ( $is_variation ) {
			$parent_id = $product->get_parent_id();
			$parent    = wc_get_product( $parent_id );

			$parent_status = $parent->get_status();

			if ( $parent_status !== 'publish' ) {
				return;
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

		$xml->setIndent( true );
		$xml->setIndentString( '      ' );
		$xml->startElement( 'item' );
		{

			$xml->writeElementNs( 'g', 'id', null, $product_id );

			$xml->startElement( 'title' );
			{
				$xml->writeCdata( str_replace( '&', '&amp;', $product->get_name() ) );
			}
			$xml->endElement();

			$xml->writeElement( 'link', $product_link );


			$xml->startElement( 'description' );
			{
				$xml->writeCdata( $description );
			}
			$xml->endElement();

			$product_price      = ( $product_type === 'package' ) ? $product->get_regular_price() : $product->get_regular_price( 'normal' );
			$product_sale_price = ( $product_type === 'package' ) ? round( (float) $product->get_sale_price(), 2 ) : round( (float) $product->get_sale_price( 'normal' ), 2 );

			if ( ! empty( $product_price ) ) {
				$product_price = sprintf( '%.02f PLN', $product_price );
			}

			if ( ! empty( $product_sale_price ) ) {
				$product_sale_price = sprintf( '%.02f PLN', $product_sale_price );
			}

			$xml->writeElementNs( 'g', 'price', null, $product_price );
			if ( $product->is_on_sale() ) {
				$xml->writeElementNs( 'g', 'sale_price', null, $product_sale_price );
			}
			if ( $product_type == 'meters' ) {
				$mib = $product->get_meta( '_meters_in_box' );
				if ( ! empty( $mib ) ) {
					$xml->writeElementNs( 'g', 'unit_pricing_measure', null, '1sqm' );
					$xml->writeElementNs( 'g', 'unit_pricing_base_measure', null, '1sqm' );
				}
			}


			$xml->writeElementNs( 'g', 'image_link', null, $image_link );

			$xml->writeElementNs( 'g', 'availability', null, ( $product->get_stock_quantity() > 0 ) ? 'in stock' : 'out of stock' );

			$ean = $product->get_global_unique_id();
			$xml->writeElementNs( 'g', 'gtin', null, $ean );

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

			$product_real_price = ( $product_sale_price > 0 ) ? $product_sale_price : $product_price;

			$costs = $this->calculate_shipping_costs( $product, $product_real_price );

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