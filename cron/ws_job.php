<?php

/**
 * WP Initialize
 */
require __DIR__ . "/../../../../wp-load.php";

/**
 * FTP Data
 */
// HERE define you payment gateway ID (from $this->id in your plugin code)
$payment_gateway_id = 'wc_cartalis';

// Get an instance of the WC_Payment_Gateways object
$payment_gateways   = WC_Payment_Gateways::instance();

// Get the desired WC_Payment_Gateway object
$payment_gateway    = $payment_gateways->payment_gateways()[$payment_gateway_id];

// Display all the raw data for this payment gateway
//echo '<pre>'; print_r( $payment_gateway); echo '</pre>';

//Connection's data
$host = $payment_gateway->settings['cartalis_ftp_host'] ?? null;
$user = $payment_gateway->settings['cartalis_ftp_user'] ?? null;
$password = $payment_gateway->settings['cartalis_ftp_password'] ?? null;
$ftp_status = $payment_gateway->settings['cartalis_ftp_status'] ?? null;
if($ftp_status === 0 || $ftp_status === null) return;

/**
 * Ftp connection
 */
include __DIR__ . '/../class/ws_ftp.php';
$ftp = new ws_ftp();
$ftp->ftpExec($host, $user, $password);

/**
 * -------------------------
 * Download file and load it
 * -------------------------
 *
 * Tracciato Record
 * Il file dati per la rendicontazione contiene due tipi di record:
 *
 * record di dettaglio,
 * record di riepilogo (obbligatorio).
 *
 * In particolare, il contenuto di un file dati ha la seguente struttura:
 *
 * [Dettaglio]
 * Riepilogo
 *
 * Il file di rendicontazione contiene da 0 a n record di dettaglio e un record di riepilogo.
 * I campi dei record per questo file di rendicontazione sono a lunghezza fissa (senza caratteri separatori tra un campo e il successivo).
 * Il formato del record di dettaglio e di riepilogo sono specificati in un file in formato xml i cui nomi sono configurabili.
 */


/**
 * Order management
 */
$order = new WC_Order('87');
echo $order->get_total()."\r\n";

