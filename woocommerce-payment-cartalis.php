<?php
/**
* Plugin Name: CARTALIS Payment for Woocommerce
* Plugin URI: https://blog.waltersanti.info
* Description: CARTALIS Payment
* Author: Walter Santi
* Author URI: https://waltersanti.info
* Version: 1.0.0
*
* @package WC_Admin
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use Ayeo\Barcode;

// AGGIUNGO UN FILTRO PER VISUALIZZARE IL GATEWAY ALL'INTERNO DELLA LISTA DI WOOCOMMERCE
add_filter('woocommerce_payment_gateways', 'Ws_add_gateway_class');
function Ws_add_gateway_class($gateways) {
$gateways[] = 'WC_Cartalis';
return $gateways;
}

// AGGIUNGO L'AZIONE
add_action('plugins_loaded', 'init_wc_cartalis_payment_gateway');
function init_wc_cartalis_payment_gateway() {

    class WC_Cartalis extends WC_Payment_Gateway {

        public function __construct() {

            //Root configuration
            $this->uploadDir = wp_upload_dir();

            // Required fields
            $this->id = 'wc_cartalis';
            $this->method_title = 'CARTALIS';
            $this->title = __( 'CARTALIS', 'woocommerce' );
            $this->has_fields = true;
            $this->method_description = __('Accetta pagamenti tramite Lottomatica', 'woocommerce');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            $this->mandante_prefisso = sanitize_text_field($this->get_option('mandante_prefisso'));
            $this->mandante_codice_identificativo = sanitize_text_field($this->get_option('mandante_codice_identificativo'));

            // Define user set variables.
            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action( 'woocommerce_thankyou', array( $this, 'ws_view_order_and_thankyou_page' ));
            //add_action( 'woocommerce_checkout_update_order_meta', 'ws_custom_checkout_field_update_order_meta' );
            add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'ws_custom_checkout_field_display_admin_order_meta'));

            // Customer Emails
            add_action( 'woocommerce_email_after_order_table', array( $this, 'email_instructions' ), 10, 3 );
            add_filter( 'woocommerce_email_attachments', array( $this, 'attach_file_woocommerce_email'), 10, 3);

            //Cron Job
            register_activation_hook (__FILE__, 'cartalis_cronstarter_activation');
            register_deactivation_hook (__FILE__, 'cartalis_cronstarter_deactivate');
            add_action('wp', 'cartalis_cronstarter_activation');
            // hook that function onto our scheduled event:
            add_action('ws_cartalis_cronjob', 'cartalis_ftp_function');
            //for testing cron
            add_filter( 'cron_schedules', 'cron_add_minute' );
            #add_action( 'init', 'cartalis_cronstarter_activation');

        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Abilita CARTALIS',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'CARTALIS',
                    'type' => 'text',
                    'description' => 'This controls the payment CARTALIS',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Customer Message',
                    'type' => 'textarea',
                    'css' => 'width:500px;',
                    'default' => 'Pay with CARTALIS',
                    'description' => 'Paga con CARTALIS.',
                ),
                'mandante_prefisso' => array(
                    'title' => 'Mandante, prefisso',
                    'type' => 'text',
                    'description' => 'Prefisso del MANDANTE',
                    'default' => '(415)',
                    'desc_tip' => true,
                ),
                'mandante_codice_identificativo' => array(
                    'title' => 'Mandante, codice identificativo',
                    'type' => 'text',
                    'description' => 'Codice identificativo del MANDANTE, 13 digit',
                    'default' => 'XXXXXXXXXXXXK',
                    'desc_tip' => true,
                ),
                'cartalis_ftp_status' => array(
                    'title' => 'Cartalis FTP Status',
                    'type' => 'text',
                    'description' => 'Attiva (1), Disattiva (0), le funzionalità ftp',
                    'default' => '0',
                    'desc_tip' => true,
                ),
                'cartalis_ftp_host' => array(
                    'title' => 'Cartalis FTP Host',
                    'type' => 'text',
                    'description' => 'Nome dell\'host cartalis per il recupero dei pagamenti effettuati',
                    'default' => 'ftp.miosito.com',
                    'desc_tip' => true,
                ),
                'cartalis_ftp_user' => array(
                    'title' => 'Cartalis FTP Username',
                    'type' => 'text',
                    'description' => 'Username per la connessione ftp',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'cartalis_ftp_password' => array(
                    'title' => 'Cartalis FTP Password',
                    'type' => 'text',
                    'description' => 'Password per la connessione ftp',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'cartalis_ftp_path' => array(
                    'title' => 'Cartalis FTP Path',
                    'type' => 'text',
                    'description' => 'Directory da cui recuperare il file',
                    'default' => 'web/dev/',
                    'desc_tip' => true,
                ),
                'cartalis_tmp_directory_' => array(
                    'title' => 'Cartalis tmp directory',
                    'type' => 'text',
                    'description' => 'Directory di appoggio per la lavorazione dei file',
                    'default' => '/tmp',
                    'desc_tip' => true,
                )
            );
        }

        // Payment process
        function process_payment($order_id) {
            global $woocommerce;

            $order = new WC_Order($order_id);

            if ( $order->get_total() > 0 ) {
                //Save CARTALIS barcode
                $this->ws_custom_checkout_field_update_order_meta($order);

                //PDF deposit generation
                $this->generateDepositPdf($order_id);

                // Mark as on-hold (we're awaiting the payment).
                $order->update_status( apply_filters( 'woocommerce_bacs_process_payment_order_status', 'on-hold', $order ), __( 'Awaiting CARTALIS payment', 'woocommerce' ) );

                //Add order note
                // The text for the note
                $note = '<a href="'.get_site_url().'/wp-content/uploads/deposit/'.$order_id.'.pdf" >Bollettino per il pagamento in ricevitoria</a>';

                // Add the note
                $order->add_order_note( $note, 1, 0 );
            } else {
                $order->payment_complete();
            }

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
                $this->generateBarcode( $order);
                $barcodeHttp = get_site_url().'/wp-content/uploads/deposit/'.$order_id.'.pdf';
                echo '<h2 class="woocommerce-order-details__title">Pagamento</h2>'
                    . '<p>Hai scelto di pagare con CARTALIS.</p>'
                    . '<p>In allegato all\'email di conferma ordine troverai il bollettino per effettuare il pagamento presso un qualsiasi punto LIS.</p>'
                    . '<p><a href="'.$barcodeHttp.'" target="_blank">Puoi scaricare il bollettino anche cliccando quì</a></p>';
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

            if ( 'wc_cartalis' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {

                $orderId = $order->get_id();
                $barcode = $this->generateBarcode( $order );

                $text = '<h2 class="email-upsell-title">Pagamento</h2>'
                        .'<p>In allegato alla presente email trovi il bollettino per il pagamento.</p>'
                        .'<p>Puoi effettuare il pagamento in uno qualsiasi dei punti LIS.</p>';

                if ( ! empty( $barcode ) ) {
                    echo wp_kses_post( wpautop( wptexturize( $text ) ) . PHP_EOL );
                }

            }

        }

        /**
         * Add attachment to email
         *
         * @param WC_Order $order Order object
         * @param $attachments
         * @return mixed
         */
        function attach_file_woocommerce_email($attachments, $id, $object)
        {
            $attachmentFile = $this->uploadDir['basedir'] . '/deposit/'.$object->get_id().'.pdf';
            if ( file_exists( $attachmentFile ) ) {
                $attachments[] = $attachmentFile;
            }
            return $attachments;
        }


        /**
         * Save barcode to order's data
         * @param $order
         */
        function ws_custom_checkout_field_update_order_meta( $order ) {
            $order_id = $order->get_id();
            $barcode = $this->generateBarcode( $order );
            if ( ! empty( $barcode ) ) {
                update_post_meta( $order_id, 'cartalisBarcode', sanitize_text_field( $barcode ) );
            }
        }

        /**
         * Display field value on the order edit page
         */
        function ws_custom_checkout_field_display_admin_order_meta( $order ){
            $order_id = $order->get_id();
            $barcodeHttp = '//'.$_SERVER['HTTP_HOST'].'/wp-content/uploads/barcode/'.$order_id.'.png';

            echo '<h3>Pagamento</h3>'
                . '<p>'.__('Pagamento tramite CARTALIS. <br />Codice a barre per eseguire il pagamento').':</p>'
                . get_post_meta( $order_id, 'cartalisBarcode', true )
                . '<p>'
                . '<a href="'.$barcodeHttp.'" target="_blank"><img src="'.$barcodeHttp.'" width="300px/></a>'
                . '</p>';
        }

        /**
         * @param $order
         * @return string
         * barcode generation
         */
        function generateBarcode( $order ){
            $order_id = $order->get_id();
            $default_amount = '000000';

            //prefisso standard che indica che i successivi 18 digit rappresentano il Codice Bollettino;
            $standard_prefix_a = '(8020)';
            //Codice bollettino
            $dispatch_code = '0000000000000001';
            $check_digit = '54';
            //prefisso standard che indica che i successivi 6 digit rappresentano l’importo della fattura;
            $standard_prefix_b = '(3902)';
            //importo della fattura in cui gli ultimi 2 digit rappresentano i centesimi.
            $order_amount = str_replace(".", "",$order->get_total());
            $amount = substr_replace($default_amount, $order_amount, 6-strlen($order_amount), strlen($order_amount));

            $code = $this->mandante_prefisso
                .$this->mandante_codice_identificativo
                .$standard_prefix_a
                .$dispatch_code
                .$check_digit
                .$standard_prefix_b
                .$amount;
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
                $builder->setWidth(1134);
                $builder->setHeight(189);
                $builder->setFontPath('FreeSans.ttf');
                $builder->setFontSize(18);
                $builder->setBackgroundColor(255, 255, 255);
                $builder->setPaintColor(0, 0, 0);
                $builder->saveImage($code);
            }

            return $code;
        }

        /**
         *  Admin Panel Options
         */
        public function admin_options() {
            echo '<h3>' . __('CARTALIS Gateway', 'woo-cartalis-payment-gateway') . '</h3>';
            echo '<p>' . __('With CARTALIS the customer can pay the order in any point of Lottomatica.', 'woo-cartalis-payment-gateway') . '</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }


        public function generateDepositPdf($order_id){
            $barcode = $this->uploadDir['basedir'].'/barcode/'.$order_id.'.png';
            $filename = $this->uploadDir['basedir'].'/deposit/'.$order_id.'.pdf';

            $text_top = utf8_decode("Vai in una tabaccheria, edicola o bar PUNTOLIS, puoi pagare in modo comodo e veloce semplicemente mostrando il codice a barre riportato sotto.");
            $text_top1 = utf8_decode("Il pagamento può essere effettuato con carte di credito e prepagate VISA e MASTERCARD, con carte PagoBancomat o contanti.");
            $text_top2 = utf8_decode("Cerca il punto più vicino a te su www.puntolis.it");

            require( __DIR__ . '/ws_fpdf.php');
            $pdf = new ws_fpdf();
            $pdf->AddPage("P", 'A4');
            $pdf->SetFont('Times','B',8);
            $pdf->SetXY(4,4);
            $pdf->Cell(85,10,'Bollettino di pagamento per l\'ordine '.$order_id );
            $pdf->SetFont('Times','',8);
            $pdf->SetXY(4,15);
            $pdf->drawTextBox($text_top, 85, 10, 'L', 'T', false);
            $pdf->SetXY(4,26);
            $pdf->drawTextBox($text_top1, 85, 10, 'L', 'T', false);
            $pdf->SetXY(4,37);
            $pdf->drawTextBox($text_top2, 85, 10, 'L', 'T', false);
            //BARCODE
            $pdf->SetXY(0,50);
            $pdf->Image($barcode, 0, null, 95, null, 'PNG');
            //MARCHIO
            $pdf->SetXY(4,85);
            $pdf->Image(__DIR__ .'/assets/img/puntolis.png', null, null, 85, null, 'PNG');

            $pdf->Output('F', $filename);
        }


        // create a scheduled event (if it does not exist already)
        function cartalis_cronstarter_activation() {
            if( !wp_next_scheduled( 'ws_cartalis_cronjob' ) ) {
                wp_schedule_event( time(), 'everyminute', 'ws_cartalis_cronjob' );
            }
        }

        // unschedule event upon plugin deactivation
        function cartalis_cronstarter_deactivate() {
            // find out when the last event was scheduled
            $timestamp = wp_next_scheduled ('ws_cartalis_cronjob');
            // unschedule previous event if any
            wp_unschedule_event ($timestamp, 'ws_cartalis_cronjob');
        }


        // here's the function we'd like to call with our cron job
        function cartalis_ftp_function() {

            //echo 'ciao';

            #include __DIR__ . '/class/ws_ftp.php';
            #$ftp = new ws_ftp;
            #$ftp->exec();
            // do here what needs to be done automatically as per your schedule
            // in this example we're sending an email

            // components for our email
            // $recepients = 'santi.walter@gmail.com';
            // $subject = 'Hello from your Cron Job';
            // $message = 'This is a test mail sent by WordPress automatically as per your schedule.';

            // let's send it
            //wp_mail( 'hello@example.com', 'WP Crontrol', 'WP Crontrol rocks!' );
        }

        // add custom interval
        function cron_add_minute( $schedules ) {
            // Adds once every minute to the existing schedules.
            $schedules['everyminute'] = array(
                'interval' => 60,
                'display' => __( 'Once Every Minute' )
            );
            return $schedules;
        }


    }
}