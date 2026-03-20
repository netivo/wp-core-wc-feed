<?php

namespace Netivo\Module\WooCommerce\Feed\Module\Admin;


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
					'title' => __( 'Ustawienia pakowania na prezent', 'netivo' ),
					'type'  => 'title',
					'id'    => 'present_packing_options',
				],
				[
					'title'    => __( 'Nazwa wyświetlana', 'netivo' ),
					'desc'     => __( 'Nazwa widoczna dla klienta w koszyku i zamówieniu.', 'netivo' ),
					'id'       => 'present_packing_name',
					'default'  => __( 'Pakowanie na prezent', 'netivo' ),
					'type'     => 'text',
					'desc_tip' => true,
				],
				[
					'title'    => __( 'Typ ceny', 'netivo' ),
					'id'       => 'present_packing_price_type',
					'default'  => 'fixed',
					'type'     => 'select',
					'options'  => [
						'fixed'      => __( 'Stała kwota', 'netivo' ),
						'percentage' => __( 'Procent wartości zamówienia', 'netivo' ),
					],
					'desc_tip' => true,
				],
				[
					'title'             => __( 'Wartość ceny', 'netivo' ),
					'id'                => 'present_packing_price_value',
					'default'           => '0',
					'type'              => 'number',
					'custom_attributes' => [
						'step' => '0.01',
						'min'  => '0',
					],
					'desc_tip'          => true,
				],
				[
					'type' => 'sectionend',
					'id'   => 'present_packing_options',
				],
			];
		}

		return $settings;
	}
}