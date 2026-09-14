<?php

namespace COGS_Studio;

use Automattic\WooCommerce\Internal\Features\FeaturesController;
use Throwable;

defined( 'ABSPATH' ) || exit;

final class Compatibility {
	public const MIN_WC_VERSION = '10.3.0';

	public function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' );
	}

	public function supported_woocommerce(): bool {
		return $this->woocommerce_active() && version_compare( WC_VERSION, self::MIN_WC_VERSION, '>=' );
	}

	public function cogs_enabled(): bool {
		if ( ! $this->supported_woocommerce() || ! class_exists( FeaturesController::class ) || ! function_exists( 'wc_get_container' ) ) {
			return false;
		}

		try {
			return (bool) wc_get_container()->get( FeaturesController::class )->feature_is_enabled( 'cost_of_goods_sold' );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	public function status(): array {
		return array(
			'woocommerce_active' => $this->woocommerce_active(),
			'wc_version'         => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'min_wc_version'     => self::MIN_WC_VERSION,
			'wc_supported'       => $this->supported_woocommerce(),
			'cogs_enabled'       => $this->cogs_enabled(),
		);
	}
}
