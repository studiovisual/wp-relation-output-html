<?php

namespace WpRloutHtml;

use WpRloutHtml\App;
use WpRloutHtml\Essentials\Curl;
use WpRloutHtml\Modules\S3;
use WpRloutHtml\Modules\Ftp;
use WpRloutHtml\Helpers;
use WpRloutHtml\Terms;

Class Posts {
	
	public function __construct(){
		
		// verifica alterações de posts
		$post_types = explode(',', get_option('post_types_rlout'));
		foreach ($post_types as $key => $post_type) {
			add_action( 'publish_'.$post_type, array($this, 'create_folder'));
			add_action( 'draft_'.$post_type, array($this, 'delete_folder'));
			add_action( 'pre_'.$post_type.'_update', array($this, 'delete_folder'));
			add_action( 'trash_'.$post_type,  array($this, 'delete_folder'));
		}
	}
	
	public function create_folder($post_id=null){
		
		if($_POST['static_output_html']){
			
			add_action('updated_post_meta', function($meta_id, $post_id, $meta_key){
				
				if($meta_key=='_edit_lock'){
					
					$post_types = explode(',', get_option('post_types_rlout'));
					
					$post =  get_post($post_id);
					if(in_array($post->post_type, $post_types)){
						
						// Gerador da archive do post estatizado
						$link_archive = get_post_type_archive_link($post->post_type);
						if($link_archive){
							Curl::generate($link_archive);
						}
						
						// Verificando os terms do post de todas as taxonomies selecionadas
						$taxonomies = explode(",", get_option('taxonomies_rlout'));
						$terms = wp_get_post_terms($post->ID, $taxonomies);
						
						$objects = array();
						
						$objects[] = $post;
						
						// categorias relacionadas
						foreach ($terms as $key => $term) {
							$objects[] = $term;
						}
						
						Curl::list_deploy($objects);
					}
					
					Posts::api($post);
					Terms::api();
				}
			},10,3);
		}
	}
	
	public function delete_folder($post_id){
		
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
				$dir_base =  explode('__trashed', '', $url_delete);
				$dir_base = get_option("path_rlout") . str_replace(site_url(), '', $dir_base);
				
				unlink($dir_base . 'index.html');
				rmdir($dir_base);
				
				Ftp::remove_file($dir_base . 'index.html');
				S3::remove_file($dir_base . 'index.html');
			}
			
		}
		
		Posts::api($post);
		Terms::api();
	}
	
	static function api($post=null){
		
		header( "Content-type: application/json");
		
		$post_types = explode(",", get_option('post_types_rlout'));

		$gerenate_all = false;

		if(empty($post)){
			$gerenate_all = true;
		}

		$urls = array();

		foreach($post_types as $post_type){
			
			if($gerenate_all==true){
				$post->post_type = $post_type;
			}
			
			$replace_url = get_option('replace_url_rlout');
			if(empty($replace_url)){
				$replace_url = site_url().'/html';
			}
			
			$posts_arr = Posts::get_post_json($post, array());

			$response = json_encode($posts_arr , JSON_UNESCAPED_SLASHES);
			
			$replace_uploads = get_option('uploads_rlout');
			
			if($replace_uploads){

				$uploads_url_rlout = get_option('uploads_url_rlout'); 
				
				$upload_url = wp_upload_dir();						
				
				$response = str_replace($upload_url['baseurl'], $replace_url.'/uploads', $response);
				if($uploads_url_rlout){
					sleep(0.5);
					$response = str_replace($uploads_url_rlout, $replace_url.'/uploads', $response);
				}
				
			}
			
			$dir_base =  get_option("path_rlout");
			if( realpath($dir_base) === false ){
				mkdir($dir_base);
			}
			
			$file_raiz = $dir_base . '/'.$post->post_type.'.json';
			
			$file = fopen($file_raiz, "w");
			
			fwrite($file, $response);
			
			$urls[] = str_replace($dir_base,$replace_url,$file_raiz);
		}
		
		return $urls;
	}
	
	static function get_post_json($post=null, $not_in=array()){
		
		$replace_url = get_option('replace_url_rlout');
		if(empty($replace_url)){
			$replace_url = site_url().'/html';
		}
		
		if(!empty($post)){
			
			$json_exist = Curl::get($replace_url.'/'.$post->post_type.'.json');
			$post_arr = json_decode($json_exist);
			if(is_array($post_arr) && !empty($post->ID)){

				$create_post = true;
				
				$new_post = Posts::new_params($post, true);
				
				foreach($post_arr as $arr_key => $arr){
					if($arr->ID==$post->ID){
						$create_post = false;
						if($post->post_status=='publish'){
							$post_arr[$arr_key] = $new_post;
						}else{
							unset($post_arr[$arr_key]);
						}
					}
				}
				
				if($create_post==true){
					$post_arr = array_unshift($post_arr, $new_post);
				}
				
				return $post_arr;
			}
			
			$posts = get_posts(array(
				'post_type'=>$post->post_type,
				'posts_per_page' => 25,
				'order'=>'DESC',
				'orderby'=>'date',
				'post__not_in'=>$not_in
				)
			);

			$posts_arr = array();

			$ignore_json_rlout = explode(',' ,get_option("ignore_json_rlout"));
			foreach ($posts as $key => $post) {
				
				$url = get_permalink($post);
				if(empty(in_array($url, $ignore_json_rlout))){
					$not_in[] = $post->ID;
					
					$posts_arr[$key] = Posts::new_params($post, true);
				}
				
			}
			
			if(count($posts)==25){
				sleep(0.1);
				$object = null;
				$object->post_type = $post->post_type;
				$posts_arr = array_merge($posts_arr, Posts::get_post_json($object, $not_in));
			}
			
			return $posts_arr;
		}
	}
	
	static function new_params($post, $show_terms=false){
		
		$rpl = get_option('replace_url_rlout');
		if(empty($rpl)){
			$rpl = site_url().'/html';
		}
		
		$new_post = array();
		
		$new_post['ID'] = $post->ID;
		$new_post['post_title'] = $post->post_title;
		$new_post['post_date'] = $post->post_date;
		$new_post['post_excerpt'] = get_the_excerpt($post);
		$size_thumb = get_option('size_thumbnail_rlout');
		
		$thumbnail = get_the_post_thumbnail_url($post, $size_thumb);
		if(empty($thumbnail)){
			$thumbnail = get_option("uri_rlout").'/img/default.jpg';
			$thumbnail = str_replace(get_option("uri_rlout"), $rpl, $thumbnail);
		}
		$new_post['thumbnail'] = $thumbnail;
		$url = str_replace(site_url(),$rpl,get_permalink($post)).'index.json';
		$new_post['post_json'] = $url;
		
		$taxonomies = explode(",", get_option('taxonomies_rlout'));
		if(!empty($taxonomies) && $show_terms==true){
			
			foreach($taxonomies as $taxonomy){
				$term = wp_get_post_terms($post->ID, array($taxonomy));
				
				if(!empty($term) && empty($term->errors)){
					foreach($term as $tm_k => $tm){
						$url = str_replace(site_url(),$rpl,get_term_link($tm)).'index.json';
						$new_post[$taxonomy][$tm_k]['term_id'] = $tm->term_id;
						$new_post[$taxonomy][$tm_k]['term_name'] = $tm->name;
						$new_post[$taxonomy][$tm_k]['term_json'] = $url;
					}
				}
			}
		}
		
		$new_post = apply_filters('rel_output_custom_post', $post, $new_post);
		
		return $new_post;
	}
}