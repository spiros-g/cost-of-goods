<?php

namespace COGS_Studio;

use WC_Order;
use WC_Product;
use WP_Query;

defined( 'ABSPATH' ) || exit;

final class Admin {
	private Compatibility $compatibility;
	private COGS_Service $cogs;
	private Profit_Calculator $profit;
	private Cost_History $history;
	private string $hook_suffix = '';

	public function __construct(
		Compatibility $compatibility,
		COGS_Service $cogs,
		Profit_Calculator $profit,
		Cost_History $history
	) {
		$this->compatibility = $compatibility;
		$this->cogs          = $cogs;
		$this->profit        = $profit;
		$this->history       = $history;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'compatibility_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( COGS_STUDIO_FILE ), array( $this, 'plugin_action_links' ) );

		add_action( 'wp_ajax_cogs_studio_dashboard', array( $this, 'ajax_dashboard' ) );
		add_action( 'wp_ajax_cogs_studio_products', array( $this, 'ajax_products' ) );
		add_action( 'wp_ajax_cogs_studio_save_cost', array( $this, 'ajax_save_cost' ) );
		add_action( 'wp_ajax_cogs_studio_bulk_cost', array( $this, 'ajax_bulk_cost' ) );
		add_action( 'wp_ajax_cogs_studio_csv_export', array( $this, 'ajax_csv_export' ) );
		add_action( 'wp_ajax_cogs_studio_csv_import', array( $this, 'ajax_csv_import' ) );
		add_action( 'wp_ajax_cogs_studio_orders', array( $this, 'ajax_orders' ) );
		add_action( 'wp_ajax_cogs_studio_history', array( $this, 'ajax_history' ) );
		add_action( 'wp_ajax_cogs_studio_system', array( $this, 'ajax_system' ) );
	}

	public function register_menu(): void {
		$this->hook_suffix = (string) add_submenu_page(
			'woocommerce',
			__( 'COGS Studio', 'cogs-studio-for-woocommerce' ),
			__( 'COGS Studio', 'cogs-studio-for-woocommerce' ),
			'manage_woocommerce',
			'cogs-studio',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'cogs-studio-admin',
			COGS_STUDIO_URL . 'assets/css/admin.css',
			array(),
			COGS_STUDIO_VERSION
		);

		wp_enqueue_script(
			'cogs-studio-admin',
			COGS_STUDIO_URL . 'assets/js/admin.js',
			array(),
			COGS_STUDIO_VERSION,
			true
		);

		wp_localize_script(
			'cogs-studio-admin',
			'COGSStudio',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cogs_studio_admin' ),
				'currency' => get_woocommerce_currency(),
				'i18n'     => array(
					'loading'       => __( 'Loading…', 'cogs-studio-for-woocommerce' ),
					'error'         => __( 'Something went wrong.', 'cogs-studio-for-woocommerce' ),
					'saved'         => __( 'Saved', 'cogs-studio-for-woocommerce' ),
					'save'          => __( 'Save', 'cogs-studio-for-woocommerce' ),
				),
			)
		);
	}

	public function plugin_action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=cogs-studio' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open COGS Studio', 'cogs-studio-for-woocommerce' ) . '</a>' );
		return $links;
	}

	public function compatibility_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$status = $this->compatibility->status();

		if ( ! $status['woocommerce_active'] ) {
			echo '<div class="notice notice-error"><p><strong>COGS Studio:</strong> ' . esc_html__( 'WooCommerce must be active.', 'cogs-studio-for-woocommerce' ) . '</p></div>';
			return;
		}

		if ( ! $status['wc_supported'] ) {
			/* translators: %s: minimum supported WooCommerce version. */
			$message = sprintf( __( 'WooCommerce %s or newer is required.', 'cogs-studio-for-woocommerce' ), Compatibility::MIN_WC_VERSION );
			echo '<div class="notice notice-error"><p><strong>COGS Studio:</strong> ' . esc_html( $message ) . '</p></div>';
			return;
		}

		if ( ! $status['cogs_enabled'] ) {
			$url = admin_url( 'admin.php?page=wc-settings&tab=advanced' );
			echo '<div class="notice notice-warning"><p><strong>COGS Studio:</strong> ' . esc_html__( 'Enable WooCommerce Cost of Goods Sold under WooCommerce → Settings → Advanced → Features.', 'cogs-studio-for-woocommerce' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open settings', 'cogs-studio-for-woocommerce' ) . '</a></p></div>';
		}
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cogs-studio-for-woocommerce' ) );
		}

		$status = $this->compatibility->status();
		?>
		<div class="wrap cogs-studio-wrap">
			<div class="cogs-studio-header">
				<div>
					<h1><?php esc_html_e( 'COGS Studio', 'cogs-studio-for-woocommerce' ); ?></h1>
					<p><?php esc_html_e( 'Native WooCommerce cost management and profitability.', 'cogs-studio-for-woocommerce' ); ?></p>
				</div>
				<span class="cogs-studio-version">v<?php echo esc_html( COGS_STUDIO_VERSION ); ?></span>
			</div>

			<?php if ( ! $status['cogs_enabled'] ) : ?>
				<div class="cogs-studio-inline-notice cogs-studio-inline-notice--warning">
					<strong><?php esc_html_e( 'Native COGS is not enabled.', 'cogs-studio-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Enable it in WooCommerce → Settings → Advanced → Features before editing costs or viewing profitability.', 'cogs-studio-for-woocommerce' ); ?>
				</div>
			<?php endif; ?>

			<nav class="cogs-studio-tabs" aria-label="<?php esc_attr_e( 'COGS Studio sections', 'cogs-studio-for-woocommerce' ); ?>">
				<button type="button" class="cogs-studio-tab is-active" data-tab="dashboard"><?php esc_html_e( 'Dashboard', 'cogs-studio-for-woocommerce' ); ?></button>
				<button type="button" class="cogs-studio-tab" data-tab="products"><?php esc_html_e( 'Products', 'cogs-studio-for-woocommerce' ); ?></button>
				<button type="button" class="cogs-studio-tab" data-tab="orders"><?php esc_html_e( 'Orders', 'cogs-studio-for-woocommerce' ); ?></button>
				<button type="button" class="cogs-studio-tab" data-tab="history"><?php esc_html_e( 'Cost History', 'cogs-studio-for-woocommerce' ); ?></button>
				<button type="button" class="cogs-studio-tab" data-tab="settings"><?php esc_html_e( 'System', 'cogs-studio-for-woocommerce' ); ?></button>
			</nav>

			<section class="cogs-studio-panel is-active" data-panel="dashboard"><div class="cogs-studio-loading"><?php esc_html_e( 'Loading dashboard…', 'cogs-studio-for-woocommerce' ); ?></div></section>
			<section class="cogs-studio-panel" data-panel="products"></section>
			<section class="cogs-studio-panel" data-panel="orders"></section>
			<section class="cogs-studio-panel" data-panel="history"></section>
			<section class="cogs-studio-panel" data-panel="settings"></section>
		</div>
		<?php
	}

	private function guard_ajax(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cogs-studio-for-woocommerce' ) ), 403 );
		}
	}

	private function guard_cogs_enabled(): void {
		if ( ! $this->compatibility->cogs_enabled() ) {
			wp_send_json_error(
				array( 'message' => __( 'Enable WooCommerce Cost of Goods Sold before using profitability data.', 'cogs-studio-for-woocommerce' ) ),
				409
			);
		}
	}

	public function ajax_dashboard(): void {
		check_ajax_referer( 'cogs_studio_admin', 'nonce' );
		$this->guard_ajax();
		$this->guard_cogs_enabled();

		$period = sanitize_key( wp_unslash( $_POST['period'] ?? '30' ) );
		if ( ! in_array( $period, array( '7', '30', '90', 'custom' ), true ) ) {
			$period = '30';
		}

		$range     = $this->dashboard_range( $period );
		$cache_key = 'custom' === $period ? '' : 'period_' . $period;
		$cache     = get_transient( Dashboard_Cache::TRANSIENT_KEY );

		if ( '' !== $cache_key && is_array( $cache ) && isset( $cache[ $cache_key ] ) && is_array( $cache[ $cache_key ] ) ) {
			wp_send_json_success( $cache[ $cache_key ] );
		}

		$data = array(
			'period'    => $period,
			'label'     => $range['label'],
			'date_from' => $range['date_from'],
			'date_to'   => $range['date_to'],
			'orders'    => $this->order_totals_for_range( $range['after'], $range['before'] ),
			'inventory' => $this->inventory_totals(),
			'status'    => $this->compatibility->status(),
		);

		if ( '' !== $cache_key ) {
			$cache               = is_array( $cache ) ? $cache : array();
			$cache[ $cache_key ] = $data;
			set_transient( Dashboard_Cache::TRANSIENT_KEY, $cache, 10 * MINUTE_IN_SECONDS );
		}

		wp_send_json_success( $data );
	}

	private function dashboard_range( string $period ): array {
		$timezone = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $timezone );

		if ( 'custom' === $period ) {
			$from = $this->parse_date_input( sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) ), false );
			$to   = $this->parse_date_input( sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) ), true );

			if ( ! $from || ! $to || $from > $to ) {
				wp_send_json_error( array( 'message' => __( 'Choose a valid custom date range.', 'cogs-studio-for-woocommerce' ) ), 400 );
			}

			return array(
				'after'     => $from->format( 'Y-m-d H:i:s' ),
				'before'    => $to->format( 'Y-m-d H:i:s' ),
				'date_from' => $from->format( 'Y-m-d' ),
				'date_to'   => $to->format( 'Y-m-d' ),
				'label'     => $from->format( 'Y-m-d' ) . ' → ' . $to->format( 'Y-m-d' ),
			);
		}

		$days = max( 1, absint( $period ) );
		$from = $now->modify( '-' . $days . ' days' );

		return array(
			'after'     => $from->format( 'Y-m-d H:i:s' ),
			'before'    => $now->format( 'Y-m-d H:i:s' ),
			'date_from' => $from->format( 'Y-m-d' ),
			'date_to'   => $now->format( 'Y-m-d' ),
			'label'     => sprintf(
				/* translators: %d: number of days. */
				_n( 'Last %d day', 'Last %d days', $days, 'cogs-studio-for-woocommerce' ),
				$days
			),
		);
	}

	private function parse_date_input( string $value, bool $end_of_day ): ?\DateTimeImmutable {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return null;
		}

		return $end_of_day ? $date->setTime( 23, 59, 59 ) : $date->setTime( 0, 0, 0 );
	}

	private function inventory_totals(): array {
		$page       = 1;
		$total_cost = 0.0;
		$total_value = 0.0;
		$total_profit = 0.0;
		$total_units = 0.0;
		$tracked = 0;

		do {
			$query = new WP_Query(
				array(
					'post_type'              => array( 'product', 'product_variation' ),
					'post_status'            => array( 'publish', 'private' ),
					'posts_per_page'         => 200,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $query->posts as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product instanceof WC_Product || ! $product->managing_stock() ) {
					continue;
				}

				$metrics = $this->profit->product_metrics( $product );
				$qty     = max( 0.0, (float) ( $metrics['stock_quantity'] ?? 0 ) );

				$total_units  += $qty;
				$total_cost   += max( 0.0, (float) ( $metrics['stock_cost'] ?? 0 ) );
				$total_value  += max( 0.0, (float) ( $metrics['stock_value'] ?? 0 ) );
				$total_profit += (float) ( $metrics['stock_profit'] ?? 0 );
				++$tracked;
			}

			$has_more = count( $query->posts ) === 200;
			++$page;
		} while ( $has_more );

		return array(
			'units'        => $total_units,
			'cost'         => $total_cost,
			'retail_value' => $total_value,
			'profit'       => $total_profit,
			'tracked'      => $tracked,
		);
	}

	private function order_totals_for_range( string $after, string $before ): array {
		$page     = 1;
		$revenue  = 0.0;
		$cogs     = 0.0;
		$profit   = 0.0;
		$count    = 0;
		$statuses = array_values( array_unique( array_merge( wc_get_is_paid_statuses(), array( 'refunded' ) ) ) );

		do {
			$result = wc_get_orders(
				array(
					'limit'        => 100,
					'page'         => $page,
					'paginate'     => true,
					'status'       => $statuses,
					'date_created' => $after . '...' . $before,
					'orderby'      => 'date',
					'order'        => 'DESC',
				)
			);

			foreach ( $result->orders as $order ) {
				if ( ! $order instanceof WC_Order ) {
					continue;
				}

				$metrics  = $this->profit->order_metrics( $order );
				$revenue += $metrics['revenue'];
				$cogs    += $metrics['cogs'];
				$profit  += $metrics['profit'];
				++$count;
			}

			++$page;
		} while ( $page <= (int) $result->max_num_pages );

		return array(
			'count'   => $count,
			'revenue' => $revenue,
			'cogs'    => $cogs,
			'profit'  => $profit,
			'margin'  => $revenue > 0 ? ( $profit / $revenue ) * 100 : 0.0,
		);
	}

	public function ajax_products(): void {
		check_ajax_referer( 'cogs_studio_admin', 'nonce' );
		$this->guard_ajax();
		$this->guard_cogs_enabled();

		$page   = max( 1, absint( $_POST['page'] ?? 1 ) );
		$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );

		$args = array(
			'post_type'              => array( 'product', 'product_variation' ),
			'post_status'            => array( 'publish', 'private', 'draft' ),
			'posts_per_page'         => 25,
			'paged'                  => $page,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		if ( '' !== $search ) {
			$sku_id = wc_get_product_id_by_sku( $search );
			if ( $sku_id > 0 ) {
				$args['post__in'] = array( $sku_id );
			} else {
				$args['s'] = $search;
			}
		}

		$query = new WP_Query( $args );
		$rows  = array();

		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}


			$metrics   = $this->profit->product_metrics( $product );
			$nominal   = $this->cogs->nominal_cost( $product );
			$parent    = $product->get_parent_id();
			$cost_mode = $this->cogs->variation_mode( $product );

			$rows[] = array(
				'id'           => $product->get_id(),
				'name'         => $product->get_name(),
				'sku'          => $product->get_sku(),
				'type'         => $product->get_type(),
				'parent_id'    => $parent,
				'price'        => $metrics['price'],
				'nominal_cost' => $nominal,
				'cost_mode'    => $cost_mode,
				'is_variation' => 'simple' !== $cost_mode,
				'cost'         => $metrics['cost'],
				'profit'       => $metrics['profit'],
				'margin'       => $metrics['margin'],
				'stock'        => $product->managing_stock() ? $product->get_stock_quantity() : null,
				'stock_status' => $product->get_stock_status(),
				'edit_url'     => get_edit_post_link( $product->get_id(), 'raw' ),
			);
		}

		wp_send_json_success(
			array(
				'rows'        => $rows,
				'page'        => $page,
				'total_pages' => (int) $query->max_num_pages,
				'total'       => (int) $query->found_posts,
			)
		);
	}

	public function ajax_save_cost(): void {
		check_ajax_referer( 'cogs_studio_admin', 'nonce' );
		$this->guard_ajax();
		$this->guard_cogs_enabled();

		$product_id = absint( $_POST['product_id'] ?? 0 );
		$raw_cost   = isset( $_POST['cost'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['cost'] ) ) ) : '';
		$mode       = sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) );

		if ( $product_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'cogs-studio-for-woocommerce' ) ), 400 );
		}

		try {
			$current_product = $this->cogs->get_product( $product_id );
			$is_variation    = method_exists( $current_product, 'set_cogs_value_is_additive' );

			if ( $is_variation ) {
				if ( ! in_array( $mode, array( 'inherit', 'override', 'additive' ), true ) ) {
					wp_send_json_error( array( 'message' => __( 'Choose a valid variation COGS mode.', 'cogs-studio-for-woocommerce' ) ), 400 );
				}

				if ( 'inherit' === $mode ) {
					$cost = null;
				} else {
					if ( '' === $raw_cost ) {
						wp_send_json_error( array( 'message' => __( 'Enter a cost for Override or Add to parent mode.', 'cogs-studio-for-woocommerce' ) ), 400 );
					}
					$normalized = wc_format_decimal( $raw_cost, 6 );
					if ( '' === $normalized || ! is_numeric( $normalized ) || (float) $normalized < 0 ) {
						wp_send_json_error( array( 'message' => __( 'Enter a valid non-negative cost.', 'cogs-studio-for-woocommerce' ) ), 400 );
					}
					$cost = (float) $normalized;
				}
			} else {
				if ( '' === $raw_cost ) {
					$cost = null;
				} else {
					$normalized = wc_format_decimal( $raw_cost, 6 );
					if ( '' === $normalized || ! is_numeric( $normalized ) || (float) $normalized < 0 ) {
						wp_send_json_error( array( 'message' => __( 'Enter a valid non-negative cost.', 'cogs-studio-for-woocommerce' ) ), 400 );
					}
					$cost = (float) $normalized;
				}
				$mode = null;
			}

			$product = $this->cogs->set_cost( $product_id, $cost, 'cogs-studio', $mode );
			$metrics = $this->profit->product_metrics( $product );

			wp_send_json_success(
				array(
					'nominal_cost' => $this->cogs->nominal_cost( $product ),
					'cost_mode'    => $this->cogs->variation_mode( $product ),
					'cost'         => $metrics['cost'],
					'profit'       => $metrics['profit'],
					'margin'       => $metrics['margin'],
				)
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	public function ajax_orders(): void {
		check_ajax_referer( 'cogs_studio_admin', 'nonce' );
		$this->guard_ajax();
		$this->guard_cogs_enabled();

		$page = max( 1, absint( $_POST['page'] ?? 1 ) );

		$statuses = array_values( array_unique( array_merge( wc_get_is_paid_statuses(), array( 'refunded' ) ) ) );

		$result = wc_get_orders(
			array(
				'limit'    => 25,
				'page'     => $page,
				'paginate' => true,
				'status'   => $statuses,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		$rows = array();
		foreach ( $result->orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$metrics  = $this->profit->order_metrics( $order );
			$customer = trim( $order->get_formatted_billing_full_name() );

			$rows[] = array(
				'id'       => $order->get_id(),
				'number'   => $order->get_order_number(),
				'date'     => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '',
				'customer' => '' !== $customer ? $customer : __( 'Guest', 'cogs-studio-for-woocommerce' ),
				'status'   => wc_get_order_status_name( $order->get_status() ),
				'revenue'  => $metrics['revenue'],
				'cogs'     => $metrics['cogs'],
				'profit'   => $metrics['profit'],
				'margin'   => $metrics['margin'],
				'edit_url' => $order->get_edit_order_url(),
			);
		}

		wp_send_json_success(
			array(
				'rows'        => $rows,
				'page'        => $page,
				'total_pages' => (int) $result->max_num_pages,
				'total'       => (int) $result->total,
			)
		);
	}

	public function ajax_history(): void {
		check_ajax_referer( 'cogs_studio_admin', 'nonce' );
		$this->guard_ajax();

		$rows = array();
		foreach ( $this->history->recent( 100 ) as $entry ) {
			$product = wc_get_product( (int) $entry['product_id'] );
			$user    = get_userdata( (int) $entry['user_id'] );

			$rows[] = array(
				'id'         => (int) $entry['id'],
				'product_id' => (int) $entry['product_id'],
				'product'    => $product instanceof WC_Product ? $product->get_name() : '#' . (int) $entry['product_id'],
				'old_cost'   => null === $entry['old_cost'] ? null : (float) $entry['old_cost'],
				'new_cost'   => null === $entry['new_cost'] ? null : (float) $entry['new_cost'],
				'user'       => $user ? $user->display_name : '—',
				'source'     => (string) $entry['source'],
				'date'       => get_date_from_gmt( $entry['created_at'], 'Y-m-d H:i' ),
			);
		}

		wp_send_json_success( array( 'rows' => $rows ) );
	}

	public function ajax_system(): void {
		check_ajax_referer( 'cogs_studio_admin', 'nonce' );
		$this->guard_ajax();

		wp_send_json_success(
			array(
				'plugin_version' => COGS_STUDIO_VERSION,
				'status'         => $this->compatibility->status(),
			)
		);
	}
}
