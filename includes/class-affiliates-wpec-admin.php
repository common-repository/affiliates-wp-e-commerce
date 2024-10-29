<?php
/**
 * affiliates-wpec-admin.php
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
 * Plugin admin section.
 */
class Affiliates_WPEC_Admin {

	const NONCE = 'aff_wpsc_admin_nonce';
	const SET_ADMIN_OPTIONS = 'set_admin_options';

	/**
	 * Adds the proper initialization action on the wp_init hook.
	 */
	public static function init() {
		add_action( 'init', array(__CLASS__, 'wp_init' ) );
	}

	/**
	 * Adds actions and filters.
	 */
	public static function wp_init() {
		add_action( 'affiliates_admin_menu', array( __CLASS__, 'affiliates_admin_menu' ) );
		add_filter( 'affiliates_footer', array( __CLASS__, 'affiliates_footer' ) );
	}

	/**
	 * Adds a submenu item to the Affiliates menu for integration options.
	 */
	public static function affiliates_admin_menu() {
		$page = add_submenu_page(
			'affiliates-admin',
			__( 'Affiliates WP e-Commerce', AFF_WPEC_PLUGIN_DOMAIN ),
			__( 'WP e-Commerce', AFF_WPEC_PLUGIN_DOMAIN ),
			AFFILIATES_ADMINISTER_OPTIONS,
			'affiliates-admin-wpec',
			array( __CLASS__, 'affiliates_admin_wpec' )
		);
		$pages[] = $page;
		add_action( 'admin_print_styles-' . $page, 'affiliates_admin_print_styles' );
		add_action( 'admin_print_scripts-' . $page, 'affiliates_admin_print_scripts' );
	}

	/**
	* Affiliates - WP e-Commerce admin section.
	*/
	public static function affiliates_admin_wpec() {

		$output = '';

		if ( !current_user_can( AFFILIATES_ADMINISTER_OPTIONS ) ) {
			wp_die( __( 'Access denied.', AFF_WPEC_PLUGIN_DOMAIN ) );
		}

		$options = get_option( Affiliates_WPEC::PLUGIN_OPTIONS , array() );

		if ( isset( $_POST['submit'] ) ) {
			if ( wp_verify_nonce( $_POST[self::NONCE], self::SET_ADMIN_OPTIONS ) ) {

				if ( !class_exists( 'Affiliates_Referral' ) ) {
					$options[Affiliates_WPEC::REFERRAL_RATE]  = floatval( $_POST[Affiliates_WPEC::REFERRAL_RATE] );
					if ( $options[Affiliates_WPEC::REFERRAL_RATE] > 1.0 ) {
						$options[Affiliates_WPEC::REFERRAL_RATE] = 1.0;
					} else if ( $options[Affiliates_WPEC::REFERRAL_RATE] < 0 ) {
						$options[Affiliates_WPEC::REFERRAL_RATE] = 0.0;
					}
				}

				$options[Affiliates_WPEC::AUTO_ADJUST_ON_INCOMPLETE_SALE]  = !empty( $_POST[Affiliates_WPEC::AUTO_ADJUST_ON_INCOMPLETE_SALE] );
				$options[Affiliates_WPEC::AUTO_ADJUST_ON_ORDER_RECEIVED]   = !empty( $_POST[Affiliates_WPEC::AUTO_ADJUST_ON_ORDER_RECEIVED] );
				$options[Affiliates_WPEC::AUTO_ADJUST_ON_ACCEPTED_PAYMENT] = !empty( $_POST[Affiliates_WPEC::AUTO_ADJUST_ON_ACCEPTED_PAYMENT] );
				$options[Affiliates_WPEC::AUTO_ADJUST_ON_JOB_DISPATCHED]   = !empty( $_POST[Affiliates_WPEC::AUTO_ADJUST_ON_JOB_DISPATCHED] );
				$options[Affiliates_WPEC::AUTO_ADJUST_ON_CLOSED_ORDER]     = !empty( $_POST[Affiliates_WPEC::AUTO_ADJUST_ON_CLOSED_ORDER] );
				$options[Affiliates_WPEC::AUTO_ADJUST_ON_DECLINED_PAYMENT] = !empty( $_POST[Affiliates_WPEC::AUTO_ADJUST_ON_DECLINED_PAYMENT] );

				$options[Affiliates_WPEC::USAGE_STATS] = !empty( $_POST[Affiliates_WPEC::USAGE_STATS] );
			}
			update_option( Affiliates_WPEC::PLUGIN_OPTIONS, $options );
		}

		$referral_rate = isset( $options[Affiliates_WPEC::REFERRAL_RATE] ) ? $options[Affiliates_WPEC::REFERRAL_RATE] : Affiliates_WPEC::REFERRAL_RATE_DEFAULT;

		$on_incomplete_sale  = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_INCOMPLETE_SALE] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_INCOMPLETE_SALE] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
		$on_order_received   = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_ORDER_RECEIVED] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_ORDER_RECEIVED] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
		$on_accepted_payment = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_ACCEPTED_PAYMENT] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_ACCEPTED_PAYMENT] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
		$on_job_dispatched   = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_JOB_DISPATCHED] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_JOB_DISPATCHED] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
		$on_closed_order     = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_CLOSED_ORDER] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_CLOSED_ORDER] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
		$on_declined_payment = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_DECLINED_PAYMENT] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_DECLINED_PAYMENT] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;

		$usage_stats   = isset( $options[Affiliates_WPEC::USAGE_STATS] ) ? $options[Affiliates_WPEC::USAGE_STATS] : Affiliates_WPEC::USAGE_STATS_DEFAULT;

		echo
			'<h2>' .
			__( 'Affiliates WP e-Commerce Integration', AFF_WPEC_PLUGIN_DOMAIN ) .
			'</h2>';

		$output .= '<form action="" name="options" method="post">';
		$output .= '<div>';
		
		$output .= '<div>';
		$output .= '<h3>' . __( 'Referral Rate', AFF_WPEC_PLUGIN_DOMAIN ) . '</h3>';
		if ( class_exists( 'Affiliates_Referral' ) ) {
			$output .= '<p>';
			$output .= __( 'The referral rate settings are as determined in <strong>Affiliates > Settings</strong>.', AFF_WPEC_PLUGIN_DOMAIN );
			$output .= '</p>';
		} else {
			$output .= '<p>';
			$output .= '<label for="' . Affiliates_WPEC::REFERRAL_RATE . '">' . __( 'Referral rate', AFF_WPEC_PLUGIN_DOMAIN) . '</label>';
			$output .= '&nbsp;';
			$output .= '<input name="' . Affiliates_WPEC::REFERRAL_RATE . '" type="text" value="' . esc_attr( $referral_rate ) . '"/>';
			$output .= '</p>';
			$output .= '<p>';
			$output .= __( 'The referral rate determines the referral amount based on the net sale made.', AFF_WPEC_PLUGIN_DOMAIN );
			$output .= '</p>';
			$output .= '<p class="description">';
			$output .= __( 'Example: Set the referral rate to <strong>0.1</strong> if you want your affiliates to get a <strong>10%</strong> commission on each sale.', AFF_WPEC_PLUGIN_DOMAIN );
			$output .= '</p>';
			$output .= '<p>';
			$output .= '<strong>';
			$output .= __( 'Get additional features with <a href="http://www.itthinx.com/plugins/affiliates-pro/" target="_blank">Affiliates Pro</a> or <a href="http://www.itthinx.com/plugins/affiliates-enterprise/" target="_blank">Affiliates Enterprise</a>.', AFF_WPEC_PLUGIN_DOMAIN );
			$output .= '</strong>';
			$output .= '</p>';
		}

		$output .= '<h3>' . __( 'Auto-adjustments of referrals', AFF_WPEC_PLUGIN_DOMAIN ) . '</h3>';

		$output .= '<p>' . __( 'The default referral status as determined in <em>Options</em> applies unless the following are enabled.', AFF_WPEC_PLUGIN_DOMAIN ) . '</p>';

		$output .= '<h4>' . __( 'Incomplete sales', AFF_WPEC_PLUGIN_DOMAIN ) . '</h4>';
		$output .= '<p>';
		$output .= '<input name="' . Affiliates_WPEC::AUTO_ADJUST_ON_INCOMPLETE_SALE . '" type="checkbox" ' . ( $on_incomplete_sale ? ' checked="checked" ' : '' ) . ' />';
		$output .= '&nbsp;';
		$output .= '<label for="' . Affiliates_WPEC::AUTO_ADJUST_ON_INCOMPLETE_SALE . '">' . __( 'Auto-adjust referral status when a sale is incomplete', AFF_WPEC_PLUGIN_DOMAIN) . '</label>';
		$output .= '</p>';
		$output .= '<p class="description">' . __( 'The referrals related to the order will be marked as pending, unless they are closed.', AFF_WPEC_PLUGIN_DOMAIN ) . '</p>';

		$output .= '<h4>' . __( 'Received orders', AFF_WPEC_PLUGIN_DOMAIN ) . '</h4>';
		$output .= '<p>';
		$output .= '<input name="' . Affiliates_WPEC::AUTO_ADJUST_ON_ORDER_RECEIVED . '" type="checkbox" ' . ( $on_order_received ? ' checked="checked" ' : '' ) . ' />';
		$output .= '&nbsp;';
		$output .= '<label for="' . Affiliates_WPEC::AUTO_ADJUST_ON_ORDER_RECEIVED . '">' . __( 'Auto-adjust referral status when an order is received', AFF_WPEC_PLUGIN_DOMAIN) . '</label>';
		$output .= '</p>';
		$output .= '<p class="description">' . __( 'The referrals related to the order will be marked as pending, unless they are closed.', AFF_WPEC_PLUGIN_DOMAIN ) . '</p>';

		$output .= '<h4>' . __( 'Accepted payment', AFF_WPEC_PLUGIN_DOMAIN ) . '</h4>';
		$output .= '<p>';
		$output .= '<input name="' . Affiliates_WPEC::AUTO_ADJUST_ON_ACCEPTED_PAYMENT . '" type="checkbox" ' . ( $on_accepted_payment ? ' checked="checked" ' : '' ) . ' />';
		$output .= '&nbsp;';
		$output .= '<label for="' . Affiliates_WPEC::AUTO_ADJUST_ON_ACCEPTED_PAYMENT . '">' . __( 'Auto-adjust referral status when the payment has been accepted', AFF_WPEC_PLUGIN_DOMAIN) . '</label>';
		$output .= '</p>';
		$output .= '<p class="description">' . __( 'The referrals related to the order will automatically be accepted, unless they are closed.', AFF_WPEC_PLUGIN_DOMAIN ) . '</p>';

		$output .= '<h4>' . __( 'Job dispatched', AFF_WPEC_PLUGIN_DOMAIN ) . '</h4>';
		$output .= '<p>';
		$output .= '<input name="' . Affiliates_WPEC::AUTO_ADJUST_ON_JOB_DISPATCHED . '" type="checkbox" ' . ( $on_job_dispatched ? ' checked="checked" ' : '' ) . ' />';
		$output .= '&nbsp;';
		$output .= '<label for="' . Affiliates_WPEC::AUTO_ADJUST_ON_JOB_DISPATCHED . '">' . __( 'Auto-adjust referral status when an order is dispatched', AFF_WPEC_PLUGIN_DOMAIN) . '</label>';
		$output .= '</p>';
		$output .= '<p class="description">' . __( 'The referrals related to the order will automatically be accepted, unless they are closed.', AFF_WPEC_PLUGIN_DOMAIN ) . '</p>';

		$output .= '<h4>' . __( 'Orders closed', AFF_WPEC_PLUGIN_DOMAIN ) . '</h4>';
		$output .= '<p>';
		$output .= '<input name="' . Affiliates_WPEC::AUTO_ADJUST_ON_CLOSED_ORDER . '" type="checkbox" ' . ( $on_closed_order ? ' checked="checked" ' : '' ) . ' />';
		$output .= '&nbsp;';
		$output .= '<label for="' . Affiliates_WPEC::AUTO_ADJUST_ON_CLOSED_ORDER . '">' . __( 'Auto-adjust referral status when an order is closed', AFF_WPEC_PLUGIN_DOMAIN) . '</label>';
		$output .= '</p>';
		$output .= '<p class="description">' . __( 'The referrals related to the order will automatically be accepted, unless they are closed.', AFF_WPEC_PLUGIN_DOMAIN ) . '</p>';

		$output .= '<h4>' . __( 'Declined payment', AFF_WPEC_PLUGIN_DOMAIN ) . '</h4>';
		$output .= '<p>';
		$output .= '<input name="' . Affiliates_WPEC::AUTO_ADJUST_ON_DECLINED_PAYMENT . '" type="checkbox" ' . ( $on_declined_payment ? ' checked="checked" ' : '' ) . ' />';
		$output .= '&nbsp;';
		$output .= '<label for="' . Affiliates_WPEC::AUTO_ADJUST_ON_DECLINED_PAYMENT . '">' . __( 'Auto-adjust referral status when payment is declined', AFF_WPEC_PLUGIN_DOMAIN) . '</label>';
		$output .= '</p>';
		$output .= '<p class="description">' . __( 'The referrals related to the order will be rejected automatically, unless they are closed.', AFF_WPEC_PLUGIN_DOMAIN ) . '</p>';

		$output .= '<h3>' . __( 'Usage stats', AFF_WPEC_PLUGIN_DOMAIN ) . '</h3>';
		$output .= '<p>';
		$output .= '<input name="' . Affiliates_WPEC::USAGE_STATS . '" type="checkbox" ' . ( $usage_stats ? ' checked="checked" ' : '' ) . '/>';
		$output .= ' ';
		$output .= '<label for="' . Affiliates_WPEC::USAGE_STATS . '">' . __( 'Allow the plugin to provide usage stats.', AFF_WPEC_PLUGIN_DOMAIN ) . '</label>';
		$output .= '<br/>';
		$output .= '<span class="description">' . __( 'This will allow the plugin to help in computing how many installations are actually using it. No personal or site data is transmitted, this simply embeds an icon on the bottom of the Affiliates admin pages, so that the number of visits to these can be counted. This is useful to help prioritize development.', AFF_WPEC_PLUGIN_DOMAIN ) . '</span>';
		$output .= '</p>';

		$output .= '<p>';
		$output .= wp_nonce_field( self::SET_ADMIN_OPTIONS, self::NONCE, true, false );
		$output .= '<input type="submit" name="submit" value="' . __( 'Save', AFF_WPEC_PLUGIN_DOMAIN ) . '"/>';
		$output .= '</p>';

		$output .= '</div>';
		$output .= '</form>';

		echo $output;

		affiliates_footer();
	}
	
	/**
	 * Add a notice to the footer that the integration is active.
	 * @param string $footer
	 */
	public static function affiliates_footer( $footer ) {
		$options     = get_option( Affiliates_WPEC::PLUGIN_OPTIONS , array() );
		$usage_stats = isset( $options[Affiliates_WPEC::USAGE_STATS] ) ? $options[Affiliates_WPEC::USAGE_STATS] : Affiliates_WPEC::USAGE_STATS_DEFAULT;
		return
			( $usage_stats ?
				'<div style="font-size:0.9em">' .
				'<p>' .
				"<img src='http://www.itthinx.com/img/affiliates-wp-e-commerce/affiliates-wp-e-commerce.png' alt=''/>" .
				__( "Powered by <a href='http://www.itthinx.com/plugins/affiliates-wp-e-commerce' target='_blank'>Affiliates WP e-Commerce Integration</a>.", AFF_WPEC_PLUGIN_DOMAIN ) .
				'</p>' .
				'</div>'
				:
				''
			) .
			$footer;
	}

}
Affiliates_WPEC_Admin::init();
