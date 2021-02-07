<?php

/**
 * Class ws_cartalis
 */
class ws_cartalis{

    /**
     * @var
     */
    public $_file;

    /**
     * Debug to log file
     * @var int 1=On, 0=Off
     */
    public static $debug = 1;

    /**
     * @var string
     */
    public static $logfile = __DIR__ . '/../var/log/cartalis.log';

    /**
     * @param $filename
     *
     * Summary record map
     * [1] – Codice CUAS
     * [12] – Numero C/C Beneficiario
     * [6] – Data valuta
     * [Blank] – [14] – Filler
     * [3] – Identificativo riepilogo: valorizzato sempre a “999”
     * [8] – Numero record dettaglio file rendicontazione
     * [12] – Importo totale rendicontato (in centesimi di Euro)
     * [8] – Valorizzato sempre a “00000000”
     * [12] – Valorizzato sempre a “000000000000”
     * [8] – Valorizzato sempre a “00000000”
     * [12] – Valorizzato sempre a “000000000000”
     * [104] – Filler (Blank)
     *
     * Row record map
     * [15] -   Id-transazione
     * [12] -   Numero C/C beneficiario (Param3)
     * [6] -    Data-transazione (Nella forma aammgg)
     * [3] -    Tipo Documento (247 = premarcati Rav/Mav)
     * [10] -   Importo (In centesimi di euro)
     * [8] -    Ufficio e Sportello (CodiceCliente + OperatorCode)
     * [1] -    Divisa (2=Euro)
     * [6] -    Data contabile accredito (allibramento) (Nella forma aammgg)
     * [16] -   Codice Cliente = Codice bollettino
     * [3] -    Tipologia di pagamento (T1 (CNT), 2 (PBM), 3 (Carta di Credito))
     * [1] -    Codice Fisso (Const = 4)
     * [119] -  Filler (Blank)
     */
    public function fileAnalize($filename){
        $paymentsData = [];
        if($filename === null) {
            $this->cartalis_logs('ERROR: File empty!');
        }
        else{
            $this->_file = file_get_contents($filename);
            //Separate rows
            $_newFile = explode("\n", $this->_file);
            $this->cartalis_logs('Num Rows: '.count($_newFile));
            $this->cartalis_logs('Summary Row (Last row): '.end($_newFile));
            $this->cartalis_logs("\nPayments Rows: \n");
            //$this->cartalis_logs(json_encode($_newFile));
            if(count($_newFile)>1){
                foreach ($_newFile as $pfi => $pfv){
                    //Exclude last rows, because this is a summary
                    if($pfi < count($_newFile)-1){
                        //Row data mapping
                        $transaciontId = substr($pfv, 0, 15);
                        $ccNumber = substr($pfv, 15, 12);
                        $dateTransmission = substr($pfv, 27, 6);
                        $docType = substr($pfv, 33, 3);
                        $amount = substr($pfv, 36, 10);
                        $office = substr($pfv, 46, 8);
                        $currency = substr($pfv, 54, 1);
                        $dateAccredit = substr($pfv, 55, 6);
                        $customerCode = substr($pfv, 61, 16);
                        $paymentType = substr($pfv, 77, 3);
                        $code = substr($pfv, 80, 1);
                        $this->cartalis_logs(
                            "ID: $transaciontId\n
                            CC Number: $ccNumber\n
                            Date Transmission: $dateTransmission\n
                            Doc Type: $docType\n
                            Amount: $amount\n
                            Office: $office\n
                            Currency: $currency\n
                            Date Accredit: $dateAccredit\n
                            Customer Code: $customerCode\n
                            Payment Type: $paymentType\n
                            Code: $code\n
                            \r\n"
                        );

                        $paymentsData[] = [
                            'transactionId' => $transaciontId,
                            'ccNumber' => $ccNumber,
                            'dateTransmission' => $dateTransmission,
                            'docType' => $docType,
                            'amount' => $amount,
                            'office' => $office,
                            'currency' => $currency,
                            'dateAccredit' => $dateAccredit,
                            'customerCode' => $customerCode,
                            'paymentType' => $paymentType,
                            'code' => $code
                        ];

                        $this->cartalis_logs($pfv."\r\n");
                    }
                }
            }
            return $paymentsData;
        }
    }

    /**
     * Create custom log
     * @param $message
     */
    public function cartalis_logs($message) {
        if(self::$debug === 1){
            $this->logWrite($message);
        }
    }

    public function cartalis_email($mailto, $message){
        if(self::$debug === 1){
            $site_title = get_bloginfo( 'name' );
            $subject = $site_title.' - Cartalis error';
            $headers = '';

            $sent_message = mail( $mailto, $subject, json_encode($message), $headers);
            if ( $sent_message === true) {
                $this->cartalis_logs('Sent email for Cartalis error.');
            } else {
                $this->cartalis_logs('Problem on sending email for Cartalis error.');
            }
        }
    }

    /**
     * @param $message
     */
    public function logWrite($message){
        if(is_array($message)) {
            $message = json_encode($message);
        }
        $file = fopen(self::$logfile,"a");
        fwrite($file, "\n" . date('Y-m-d h:i:s') . " :: " . $message);
        fclose($file);
    }

}