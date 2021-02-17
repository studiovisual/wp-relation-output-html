<?php

use AwsWp\S3\S3Client;

Class S3Rlout {

    public function upload_file($file_dir, $ignore_cloud=true){
        if($file_dir){
            
            $access_key = get_option('s3_key_rlout');
            $secret_key = get_option('s3_secret_rlout');
            $acl_key = get_option('s3_acl_rlout');
            
            // echo $secret_key;
            if(!empty($secret_key)){
                
                session_start();
                
                // creates a client object, informing AWS credentials
                $clientS3 = S3Client::factory(array(
                    'key'    => $access_key,
                    'secret' => $secret_key
                ));
                // putObject method sends data to the chosen bucket (in our case, teste-marcelo)
                
                $file_dir = str_replace("//", "/", $file_dir);
                $file_dir = str_replace("./", "/", $file_dir);
                
                $key_file_s3 = str_replace(get_option("path_rlout").'/','', $file_dir);
                $key_file_s3 = str_replace(get_option("path_rlout"),'', $key_file_s3);
                
                $directory_empty = explode('/', $key_file_s3);
                
                if(!empty($key_file_s3) && !empty(end($directory_empty)) ){
                    
                    $response = $clientS3->putObject(array(
                        'Bucket' => get_option('s3_bucket_rlout'),
                        'Key'    => $key_file_s3,
                        'SourceFile' => $file_dir,
                        'ACL'    => $acl_key
                    ));

                    if($response && $ignore_cloud==false){
                        $key_file_s3 = str_replace('index.html', '', $key_file_s3);
                        $this->invalidfileaws('/'.$key_file_s3);
                    }
                }
                
            }
        }
    }

    public function remove_file($file_dir){
			
        $access_key = get_option('s3_key_rlout');
        $secret_key = get_option('s3_secret_rlout');
        
        if(!empty($secret_key)){
            
            session_start();
            
            // creates a client object, informing AWS credentials
            $clientS3 = S3Client::factory(array(
                'key'    => $access_key,
                'secret' => $secret_key
            ));
            
            $key_file_s3 = str_replace(get_option("path_rlout").'/','', $file_dir);
            $key_file_s3 = str_replace(get_option("path_rlout"),'', $key_file_s3);
            
            $directory_empty = explode('/', $key_file_s3);

            if(!empty($key_file_s3) && !empty(end($directory_empty)) ){

                $response = $clientS3->deleteObject(array(
                    'Bucket' => get_option('s3_bucket_rlout'),
                    'Key' => $key_file_s3
                ));
                
                if($response){
                    $key_file_s3 = str_replace('index.html', '', $key_file_s3);
                    $this->invalidfileaws('/'.$key_file_s3);
                }
                
                return $response;
            }
            
        }
    }
}