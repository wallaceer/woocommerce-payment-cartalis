<?php

class ws_utilities extends ws_cartalis {

    public function unzip($file, $destinationDir){
        $zip = new ZipArchive;
        if ($zip->open('$file') === TRUE) {

            // Unzip Path
            $zip->extractTo('/Destination/Directory/');
            $zip->close();
            echo 'Unzipped Process Successful!';
        } else {
            echo 'Unzipped Process failed';
        }
    }

}