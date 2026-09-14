<?php

namespace COGS_Studio;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

final class Compatibility {
	public const MIN_WC_VERSION = '10.3.0';
	public const TESTED_WC_VERSION = '11.1';

	public function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' );
	}

	public function supported_woocommerce(): bool {
		return $this->woocommerce_active() && version_compare( WC_VERSION, self::MIN_WC_VERSION, '>=' );
	}

	public function cogs_enabled(): bool {
		if ( ! $this->supported_woocommerce() || ! class_exists( FeaturesUtil::class ) ) {
			return false;
		}

		return FeaturesUtil::feature_is_enabled( 'cost_of_goods_sold' );
	}

	public function status(): array {
		global $wp_version;

		return array(
			'wordpress_version'   => $wp_version,
			'php_version'         => PHP_VERSION,
			'woocommerce_active' => $this->woocommerce_active(),
			'wc_version'         => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'min_wc_version'     => self::MIN_WC_VERSION,
			'tested_wc_version'  => self::TESTED_WC_VERSION,
			'wc_supported'       => $this->supported_woocommerce(),
			'cogs_enabled'       => $this->cogs_enabled(),
		);
	}
}
