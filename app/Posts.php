<?php

namespace WpRloutHtml;

use WpRloutHtml\App;
use WpRloutHtml\Essentials\Curl;
use WpRloutHtml\Modules\S3;
use WpRloutHtml\Helpers;
use WpRloutHtml\Terms;

Class Posts {

    public function __construct(){

        //apps
        $this->curl = new Curl;
        $this->terms = new Terms;
        $this->s3 = new S3;

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
					foreach ($post_types as $key => $pt) {
						$link = get_post_type_archive_link($pt);
						if($link){
							$this->curl->generate(get_post_type_archive_link($pt));
						}
					}
					sleep(0.5);
					
					if(empty($post_id)){
						
						$objects = get_posts(array('post_type'=>$post_types, 'posts_per_page'=>-1));
						
						$this->curl->list_deploy($objects);
						foreach ($taxonomies as $key => $tax) {
							$objects = get_terms( array('taxonomy' => $tax, 'hide_empty' => false) );
							$this->curl->list_deploy($objects);
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
							
							$this->curl->list_deploy($objects);
						}
					}

					sleep(0.5);
					
					// $this->git_upload_file('Atualização de object');

					$this->api(true);
					$this->terms->api(true);
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
				
				$this->ftp_remove_file($dir_base . 'index.html');
				$this->s3_remove_file($dir_base . 'index.html');
			}
			
		}

		$this->api_posts(true);
		$this->api_terms(true);
	}

    static function object_post($object, $show_terms=true){
		
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
			
			$object = Helpers::url_json_obj($object);
			
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
					$object->terms[] = Terms::object_term($term, false);
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
    
    public function api($generate){
        
        header( "Content-type: application/json");
        $post_types = explode(",", get_option('post_types_rlout'));
        $urls = array();
        $rpl = get_option('replace_url_rlout');
        if(empty($rpl)){
            $rpl = site_url().'/html';
        }
        foreach ($post_types as $key => $post_type) {
            
            $posts_arr = $this->get_post_json($post_type);
            
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
                
                // $this->ftp_upload_file($file_raiz);
                $this->s3->upload_file($file_raiz, false);
                
                $urls[] = str_replace($dir_base,$rpl,$file_raiz);
            }else{
                
                die($response);
            }
        }
        return $urls;
    }
    
    static function get_post_json($post_type, $not_in=array()){
        
        $rpl = get_option('replace_url_rlout');
        if(empty($rpl)){
            $rpl = site_url().'/html';
        }
        
        $posts = get_posts(array(
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
            $posts_arr = array_merge($posts_arr, Posts::get_post_json($post_type, $not_in));
        }
        
        return $posts_arr;
    }
}