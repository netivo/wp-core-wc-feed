<?php

namespace Netivo\Module\WooCommerce\Feed;

use Netivo\Module\WooCommerce\Feed\Admin\Admin;
use Netivo\Module\WooCommerce\Feed\Endpoint\Export;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}
class Module {
	public function __construct() {
		new Export();

		if ( is_admin() ) {
			new Admin();
		}
	}
}