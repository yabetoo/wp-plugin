<?php

add_filter( 'woocommerce_gateway_description', 'yabetoo_pay_description_fields', 20, 2 );
add_action( 'woocommerce_checkout_process', 'yabetoo_pay_description_fields_validation' );
add_action( 'woocommerce_checkout_update_order_meta', 'yabetoo_checkout_update_order_meta', 10, 1 );
add_action( 'woocommerce_admin_order_data_after_billing_address', 'yabetoo_order_data_after_billing_address', 10, 1 );
add_action( 'woocommerce_order_item_meta_end', 'yabetoo_order_item_meta_end', 10, 3 );

function yabetoo_pay_description_fields( $description, $payment_id ) {

    if ( 'yabetoopay' !== $payment_id ) {
        return $description;
    }

    ob_start();

    echo '<div style="display: block; width:300px; height:auto; margin-top: 20px">';
    //echo '<img src="' . plugins_url('../assets/icon.png', __FILE__ ) . '">';

    echo '<div style="margin-bottom: 20px">';
    woocommerce_form_field(
        'paying_country',
        array(
            'type' => 'select',
            'label' => __( 'Payment Country', 'yabetoo-payments-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
            'options' => array(
                'none' => __( 'Select Country', 'yabetoo-payments-woo' ),
                'congo' => __( 'Congo', 'yabetoo-payments-woo' ),
                'congo_rdc' => __( 'Congo RDC', 'yabetoo-payments-woo' ),
            ),
        )
    );
    echo '</div>';

    echo '<div style="margin-bottom: 20px">';
    woocommerce_form_field(
        'paying_network',
        array(
            'type' => 'select',
            'label' => __( 'Payment Network', 'yabetoo-payments-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
            'options' => array(
                'none' => __( 'Select Phone Network', 'yabetoo-payments-woo' ),
                'mtn_mobile' => __( 'MTN Mobile Money', 'yabetoo-payments-woo' ),
                'airtel_money' => __( 'Airtel Money', 'yabetoo-payments-woo' ),
            ),
        )
    );
    echo '</div>';

    woocommerce_form_field(
        'payment_number',
        array(
            'type' => 'text',
            'label' =>__( 'Payment Phone Number', 'yabetoo-payments-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
        )
    );

    echo '</div>';

    $description .= ob_get_clean();

    return $description;
}


function yabetoo_pay_description_fields_validation() {
    if( 'yabetoopay' === $_POST['payment_method'] && ! isset( $_POST['payment_number'] )  || empty( $_POST['payment_number'] ) ) {
        wc_add_notice( 'Please enter a number that is to be billed', 'error' );
    }
}

function yabetoo_checkout_update_order_meta( $order_id ) {
    if( isset( $_POST['payment_number'] ) || ! empty( $_POST['payment_number'] ) ) {
        update_post_meta( $order_id, 'payment_number', $_POST['payment_number'] );
    }
}

function yabetoo_order_data_after_billing_address( $order ) {
    echo '<p><strong>' . __( 'Payment Phone Number:', 'yabetoo-payments-woo' ) . '</strong><br>' . get_post_meta( $order->get_id(), 'payment_number', true ) . '</p>';
}

function techiepress_order_item_meta_end( $item_id, $item, $order ) {
    echo '<p><strong>' . __( 'Payment Phone Number:', 'yabetoo-payments-woo' ) . '</strong><br>' . get_post_meta( $order->get_id(), 'payment_number', true ) . '</p>';
}