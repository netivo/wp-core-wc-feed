<?php

namespace Netivo\Module\WooCommerce\Feed\Module\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

class Admin {

	public function __construct() {
		new Settings();
	}
}