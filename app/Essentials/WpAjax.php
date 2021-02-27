<?php

namespace WpRloutHtml\Essentials;

use WpRloutHtml\Essentials\Curl;
use WpRloutHtml\Helpers;
use WpRloutHtml\Terms;
use WpRloutHtml\Posts;
use WpRloutHtml\Modules\S3;

Class WpAjax {
    
    public function __construct() {

        // Ajax de arquivos e páginas: /wp-admin/admin-ajax.php?action=static_output_reset
        add_action('wp_ajax_static_output_reset', array($this, 'delete_all') );
        // Ajax de arquivos e páginas: /wp-admin/admin-ajax.php?action=static_output_upload
        add_action('wp_ajax_static_output_upload', array($this, 'upload_all') );

        // Ajax de arquivos e páginas: /wp-admin/admin-ajax.php?action=static_output_deploy&file_url=
        add_action('wp_ajax_static_output_deploy', array($this, 'deploy') );
        
        // Ajax Gerador de JSON (postype, taxonomy): /wp-admin/admin-ajax.php?action=output_deploy_json
        add_action('wp_ajax_static_output_deploy_json', array($this, 'deploy_json') );
        
        // Ajax que lista todas os posts e terms escolhidos individualmente
        add_action('wp_ajax_static_output_files', array($this, 'files') );
        
        // Ajax que lista todas os posts e terms disponiveis para estatização
        add_action('wp_ajax_all_search_posts', array($this, 'all_search_posts') );
    }
    
    public function delete_all(){

        $base_html = Helpers::getOption('path_rlout');

        // $delete_static = Helpers::rrmdir($base_html.'/');
        
        // if($delete_static==true){
            die('Tudo pronto, estamos iniciando a estatização!');
        // }
    }

    public function upload_all(){

        $base_html = Helpers::getOption('path_rlout').'/';

        $verify_files = scandir($base_html);
        unset($verify_files[0]);
        unset($verify_files[1]);
        
        $per_page = 100;
        $offset = $_GET['offset'];

        foreach($verify_files as $obj_key => $object){
            $dir = $base_html.$object;
            if(is_dir($dir)){

                $dir = $base_html.$object.'/';

                if($obj_key>=$offset){
                    if($obj_key<=$per_page+$offset){
                        
                        $response = S3::upload_file($dir);
                    }else{
                        die('- Upload de '.$_GET['offset'].' até '.($per_page+$_GET['offset']).' arquivos/categorias e páginas realizado com sucesso!');
                    }
                }

            }else{

                $dir = $base_html.$object;

                $response = S3::upload_file($dir);
            }
        }

        die('- Upload da pasta em pequeno porte realizado!');

    }

    public function deploy(){

        $file = $_GET['file_url'];
        if(!empty($file) && filter_var($file, FILTER_VALIDATE_URL)){
            $response = Curl::generate($file,false,false,false);
            die($response);
        }
    }
    
    public function deploy_json(){
        
		header( "Content-type: application/json");

        $terms = Terms::api(null, false);
        $posts = Posts::api(null, false);
        $urls = array_merge($terms, $posts);
        die(json_encode($urls));
    }
    
    public function files(){
        
        $taxonomy = $_GET['taxonomy'];
        $post_type = $_GET['post_type'];
        $urls = array();
        
        // Subfiles
        $files = explode(',', Helpers::getOption('subfiles_rlout'));
        foreach ($files as $key => $file) {
            
            if(!empty($file)){
                $urls[] = $file;
            }
        }
        
        // Taxonomy
        if($taxonomy=='all'){
            $taxonomy = explode(",", Helpers::getOption('taxonomies_rlout'));
        }else if(!empty($taxonomy)){
            $taxonomy = array($taxonomy);
        }
        foreach($taxonomy as $tax){
            $terms = get_terms(array("taxonomy"=>$tax, 'hide_empty' => false));
            $ignore_json_rlout = explode(',', Helpers::getOption('ignore_json_rlout'));
            foreach ($terms as $key => $term) {
                $url = get_term_link($term);
                if(array_search($url, $ignore_json_rlout)!='NULL'){
                    $urls[] = $url;
                }
            }
        }
        // Post_type
        $args_posts = array();
        if($post_type=='all'){
            $post_type = explode(",", Helpers::getOption('post_types_rlout'));
        }else if(!empty($post_type)){
            $post_type = array($post_type);
        }

        if($post_type){
            foreach($post_type as $pt){
                $url = get_post_type_archive_link($pt);
                if($url){
                    $urls[] = $url;
                }
            }
            
            $urls = $this->recursive_post($post_type, $urls);
        }
        
        header("Content-type: application/json");
        die(json_encode($urls));
    }
    public function recursive_post($post_type, $urls=array(), $not_in=array()){
        $args_posts = array();
        $args_posts['post_type'] = $post_type;
        $args_posts['posts_per_page'] = 100;
        $args_posts['post_status'] = array('publish');
        $args_posts['order'] = 'DESC';
        $args_posts['orderby'] = 'date';
        $args_posts['post__not_in'] = $not_in;
        
        $posts = get_posts($args_posts);
        $ignore_json_rlout = explode(',', Helpers::getOption('ignore_json_rlout'));
        foreach($posts as $post){
            $url = get_permalink($post);
            if(array_search($url, $ignore_json_rlout)!='NULL'){
                $not_in[] = $post->ID;
                $urls[] = $url;
            }
        }
        if(count($posts)==100){
            sleep(0.5);
            $urls = array_unique(array_merge($urls, $this->recursive_post($post_type, $urls, $not_in)));
        }
        return array_values($urls);
    }
    
    public function title_filter( $where, &$wp_query )
    {
        global $wpdb;
        if ( $search_term = $wp_query->get( 'search_prod_title' ) ) {
            $where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql( like_escape( $search_term ) ) . '%\'';
        }
        return $where;
    }
    
    public function all_search_posts(){
        
        $array_search = array();
        
        $post_types = explode(",", Helpers::getOption('post_types_rlout'));
        $args_posts = array();
        $args_posts['post_type'] = $post_types;
        $args_posts['posts_per_page'] = 25;
        $args_posts['post_status'] = 'publish';
        $args_posts['order'] = 'DESC';
        $args_posts['orderby'] = 'date';
        $args_posts['suppress_filters'] = false;
        $args_posts['search_prod_title'] = $_GET['search'];

        add_filter( 'posts_where', array($this,'title_filter'), 10, 2 );
        $posts = get_posts($args_posts);
        remove_filter( 'posts_where', array($this, 'title_filter'), 10, 2 );
        
        $key_all = 0;
        foreach($posts as $key_post => $post){
            $array_search['results'][$key_all]['id'] = get_permalink($post);
            $array_search['results'][$key_all]['text'] = $post->post_title;
            $key_all++;
        }
        
        $taxonomies = explode(",", Helpers::getOption('taxonomies_rlout'));
        foreach($taxonomies as $tax){
            $terms = get_terms(array("name__like"=>$_GET['search'],"taxonomy"=>$tax, 'hide_empty' => false));
            foreach ($terms as $key_t => $term) {
                $array_search['results'][$key_all]['id'] = get_term_link($term);
                $array_search['results'][$key_all]['text'] = $term->name;
                $key_all++;
            }
        }
        
        $array_search['total'] = count($array_search['results']);
        
        header("Content-type: application/json");
        die(json_encode($array_search));
        
    }
}