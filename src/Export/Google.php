<?php

namespace Netivo\Module\WooCommerce\Feed\Export;

use XMLWriter;

class Google extends Export {
	protected string $name = 'google';

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

	protected function parse_product_xml( $product, $product_id, XMLWriter $xml ): void {
		$product_data = $this->get_data_for_product( $product, $product_id );
		
		if ( empty( $product_data['id'] ) ) {
			return;
		}

		$xml->setIndent( true );
		$xml->setIndentString( '      ' );
		$xml->startElement( 'item' );
		{

			$xml->writeElementNs( 'g', 'id', null, $product_data['id'] );

			$xml->startElement( 'title' );
			{
				$xml->writeCdata( str_replace( '&', '&amp;', $product_data['name'] ) );
			}
			$xml->endElement();

			$xml->writeElement( 'link', $product_data['link'] );


			$xml->startElement( 'description' );
			{
				$xml->writeCdata( $product_data['description'] );
			}
			$xml->endElement();

			if ( ! empty( $product_data['price'] ) ) {
				$product_data['price'] = sprintf( '%.02f PLN', $product_data['price'] );
			}

			if ( ! empty( $product_data['sale_price'] ) ) {
				$product_data['sale_price'] = sprintf( '%.02f PLN', $product_data['sale_price'] );
			}

			$xml->writeElementNs( 'g', 'price', null, $product_data['price'] );
			if ( $product->is_on_sale() ) {
				$xml->writeElementNs( 'g', 'sale_price', null, $product_data['sale_price'] );
			}
			if ( $product_data['type'] == 'meters' ) {
				$mib = $product->get_meta( '_meters_in_box' );
				if ( ! empty( $mib ) ) {
					$xml->writeElementNs( 'g', 'unit_pricing_measure', null, '1sqm' );
					$xml->writeElementNs( 'g', 'unit_pricing_base_measure', null, '1sqm' );
				}
			}


			$xml->writeElementNs( 'g', 'image_link', null, $product_data['image_link'] );

			$xml->writeElementNs( 'g', 'availability', null, ( $product->get_stock_quantity() > 0 ) ? 'in stock' : 'out of stock' );

			$xml->writeElementNs( 'g', 'gtin', null, $product_data['ean'] );

			$xml->startElementNs( 'g', 'brand', null );
			{
				$xml->writeCdata( str_replace( '&', '&amp;', implode( ',', $product_data['brands'] ) ) );
			}
			$xml->endElement();

			$xml->startElementNs( 'g', 'mpn', null );
			{
				$xml->writeCdata( get_post_meta( $product_id, '_manufacturer_id', true ) );
			}
			$xml->endElement();


			$xml->startElementNs( 'g', 'product_type', null );
			{
				$xml->writeCdata( str_replace( '&', '&amp;', $product_data['category'] ) );
			}
			$xml->endElement();

			if ( ! empty( $product_data['costs'] ) ) {
				$min     = min( $product_data['costs'] );
				$service = array_keys( $product_data['costs'], $min );
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
}