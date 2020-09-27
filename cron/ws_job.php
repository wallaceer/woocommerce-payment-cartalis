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
$remote_dir = $payment_gateway->settings['cartalis_ftp_path'] ?? null;
$tmp_dir = $payment_gateway->settings['cartalis_tmp_directory_'] ?? null;

if($ftp_status === 0 || $ftp_status === null) return;

/**
 * Ftp connection
 */
include __DIR__ . '/../class/ws_ftp.php';
include __DIR__ . '/../class/ws_utilities.php';

//Payments report file
$file = 'DUFER5190220200914002758001.zip';
$filetxt = 'DUFER5190220200914002758001.txt';

$ftp = new ws_ftp();
$filejob = $ftp->ftpExec($host, $user, $password, $remote_dir, $tmp_dir, $file);
if($filejob === false) {
    exit('FATAL ERROR: file not exist!');
}else{

    //Unzip file
    $util = new ws_utilities();
    $util->unzip($tmp_dir.DIRECTORY_SEPARATOR.$file, $tmp_dir);

    //Extracts data
    $rowsData = [];
    $analysis = new ws_cartalis();
    $paymentsRowsData = $analysis->fileAnalize($tmp_dir.DIRECTORY_SEPARATOR.$filetxt);
}

/**
 * Order's payment update
 */
foreach($paymentsRowsData as $prd){
    $order_id = str_replace("0", "", $prd['customerCode']);

    if(preg_match("/[0-9]+/", $order_id)){
        if ( !function_exists( 'wc_get_order' ) ) {
            require_once '/includes/wc-order-functions.php';
        }

        // NOTICE! Understand what this does before running.
        $result = wc_get_order($order_id);

        if($result !== false){
            $order = new WC_Order($order_id);
            $order->update_status('processing', __('Pagamento CARTALIS accreditato in data ').$prd['dateAccredit']." (aammgg). ");
            $ftp->cartalis_logs("Payment for order ".$order_id." updated to Processing!");
        }else{
            $ftp->cartalis_logs("ERROR: Order ".$order_id." to update not found!");
        }

    }

}

/**
 * At the end of job, should delete the local file
 */
$ftp->localFileDelete($tmp_dir.DIRECTORY_SEPARATOR.$file);
