<?php


include __DIR__.'/ws_cartalis.php';

class ws_ftp extends ws_cartalis {

    /**
     * Remote directory
     * @var string
     */
    #static $remote_dir = '/web/dev/';
    /**
     * Remote file to load
     * @var string
     */
    //static $remote_file = 'ETRA29000820200902002637001.txt';

    /**
     * Open connection
     * @return false|resource
     */
    public function ftpConnection($host, $user, $password, $remote_dir){
        $ftp_host = $host;
        $ftp_username = $user;
        $ftp_password = $password;
        $ftp_conn = ftp_connect($ftp_host);
        $ftp_login = ftp_login($ftp_conn, $ftp_username, $ftp_password);
        $this->ftpPassiveMode($ftp_conn, true);
        if ((!$ftp_conn) || (!$ftp_login)) {
            $this->cartalis_logs("FTP connection has failed!");
        } else {
            $this->cartalis_logs("Connected to ".$ftp_host.", for user ".$ftp_username." with connection ".$ftp_conn);
            $this->cartalis_logs("Current directory: " . ftp_pwd($ftp_conn));
            $this->cartalis_logs("Setting dir ".$remote_dir);
            if (ftp_chdir($ftp_conn, $remote_dir)) {
                $this->cartalis_logs("Current directory is now: " . ftp_pwd($ftp_conn));
            } else {
                $this->cartalis_logs("Couldn't change directory");
            }
        }

        return $ftp_conn;
    }

    protected function ftpPassiveMode($conn_id, $pasv=false){
        return ftp_pasv($conn_id, $pasv);
    }

    /**
     * Close connection
     * @param $conn_id
     */
    function ftpCloseConnection($conn_id){
        ftp_close($conn_id);
        $this->cartalis_logs('Connection closed!');
    }

    /**
     * Create custom log
     * @param $message
     */
    /*function ws_logs($message) {
        if(self::$debug === 1){
            $log = new ws_logs();
            $log->logWrite($message);
        }
    }*/

    /**
     * Extract list of files in current directory for current connection
     * @param null $conn_id
     * @param $remote_dir
     * @return array|null
     */
    protected function ftpRawList($conn_id=null, $remote_dir){
        $list_of_files = null;
        if($conn_id !== null){
            $list_of_files = ftp_rawlist($conn_id, $remote_dir);
        }
        return $list_of_files;
    }

    /**
     * Get selected file to local
     * @param null $conn_id
     * @param $remote_dir
     * @param $tmp_dir
     * @param $file
     * @return string|null
     */
/*    protected function ftpGet($conn_id, $remote_dir, $tmp_dir, $file){
        // path to remote file
        $remote_file = $file;
        $local_file_tmp = $tmp_dir.DIRECTORY_SEPARATOR.$file;
        #$local_file = $tmp_dir.DIRECTORY_SEPARATOR.'file.txt';
        #$local_file = $file;

        // open some file to write to
        #$handle = fopen($local_file, 'w');

        // try to download $remote_file and save it to $handle
        if(ftp_get($conn_id, $local_file_tmp, $remote_file, FTP_BINARY)){

            header("Content-type: application/x-rar-compressed");

            // Also helps to send Content-length
            header("Content-length: " . filesize($local_file_tmp));

            // Dump out the file contents
            //echo file_get_contents($local_file_tmp);
            #fwrite($handle, file_get_contents($local_file_tmp), filesize($local_file_tmp));


        #if (ftp_nb_get($conn_id, $local_file, $remote_file, FTP_BINARY)) {
            $this->cartalis_logs("Successfully written $remote_file to $local_file_tmp");
            $this->cartalis_logs(file_get_contents($local_file_tmp));
            $result = true;
        } else {
            $this->cartalis_logs("There was a problem while downloading $remote_file to $local_file_tmp with connection ".$conn_id);
            $result = false;
        }

        #fclose($handle);
         return $result;
    }*/
    protected function ftpGet($conn_id, $remote_dir, $tmp_dir, $file){
        // path to remote file
        $remote_file = $file;
        $local_file_tmp = $tmp_dir.DIRECTORY_SEPARATOR.$file;
        #$local_file = $tmp_dir.DIRECTORY_SEPARATOR.'file.txt';
        #$local_file = $file;

        // open some file to write to
        #$handle = fopen($local_file, 'w');

        // try to download $remote_file and save it to $handle
        if(ftp_get($conn_id, $local_file_tmp, $remote_file, FTP_BINARY)){

            #header("Content-type: application/x-rar-compressed");

            // Also helps to send Content-length
            #header("Content-length: " . filesize($local_file_tmp));

            // Dump out the file contents
            //echo file_get_contents($local_file_tmp);
            #fwrite($handle, file_get_contents($local_file_tmp), filesize($local_file_tmp));


            #if (ftp_nb_get($conn_id, $local_file, $remote_file, FTP_BINARY)) {
            $this->cartalis_logs("Successfully written $remote_file to $local_file_tmp");
            #$this->cartalis_logs(file_get_contents($local_file_tmp));
            $result = true;
        } else {
            $this->cartalis_logs("There was a problem while downloading $remote_file to $local_file_tmp with connection ".$conn_id);
            $result = false;
        }

        #fclose($handle);
        return $result;
    }

    /**
     * Application
     * @param $host
     * @param $user
     * @param $password
     * @param $remote_dir
     * @param $tmp_dir
     * @param $file
     * @return string|null
     */
    public function ftpExec($host, $user, $password, $remote_dir, $tmp_dir, $file){
        $resFile = null;

        if($host === null || $user === null || $password === null){
            $this->cartalis_logs("Ftp parameters missing!");
            return $resFile;
        }

        //Connecting to ftp server
        $ftp_conn = $this->ftpConnection($host, $user, $password, $remote_dir);
        if($ftp_conn !== false){
            //Custom sections
            //Files list
            $filesList = $this->ftpRawList($ftp_conn, $remote_dir);
            if($filesList !== null){
                $this->cartalis_logs("List of files: ".json_encode($filesList));
                $resFile = $this->ftpGet($ftp_conn, $remote_dir, $tmp_dir, $file);
            }else{
                $this->cartalis_logs("No file presents!");
            }

            //Close connection
            $this->ftpCloseConnection($ftp_conn);

        }
        else{
            $this->cartalis_logs("Connection error!");
        }

        return $resFile;
    }


    public function localFileDelete($filename){
        try{
            unlink($filename);
        }
        catch (\Exception $e){
            $this->cartalis_logs('Local file delete error: '.$e->getMessage());
        }

    }

}




