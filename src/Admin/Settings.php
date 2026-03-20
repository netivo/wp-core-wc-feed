<?php

namespace Netivo\Module\WooCommerce\Feed\Admin;


if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

class Settings {

	public function __construct() {
		add_filter( 'woocommerce_get_sections_products', [ $this, 'add_section' ] );
		add_filter( 'woocommerce_get_settings_products', [ $this, 'add_settings' ], 10, 2 );
		add_action( 'woocommerce_update_options_products_export_feed', [ $this, 'save_settings' ] );
	}

	public function save_settings() {
		woocommerce_update_options( $this->add_settings( [], 'export_feed' ) );
	}

	/**
	 * Add "Pakowanie na prezent" section to Products settings tab.
	 *
	 * @param array $sections
	 *
	 * @return array
	 */
	public function add_section( $sections ) {
		$sections['export_feed'] = __( 'Ustawienia Eksportu plików feedowych', 'netivo' );

		return $sections;
	}

	/**
	 * Add settings fields to the section.
	 *
	 * @param array $settings
	 * @param string $current_section
	 *
	 * @return array
	 */
	public function add_settings( $settings, $current_section ) {
		if ( 'export_feed' === $current_section ) {
			$settings = [
				[
					'title' => __( 'Ustawienia generowania plików feed', 'netivo' ),
					'type'  => 'title',
					'id'    => 'nt_feed_settings',
				],
				[
					'title'    => __( 'Tytuł', 'netivo' ),
					'desc'     => __( 'Tytuł w dokumencie XML.', 'netivo' ),
					'id'       => 'nt_feed_title',
					'default'  => __( 'Produkty ' . get_bloginfo( 'name' ), 'netivo' ),
					'type'     => 'text',
					'desc_tip' => true,
				],
				[
					'title'    => __( 'Adres strony', 'netivo' ),
					'desc'     => __( 'Adres strony w dokumencie XML.', 'netivo' ),
					'id'       => 'nt_feed_url',
					'default'  => __( home_url(), 'netivo' ),
					'type'     => 'text',
					'desc_tip' => true,
				],
				[
					'title'    => __( 'Opis', 'netivo' ),
					'desc'     => __( 'Opis w dokumencie XML.', 'netivo' ),
					'id'       => 'nt_feed_description',
					'default'  => __( 'Oferta sklepu internetowego ' . get_bloginfo( 'name' ), 'netivo' ),
					'type'     => 'text',
					'desc_tip' => true,
				],
				[
					'type' => 'sectionend',
					'id'   => 'nt_feed_settings',
				],
			];
		}

		return $settings;
	}
}