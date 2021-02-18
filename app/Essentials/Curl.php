<?php

namespace WpRloutHtml\Essentials;

use WpRloutHtml\App;
use WpRloutHtml\Posts;
use WpRloutHtml\Terms;
use WpRloutHtml\Helpers;
use WpRloutHtml\Modules\S3;
use WpRloutHtml\Modules\Ftp;
use WpRloutHtml\Modules\Git;

Class Curl {

	// Envia todos os objetos recebidos para o generate
    static function list_deploy($objs=null){

		if(!empty($objs)){
			
			foreach ($objs as $key => $obj) {
				
				Curl::generate($obj);
				sleep(0.5);
			}
		}
	
	}

	// Recebe o Objeto (post ou term) e descobre a Url para enviar a função deploy_upload();
    static function generate($object, $home=null){

		update_option('robots_rlout', '0');
		update_option('blog_public', '1');
		$text_post = get_post(url_to_postid($object));
		if(!empty($text_post)){
			$object = $text_post;
		}else{
			$taxonomy = explode(",", get_option('taxonomies_rlout'));
			foreach($taxonomy as $tax){
				$slug_term = explode("/",$object);
				foreach($slug_term as $key_b => $barra){
					$term_exist = get_term_by('slug',$slug_term[$key_b], $tax);
					if($term_exist){
						$object = $term_exist;
					}
				}
			}
		}
		if(!empty($object->ID)){
			
			$url = get_permalink( $object );
			$slug = $object->post_name;
			
			$thumbnails = get_intermediate_image_sizes();
			foreach ($thumbnails as $key => $t) {
				$url_thumb = get_the_post_thumbnail_url($object, $t);
				Curl::deploy_upload($url_thumb, '/uploads');
			}
		} else if(!empty($object->term_id)){
			
			$url = get_term_link( $object );
			$slug = $object->slug;
		}else{
			
			if($home){
				$url = site_url('/');
			}else{
				$url = $object;
			}
		}
		
		if(filter_var($url, FILTER_VALIDATE_URL)==false){
			return $url.' - URL COM ERRO DE SINTAX';
		}

		$response = Curl::get($url);

		$original_response = $response;
		
		if ($response) {
			
			$response = Helpers::replace_json($response);
			
			$dir_base =  get_option("path_rlout");
			if( is_dir($dir_base) === false ){
				mkdir($dir_base);
			}

			$uri = get_option("uri_rlout");

			$replace_raiz = str_replace($uri, '', $url);
			$replace_raiz = str_replace(site_url(), '', $replace_raiz);
			$dir_base = realpath($dir_base) . $replace_raiz;

			$verify_files_point = explode('.',$replace_raiz);

			$file_default = '/index.html';
			$json_default = '/index.json';

			if(count($verify_files_point)>1){
				$file_default = '';
				$json_default = '';

				if($verify_files_point[1]=='xml'){

					$htt = str_replace('https:', '', site_url());
					$htt = str_replace('http:', '', $htt);
					$original_response = str_replace(site_url(), get_option("replace_url_rlout"), $original_response);
					$original_response = str_replace('href="'.$htt, 'href="'.get_option("replace_url_rlout"), $original_response);
					$xml = simplexml_load_string($response);
					foreach($xml->sitemap as $sitemap){
						if(isset($sitemap->loc)){
							$url_map = (array) $sitemap->loc;
							if(!empty($url_map)){
								Curl::generate($url_map[0]);
							}
						}
					}
					$response=$original_response;
				}
			}
			
			$explode_raiz = explode("/", $dir_base);
			foreach ($explode_raiz as $keyp => $raiz) {
				$wp_raiz = $wp_raiz . $raiz . '/';
				if( realpath($wp_raiz) === false && $keyp+1<count($explode_raiz)){
					mkdir($wp_raiz);
				}
			}

			$file = fopen($dir_base . $file_default,"w");
			
			$file_json = fopen($dir_base . $json_default,"w");
			
			$replace_uploads = get_option('uploads_rlout');
			
			$uploads_url_rlout = get_option('uploads_url_rlout'); 
			
			if($replace_uploads){
				$upload_url = wp_upload_dir();
				
				$response = Helpers::replace_reponse($upload_url['baseurl'], $response, '/uploads');

				if($uploads_url_rlout){
					$response = Helpers::replace_reponse($uploads_url_rlout, $response, '/uploads');
				}
				
			}
			
			$response = Helpers::replace_reponse(get_option("uri_rlout"), $response);
			
			$jsons = array();

			$ignore_files_rlout = explode(',', get_option("ignore_files_rlout"));
			if(empty(in_array($url, $ignore_files_rlout))){

				fwrite($file, $response);
			
				Git::upload_file('Atualização de object');
				Ftp::upload_file($dir_base . $file_default);
				S3::upload_file($dir_base . $file_default, false);

				$amp = get_option('amp_rlout');
				if(!empty($amp)){
					Curl::deploy_upload($url.'/amp/');
				}
			}


			if(term_exists($object->term_id)){
				Terms::object_term($object);
			}else{
				Posts::object_post($object);
			}
			
			if($json_default!=''){
				
				$response_json = Helpers::replace_reponse(get_option("uri_rlout"), json_encode($object));

				$ignore_json_rlout = explode(',' ,get_option("ignore_json_rlout"));
				if(empty(in_array($url, $ignore_json_rlout))){

					fwrite($file_json,  $response_json);
				
					Git::upload_file('Atualização de object');
					Ftp::upload_file($dir_base . $json_default);
					S3::upload_file($dir_base . $json_default, true);
				}
			}
			
			update_option('robots_rlout', '1');
			return $url;
		}
	}

	// Recebe a URl da página ou media e gera o HTML, em seguida faz upload no S3 e FTP
    static function deploy_upload($url, $media=null){
		
        if(empty(in_array($url, App::$repeat_files_rlout)) && !empty($url)){
            
            $url = explode('?', $url);
            
            $url = $url[0];
            
            $url_point = explode(".", $url);
            
            $url_space = explode(" ", $url_point[count($url_point)-1]);
            
            $url_point[count($url_point)-1] = $url_space[0];
            
            $url = implode(".", $url_point);
            
            $response = Curl::get($url);
            
            if ($response) {
                
                $response = Helpers::replace_json($response);
                
                $dir_base =  get_option("path_rlout");
                if( is_dir($dir_base) === false ){
                    mkdir($dir_base);
                }
                
                if($media){
                    $dir_base =  get_option("path_rlout") . $media;
                    if( is_dir($dir_base) === false ){
                        mkdir($dir_base);
                    }
                }
                
                $url = urldecode($url);
                
                if($media){
                    $upload_url = wp_upload_dir();
                    $uploads_url_rlout = get_option('uploads_url_rlout'); 
                    $file_name = str_replace($upload_url['baseurl'], '', $url);
                    
                    if($uploads_url_rlout){
                        $file_name = str_replace($uploads_url_rlout, '', $file_name);
                    }
                }else{
                    $file_name = str_replace(get_option("uri_rlout"), '', $url);
                    $file_name = str_replace(site_url(), '', $file_name);
                }
                
                $folders = explode("/", $file_name);
                foreach ($folders as $key => $folder) {
                    if($key+1<count($folders)){
                        $dir_base = $dir_base . '/' . $folder;
                        if( is_dir($dir_base) === false ){
                            mkdir($dir_base);
                        }
                    }
                }
                
                $css = explode(".css", end($folders));
                if(!empty($css[1])){
                    $attrs = explode("url(", $response);
                    if(empty($attrs)){
                        $attrs = explode("url (", $response);
                    }						
                    
                    if(!empty($attrs)){
                        unset($attrs[0]);
                        foreach ($attrs as $key_att => $attr) {
                            $http = explode("http", $attr);
                            if(!$http[1]){
                                $attr = explode(")", $attr);
                                $attr = str_replace('"', '', $attr[0]);
                                $attr = str_replace("'", "", $attr);
                                
                                $attr = $dir_base  . '/' . $attr;
                                
                                $attr = str_replace(get_option("path_rlout"), '', $attr);
                                
                                $attr = get_option("uri_rlout") . $attr;
                                
                                $svg = explode("data:image", $attr);
                                
                                if(!$svg[1]){
                                    Curl::deploy_upload($attr);
                                    App::$repeat_files_rlout[] = $attr;
                                }
                            }
                        }
                    }
                }
                
                $folders_point = explode(".", end($folders));
                
                $folders_space = explode(" ", $folders_point[count($folders_point)-1]);
                
                $folders_point[count($folders_point)-1] = $folders_space[0];
                
                $folders = implode(".", $folders_point);
                
                $file = fopen( realpath($dir_base) . '/' . $folders,"w");
                
                fwrite($file, $response);
                
                Ftp::upload_file($dir_base . '/' . $folders);
                S3::upload_file($dir_base . '/' . $folders);
            }
        }
    }

	static function get($url){

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_HTTPHEADER => array(
				"cache-control: no-cache",
				"Authorization: Basic ".base64_encode(get_option('userpwd_rlout').":".get_option('passpwd_rlout'))
			),
		));
		
		$response = curl_exec($curl);
		$err = curl_error($curl);
		
		curl_close($curl);
		
		if ($err) {
			return null;
		} else {
			return $response;
		}
	}
}