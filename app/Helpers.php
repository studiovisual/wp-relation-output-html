<?php

Class HelpersRlout {

	public function __construct(){

		$this->curl = new CurlRlout;

		add_action('init', array($this, 'blog_public') );
		add_filter('excerpt_more', array($this, 'custom_excerpt_more') );

		if(isset($_GET['importants_rlout'])){
			
			add_action('init', function(){
				$response_essenciais = $this->importantfiles_generate();
				
				if($response_essenciais){
					
					echo '<script>alert("Páginas importantes Atualizadas!");</script>';
					echo '<script>window.location = document.URL.replace("&importants_rlout=true","").replace("?importants_rlout=true","");</script>';
				}
			});
		}

		if(isset($_GET['essenciais_rlout'])){
			
			$response_essenciais = $this->helpers->subfiles_generate();
			
			if($response_essenciais){
				
				add_action('init', function(){
					$this->posts->api(true);
					$this->terms->api(true);
				});
				
				echo '<script>alert("Arquivos Essenciais Atualizados!");</script>';
				echo '<script>window.location = document.URL.replace("&essenciais_rlout=true","").replace("?essenciais_rlout=true","");</script>';
			}
		}
	}

	public function subfiles_generate(){
		
		$files = explode(',', get_option("subfiles_rlout"));
		
		foreach ($files as $key => $file) {
			
			if(!empty($file)){
				
				$this->deploy_upload($file);
				$this->repeat_files_rlout[] = $file;
			}
		}
		return $files;
	}

	public function custom_excerpt_more( $more ) {
		return '';
	}

    public function blog_public(){
		
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

    public function gen_html_cron_function() {
		
		$hora_marcada = strtotime(get_option('horario_cron_rlout'));
		
		if($hora_marcada==strtotime(date('H:i'))){
			
			$dir_base =  get_option("path_rlout");
			
			if( is_dir($dir_base) === true ){
				
				// rmdir($dir_base);
				$this->ftp_remove_file($dir_base);
				$this->s3_remove_file($dir_base);
			}
			
			$this->post_auto_deploy();
		}
	}

    public function importantfiles_generate(){
		
		// Generate FILE 1
		$files = explode(',', get_option("pages_important_rlout"));
		
		foreach ($files as $key => $file) {
			
			if(!empty($file)){
				
				$this->curl_generate($file);
				$this->repeat_files_rlout[] = $file;
			}
		}
		return $files;
	}

    public function url_json_obj($object){
		
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

    public function replace_reponse($url_replace, $response, $media=null, $debug=false){
			
        // pegando itens 
        $itens_theme = explode($url_replace, $response);
        
        unset($itens_theme[0]);
        foreach($itens_theme as $keyj => $item){
            
            $item = explode('"', $item);
            $item = explode("'", $item[0]);
            $item = explode(")", $item[0]);
            $item = $url_replace . $item[0];
            
            if(!empty($item)){
                $this->deploy_upload($item, $media);
                $this->repeat_files_rlout[] = $item;
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
    
    public function replace_json($response){
        
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