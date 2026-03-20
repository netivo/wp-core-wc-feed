<?php

namespace Netivo\Module\Woocommerce\Feed\Module\Endpoint;

use Netivo\Core\Endpoint;
use Netivo\Module\Woocommerce\Feed\Module\Export\Google2;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

class Export extends Endpoint {
	/**
	 * Name of the endpoint. It is name of query var.
	 *
	 * @var string
	 */
	protected string $name = 'export';

	/**
	 * Type of endpoint. One of: template, action
	 * template - endpoint will load custom template
	 * action - endpoint will do action and exit
	 *
	 * @var string
	 */
	protected string $type = 'action';

	/**
	 * Endpoint mask describing the places the endpoint should be added.
	 *
	 * @var int
	 */
	protected int $place = EP_ROOT;

	public function doAction( mixed $var ): void {
		$var_array = explode( '/', $var );

		if( count( $var_array ) < 2 ) {
			echo 'Too few arguments.< /br>';
			return;
		}

		$action = $var_array[0];
		$target = $var_array[1];
		$export = null;

		switch ( $target ) {
			case 'google':
				$export = new Google2();
				break;
			case 'ceneo':
				$export = new Ceneo();
				break;
			case 'facebook':
				$export = new Facebook();
				break;
		}

		if ( ! empty( $export ) && method_exists( $export, $action )) {
			$export->$action();
		}
	}
}