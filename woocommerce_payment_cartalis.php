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

defined('ABSPATH') || exit;

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

// CAMPI OBLIGATORI
$this->id = 'wc_cartalis';
$this->method_title = 'CARTALIS';
$this->title = 'CARTALIS';
$this->has_fields = true;
$this->method_description = 'CARTALIS payment gateway';

// CARICO LE IMPOSTAZIONI
$this->init_form_fields();
$this->init_settings();
$this->enabled = $this->get_option('enabled');
$this->title = $this->get_option('title');
$this->description = $this->get_option('description');

// PROCESSO LE IMPOSTAZIONI
add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
}

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
'default' => 'Weld Payment Gateway',
'description' => 'Paga con CARTALIS.',
)
);
}

// PROCESSO DI PAGAMENTO
function process_payment($order_id) {
global $woocommerce;

$order = new WC_Order($order_id);

// QUI DOVETE METTERE IL VOSTRO CODICE
$res = 'CARTALIS pagato walter';//$this->CustomPayment();
// FINE VOSTRO CODICE

$order->update_status('processing', 'Additional data like transaction id or reference number');
$woocommerce->cart->empty_cart();
$order->reduce_order_stock();

return array(
'result' => 'success',
'redirect' => $this->get_return_url($order)
);
}
}
}