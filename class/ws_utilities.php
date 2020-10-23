<?php

class ws_utilities extends ws_cartalis {

    /**
     * Unzip cartalis file report
     * @param $file
     * @param $destination
     */
    public function unzip($file, $destination){
        $zip = new ZipArchive;
        if ($zip->open($file) === TRUE) {

            // Unzip Path
            if($zip->extractTo($destination)){
                $this->cartalis_logs($file .' unzipped to '.$destination);
            }
            else{
                $this->cartalis_logs('Error unzipping '.$file .' to '.$destination);
            }
            $zip->close();
        } else {
            $this->cartalis_logs('Error opening '.$file);
        }
    }

    /**
     * Zip log file
     */
    public function wsZip(){
        $newfile = null;
        $file = static::$logfile;
        if ($handle = fopen($file, "r")){
            $zip_file = $file."-".date("Y-m-d").".zip";
            $zip = new ZipArchive;
            if ($zip->open($zip_file, ZipArchive::CREATE)!==TRUE)
            {
                $this->cartalis_logs("Cannot open <$zip_file>\n");
            }

            $zip->addFile(realpath($file));

            $zip->close();
        }

    }

    /**
     * Empty the log file
     */
    public function emptyFileLog(){
        $file = static::$logfile;
        $f = fopen($file, "r+");
        if ($f !== false) {
            ftruncate($f, 0);
            fclose($f);
        }
    }

}