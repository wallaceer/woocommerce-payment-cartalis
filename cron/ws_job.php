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
$tmp_dir = $payment_gateway->settings['cartalis_tmp_directory'] ?? null;
$email_alert = $payment_gateway->settings['cartalis_email_alert'] ?? null;
$status_paid = $payment_gateway->settings['cartalis_status_order_paid'] ?? null;
$status_new_order = sanitize_text_field($payment_gateway->settings['cartalis_status_new_order']) ?? null;

if($ftp_status === 0 || $ftp_status === null) return;

/**
 * Ftp connection
 */
include __DIR__ . '/../class/ws_ftp.php';
include __DIR__ . '/../class/ws_utilities.php';

//Payments report file
$file = null; //'DUFER5190220200914002758001.zip';
//$filetxt = 'DUFER5190220200914002758001.txt';

$ftp = new ws_ftp();
$filejob = $ftp->ftpExec($host, $user, $password, $remote_dir, $tmp_dir, $file);

if($filejob === false || $filejob === null) {
    $ftp->cartalis_email($email_alert, 'FATAL ERROR CARTALIS: Il file di rendicontazione per il giorno '.date("d-m-Y").' sul server ftp non esiste!');
    return $ftp->cartalis_logs("FATAL ERROR: file not exist!!\r\n");
}else{
    //Unzip file
    $util = new ws_utilities();
    $util->unzip($tmp_dir.DIRECTORY_SEPARATOR.$filejob, $tmp_dir);
    $ftp->cartalis_logs("Downloaded file ".$filejob."\r\n");

    //Extracts data
    $fileToAnalyze = preg_replace('/.[^.]*$/', '', $filejob).'.txt';
    if(!file_exists('/tmp/'.$fileToAnalyze)){
        return $ftp->cartalis_logs("FATAL ERROR: File to analyze ".$fileToAnalyze." not exist!\r\n");
    }
    $ftp->cartalis_logs("Analyzing file ".$fileToAnalyze."\r\n");
    $rowsData = [];
    $analysis = new ws_cartalis();
    $paymentsRowsData = $analysis->fileAnalize($tmp_dir.DIRECTORY_SEPARATOR.$fileToAnalyze);


    /**
     * Order's payment update
     */
    foreach($paymentsRowsData as $prd){
        //Remove 0 at the beginning
        $order_id = (int)$prd['customerCode'];
        $order_id = (string) $order_id;

        if(preg_match("/[0-9]+/", $order_id)){
            if ( !function_exists( 'wc_get_order' ) ) {
                require_once '/includes/wc-order-functions.php';
            }

            $result = wc_get_order($order_id);

            if($result !== false){
                $order = new WC_Order($order_id);
                if($order->get_status() === $status_new_order){
                    $order->update_status($status_paid, __('Pagamento CARTALIS registrato in data '.$prd['dateTransmission'].' e accreditato in data ').$prd['dateAccredit']." (aammgg). ", true);
                    $ftp->cartalis_logs("\e[32mPayment for order ".$order_id." updated to $status_paid!\e[39m");
                }else{
                    $ftp->cartalis_logs("\e[33mPayment for order ".$order_id." are already $status_paid or $status_paid is null!\e[39m");
                }
            }else{
                $ftp->cartalis_logs("\e[31mERROR: Order ".$order_id." to update not found!\e[39m");
            }

        }

    }

    /**
     * At the end of job, should delete the local file
     */
    $ftp->localFileDelete($tmp_dir.DIRECTORY_SEPARATOR.$filejob);
    $ftp->localFileDelete($tmp_dir.DIRECTORY_SEPARATOR.$fileToAnalyze);

    /**
     * Zipping yesterady log file
     */
    $util->wsZip();

    /**
     * Empty log file
     */
    $util->emptyFileLog();

}