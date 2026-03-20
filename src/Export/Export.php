<?php

namespace Netivo\Module\Woocommerce\Feed\Module\Export;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

abstract class Export {
	protected string $name;
	public abstract function start(): void;

	public abstract function proceed(): void;

}