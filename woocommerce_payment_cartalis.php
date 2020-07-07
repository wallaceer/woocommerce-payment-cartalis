<?php
/**
* Plugin Name: CARTALIS Payment for Woocommerce
* Plugin URI: https://blog.waltersanti.info
* Description: CARTALIS Payment
* Author: Walter Santi
* Author URI: https://waltersanti.info
* Version: 0.1
*
* @package WC_Admin
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use Ayeo\Barcode;

// AGGIUNGO UN FILTRO PER VISUALIZZARE IL GATEWAY ALL'INTERNO DELLA LISTA DI WOOCOMMERCE
add_filter('woocommerce_payment_gateways', 'Custom_add_gateway_class');
function Custom_add_gateway_class($gateways) {
$gateways[] = 'WC_Cartalis';
return $gateways;
}

// AGGIUNGO L'AZIONE
add_action('plugins_loaded', 'init_wc_custom_payment_gateway');
function init_wc_custom_payment_gateway() {

    class WC_Cartalis extends WC_Payment_Gateway {

        public function __construct() {

            // Required fields
            $this->id = 'wc_cartalis';
            $this->method_title = 'CARTALIS';
            $this->title = __( 'CARTALIS', 'woocommerce' );
            $this->has_fields = true;
            $this->method_description = __('CARTALIS payment gateway', 'woocommerce');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action( 'woocommerce_thankyou', array( $this, 'ws_view_order_and_thankyou_page' ));
            add_action( 'woocommerce_checkout_update_order_meta', 'ws_custom_checkout_field_update_order_meta' );
            add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'ws_custom_checkout_field_display_admin_order_meta'));

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable Custom',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'CARTALIS',
                    'type' => 'text',
                    'description' => 'This controls the payment CARTALIS',
                    'default' => 'CARTALIS Payment Gateway',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Customer Message',
                    'type' => 'textarea',
                    'css' => 'width:500px;',
                    'default' => 'Pay with CARTALIS',
                    'description' => 'Paga con CARTALIS.',
                )
            );
        }

        // PROCESSO DI PAGAMENTO
        function process_payment($order_id) {
            global $woocommerce;

            $order = new WC_Order($order_id);

            // QUI DOVETE METTERE IL VOSTRO CODICE
            //$res = 'CARTALIS pagato walter';//$this->CustomPayment();
            // FINE VOSTRO CODICE

            if ( $order->get_total() > 0 ) {
                // Mark as on-hold (we're awaiting the payment).
                $order->update_status( apply_filters( 'woocommerce_bacs_process_payment_order_status', 'on-hold', $order ), __( 'Awaiting CARTALIS payment', 'woocommerce' ) );
            } else {
                $order->payment_complete();
            }

            //Save CARTALIS barcode
            $this->ws_custom_checkout_field_update_order_meta($order_id);

            $woocommerce->cart->empty_cart();
            //$order->reduce_order_stock();

            return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
            );
        }

        /**
         * Output for the order received page.
         *
         * @param int $order_id Order ID.
         * @param WC_Order $order Order object.
         */
        public function ws_view_order_and_thankyou_page( $order_id ) {
            $order = new WC_Order($order_id);
            if('wc_cartalis' === $order->get_payment_method()){
                $this->generateBarcode( $order_id);
                echo 'codice a barre CARTALIS x pagamento <img src="//'.$_SERVER['HTTP_HOST'].'/wp-content/uploads/barcode/'.$order_id.'.png" />';
            }
        }

        /**
         * Add content to the WC emails.
         *
         * @param WC_Order $order Order object.
         * @param bool     $sent_to_admin Sent to admin.
         * @param bool     $plain_text Email format: plain text or HTML.
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

            if ( ! $sent_to_admin && 'wc_cartalis' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
                //if ( $this->instructions ) {
                    echo wp_kses_post( wpautop( wptexturize( 'codice a barre CARTALIS x pagamento' ) ) . PHP_EOL );
               // }
                //$this->bank_details( $order->get_id() );
            }

        }

        function ws_custom_checkout_field_update_order_meta( $order_id ) {
            $barcode = $this->generateBarcode( $order_id );
            if ( ! empty( $barcode ) ) {
                update_post_meta( $order_id, 'cartalisBarcode', sanitize_text_field( $barcode ) );
            }
        }

        /**
         * Display field value on the order edit page
         */
        function ws_custom_checkout_field_display_admin_order_meta( $order ){
            echo '<p><strong>'.__('CARTALIS Barcode').':</strong> ' . get_post_meta( $order->get_id(), 'cartalisBarcode', true ) . '<img src="//'.$_SERVER['HTTP_HOST'].'/wp-content/uploads/barcode/'.$order->get_id().'.png" /></p>';
        }

        function generateBarcode( $order_id ){
            $code = '(10)123456(400)11';
            $barcodefile = 'wp-content/uploads/barcode/'.$order_id.'.png';
            /**
             * Barcode generation
             */
            if (!file_exists($barcodefile)) {
                require_once('vendor/autoload.php');
                $builder = new Barcode\Builder();
                $builder->setBarcodeType('gs1-128');
                $builder->setFilename($barcodefile);
                $builder->setImageFormat('png');
                $builder->setWidth(500);
                $builder->setHeight(150);
                $builder->setFontPath('FreeSans.ttf');
                $builder->setFontSize(15);
                $builder->setBackgroundColor(255, 255, 255);
                $builder->setPaintColor(0, 0, 0);
                $builder->saveImage($code);
            }

            return $code;
        }

    }
}