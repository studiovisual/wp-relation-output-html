<?php
/***************************************************************************
Plugin Name:  Relation Output HTML
Plugin URI:   http://www.claudioweb.com.br/
Description:  Este plugin transforma todos os conteúdos salvos e relaxionados em páginas staticas HTML para servidores como FTP e S3
Version:      1.0
Author:       Claudio Web (claudioweb)
Author URI:   http://www.claudioweb.com.br/
Text Domain:  relation-output-html
**************************************************************************/

require "wp-ajax.php";
require "vendor/autoload.php";

use AwsWp\S3\S3Client;
use AwsWp\CloudFront\CloudFrontClient;

Class RelOutputHtml {
	
	private $name_plugin;
	
	private $repeat_files_rlout;
	
	public function __construct() {
		
		new WpAjaxRelOutHtml;
		
		$this->name_plugin = 'Relation Output HTML';
		
		$this->repeat_files_rlout = array();
		
		add_action('init', array($this, 'blog_public') );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ));
		

		// verifica alterações de POST

		$post_types = explode(',', get_option('post_types_rlout'));
		foreach ($post_types as $key => $post_type) {
			add_action( 'publish_'.$post_type, array($this, 'post_auto_deploy'));
			add_action( 'draft_'.$post_type, array($this, 'post_delete_folder'));
			add_action( 'pre_'.$post_type.'_update', array($this, 'post_delete_folder'));
			add_action( 'trash_'.$post_type,  array($this, 'post_delete_folder'));
		}
		
		// verifica alterações de TERMS
		add_action( 'create_term', array($this, 'term_create_folder'), 10, 3);
		add_action( 'edit_term', array($this, 'term_create_folder'), 10, 3);
		add_action( 'pre_delete_term', array($this, 'term_delete_folder'), 10, 2);
		
		add_action('wp_ajax_posts', array($this, 'api_posts') );
		// Definindo action para acesso público
		add_action('wp_ajax_nopriv_posts', array($this, 'api_posts') );
		
		add_action('wp_ajax_terms', array($this, 'api_terms') );
		// Definindo action para acesso público
		add_action('wp_ajax_nopriv_terms', array($this, 'api_terms') );
		
		//removendo Infos Header
		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('wp_print_styles', 'print_emoji_styles');
		remove_action('wp_head', 'rsd_link');
		remove_action('wp_head', 'wlwmanifest_link');
		remove_action('wp_head', 'wp_generator');

		add_action( 'activated_plugin', array($this, 'log_table') );

		
		add_action('admin_enqueue_scripts', array($this, 'my_enqueue') );
		
		// add_action("init", array($this, 'json_generate'));
		
		//Schedule an action if it's not already scheduled
		//wp_schedule_event( strtotime(get_option('horario_cron_rlout')), 'daily', 'gen_html_cron_hook' );
		
		///Hook into that action that'll fire every six hours
		//add_action( 'gen_html_cron_hook', array($this, 'gen_html_cron_function') );
		
		if(!empty($_POST['salvar'])){
			
			unset($_POST['salvar']);
			$key_fields = explode(',', $_POST['keys_fields']);
			foreach ($key_fields as $key_field) {
				$value_field = $_POST[$key_field];
				if(is_array($value_field)){
					update_option( $key_field, implode(',', $value_field) );
				}else{
					update_option( $key_field, $value_field );
				}
			}
			
			$redirect_param = sanitize_title($this->name_plugin) . '-config';
			
			header('Location:'.admin_url('admin.php?page='.$redirect_param));
			exit;
		}

		add_filter( 'update_footer', array($this, 'config_admin_var') );
		
		if(!empty($_POST['deploy_all_static'])){
			
			if ( ! function_exists( 'get_home_path' ) || ! function_exists( 'wp_get_current_user' ) ) {
				include_once(ABSPATH . '/wp-admin/includes/file.php');
				include_once(ABSPATH . '/wp-includes/pluggable.php');
			}
			
			$user = wp_get_current_user();
			
			if(in_array('administrator', $user->roles)){
				
				add_action("init", array($this, 'post_auto_deploy'), 9999);
			}
			
			$redirect_param = sanitize_title($this->name_plugin) . '-config';
			
			header('Location:'.admin_url('admin.php?&loading_deploy=true&page='.$redirect_param));
		}
		
		if(!empty($_POST['json_generate'])){
			
			if ( ! function_exists( 'get_home_path' ) || ! function_exists( 'wp_get_current_user' ) ) {
				include_once(ABSPATH . '/wp-admin/includes/file.php');
				include_once(ABSPATH . '/wp-includes/pluggable.php');
			}
			
			$user = wp_get_current_user();
			
			if(in_array('administrator', $user->roles)){
				
				add_action("init", array($this, 'json_generate'), 9999);
			}
			
			$redirect_param = sanitize_title($this->name_plugin) . '-config';
			
			header('Location:'.admin_url('admin.php?&loading_deploy=true&page='.$redirect_param));
		}
		
		add_filter('excerpt_more', array($this, 'custom_excerpt_more') );
		add_action('admin_bar_menu', array($this, 'add_toolbar_items'), 100);

		if(isset($_GET['cloudfront_rlout'])){

			$response_cloudfront = $this->invalidfileaws('/*');
			if($response_cloudfront){
				echo '<script>alert("Cloudfront Atualizados!");</script>';
				echo '<script>window.location = document.URL.replace("&cloudfront_rlout=true","").replace("?cloudfront_rlout=true","");</script>';
			}
		}

		if(isset($_GET['essenciais_rlout'])){

			$response_essenciais = $this->subfiles_generate();

			if($response_essenciais){
				
				add_action('init', function(){
					$this->api_posts(true);
					$this->api_terms(true);
				});

				echo '<script>alert("Arquivos Essenciais Atualizados!");</script>';
				echo '<script>window.location = document.URL.replace("&essenciais_rlout=true","").replace("?essenciais_rlout=true","");</script>';
			}
		}

		if(isset($_GET['importants_rlout'])){

			add_action('init', function(){
			$response_essenciais = $this->importantfiles_generate();

				if($response_essenciais){

					echo '<script>alert("Páginas importantes Atualizadas!");</script>';
					echo '<script>window.location = document.URL.replace("&importants_rlout=true","").replace("?importants_rlout=true","");</script>';
				}
			});
		}

		add_action( 'admin_enqueue_scripts', array($this,'rudr_select2_enqueue') );
	}

	public function rudr_select2_enqueue(){

		if($_GET['page']=='relation-output-html-config'){		
			wp_enqueue_style('select2', plugin_dir_url(__FILE__) . '/inc/css/select2.min.css' );
			wp_enqueue_script('select2', plugin_dir_url(__FILE__) . '/inc/js/select2.min.js', array('jquery') );
			
			wp_enqueue_script('my_custom_script_relation_output', plugin_dir_url(__FILE__) . '/select2.js');
		}
	}

	public function config_admin_var(){
		echo '<style>#loading_rlout h2{text-align:center;} #loading_rlout{display:none;position:fixed;left:0;top:0;width:100%;height:100%;z-index: 99999;background:rgba(255,255,255,0.9);} #loading_rlout .loader_rlout{position: relative;margin: 60px auto;display: block;top: 33%;border:16px solid #f3f3f3;border-radius:50%;border-top:16px solid #3498db;width:120px;height:120px;-webkit-animation:spin 2s linear infinite;animation:spin 2s linear infinite}@-webkit-keyframes spin{0%{-webkit-transform:rotate(0)}100%{-webkit-transform:rotate(360deg)}}@keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}</style>';
		echo '<div id="loading_rlout"><div class="loader_rlout"></div><h2>Por favor aguarde um instante, estamos processando o HTML.</h2></div>';
		echo '<script>jQuery(function(){ jQuery("#wp-admin-bar-relation-output-html-rlout li a").click(function(){jQuery("#loading_rlout").fadeIn();}); });</script>';
	}

	public function add_toolbar_items($admin_bar){
		$admin_bar->add_menu( array(
			'id'    => 'relation-output-html-rlout',
			'title' => 'Relation Output HTML',
			'parent' => null,
			'href'  => '',
			'meta' => [
				'title' => 'Limpeza e estatização dos principais arquivos e arquios ignorados',
			]
		));

		$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		
		$actual_link = str_replace('?cloudfront_rlout=true', '', $actual_link);
		$actual_link = str_replace('&cloudfront_rlout=true', '', $actual_link);

		$actual_link = str_replace('?essenciais_rlout=true', '', $actual_link);
		$actual_link = str_replace('&essenciais_rlout=true', '', $actual_link);

		$actual_link = str_replace('?importants_rlout=true', '', $actual_link);
		$actual_link = str_replace('&importants_rlout=true', '', $actual_link);

		$get_param = explode('?', $actual_link);

		if(count($get_param)>1){
			$cloudfront_link = $actual_link.'&cloudfront_rlout=true';
			$essenciais_link = $actual_link.'&essenciais_rlout=true';
			$importants_link = $actual_link.'&importants_rlout=true';
		}else{
			$cloudfront_link = $actual_link.'?cloudfront_rlout=true';
			$essenciais_link = $actual_link.'?essenciais_rlout=true';
			$importants_link = $actual_link.'?importants_rlout=true';
		}

		$admin_bar->add_menu( array(
			'id'    => 'cloudfront-html-rlout',
			'title' => 'Limpar Cloudfront',
			'parent' => 'relation-output-html-rlout',
			'href'  => $cloudfront_link
		));

		$admin_bar->add_menu( array(
			'id'    => 'importants-html-rlout',
			'title' => 'Atualizar páginas importantes',
			'parent' => 'relation-output-html-rlout',
			'href'  => $importants_link
		));

		$admin_bar->add_menu( array(
			'id'    => 'essenciais-html-rlout',
			'title' => 'Gerar aquivos ignorados',
			'parent' => 'relation-output-html-rlout',
			'href'  => $essenciais_link
		));
	}

	public function invalidfileaws($response){

		$DistributionId = get_option('s3_distributionid_rlout');

		if(!empty($DistributionId)){
			$CallerReference = (string) rand(100000,9999999).strtotime(date('Y-m-dH:i:s'));
			$raiz = str_replace(site_url(), '', $response);
			
			$access_key = get_option('s3_key_rlout');
			$secret_key = get_option('s3_secret_rlout');
			$acl_key = get_option('s3_acl_rlout');
			$region = get_option('s3_region_rlout');
			
			$cloudFrontClient = CloudFrontClient::factory(array(
				'region' => $region,
				'version' => '2016-01-28',
		
				'credentials' => [
					'key'    => $access_key,
					'secret' => $secret_key,
				]
			));

			// $result = $cloudFrontClient->listDistributions([]);
			// die(var_dump($result));
			// $result = $cloudFrontClient->listInvalidations(['DistributionId'=>$DistributionId]);

			$args = [
				'DistributionId' => $DistributionId,
				'CallerReference' => $CallerReference,
				'Paths' => [
					'Quantity' => 1,
					'Items' => [$raiz],
				],
			];
			
			$result = $cloudFrontClient->createInvalidation($args);

			return $result;
		}
	}

	public function my_enqueue($hook) {
		// Only add to the edit.php admin page.
		// See WP docs.
		if ('post.php' == $hook || 'edit-tags.php' == $hook || 'edit.php' == $hook || 'term.php' == $hook) {
			
			global $post;

			$post_types = explode(',', get_option('post_types_rlout'));
			$post_type = $_GET['post_type'];
			if(empty($post_type)){
				$post_type = $post->post_type;
			}

			$taxonomies = explode(',', get_option('taxonomies_rlout'));
			$taxonomy = $_GET['taxonomy'];

			if(in_array($taxonomy, $taxonomies) || in_array($post_type, $post_types)){
			
				wp_enqueue_script('my_custom_script_relation_output', plugin_dir_url(__FILE__) . '/myscript.js');
			}
		}
	}
	
	public function custom_excerpt_more( $more ) {
		return '';
	}

	public function log_table(){

		global $wpdb;

		$table_name = $wpdb->prefix . "relation_output"; 
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		text text NOT NULL,
		PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
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
	
	public function term_create_folder($term_id, $tt_id, $taxonomy, $deleted_term=null){
		
		if($_POST['static_output_html']){
			$term = get_term($term_id);
			
			$taxonomies = explode(',', get_option('taxonomies_rlout'));
			
			if(in_array($term->taxonomy, $taxonomies)){
				
				$slug_old = $term->slug;
				$slug_new = $_POST['slug'];
				
				$url = str_replace(site_url(), '', get_term_link($term));
				
				if($slug_old!=$slug_new){
					
					$term->slug = $slug_new;
				}
				
				$dir_base = get_option("path_rlout") . $url;
				
				$objects = array($term);
				
				$this->deploy($objects);
			}
			$this->api_posts(true);
			$this->api_terms(true);
		}
	}
	
	public function term_delete_folder($term_id, $taxonomy){
		$term = get_term($term_id);

		$taxonomies = explode(',', get_option('taxonomies_rlout'));
		if(in_array($term->taxonomy, $taxonomies)){
			
			$slug_old = $term->slug;
			$slug_new = $_POST['slug'];
			
			$url = str_replace(site_url(), '', get_term_link($term));
			
			if($slug_old!=$slug_new){
				
				$term->slug = $slug_new;
			}
			
			$dir_base = get_option("path_rlout") . $url;
			
			unlink($dir_base . '/index.html');
			rmdir($dir_base);


			$this->ftp_remove_file($dir_base . '/index.html');
			$this->s3_remove_file($dir_base . '/index.html');
		}
	}
	
	public function post_delete_folder($post_id){
		
		$post = get_post($post_id);
		
		$post_types = explode(',', get_option('post_types_rlout'));
		
		if(in_array($post->post_type, $post_types)){
			if($post->post_status=='publish' && $_POST['post_status']=='publish'){
				
				$slug_old = $post->post_name;
				
				$slug_new = $_POST['post_name'];
				
				if($slug_old==$slug_new){
					
					return false;
				}
			}
			
			$url_delete = get_sample_permalink($post);

			$url_del = str_replace('%pagename%',$url_delete[1],$url_delete[0]);
			$url_del = str_replace('%postname%',$url_delete[1],$url_del);
			$url_delete = $url_del;
			if($url_delete){
				$dir_base =  str_replace('__trashed', '', $url_delete);
				$dir_base = get_option("path_rlout") . str_replace(site_url(), '', $dir_base);

				unlink($dir_base . 'index.html');
				rmdir($dir_base);
				
				$this->ftp_remove_file($dir_base . 'index.html');
				$this->s3_remove_file($dir_base . 'index.html');
			}
			
		}

		$this->api_posts(true);
		$this->api_terms(true);
	}
	
	//create your function, that runs on cron
	public function gen_html_cron_function() {
		
		$hora_marcada = strtotime(get_option('horario_cron_rlout'));
		
		if($hora_marcada==strtotime(date('H:i'))){
			
			$dir_base =  get_option("path_rlout");
			
			if( realpath($dir_base) === true ){
				
				// rmdir($dir_base);
				$this->ftp_remove_file($dir_base);
				$this->s3_remove_file($dir_base);
			}
			
			$this->post_auto_deploy();
		}
	}
	
	public function post_auto_deploy($post_id=null){

		if($_POST['static_output_html']){
			add_action('updated_post_meta', function($meta_id, $post_id, $meta_key){

				if($meta_key=='_edit_lock'){
					
					$post_types = explode(',', get_option('post_types_rlout'));
					foreach ($post_types as $key => $pt) {
						$link = get_post_type_archive_link($pt);
						if($link){
							$this->curl_generate(get_post_type_archive_link($pt));
						}
					}
					sleep(0.5);
					
					if(empty($post_id)){
						
						$objects = get_posts(array('post_type'=>$post_types, 'posts_per_page'=>-1));
						
						$this->deploy($objects);
						foreach ($taxonomies as $key => $tax) {
							$objects = get_terms( array('taxonomy' => $tax, 'hide_empty' => false) );
							$this->deploy($objects);
						}
						
					}else{
						
						$post =  get_post($post_id);
						
						if(in_array($post->post_type, $post_types)){
							
							$terms = wp_get_post_terms($post->ID, $taxonomies);
							
							$objects = array();
							
							$objects[] = $post;
							
							// categorias relacionadas
							foreach ($terms as $key => $term) {
								$objects[] = $term;
							}
							
							$this->deploy($objects);
						}
					}

					sleep(0.5);
					
					$this->git_upload_file('Atualização de object');

					$this->api_posts(true);
					$this->api_terms(true);
				}
			},10,3);
		}
	}
	
	public function deploy($objs=null){
		
		// update_option('robots_rlout', '0');
		// update_option('blog_public', '1');
		// sleep(0.5);
		
		if(!empty($objs)){
			
			foreach ($objs as $key => $obj) {
				
				$this->curl_generate($obj);
				sleep(0.5);
			}
		}
		
		// sleep(0.5);
		// $this->subfiles_generate();
		// $this->importantfiles_generate();
		// sleep(0.5);
		// $this->curl_generate(null, true);
		// sleep(0.5);
		// update_option('robots_rlout', '1');
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

	public function subfiles_generate(){
		
		// Generate FILE 1
		$files = explode(',', get_option("subfiles_rlout"));
		
		foreach ($files as $key => $file) {
			
			if(!empty($file)){
				
				$this->deploy_upload($file);
				$this->repeat_files_rlout[] = $file;
			}
		}
		return $files;
	}
	
	public function json_generate(){
		
		$this->api_posts(true);
		$this->api_terms(true);
		sleep(0.5);
		// Generate JSON 1
		$jsons = explode(',', get_option("api_1_rlout"));
		$json = array();
		foreach ($jsons as $key => $json) {
			
			if(!empty($json)){
				
				$json_name = explode("action=", $json);
				$json_name = explode("&", $json_name[1]);
				$json_name = $json_name[0];
				
				$curl = curl_init();

				$loginpassw = get_option('login_proxy_rlout').':'.get_option('pass_proxy_rlout');
				$proxy_ip = get_option('ip_proxy_rlout');
				$proxy_port = get_option('port_proxy_rlout');

				curl_setopt_array($curl, array(
					CURLOPT_URL => $json,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 120,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "GET",
					CURLOPT_PROXYPORT => $proxy_port,
					CURLOPT_PROXYTYPE => 'HTTP',
					CURLOPT_PROXY =>  $proxy_ip,
					CURLOPT_PROXYUSERPWD => $loginpassw,
					CURLOPT_HTTPHEADER => array(
						"cache-control: no-cache",
						"Authorization: Basic ".base64_encode(get_option('userpwd_rlout').":".get_option('passpwd_rlout'))
					),
				));
				
				$response = curl_exec($curl);
				$err = curl_error($curl);
				
				curl_close($curl);
				
				if ($err) {
					echo "cURL Error #:" . $err;
				} else {
					
					$dir_base =  get_option("path_rlout");
					if( realpath($dir_base) === false ){
						mkdir($dir_base);
					}
					
					$file_raiz = $dir_base . '/' . $json_name . '.json';
					
					$file = fopen($file_raiz, "w");
					
					fwrite($file, $response);
					
					$this->ftp_upload_file($file_raiz);
					$this->s3_upload_file($file_raiz, false);
				}
			}
		}
		
	}

	public function save_log($url){

		global $wpdb;

		$table_name = $wpdb->prefix . 'relation_output';

		$wpdb->insert( 
			$table_name, 
			array( 
				'time' => current_time( 'mysql' ),
				'text' => $url, 
			) 
		);
	}
	
	public function curl_generate($object, $home=null){
		
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
				$this->deploy_upload($url_thumb, '/uploads');
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

		// $this->save_log($url);

		$curl = curl_init();

		$loginpassw = get_option('login_proxy_rlout').':'.get_option('pass_proxy_rlout');
		$proxy_ip = get_option('ip_proxy_rlout');
		$proxy_port = get_option('port_proxy_rlout');

		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_PROXYPORT => $proxy_port,
			CURLOPT_PROXYTYPE => 'HTTP',
			CURLOPT_PROXY =>  $proxy_ip,
			CURLOPT_PROXYUSERPWD => $loginpassw,
			CURLOPT_HTTPHEADER => array(
				"cache-control: no-cache",
				"Authorization: Basic ".base64_encode(get_option('userpwd_rlout').":".get_option('passpwd_rlout'))
			),
		));
		
		$response = curl_exec($curl);

		$original_response = $response;

		$err = curl_error($curl);
		
		curl_close($curl);
		
		if ($err) {
			return "cURL Error #:" . $err;
		} else {
			
			$response = $this->replace_json($response);
			
			$dir_base =  get_option("path_rlout");
			if( realpath($dir_base) === false ){
				mkdir($dir_base);
			}

			$uri = get_option("uri_rlout");

			$replace_raiz = str_replace($uri, '', $url);
			$replace_raiz = str_replace(site_url(), '', $replace_raiz);
			$dir_base = $dir_base . $replace_raiz;

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
								$this->curl_generate($url_map[0]);
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

			$file = fopen( $dir_base . $file_default,"w");
			
			$file_json = fopen( $dir_base . $json_default,"w");
			
			$replace_uploads = get_option('uploads_rlout');
			
			$uploads_url_rlout = get_option('uploads_url_rlout'); 
			
			if($replace_uploads){
				$upload_url = wp_upload_dir();
				
				$response = $this->replace_reponse($upload_url['baseurl'], $response, '/uploads');

				if($uploads_url_rlout){
					$response = $this->replace_reponse($uploads_url_rlout, $response, '/uploads');
				}
				
			}
			
			$response = $this->replace_reponse(get_option("uri_rlout"), $response);
			
			$jsons = array();

			$ignore_files_rlout = explode(',', get_option("ignore_files_rlout"));
			if(empty(in_array($url, $ignore_files_rlout))){
			
				fwrite($file, $response);
			
				$this->ftp_upload_file($dir_base . $file_default);
				$this->s3_upload_file($dir_base . $file_default, false);
			}

			if(term_exists($object->term_id)){
				$this->object_term($object);
			}else{
				$this->object_post($object);
			}
			
			if($json_default!=''){
				$response_json = $this->replace_reponse(get_option("uri_rlout"), json_encode($object));
				

				$ignore_json_rlout = explode(',' ,get_option("ignore_json_rlout"));
				if(empty(in_array($url, $ignore_json_rlout))){

					fwrite($file_json,  $response_json);
				
				
					$this->ftp_upload_file($dir_base . $json_default);
				
					$this->s3_upload_file($dir_base . $json_default, true);
				}
			}
			
			update_option('robots_rlout', '1');
			return $url;
		}
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
	
	public function object_post($object, $show_terms=true){
		
		$url = get_permalink($object);
		$ignore_json_rlout = explode(',' ,get_option("ignore_json_rlout"));
		if(empty(in_array($url, $ignore_json_rlout))){
			unset($object->post_author);
			unset($object->comment_status);
			unset($object->ping_status);
			unset($object->post_password);
			unset($object->to_ping);
			unset($object->pinged);
			unset($object->post_content_filtered);
			unset($object->post_parent);
			unset($object->guid);
			unset($object->post_mime_type);
			unset($object->comment_count);
			unset($object->filter);
			
			$object = $this->url_json_obj($object);
			
			$object->post_type = $object->post_type;
			
			$object->thumbnails = array();
			$sizes = get_intermediate_image_sizes();
			foreach($sizes as $size){
				$object->thumbnails[$size] = get_the_post_thumbnail_url($object, $size);
				if(empty($object->thumbnails[$size])){
					$object->thumbnails[$size] = get_option("uri_rlout").'/img/default.jpg';
				}
			}
			$object->thumbnails['full'] = get_the_post_thumbnail_url($object, 'full');
			
			if($show_terms){
				$terms = wp_get_post_terms($object->ID, explode(",", get_option('taxonomies_rlout')) );
				$object->terms = array();
				foreach ($terms as $keyterm => $term) {
					$object->terms[] = $this->object_term($term, false);
				}
			}
			
			$metas =  get_post_meta($object->ID);
			
			$metas_arr = array();
			foreach ($metas as $key_mm => $meta) {
				$thumb = wp_get_attachment_image_src($meta[0], 'full');
				if(!empty($thumb)){
					$sizes = get_intermediate_image_sizes();
					foreach ($sizes as $key_sz => $size) {
						$metas_arr[$key_mm][] = wp_get_attachment_image_src($meta[0], $size);
					}
				}else{
					$metas_arr[$key_mm] = $meta;
				}
			}
			
			$object->metas = $metas_arr;
			
			return $object;
		}
		
	}
	
	public function object_term($object, $show_posts=true){
		
		$url = get_term_link($object);
		$ignore_json_rlout = explode(',' ,get_option("ignore_json_rlout"));
		if(empty(in_array($url, $ignore_json_rlout))){

			unset($object->term_group);
			unset($object->term_taxonomy_id);
			unset($object->parent);
			unset($object->filter);
			
			$object = $this->url_json_obj($object);
			
			$args_posts = array();
			$args_posts['post_type'] = explode(",", get_option('post_types_rlout'));
			$args_posts['posts_per_page'] = -1;
			$args_posts['order'] = 'DESC';
			$args_posts['orderby'] = 'date';
			$args_posts['tax_query'][0]['taxonomy'] = $object->taxonomy;
			$args_posts['tax_query'][0]['terms'] = array($object->term_id);
			
			if($show_posts){
				$posts = get_posts($args_posts);
				$object->posts = array();
				foreach ($posts as $key_p => $post) {
					
					$post = $this->object_post($post);
					$size_thumb = get_option('size_thumbnail_rlout');
					$object->posts[$key_p]['ID'] = $post->ID;
					$object->posts[$key_p]['post_title'] = $post->post_title;
					$object->posts[$key_p]['post_date'] = $post->post_date;
					$object->posts[$key_p]['post_excerpt'] = get_the_excerpt($post);
					$object->posts[$key_p]['thumbnail'] = $post->thumbnails[$size_thumb];
					$object->posts[$key_p]['post_json'] = $post->post_json;
					$object->posts[$key_p] = apply_filters('rel_output_custom_post', $post, $object->posts[$key_p]);
					
				}
			}
			
			$metas = get_term_meta($object->term_id);
			$metas_arr = array();
			foreach ($metas as $key_mm => $meta) {
				$thumb = wp_get_attachment_image_src($meta[0], 'full');
				if(!empty($thumb)){
					$sizes = get_intermediate_image_sizes();
					foreach ($sizes as $key_sz => $size) {
						$metas_arr[$key_mm][] = wp_get_attachment_image_src($meta[0], $size);
					}
				}else{
					$metas_arr[$key_mm] = $meta;
				}
			}
			
			$object->metas = $metas_arr;
			
			return $object;
		}
	}

	public function get_post_api($post_type, $not_in=array()){

		$rpl = get_option('replace_url_rlout');
		if(empty($rpl)){
			$rpl = site_url().'/html';
		}

		$posts = get_posts(
			array(
				'post_type'=>$post_type,
				'posts_per_page' => 25,
				'order'=>'DESC',
				'orderby'=>'date',
				'post__not_in'=>$not_in
			)
		);

		$ignore_json_rlout = explode(',' ,get_option("ignore_json_rlout"));
		foreach ($posts as $key => $post) {
			
			$url = get_permalink($post);
			if(empty(in_array($url, $ignore_json_rlout))){
				$not_in[] = $post->ID;
				$posts_arr[$key]['ID'] = $post->ID;
				$posts_arr[$key]['post_title'] = $post->post_title;
				$posts_arr[$key]['post_date'] = $post->post_date;
				$posts_arr[$key]['post_excerpt'] = get_the_excerpt($post);
				$size_thumb = get_option('size_thumbnail_rlout');
				
				$thumbnail = get_the_post_thumbnail_url($post, $size_thumb);
				if(empty($thumbnail)){
					$thumbnail = get_option("uri_rlout").'/img/default.jpg';
					$thumbnail = str_replace(get_option("uri_rlout"), $rpl, $thumbnail);
				}
				$posts_arr[$key]['thumbnail'] = $thumbnail;
				$url = str_replace(site_url(),$rpl,get_permalink($post)).'index.json';
				$posts_arr[$key]['post_json'] = $url;
								
				$taxonomies = explode(",", get_option('taxonomies_rlout'));

				if(!empty($taxonomies)){
					
					foreach($taxonomies as $taxonomy){
						$term = wp_get_post_terms($post->ID, array($taxonomy));
						
						if(!empty($term) && empty($term->errors)){
							foreach($term as $tm_k => $tm){
								$url = str_replace(site_url(),$rpl,get_term_link($tm)).'index.json';
								$posts_arr[$key][$taxonomy][$tm_k]['term_id'] = $tm->term_id;
								$posts_arr[$key][$taxonomy][$tm_k]['term_name'] = $tm->name;
								$posts_arr[$key][$taxonomy][$tm_k]['term_json'] = $url;
							}
						}
					}
				}
				$posts_arr[$key] = apply_filters('rel_output_custom_post', $post, $posts_arr[$key]);
			}
			
		}
		if(count($posts)==25){
			sleep(0.1);
			$posts_arr = array_merge($posts_arr, $this->get_post_api($post_type, $not_in));
		}
		return $posts_arr;
		
	}
	
	public function api_posts($generate){
		
		header( "Content-type: application/json");
		$post_types = explode(",", get_option('post_types_rlout'));
		$urls = array();
		$rpl = get_option('replace_url_rlout');
		if(empty($rpl)){
			$rpl = site_url().'/html';
		}
		foreach ($post_types as $key => $post_type) {
			
				$posts_arr = $this->get_post_api($post_type);
				
				$response = json_encode($posts_arr , JSON_UNESCAPED_SLASHES);
				
				if($generate==true){
					
					sleep(0.5);
					$replace_uploads = get_option('uploads_rlout');
					
					$uploads_url_rlout = get_option('uploads_url_rlout'); 
					
					if($replace_uploads){
						
						$upload_url = wp_upload_dir();						
						
						$response = str_replace($upload_url['baseurl'], $rpl.'/uploads', $response);
						if($uploads_url_rlout){
							sleep(0.5);
							$response = str_replace($uploads_url_rlout, $rpl.'/uploads', $response);
						}
						
					}
					
					$dir_base =  get_option("path_rlout");
					if( realpath($dir_base) === false ){
						mkdir($dir_base);
					}
					
					$file_raiz = $dir_base . '/'.$post_type.'.json';
					
					$file = fopen($file_raiz, "w");
					
					fwrite($file, $response);
					
					$this->ftp_upload_file($file_raiz);
					$this->s3_upload_file($file_raiz, false);
					
					$urls[] = str_replace($dir_base,$rpl,$file_raiz);
				}else{
					
					die($response);
				}
			}
			return $urls;
		}
		
		public function api_terms($generate){
			
			header( "Content-type: application/json");
			
			$taxonomies = explode(",", get_option('taxonomies_rlout'));
			$urls = array();
			
			foreach($taxonomies as $tax){
				
				$terms = get_terms(array("taxonomy"=>$tax, 'hide_empty' => false));
				
				$rpl = get_option('replace_url_rlout');
				if(empty($rpl)){
					$rpl = site_url().'/html';
				}
				
				foreach ($terms as $key => $term) {
					$term = $this->object_term($term, false);
				}
				
				$response = json_encode($terms , JSON_UNESCAPED_SLASHES);
				
				if($generate==true){
					
					sleep(0.5);
					
					$replace_uploads = get_option('uploads_rlout');
					
					$uploads_url_rlout = get_option('uploads_url_rlout'); 
					
					if($replace_uploads){
						
						$upload_url = wp_upload_dir();						
						
						$response = str_replace($upload_url['baseurl'], $rpl.'/uploads', $response);
						
						sleep(0.5);
						
						if($uploads_url_rlout){
							$response = str_replace($uploads_url_rlout, $rpl.'/uploads', $response);
						}
					}
					
					$dir_base =  get_option("path_rlout");
					if( realpath($dir_base) === false ){
						mkdir($dir_base);
					}
					
					$file_raiz = $dir_base . '/'.$tax.'.json';
					
					$file = fopen($file_raiz, "w");
					
					fwrite($file, $response);
					
					$this->ftp_upload_file($file_raiz);
					$this->s3_upload_file($file_raiz, false);
					
					$urls[] = str_replace($dir_base,$rpl,$file_raiz);
					
				}else{
					
					die($response);
				}
			}
			return $urls;
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
		
		public function deploy_upload($url, $media=null){
			
			if(empty(in_array($url, $this->repeat_files_rlout)) && !empty($url)){
				
				$curl = curl_init();
				
				$url = explode('?', $url);
				
				$url = $url[0];
				
				$url_point = explode(".", $url);
				
				$url_space = explode(" ", $url_point[count($url_point)-1]);
				
				$url_point[count($url_point)-1] = $url_space[0];
				
				$url = implode(".", $url_point);

				$loginpassw = get_option('login_proxy_rlout').':'.get_option('pass_proxy_rlout');
				$proxy_ip = get_option('ip_proxy_rlout');
				$proxy_port = get_option('port_proxy_rlout');

				curl_setopt_array($curl, array(
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 120,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "GET",
					CURLOPT_PROXYPORT => $proxy_port,
					CURLOPT_PROXYTYPE => 'HTTP',
					CURLOPT_PROXY =>  $proxy_ip,
					CURLOPT_PROXYUSERPWD => $loginpassw,
					CURLOPT_HTTPHEADER => array(
						"cache-control: no-cache",
						"Authorization: Basic ".base64_encode(get_option('userpwd_rlout').":".get_option('passpwd_rlout'))
					),
				));
				
				$response = curl_exec($curl);
				$err = curl_error($curl);
				
				curl_close($curl);
				
				if ($err) {
					echo "cURL Error #:" . $err;
				} else {
					
					$response = $this->replace_json($response);
					
					$dir_base =  get_option("path_rlout");
					if( realpath($dir_base) === false ){
						mkdir($dir_base);
					}
					
					if($media){
						$dir_base =  get_option("path_rlout") . $media;
						if( realpath($dir_base) === false ){
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
							if( realpath($dir_base) === false ){
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
										$this->deploy_upload($attr);
										$this->repeat_files_rlout[] = $attr;
									}
								}
							}
						}
					}
					
					$folders_point = explode(".", end($folders));
					
					$folders_space = explode(" ", $folders_point[count($folders_point)-1]);
					
					$folders_point[count($folders_point)-1] = $folders_space[0];
					
					$folders = implode(".", $folders_point);
					
					$file = fopen( $dir_base . '/' . $folders,"w");
					
					fwrite($file, $response);
					
					$this->ftp_upload_file($dir_base . '/' . $folders);
					$this->s3_upload_file($dir_base . '/' . $folders);
				}
			}
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
		
		public function s3_upload_file($file_dir, $ignore_cloud=true){
			
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
		
		public function s3_remove_file($file_dir){
			
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
					$key_file_s3 = str_replace("//", "/", $key_file_s3);
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
		
		public function ftp_upload_file($file_dir){
			
			$ftp_server = get_option('ftp_host_rlout');//serverip
			
			// require_once('ftp_commands/create_file.php');
		}
		
		public function ftp_remove_file($file_dir){
			
			$ftp_server = get_option('ftp_host_rlout');//serverip
			
			// require_once('ftp_commands/delete_file.php');
		}
		
		public function git_upload_file($commit){
			
			$repository = get_option('git_repository_rlout');
			
			// require_once('github_proc.php');
		}
		
		public function add_admin_menu(){


			if ( ! function_exists( 'get_home_path' ) || ! function_exists( 'wp_get_current_user' ) ) {
				include_once(ABSPATH . '/wp-admin/includes/file.php');
				include_once(ABSPATH . '/wp-includes/pluggable.php');
			}
			
			$user = wp_get_current_user();
			
			if(in_array('administrator', $user->roles)){
				
				add_menu_page(
					$this->name_plugin,
					$this->name_plugin,
					'manage_options', 
					sanitize_title($this->name_plugin), 
					array($this,'reloutputhtml_home'), 
					'', //URL ICON
					93.1110 // Ordem menu
				);
				
				add_submenu_page( 
					sanitize_title($this->name_plugin), 
					'Configurações', 
					'Configurações', 
					'manage_options', 
					sanitize_title($this->name_plugin).'-config', 
					array($this,'reloutputhtml_settings')
				);
			}
		}
		
		public function reloutputhtml_home(){
			
			$fields = array('primeira_config'=>'Primeira Configuração');
			
			include "templates/home.php";
		}
		
		public function reloutputhtml_settings(){
			
			if ( ! function_exists( 'get_home_path' ) || ! function_exists( 'wp_get_current_user' ) ) {
				include_once(ABSPATH . '/wp-admin/includes/file.php');
				include_once(ABSPATH . '/wp-includes/pluggable.php');
			}
			
			$fields = array();

			$fields['replace_url_rlout'] = array('type'=>'text','label'=>'Substituir a URL <br>
			<small>Default: ('.site_url().'/html)</small>');
			
			$fields['post_types_rlout'] = array('type'=>'select2', 'label'=>'Post Type para deploy', 'multiple'=>'multiple');
			$fields['post_types_rlout']['options'] = get_post_types();
			
			
			$fields['taxonomies_rlout'] = array('type'=>'select2', 'label'=>'Taxonomy para deploy', 'multiple'=>'multiple');
			$fields['taxonomies_rlout']['options'] = get_taxonomies();
			
			$fields['uploads_rlout'] = array('type'=>'checkbox', 'label'=>"<small> Todas as imagens em: <br>
			(<b>".wp_upload_dir()['baseurl']."</b>) serão TRANSFERIDAS</small>");
			
			$fields['uploads_url_rlout'] = array('type'=>'text', 'label'=>"<small> URL de imagens para transferi-las");

			$fields['size_thumbnail_rlout'] = array('type'=>'select', 'label'=>'Tamanho padrão (thumbnail)');
			$sizes = get_intermediate_image_sizes();
			foreach($sizes as $size){
				$fields['size_thumbnail_rlout']['options'][] = $size;
			}

			$fields['path_rlout'] = array('type'=>'text','disabled'=>'disabled','label'=>"Path:<br><small> ".get_home_path() . 'html</small>');
			
			$fields['uri_rlout'] = array('type'=>'text', 'label'=>"Directory_uri():<br><small>Caminho do template</small>");
			
			$fields['robots_rlout'] = array('type'=>'checkbox', 'label'=>'Evitar mecanismos de pesquisa em: '.site_url());
			
			//$fields['horario_cron_rlout'] = array('type'=>'time', 'label'=>'Horário para sincronização diária');
			
			// $fields['api_1_rlout'] = array('type'=>'repeater','label'=>'URL API AJAX STATIC<br>
			// 	<small>Default: ('.site_url().'/wp-admin/admin-ajax.php?action=<u>EXEMPLO</u>)</small>');
						
			$fields['ignore_json_rlout'] = array( 'multiple'=>'multiple','type'=>'select2','action_ajax'=>'all_search_posts','label'=>'Ignorar páginas no JSON<br>
			<small>insira a URL de todos os arquivos que devem ser ignorados no JSON. </small>');

			$fields['ignore_files_rlout'] = array( 'multiple'=>'multiple','type'=>'select2','action_ajax'=>'all_search_posts','label'=>'Ignorar páginas<br>
			<small>insira a URL de todos os arquivos que devem ser ignorados. </small>');
			
			$fields['pages_important_rlout'] = array( 'multiple'=>'multiple','action_ajax'=>'all_search_posts','type'=>'select2','label'=>'Páginas importantes (URL)<br>
			<small>Páginas importantes para serem atualizadas ao atualizar os posts</small>');
			
			$fields['subfiles_rlout'] = array('type'=>'repeater','label'=>'Arquivos ignorados<br>
			<small>insira a URL de todos os arquivos que foram ignorados pelo sistema.</small>');
			
			$fields['s3_rlout'] = array('type'=>'label','label'=>'Storage AWS S3');
			
			$fields['s3_distributionid_rlout'] = array('type'=>'text','label'=>'Distribution ID (Cloudfront)');
			
			$fields['s3_key_rlout'] = array('type'=>'text', 'label'=>'S3 Key');
			
			$fields['s3_secret_rlout'] = array('type'=>'text', 'label'=>'S3 Secret');
			
			$fields['s3_region_rlout'] = array('type'=>'select', 'label'=>'S3 Region');
			$fields['s3_region_rlout']['options'][] = 'us-east-1';
			$fields['s3_region_rlout']['options'][] = 'us-east-2';
			$fields['s3_region_rlout']['options'][] = 'us-west-1';
			$fields['s3_region_rlout']['options'][] = 'us-west-2';
			$fields['s3_region_rlout']['options'][] = 'ca-central-1';
			$fields['s3_region_rlout']['options'][] = 'ap-south-1';
			$fields['s3_region_rlout']['options'][] = 'ap-northeast-2';
			$fields['s3_region_rlout']['options'][] = 'ap-southeast-1';
			$fields['s3_region_rlout']['options'][] = 'ap-southeast-2';
			$fields['s3_region_rlout']['options'][] = 'ap-northeast-1';
			$fields['s3_region_rlout']['options'][] = 'eu-central-1';
			$fields['s3_region_rlout']['options'][] = 'eu-west-1';
			$fields['s3_region_rlout']['options'][] = 'eu-west-2';
			$fields['s3_region_rlout']['options'][] = 'sa-east-1';
			
			
			$fields['s3_acl_rlout'] = array('type'=>'select', 'label'=>'S3 ACL');
			$fields['s3_acl_rlout']['options'][] = 'private';
			$fields['s3_acl_rlout']['options'][] = 'public-read';
			$fields['s3_acl_rlout']['options'][] = 'public-read-write';
			$fields['s3_acl_rlout']['options'][] = 'authenticated-read';
			$fields['s3_acl_rlout']['options'][] = 'aws-exec-read';
			$fields['s3_acl_rlout']['options'][] = 'bucket-owner-read';
			$fields['s3_acl_rlout']['options'][] = 'bucket-owner-full-control';
			
			$fields['s3_bucket_rlout'] = array('type'=>'text', 'label'=>'S3 Bucket');

			$fields['pwd_rlout'] = array('type'=>'label','label'=>'PWD ACESSO');
			$fields['userpwd_rlout'] = array('type'=>'text','label'=>'USUÁRIO PWD');
			$fields['passpwd_rlout'] = array('type'=>'text','label'=>'SENHA PWD');

			$fields['proxy_rlout'] = array('type'=>'label','label'=>'PROXY ACESSO');
			$fields['ip_proxy_rlout'] = array('type'=>'text','label'=>'IP PROXY');
			$fields['port_proxy_rlout'] = array('type'=>'text','label'=>'PORT PROXY');
			$fields['login_proxy_rlout'] = array('type'=>'text','label'=>'USUÁRIO PROXY');
			$fields['pass_proxy_rlout'] = array('type'=>'text','label'=>'SENHA PROXY');
			
			$fields['ftp_rlout'] = array('type'=>'label','label'=>'FTP SERVER');
			$fields['ftp_host_rlout'] = array('type'=>'text','label'=>'FTP Host');
			$fields['ftp_user_rlout'] = array('type'=>'text','label'=>'FTP User');
			$fields['ftp_passwd_rlout'] = array('type'=>'text','label'=>'FTP Password');
			$fields['ftp_folder_rlout'] = array('type'=>'text','label'=>'FTP Pasta 
			<br> <small>Sempre inserir <u>/</u> (barra) no final</small>');
			
			$fields['git_rlout'] = array('type'=>'label','label'=>'GITHUB PAGES');
			$fields['git_repository_rlout'] = array('type'=>'text', 'label'=>'URL Repository github');
			
			
			include "templates/configuracoes.php";
		}
		
	}
	
	$init_plugin = new RelOutputHtml;
	
?>