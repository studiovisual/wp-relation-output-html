<?php 
require "vendor/autoload.php";

use Aws\S3\S3Client;

Class WpAjaxRelOutHtml {
    
    public function __construct() {
        
        // deploy
        add_action('wp_ajax_static_output_deploy', array($this, 'deploy') );
        
        // deploy
        add_action('wp_ajax_static_output_deploy_json', array($this, 'deploy_json') );
        
        // get files
        add_action('wp_ajax_static_output_files', array($this, 'files') );
    }
    
    public function deploy(){
        $file = $_GET['file_url'];
        if(!empty($file)){
            $rlout = new RelOutputHtml;
            die($rlout->curl_generate($file));
        }
    }
    
    public function deploy_json(){
        $rlout = new RelOutputHtml;
        $terms = $rlout->api_terms(true);
        $posts = $rlout->api_posts(true);
        
        $urls = array_merge($terms, $posts);

        die(json_encode($urls));
    }
    
    public function files(){
        
        $rlout = new RelOutputHtml;
        $taxonomy = $_GET['taxonomy'];
        $post_type = $_GET['post_type'];
        $urls = array();
        
        // Taxonomy
        if($taxonomy=='all'){
            $taxonomy = explode(",", get_option('taxonomies_rlout'));
        }
        foreach($taxonomy as $tax){
            $terms = get_terms(array("taxonomy"=>$tax, 'hide_empty' => false));
            foreach ($terms as $key => $term) {
                $urls[] = get_term_link($term);
                sleep(0.01);
            }
        }
        // Post_type
        $args_posts = array();
        if($post_type=='all'){
            $post_type = explode(",", get_option('post_types_rlout'));
        }
        foreach($post_type as $pt){
            $url = get_post_type_archive_link($pt);
            if($url){
                $urls[] = $url;
                sleep(0.01);
            }
        }
        $args_posts = array();
        $args_posts['post_type'] = $post_type;
        $args_posts['posts_per_page'] = -1;
        $args_posts['order'] = 'DESC';
        $args_posts['orderby'] = 'post_modified';
        $posts = get_posts($args_posts);
        foreach($posts as $post){
            $urls[] = get_permalink($post);
            sleep(0.01);
        }
        
        header("Content-type: application/json");
        die(json_encode($urls));
    }
}