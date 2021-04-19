<?php

/**
 * Plugin Name: YabetooPay
 * Plugin URI: https://omukiguy.com
 * Author Name: Yabetoo Inc.
 * Author URI: https://yabetoopay.com
 * Description: This plugin allows for local content payment systems.
 * Version: 0.1.0
 * License: 0.1.0
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: yabetoo-pay-woo
 *
 * Class WC_Gateway_Yabetoo file.
 *
 * @package WooCommerce\Yabetoo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_action( 'plugins_loaded', 'yabetoo_payment_init', 11 );
add_filter( 'woocommerce_currencies', 'yabetoo_add_ugx_currencies' );
add_filter( 'woocommerce_currency_symbol', 'yabetoo_add_ugx_currencies_symbol', 10, 2 );
add_filter( 'woocommerce_payment_gateways', 'add_to_woo_yabetoo_payment_gateway');

function yabetoo_payment_init() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
        require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-payment-gateway-yabetoo.php';
        require_once plugin_dir_path( __FILE__ ) . '/includes/yabetoo-order-statuses.php';
        //require_once plugin_dir_path( __FILE__ ) . '/includes/yabetoo-checkout-description-fields.php';
    }
}

function add_to_woo_yabetoo_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Yabetoo';
    return $gateways;
}

function yabetoo_add_ugx_currencies( $currencies ) {
    $currencies['UGX'] = __( 'Ugandan Shillings', 'yabetoo-payments-woo' );
    return $currencies;
}

function yabetoo_add_ugx_currencies_symbol( $currency_symbol, $currency ) {
    switch ( $currency ) {
        case 'UGX':
            $currency_symbol = 'UGX';
            break;
    }
    return $currency_symbol;
}