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
		add_action( 'woocommerce_update_options_products_feed_settings', [ $this, 'save_settings' ] );
	}

	public function save_settings() {
		woocommerce_update_options( $this->add_settings( [], 'feed_settings' ) );
	}

	/**
	 * Add "Pakowanie na prezent" section to Products settings tab.
	 *
	 * @param array $sections
	 *
	 * @return array
	 */
	public function add_section( $sections ) {
		$sections['feed_settings'] = __( 'Ustawienia Eksportu plików feedowych', 'netivo' );

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
		if ( 'feed_settings' === $current_section ) {
			$enabled_types = get_option( 'nt_feed_enabled_types', [] );
			$settings      = [
				[
					'title' => __( 'Feedy produktowe', 'netivo' ),
					'type'  => 'title',
					'id'    => 'nt_feed_types',
				],
				[
					'title'   => __( 'Włączone feedy', 'netivo' ),
					'type'    => 'multiselect',
					'id'      => 'nt_feed_enabled_types',
					'options' => [
						'google'   => __( 'Google', 'netivo' ),
						'facebook' => __( 'Facebook', 'netivo' ),
						//'ceneo' => __( 'Ceneo', 'netivo' ),
					],
					'default' => $enabled_types,
				],
				[
					'type' => 'sectionend',
					'id'   => 'nt_feed_types',
				]
			];

			if ( ! empty( $enabled_types ) ) {
				foreach ( $enabled_types as $type ) {
					$default_title       = sprintf( __( 'Produkty %s - %s', 'netivo' ), get_bloginfo( 'name' ), ucfirst( $type ) );
					$default_description = sprintf( __( 'Oferta sklepu internetowego %s - %s', 'netivo' ), get_bloginfo( 'name' ), ucfirst( $type ) );
					$default_home_url    = home_url();

					$title       = apply_filters( 'netivo/woocommerce/feed/default_title', $default_title, $type );
					$description = apply_filters( 'netivo/woocommerce/feed/default_description', $default_description, $type );
					$home_url    = apply_filters( 'netivo/woocommerce/feed/default_home_url', $default_home_url, $type );

					$settings[] = [
						'title' => __( 'Ustawienia treści w plikach feed dla ' . ucfirst( $type ), 'netivo' ),
						'type'  => 'title',
						'id'    => "nt_feed_settings_$type",
					];
					$settings[] = [
						'title'    => sprintf( __( 'Tytuł - %s', 'netivo' ), ucfirst( $type ) ),
						'desc'     => sprintf( __( 'Tytuł w dokumencie XML dla %s.', 'netivo' ), ucfirst( $type ) ),
						'id'       => "nt_feed_title_$type",
						'default'  => $title,
						'type'     => 'text',
						'desc_tip' => true,
					];
					$settings[] = [
						'title'    => sprintf( __( 'Adres strony - %s', 'netivo' ), ucfirst( $type ) ),
						'desc'     => sprintf( __( 'Adres strony w dokumencie XML dla %s.', 'netivo' ), ucfirst( $type ) ),
						'id'       => "nt_feed_url_$type",
						'default'  => $home_url,
						'type'     => 'text',
						'desc_tip' => true,
					];
					$settings[] = [
						'title'    => sprintf( __( 'Opis - %s', 'netivo' ), ucfirst( $type ) ),
						'desc'     => sprintf( __( 'Opis w dokumencie XML dla %s.', 'netivo' ), ucfirst( $type ) ),
						'id'       => "nt_feed_description_$type",
						'default'  => $description,
						'type'     => 'text',
						'desc_tip' => true,
					];
					$settings[] = [
						'type' => 'sectionend',
						'id'   => "nt_feed_settings_$type",
					];
				}
			}
		}

		return $settings;
	}
}