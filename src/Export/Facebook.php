<?php

namespace Netivo\Module\WooCommerce\Feed\Export;

use WC_Product;
use XMLWriter;

class Facebook extends Export {
	protected string $name = 'facebook';

	protected function parse_product_xml( WC_Product $product, string|int $product_id, XMLWriter $xml ): void {
		$product_data = $this->get_data_for_product( $product, $product_id );

		if ( empty( $product_data['id'] ) ) {
			return;
		}

		$xml->setIndent( true );
		$xml->setIndentString( '      ' );
		$xml->startElement( 'item' );
		{
			$xml->writeElementNs( 'g', 'id', null, $product_data['sku'] );

			$xml->startElementNs( 'g', 'title', null );
			{
				$xml->writeCdata( str_replace( '&', '&amp;', $product_data['name'] ) );
			}
			$xml->endElement();

			$xml->writeElementNs( 'g', 'link', null, $product_data['link'] );

			$xml->startElementNs( 'g', 'description', null );
			{
				$xml->writeCdata( strip_tags( $product_data['description'] ) );
			}
			$xml->endElement();

			$xml->startElementNs( 'g', 'rich_text_description', null );
			{
				$xml->writeCdata( $product_data['rich_description'] );
			}
			$xml->endElement();

			$xml->writeElementNs( 'g', 'price', null, sprintf( '%.2f PLN', $product_data['price'] ) );
			$xml->writeElementNs( 'g', 'image_link', null, $product_data['image_link'] );

			if ( ! empty( $product_data['gallery'] ) ) {
				$xml->writeElementNs( 'g', 'additional_image_link', null, implode( ',', $product_data['gallery'] ) );
			}

			//$xml->writeElementNs( 'g', 'condition', null, 'new' ); czy na pewno?
			$xml->writeElementNs( 'g', 'availability', null, $product_data['availability'] );
			$xml->writeElementNs( 'g', 'gtin', null, $product_data['ean'] );

			$xml->startElementNs( 'g', 'brand', null );
			{
				$xml->writeCdata( $product_data['brands'] );
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

	protected function finish( XMLWriter $xml, string|int $part_count ): void {
		if ( file_exists( ABSPATH . '/export_facebook.xml' ) ) {
			unlink( ABSPATH . '/export_facebook.xml' );
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

		file_put_contents( ABSPATH . '/export_facebook.xml', $xml->flush() );

		delete_option( '_nt_export_' . $this->name );
		delete_option( '_nt_export_part_' . $this->name );
	}
}