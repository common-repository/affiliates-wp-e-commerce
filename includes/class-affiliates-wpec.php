<?php
/**
 * affiliates-wpec.php
 * 
 * Copyright (c) 2013 "kento" Karim Rahimpur www.itthinx.com
 * 
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 * 
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * This header and all notices must be kept intact.
 * 
 * @author Karim Rahimpur
 * @package affiliates-wp-e-commerce
 * @since affiliates-wp-e-commerce 2.0.0
 */

/**
 * Plugin main class.
 */
class Affiliates_WPEC {

	const PLUGIN_OPTIONS = 'affiliates_wpsc';

	// Order statuses are defined through this global $wpsc_purchlog_statuses
	// in wp-e-commerce/wpsc-core/wpsc-functions.php,
	// function wpsc_core_load_purchase_log_statuses()
	// and the order field's value (1,2, ..., 6) is stored in the 'processed'
	// column of the wp_wpsc_purchase_logs table.
	const INCOMPLETE_SALE  = 1;
	const ORDER_RECEIVED   = 2;
	const ACCEPTED_PAYMENT = 3;
	const JOB_DISPATCHED   = 4;
	const CLOSED_ORDER     = 5;
	const DECLINED_PAYMENT = 6;

	const AUTO_ADJUST_ON_INCOMPLETE_SALE  = 'auto_incomplete_sale';
	const AUTO_ADJUST_ON_ORDER_RECEIVED   = 'auto_order_received';
	const AUTO_ADJUST_ON_ACCEPTED_PAYMENT = 'auto_accepted_payment';
	const AUTO_ADJUST_ON_JOB_DISPATCHED   = 'auto_job_dispatched';
	const AUTO_ADJUST_ON_CLOSED_ORDER     = 'auto_closed_order';
	const AUTO_ADJUST_ON_DECLINED_PAYMENT = 'auto_declined_payment';

	const REFERRAL_RATE         = "referral-rate";
	const REFERRAL_RATE_DEFAULT = "0";
	const USAGE_STATS           = 'usage_stats';
	const USAGE_STATS_DEFAULT   = true;

	const AUTO_ADJUST_DEFAULT = true;

	const RATE_ADJUSTED = 'rate-adjusted';

	private static $admin_messages = array();

	/**
	 * Activation handler.
	 */
	public static function activate() {
	}

	/**
	 * Prints admin notices.
	 */
	public static function admin_notices() {
		if ( !empty( self::$admin_messages ) ) {
			foreach ( self::$admin_messages as $msg ) {
				echo $msg;
			}
		}
	}

	/**
	 * Initializes the integration if dependencies are verified.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		if ( self::check_dependencies() ) {
			register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
			include_once 'class-affiliates-wpec-handler.php';
			if ( is_admin() ) {
				include_once 'class-affiliates-wpec-admin.php';
			}
		}
	}

	/**
	 * Check dependencies and print notices if they are not met.
	 * @return true if ok, false if plugins are missing
	 */
	public static function check_dependencies() {

		$result = true;

		$active_plugins = get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );
			$active_sitewide_plugins = array_keys( $active_sitewide_plugins );
			$active_plugins = array_merge( $active_plugins, $active_sitewide_plugins );
		}

		// required plugins
		$affiliates_is_active =
			in_array( 'affiliates/affiliates.php', $active_plugins ) ||
			in_array( 'affiliates-pro/affiliates-pro.php', $active_plugins ) ||
			in_array( 'affiliates-enterprise/affiliates-enterprise.php', $active_plugins );
		$wpec_is_active = in_array( 'wp-e-commerce/wp-shopping-cart.php', $active_plugins );
		if ( !$affiliates_is_active ) {
			self::$admin_messages[] =
				"<div class='error'>" .
				__( 'The <strong>Affiliates WP e-Commerce Integration</strong> plugin requires an appropriate Affiliates plugin: <a href="http://www.itthinx.com/plugins/affiliates" target="_blank">Affiliates</a>, <a href="http://www.itthinx.com/plugins/affiliates-pro" target="_blank">Affiliates Pro</a> or <a href="http://www.itthinx.com/plugins/affiliates-enterprise" target="_blank">Affiliates Enterprise</a>.', AFF_WPEC_PLUGIN_DOMAIN ) .
				"</div>";
		}
		if ( !$wpec_is_active ) {
			self::$admin_messages[] =
				"<div class='error'>" .
				__( 'The <strong>Affiliates WP e-Commerce Integration</strong> plugin requires <a href="http://wordpress.org/extend/plugins/wp-e-commerce" target="_blank">WP e-Commerce</a>.', AFF_WPEC_PLUGIN_DOMAIN ) .
				"</div>";
		}
		if ( !$affiliates_is_active || !$wpec_is_active ) {
			$result = false;
		}

		// deactivate the old plugin
		$affiliates_wpsc_is_active = in_array( 'affiliates-wpsc/affiliates-wpsc.php', $active_plugins );
		if ( $affiliates_wpsc_is_active ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			if ( function_exists('deactivate_plugins' ) ) {
				deactivate_plugins( 'affiliates-wpsc/affiliates-wpsc.php' );
				self::$admin_messages[] =
					"<div class='error'>" .
					__( 'The <strong>Affiliates WP e-Commerce Integration</strong> plugin version 2 and above replaces the former integration plugin (version number below 2.x).<br/>The former plugin has been deactivated and can now be deleted.', AFF_WPEC_PLUGIN_DOMAIN ) .
					"</div>";
			} else {
				self::$admin_messages[] =
				"<div class='error'>" .
				__( 'The <strong>Affiliates WP e-Commerce Integration</strong> plugin version 2 and above replaces the former integration plugin with an inferior version number.<br/><strong>Please deactivate and delete the former integration plugin with version number below 2.x.</strong>', AFF_WPEC_PLUGIN_DOMAIN ) .
				"</div>";
			}
		}

		return $result;
	}

}
Affiliates_WPEC::init();
