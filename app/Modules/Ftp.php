<?php

namespace WpRloutHtml\Modules;

use WpRloutHtml\Helpers;

Class Ftp {
    
    static function upload_file($file_dir){
        
        //serverip
        $ftp_server = Helpers::getOption('ftp_host_rlout');
        
        if(!empty($ftp_server)){
            
            $conn_id = ftp_connect($ftp_server);
            
            // login with username and password
            $user = Helpers::getOption('ftp_user_rlout');
            
            $passwd = Helpers::getOption('ftp_passwd_rlout');
            
            $folder = Helpers::getOption('ftp_folder_rlout');
            
            $login_result = ftp_login($conn_id, $user, $passwd);
            
            $destination_file = $folder . str_replace(Helpers::getOption('path_rlout'), '', $file_dir);
            
            // upload the file
            $upload = ftp_put($conn_id, $destination_file, $file_dir, FTP_BINARY);
            
            // close the FTP stream
            ftp_close($conn_id);
        }
    }
    
    static function remove_file($file_dir){
        
        //serverip
        $ftp_server = Helpers::getOption('ftp_host_rlout');
        
        if(!empty($ftp_server)){
            
            $conn_id = ftp_connect($ftp_server);
            
            // login with username and password
            $user = Helpers::getOption('ftp_user_rlout');
            
            $passwd = Helpers::getOption('ftp_passwd_rlout');
            
            $folder = Helpers::getOption('ftp_folder_rlout');
            
            $login_result = ftp_login($conn_id, $user, $passwd);
            
            $destination_file = $folder . str_replace(Helpers::getOption('path_rlout'), '', $file_dir);
            
            // upload the file
            $delete = ftp_delete($conn_id, $destination_file);
            
            // close the FTP stream
            ftp_close($conn_id);
        }
    }
}