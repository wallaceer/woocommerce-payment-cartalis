<?php

class Ws_Ftp {

    /**
     * Remote directory
     * @var string
     */
    static $remote_dir = '/web/';
    /**
     * Remote file to load
     * @var string
     */
    static $remote_file = 'robots.txt';
    /**
     * Debug to log file
     * @var int 1=On, 0=Off
     */
    static $debug = 1;

    /**
     * Open connection
     * @return false|resource
     */
    public function ftpConnection($host, $user, $password){
        $ftp_host = $host;
        $ftp_username = $user;
        $ftp_password = $password;
        $ftp_conn = ftp_connect($ftp_host);
        $ftp_login = ftp_login($ftp_conn, $ftp_username, $ftp_password);
        $this->ftpPassiveMode($ftp_conn, true);
        if ((!$ftp_conn) || (!$ftp_login)) {
            $this->ws_logs("FTP connection has failed!");
        } else {
            $this->ws_logs("Connected to ".$ftp_host.", for user ".$ftp_username." with connection".$ftp_conn);
            $this->ws_logs("Current directory: " . ftp_pwd($ftp_conn));
            if (ftp_chdir($ftp_conn, self::$remote_dir)) {
                $this->ws_logs("Current directory is now: " . ftp_pwd($ftp_conn));
            } else {
                $this->ws_logs("Couldn't change directory");
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
        $this->ws_logs('Connection closed!');
    }

    /**
     * Create custom log
     * @param $message
     */
    function ws_logs($message) {
        if(self::$debug === 1){
            if(is_array($message)) {
                $message = json_encode($message);
            }
            $file = fopen("/var/log/cartalis.log","a");
            fwrite($file, "\n" . date('Y-m-d h:i:s') . " :: " . $message);
            fclose($file);
        }
    }

    /**
     * Extract list of files in current directory for current connection
     * @param null $conn_id
     * @return array|null
     */
    protected function ftpRawList($conn_id=null){
        $list_of_files = null;
        if($conn_id !== null){
            $list_of_files = ftp_rawlist($conn_id, self::$remote_dir);
        }
        return $list_of_files;
    }

    /**
     * Get selected file to local
     * @param null $conn_id
     */
    protected function ftpGet($conn_id=null){
        // path to remote file
        $remote_file = self::$remote_dir.self::$remote_file;
        $local_file = '/tmp/riassunto.txt';

        // open some file to write to
        $handle = fopen($local_file, 'w');

        // try to download $remote_file and save it to $handle
        if (ftp_fget($conn_id, $handle, $remote_file, FTP_ASCII, 0)) {
            $this->ws_logs("successfully written to $local_file");
        } else {
            $this->ws_logs("There was a problem while downloading $remote_file to $local_file with connection".$conn_id);
        }

        fclose($handle);

    }





    /**
     * Application
     */
    public function ftpExec($host, $user, $password){
        $ftp_conn = $this->ftpConnection($host, $user, $password);
        if($ftp_conn !== false){
            //Custom ections
            //Files list
            $filesList = $this->ftpRawList($ftp_conn);
            if($filesList !== null){
                $this->ws_logs("List of files: ".json_encode($filesList));
                $this->ftpGet($ftp_conn);
            }else{
                $this->ws_logs("No file presents!");
            }

            //Close connection
            $this->ftpCloseConnection($ftp_conn);
        }

    }


}




