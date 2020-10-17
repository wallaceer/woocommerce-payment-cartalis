<?php


include __DIR__.'/ws_cartalis.php';

class ws_ftp extends ws_cartalis {

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
     * Extract list of files with details in current directory for current connection
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
     * Extract list of files in current directory for current connection
     * @param null $conn_id
     * @param $remote_dir
     * @return array|null
     */
    protected function ftpNList($conn_id=null, $remote_dir){
        $list_of_files = null;
        if($conn_id !== null){
            $list_of_files = ftp_nlist($conn_id, $remote_dir);
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
    protected function ftpGet($conn_id, $remote_dir, $tmp_dir, $file){
        // path to remote file
        $remote_file = $file;
        $local_file_tmp = $tmp_dir.DIRECTORY_SEPARATOR.$file;

        // try to download $remote_file and save it to $handle
        if(ftp_get($conn_id, $local_file_tmp, $remote_file, FTP_BINARY)){
            $this->cartalis_logs("Successfully written $remote_file to $local_file_tmp");
            $result = true;
        } else {
            $this->cartalis_logs("There was a problem while downloading $remote_file to $local_file_tmp with connection ".$conn_id);
            $result = false;
        }
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
    public function ftpExec($host, $user, $password, $remote_dir, $tmp_dir, $file=null){
        $resFile = false;

        if($host === null || $user === null || $password === null){
            $this->cartalis_logs("Ftp parameters missing!");
            return $resFile;
        }

        //Connecting to ftp server
        $ftp_conn = $this->ftpConnection($host, $user, $password, $remote_dir);
        if($ftp_conn !== false){
            //Custom sections
            //Files list
            $filesList = $this->ftpNList($ftp_conn, "-a ".$remote_dir);
            if($filesList !== null){
                $this->cartalis_logs("List of files: ".json_encode($filesList));
                if($file !== null){
                    //I know the name of file that I should download
                    if($this->ftpGet($ftp_conn, $remote_dir, $tmp_dir, $file) === true){
                        $resFile = $file;
                    }
                    else {
                        return false;
                    }
                }
                else{
                    //I don't know the name of file that I should download, therefore I should download the last modified file from a list
                    $filedata = $this->ftpGetLastModifiedFile($ftp_conn, $remote_dir, $tmp_dir);
                    $dateToVerify = date('Y-m-d', mktime(0, 0, 0, date("m") , date("d")-1,date("Y")));
                    if($filedata['filetime'] == $dateToVerify){
                        $resFile = $filedata['file'];
                    }
                    else{
                        $this->cartalis_logs("File downloaded with date ".$filedata['filetime']." is a wrong file!");
                    }
                }
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


    public function ftpGetLastModifiedFile($ftp_conn, $remote_dir, $tmp_dir){
        // get list of files on given path
        $files = ftp_nlist($ftp_conn, '');

        $mostRecent = array(
            'time' => 0,
            'file' => null
        );

        foreach ($files as $fl) {
            // get the last modified time for the file
            $time = ftp_mdtm($ftp_conn, $fl);

            if ($time > $mostRecent['time']) {
                // this file is the most recent so far
                $mostRecent['time'] = $time;
                $mostRecent['file'] = $fl;
            }
        }

        if(preg_match("/[0-9a-zA-Z]/", $mostRecent['file'])){
            $this->cartalis_logs('File timestamp: '.date("Y-m-d", $mostRecent['time']).' - '.$mostRecent['time']);
            if($this->ftpGet($ftp_conn, $remote_dir, $tmp_dir, $mostRecent['file']) === true){
                return array('file'=>$mostRecent['file'], 'filetime'=>date("Y-m-d", $mostRecent['time']));
            }else{
                return false;
            }
        }

    }


}




