<?php
Class PostsRlout {

    public function __construct(){

        // verifica alterações de posts
		$post_types = explode(',', get_option('post_types_rlout'));
		foreach ($post_types as $key => $post_type) {
			add_action( 'publish_'.$post_type, array($this, 'post_auto_deploy'));
			add_action( 'draft_'.$post_type, array($this, 'post_delete_folder'));
			add_action( 'pre_'.$post_type.'_update', array($this, 'post_delete_folder'));
			add_action( 'trash_'.$post_type,  array($this, 'post_delete_folder'));
		}

        add_action('wp_ajax_posts', array($this, 'api_posts') );
		// Definindo action para acesso público
		add_action('wp_ajax_nopriv_posts', array($this, 'api_posts') );
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
    
    public function get_post_api($post_type, $not_in=array()){
        
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
            $posts_arr = array_merge($posts_arr, $this->get_post_api($post_type, $not_in));
        }
        
        return $posts_arr;
    }
}