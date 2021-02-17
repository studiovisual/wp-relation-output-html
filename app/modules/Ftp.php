<?php
Class FtpRlout {
    
    public function upload_file($file_dir){
        
        //serverip
        $ftp_server = get_option('ftp_host_rlout');
        
        if(!empty($ftp_server)){
            
            $conn_id = ftp_connect($ftp_server);
            
            // login with username and password
            $user = get_option('ftp_user_rlout');
            
            $passwd = get_option('ftp_passwd_rlout');
            
            $folder = get_option('ftp_folder_rlout');
            
            $login_result = ftp_login($conn_id, $user, $passwd);
            
            $destination_file = $folder . str_replace(get_option("path_rlout"), '', $file_dir);
            
            // upload the file
            $upload = ftp_put($conn_id, $destination_file, $file_dir, FTP_BINARY);
            
            // close the FTP stream
            ftp_close($conn_id);
        }
    }
    
    public function remove_file($file_dir){
        
        //serverip
        $ftp_server = get_option('ftp_host_rlout');
        
        if(!empty($ftp_server)){
            
            $conn_id = ftp_connect($ftp_server);
            
            // login with username and password
            $user = get_option('ftp_user_rlout');
            
            $passwd = get_option('ftp_passwd_rlout');
            
            $folder = get_option('ftp_folder_rlout');
            
            $login_result = ftp_login($conn_id, $user, $passwd);
            
            $destination_file = $folder . str_replace(get_option("path_rlout"), '', $file_dir);
            
            // upload the file
            $delete = ftp_delete($conn_id, $destination_file);
            
            // close the FTP stream
            ftp_close($conn_id);
        }
    }
}