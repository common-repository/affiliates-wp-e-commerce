<?php
/**
 * affiliates-wpec-handler.php
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
 * Plugin main handler class.
 */
class Affiliates_WPEC_Handler {

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
		// need to have it after init so we can check the version
		if ( version_compare( WPSC_VERSION, '3.8.9' ) < 0 ) {
			add_filter( 'wpsc_transaction_result_report', array( __CLASS__, 'wpsc_transaction_result_report' ) );
		} else {
			// Can't use that one as the items don't seem to be there :
			// add_action( 'wpsc_purchase_log_insert', array( __CLASS__, 'wpsc_purchase_log_insert' ) );
			// Using this action instead - the invoking function also sets the global $purchase_log for compatibility with < 3.8.9
			// do_action( 'wpsc_transaction_results_shutdown', $purchase_log_object, $sessionid, $display_to_screen );
			// We're only interested in the first argument:
			add_action( 'wpsc_transaction_results_shutdown', array( __CLASS__, 'wpsc_transaction_results_shutdown' ) );
		}

		// @todo both in < 3.8.9 and >= 3.8.9 ... this has a note indicating possible removal - update when necessary
		// do_action('wpsc_edit_order_status', array('purchlog_id'=>$purchlog_id, 'purchlog_data'=>$log_data, 'new_status'=>$purchlog_status));
		add_action( 'wpsc_edit_order_status', array( __CLASS__, 'wpsc_edit_order_status' ) );
		add_action( 'wpsc_payment_successful', array( __CLASS__, 'wpsc_payment_successful' ) );
		add_action( 'wpsc_payment_failed', array( __CLASS__, 'wpsc_payment_failed' ) );
		add_action( 'wpsc_payment_incomplete', array( __CLASS__, 'wpsc_payment_incomplete' ) );

		if ( class_exists( 'Affiliates_Attributes' ) ) {
			$options = get_option( Affiliates_WPEC::PLUGIN_OPTIONS , array() );
			$rate_adjusted = isset( $options[Affiliates_WPEC::RATE_ADJUSTED] );
			if ( !$rate_adjusted ) {
				$referral_rate = isset( $options[Affiliates_WPEC::REFERRAL_RATE] ) ? $options[Affiliates_WPEC::REFERRAL_RATE] : Affiliates_WPEC::REFERRAL_RATE_DEFAULT;
				if ( $referral_rate ) {
					$key   = get_option( 'aff_def_ref_calc_key', null );
					$value = get_option( 'aff_def_ref_calc_value', null );
					if ( empty( $key ) ) {
						if ( $key = Affiliates_Attributes::validate_key( 'referral.rate' ) ) {
							update_option( 'aff_def_ref_calc_key', $key );
						}
						if ( $referral_rate = Affiliates_Attributes::validate_value( $key, $referral_rate ) ) {
							update_option( 'aff_def_ref_calc_value', $referral_rate );
						}
					}
				}
				$options[Affiliates_WPEC::RATE_ADJUSTED] = 'yes';
				update_option( Affiliates_WPEC::PLUGIN_OPTIONS, $options );
			}
		} else {
			// playing around? reset the rate flag so it gets set when
			// switching plugins
			$options = get_option( Affiliates_WPEC::PLUGIN_OPTIONS , array() );
			unset( $options[Affiliates_WPEC::RATE_ADJUSTED] );
			update_option( Affiliates_WPEC::PLUGIN_OPTIONS, $options );
		}

	}

	public static function wpsc_payment_successful() {
		global $purchase_log;
		if ( isset( $purchase_log['id'] ) ) {
			self::order_status_accepted_payment( $purchase_log['id'] );
		}
	}

	public static function wpsc_payment_failed() {
		global $purchase_log;
		if ( isset( $purchase_log['id'] ) ) {
			self::order_status_declined_payment( $purchase_log['id'] );
		}
	}

	public static function wpsc_payment_incomplete() {
		global $purchase_log;
		if ( isset( $purchase_log['id'] ) ) {
			self::order_status_declined_payment( $purchase_log['id'] );
		}
	}

	/**
	 * Record a referral when a new order has been placed.
	 * 
	 * wpsc >= 3.8.9
	 * 
	 * @param WPSC_Purchase_Log $purchase_log
	 */
	public static function wpsc_purchase_log_insert( $purchase_log ) {
		global $wpdb;
		$purchase_logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE id = %d",
			intval( $purchase_log->get( 'id' ) )
		) );
		if ( $purchase_logs && count( $purchase_logs ) > 0 ) {
			$that_purchase_log = array_shift( $purchase_logs );
			$order_id  = isset( $that_purchase_log->id ) ? $that_purchase_log->id : null;
			$processed = isset( $that_purchase_log->processed ) ? $that_purchase_log->processed : null;
			self::handle( $order_id, $processed );
		}
	}

	/** 
	 * Process new order.
	 * 
	 * Hooked on wpsc_transaction_results_shutdown, available from 3.8.9
	 * 
	 * @param WPSC_Purchase_Log $purchase_log_object
	 */
	public static function wpsc_transaction_results_shutdown( $purchase_log_object ) {
		$order_id  = $purchase_log_object->get( 'id' );
		$processed = $purchase_log_object->get( 'processed' );
		self::handle( $order_id, $processed );
	}

	/**
	 * Record a referral when a new order has been placed.
	 * 
	 * for wpsc < 3.8.9
	 *  
	 * @param unknown_type $report
	 */
	public static function wpsc_transaction_result_report( $report ) {
		global $purchase_log;
		$order_id  = isset( $purchase_log['id'] ) ? $purchase_log['id'] : null;
		$processed = isset( $purchase_log['processed'] ) ? $purchase_log['processed'] : null;
		self::handle( $order_id, $processed );
		return $report;
	}

	/**
	 * Record a referral for the order.
	 * 
	 * @param unknown_type $order_id
	 * @param unknown_type $processed
	 */
	public static function handle( $order_id, $processed ) {

		global $wpdb, $purchase_log, $order_url, $wpsc_purchlog_statuses;

		// avoid duplicating referrals when this is triggered upon order status change
		$referrals_table = _affiliates_get_tablename( 'referrals' );
		$referrals = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $referrals_table WHERE reference = %s", $order_id ) );
		if ( count( $referrals ) > 0 ) {
			// needs to be called to update the referral when the payment
			// for an order has been accepted by an admin, we will get here
			self::order_status_update( $order_id, $processed );
			return;
		}

		$order_subtotal = "0";

		// Iterate over these and sum up, see purchaselogs.class.php
		// must be done that way to exclude shipping as that is included
		// in the 'totalprice' column of the wp_wpsc_purchase_logs table
		// tax is also included but could be deducted as they are in the
		// 'wpec_taxes_total' column of that table.
		require_once WPSC_FILE_PATH . '/wpsc-includes/purchaselogs.class.php';
		$items = new wpsc_purchaselogs_items( $order_id );

		if ( $items ) {
			while( $items->have_purch_item() ) {
				$item = $items->next_purch_item();
				if ( $item ) {
					$quantity = $item->quantity;
					$price = $item->price;
					$order_subtotal = bcadd( $order_subtotal, bcmul( $quantity, $price ) );
				}
			}
		}

		$total         = bcadd( "0", $order_subtotal, AFFILIATES_REFERRAL_AMOUNT_DECIMALS );
		$currency_type = get_option( 'currency_type' );
		$currency      = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM " . WPSC_TABLE_CURRENCY_LIST . " WHERE id = %d", intval( $currency_type ) ) );

		$order_link = sprintf(
			__( '<a href="%s">Order #%s</a>' ),
			esc_url(
				add_query_arg(
					array( 'c'  => 'item_details', 'id' => $order_id ),
					admin_url( 'index.php?page=wpsc-purchase-logs' )
				)
			),
			$order_id
		);

		$data = array(
			'order_id' => array(
				'title'  => 'Order #',
				'domain' => AFF_WPEC_PLUGIN_DOMAIN,
				'value'  => esc_sql( $order_id )
			),
			'order_total' => array(
				'title'  => 'Total',
				'domain' =>  AFF_WPEC_PLUGIN_DOMAIN,
				'value'  => esc_sql( $total )
			),
			'order_currency' => array(
				'title'  => 'Currency',
				'domain' =>  AFF_WPEC_PLUGIN_DOMAIN,
				'value'  => esc_sql( $currency )
			),
			'order_link' => array(
				'title'  => 'Order',
				'domain' =>  AFF_WPEC_PLUGIN_DOMAIN,
				'value'  => esc_sql( $order_link )
			)
		);

		$post_id = 0;
		$description = sprintf( __( 'Order #%s', AFF_WPEC_PLUGIN_DOMAIN ), $order_id );

		if ( class_exists( 'Affiliates_Referral_WordPress' ) ) {
			$r = new Affiliates_Referral_WordPress();
			$r->evaluate( $post_id, $description, $data, $total, null, $currency, null, 'sale', $order_id );
		} else {
			$options = get_option( Affiliates_WPEC::PLUGIN_OPTIONS , array() );
			$referral_rate  = isset( $options[Affiliates_WPEC::REFERRAL_RATE] ) ? $options[Affiliates_WPEC::REFERRAL_RATE] : Affiliates_WPEC::REFERRAL_RATE_DEFAULT;
			$amount = round( floatval( $referral_rate ) * floatval( $total ), AFFILIATES_REFERRAL_AMOUNT_DECIMALS );
			affiliates_suggest_referral( $post_id, $description, $data, $amount, $currency, null, 'sale', $order_id );
		}

		self::order_status_update( $order_id, $processed );
	}

	private static function order_status_update( $order_id, $processed ) {
		$options = get_option( Affiliates_WPEC::PLUGIN_OPTIONS , array() );
		switch ( $processed ) {
			case Affiliates_WPEC::INCOMPLETE_SALE :
				$condition = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_INCOMPLETE_SALE] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_INCOMPLETE_SALE] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
				if ( $condition ) {
					self::order_status_incomplete_sale( $order_id );
				}
				break;
			case Affiliates_WPEC::ORDER_RECEIVED :
				$condition = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_ORDER_RECEIVED] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_ORDER_RECEIVED] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
				if ( $condition ) {
					self::order_status_order_received( $order_id );
				}
				break;
			case Affiliates_WPEC::ACCEPTED_PAYMENT :
				$condition = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_ACCEPTED_PAYMENT] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_ACCEPTED_PAYMENT] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
				if ( $condition ) {
					self::order_status_accepted_payment( $order_id );
				}
				break;
			case Affiliates_WPEC::JOB_DISPATCHED :
				$condition = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_JOB_DISPATCHED] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_JOB_DISPATCHED] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
				if ( $condition ) {
					self::order_status_job_dispatched( $order_id );
				}
				break;
			case Affiliates_WPEC::CLOSED_ORDER :
				$condition = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_CLOSED_ORDER] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_CLOSED_ORDER] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
				if ( $condition ) {
					self::order_status_closed_order( $order_id );
				}
				break;
			case Affiliates_WPEC::DECLINED_PAYMENT :
				$condition = isset( $options[Affiliates_WPEC::AUTO_ADJUST_ON_DECLINED_PAYMENT] ) ? $options[Affiliates_WPEC::AUTO_ADJUST_ON_DECLINED_PAYMENT] : Affiliates_WPEC::AUTO_ADJUST_DEFAULT;
				if ( $condition ) {
					self::order_status_declined_payment( $order_id );
				}
				break;
		}
	}

	/**
	 * 
	 * @param array $info
	 */
	public static function wpsc_edit_order_status( $info ) {
		$order_id = isset( $info['purchlog_id'] ) ? $info['purchlog_id'] : null;
		// what a mess ... new_status is the new value for processed, the current value in purchlog_data is the old one
		$processed = isset( $info['new_status'] ) ? $info['new_status'] : null;
// 		$processed = isset( $info['purchlog_data'] ) && isset( $info['purchlog_data']['processed'] ) ? $info['purchlog_data']['processed'] : null;
		self::order_status_update( $order_id, $processed );
	}

	/**
	* Rejects referrals for the given order.
	* Only acts on referrals that have not been closed.
	*
	* @param int $order_id order post id
	*/
	public static function order_status_declined_payment( $order_id ) {
		global $wpdb;
		$referrals_table = _affiliates_get_tablename( "referrals" );
		$referrals = $wpdb->get_results( $wpdb->prepare(
			"SELECT referral_id FROM $referrals_table WHERE reference = %s AND status != %s AND status != %s",
			$order_id,
			AFFILIATES_REFERRAL_STATUS_REJECTED,
			AFFILIATES_REFERRAL_STATUS_CLOSED
		) );
		if ( $referrals ) {
			foreach( $referrals as $referral ) {
				if ( class_exists( 'Affiliates_Referral_WordPress' ) ) {
					try {
						$r = new Affiliates_Referral_WordPress( $referral->referral_id );
						$r->update( array( 'status' => AFFILIATES_REFERRAL_STATUS_REJECTED ) );
					} catch ( Exception $ex ) {
					}
				} else {
					$wpdb->query( $wpdb->prepare(
						"UPDATE $referrals_table SET status = %s WHERE reference = %s AND status != %s AND status != %s",
						AFFILIATES_REFERRAL_STATUS_REJECTED,
						$order_id,
						AFFILIATES_REFERRAL_STATUS_CLOSED,
						AFFILIATES_REFERRAL_STATUS_REJECTED
					) );
				}
			}
		}
	}

	/**
	 * Accepts referrals for the given order.
	 * Only acts on referrals that have not been closed.
	 *
	 * @param int $order_id order post id
	 */
	public static function order_status_accepted_payment( $order_id ) {
		global $wpdb;
		$referrals_table = _affiliates_get_tablename( "referrals" );
		$referrals = $wpdb->get_results( $wpdb->prepare(
			"SELECT referral_id FROM $referrals_table WHERE reference = %s AND status != %s AND status != %s",
			$order_id,
			AFFILIATES_REFERRAL_STATUS_ACCEPTED,
			AFFILIATES_REFERRAL_STATUS_CLOSED
		) );
		if ( $referrals ) {
			foreach( $referrals as $referral ) {
				if ( class_exists( 'Affiliates_Referral_WordPress' ) ) {
					try {
						$r = new Affiliates_Referral_WordPress( $referral->referral_id );
						$r->update( array( 'status' => AFFILIATES_REFERRAL_STATUS_ACCEPTED ) );
					} catch ( Exception $ex ) {
					}
				} else {
					$wpdb->query( $wpdb->prepare(
						"UPDATE $referrals_table SET status = %s WHERE reference = %s AND status != %s AND status != %s",
						AFFILIATES_REFERRAL_STATUS_ACCEPTED,
						$order_id,
						AFFILIATES_REFERRAL_STATUS_CLOSED,
						AFFILIATES_REFERRAL_STATUS_ACCEPTED
					) );
				}
			}
		}
	}

	/**
	 * Accepts referrals for the given order.
	 * Only acts on referrals that have not been closed.
	 * 
	 * @see order_status_accepted_payment()
	 *
	 * @param int $order_id order post id
	 */
	public static function order_status_job_dispatched( $order_id ) {
		self::order_status_accepted_payment( $order_id );
	}

	/**
	 * Accepts referrals for the given order.
	 * Only acts on referrals that have not been closed.
	 *
	 * @see order_status_accepted_payment()
	 *
	 * @param int $order_id order post id
	 */
	public static function order_status_closed_order( $order_id ) {
		self::order_status_accepted_payment( $order_id );
	}

	/**
	 * Marks referrals for the given order as pending.
	 * Only acts on referrals that have not been closed.
	 *
	 * @see order_status_order_received()
	 *
	 * @param int $order_id order post id
	 */
	public static function order_status_incomplete_sale( $order_id ) {
		self::order_status_order_received( $order_id );
	}

	/**
	 * Marks referrals for the given order as pending.
	 * Only acts on referrals that have not been closed.
	 *
	 * @param int $order_id order post id
	 */
	public static function order_status_order_received( $order_id ) {
		global $wpdb;
		$referrals_table = _affiliates_get_tablename( "referrals" );
		$referrals = $wpdb->get_results( $wpdb->prepare(
			"SELECT referral_id FROM $referrals_table WHERE reference = %s AND status != %s AND status != %s",
			$order_id,
			AFFILIATES_REFERRAL_STATUS_PENDING,
			AFFILIATES_REFERRAL_STATUS_CLOSED
		) );
		if ( $referrals ) {
			foreach( $referrals as $referral ) {
				if ( class_exists( 'Affiliates_Referral_WordPress' ) ) {
					try {
						$r = new Affiliates_Referral_WordPress( $referral->referral_id );
						$r->update( array( 'status' => AFFILIATES_REFERRAL_STATUS_PENDING ) );
					} catch ( Exception $ex ) {
					}
				} else {
					$wpdb->query( $wpdb->prepare(
						"UPDATE $referrals_table SET status = %s WHERE reference = %s AND status != %s AND status != %s",
						AFFILIATES_REFERRAL_STATUS_PENDING,
						$order_id,
						AFFILIATES_REFERRAL_STATUS_CLOSED,
						AFFILIATES_REFERRAL_STATUS_PENDING
					) );
				}
			}
		}
	}

}
Affiliates_WPEC_Handler::init();
