<?php

namespace Netivo\Module\Woocommerce\Feed\Module\Export;

class Google extends Export {
	protected string $name = 'google';

	public function start(): void {
		global $wpdb;
		$current_export = get_option( '_nt_export_' . $this->name );

		if ( ! empty( $current_export ) ) {
			echo 'Export already started <br />';
			exit;
		}

		$sql      = "SELECT ID FROM {$wpdb->posts} AS posts
          LEFT JOIN {$wpdb->postmeta} AS meta1 ON meta1.post_id = posts.ID AND meta1.meta_key = '_export_google'
          WHERE posts.post_type='product' AND posts.post_status='publish'";

		$current_export = $wpdb->get_results( $sql );
		update_option( '_nt_export_' . $this->name, $current_export );
	}

	public function proceed(): void {

	}
}