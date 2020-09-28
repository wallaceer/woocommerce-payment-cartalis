<?php

class ws_utilities extends ws_cartalis {

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



}