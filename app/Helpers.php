<?php

namespace WpRloutHtml;

use WpRloutHtml\Essentials\Curl;
use WpRloutHtml\Modules\S3;
use WpRloutHtml\Modules\Ftp;

Class Helpers {

	static function subfiles_generate(){

		$curl = new Curl;
		
		$files = explode(',', get_option("subfiles_rlout"));
		
		foreach ($files as $key => $file) {
			
			if(!empty($file)){
				
				Curl::deploy_upload($file);
				App::$repeat_files_rlout[] = $file;
			}
		}
		return $files;
	}

    static function blog_public(){
		
		$robots = get_option('robots_rlout');
		
		if($robots){
			
			update_option('blog_public', '0');
		}else{
			
			update_option('blog_public', '1');
		}
		
		include_once(ABSPATH . '/wp-admin/includes/file.php');
		include_once(ABSPATH . '/wp-includes/pluggable.php');
		
		$raiz = get_home_path().'html';
		update_option('path_rlout', $raiz);
		
		if(defined('PATH_RLOUT')==true){
			update_option('path_rlout', PATH_RLOUT);
		}
		
		$uri = get_option("uri_rlout");
		if(empty($uri)){
			update_option('uri_rlout', get_template_directory_uri());
		}
	}

    static function gen_html_cron_function() {
		
		$hora_marcada = strtotime(get_option('horario_cron_rlout'));
		
		if($hora_marcada==strtotime(date('H:i'))){
			
			$dir_base =  get_option("path_rlout");
			
			if( is_dir($dir_base) === true ){
				
				rmdir($dir_base);
				Ftp::remove_file($dir_base);
				S3::remove_file($dir_base);
			}
			
			$this->post_auto_deploy();
		}
	}

    static function importantfiles_generate(){
		
		// Generate FILE 1
		$files = explode(',', get_option("pages_important_rlout"));
		
		foreach ($files as $key => $file) {
			
			if(!empty($file)){
				
				Curl::generate($file);
				App::$repeat_files_rlout[] = $file;
			}
		}
		return $files;
	}

    static function url_json_obj($object){
		
		$dir_base =  get_option("path_rlout");
		$rpl = get_option('replace_url_rlout');
		if(empty($rpl)){
			$rpl = site_url().'/html';
		}
		
		if(term_exists($object->term_id)){
			
			$object->term_json = str_replace(site_url(), $rpl, get_term_link($object)) . 'index.json';
		}else{
			
			$object->post_json = str_replace(site_url(), $rpl, get_permalink($object)) . 'index.json';
		}
		
		return $object;
	}

    static function replace_reponse($url_replace, $response, $media=null, $debug=false){
		
		$curl = new Curl;

        // pegando itens 
        $itens_theme = explode($url_replace, $response);
        
        unset($itens_theme[0]);
        foreach($itens_theme as $keyj => $item){
            
            $item = explode('"', $item);
            $item = explode("'", $item[0]);
            $item = explode(")", $item[0]);
            $item = $url_replace . $item[0];
            
            if(!empty($item)){

                App::$repeat_files_rlout[] = $item;
                Curl::deploy_upload($item, $media);
            }
        }
        
        
        //replace url
        $rpl = get_option('replace_url_rlout');
        if(empty($rpl)){
            $rpl = site_url().'/html';
        }
        $rpl_original = $rpl;
        $rpl = $rpl . $media;
        if($rpl!=site_url() && $rpl!=$url_replace){
            
            $response = str_replace($url_replace, $rpl, $response);
            if(!$media){
                
                $response = str_replace(site_url(), $rpl, $response);
            }
        }
        $rpl_dir = str_replace(site_url(), '', $rpl_original);
        if(!empty($rpl_dir)){
            $response = str_replace($rpl_dir.$rpl_dir,$rpl_dir, $response);
        }
        return $response;
    }
    
    static function replace_json($response){

        $jsons = explode(",", get_option("api_1_rlout"));
        
        foreach ($jsons as $key => $json) {
            
            $json_name = explode("action=", $json);
            $json_name = explode("&", $json_name[1]);
            $json_name = get_option("path_rlout") . $json_name[0] . '.json';
            
            $response = str_replace($json, $json_name, $response);
        }
        
        return $response;
    }
}