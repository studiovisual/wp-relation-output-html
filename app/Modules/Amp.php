<?php

namespace WpRloutHtml\Modules;

use WpRloutHtml\Helpers;

Class Amp {

    static function url(){

        $qtd_pages = Helpers::getOption('amp_pagination_rlout');
        
        $urls = array();

        $urls[] = site_url();

		$taxonomies = explode(',', Helpers::getOption('taxonomies_rlout'));
        
        $terms = get_term(array(
         'taxonomy'=> $taxonomies,
         'hide_empty'=>true
        ));

        foreach($terms as $key_term => $term){
            $urls[] = get_term_link($term);
        }

        return $urls; 
    }

    static function remove_pagination($response){

        $qtd_pages = Helpers::getOption('amp_pagination_rlout');
        $static_url = Helpers::getOption('replace_url_rlout');

        $string_pagination = 'amp/page/'.($qtd_pages+1).'/';
    
        if(strpos($response, $string_pagination)){
           $response = str_replace('<div class="right"><a href="'.$static_url.'/'.$string_pagination.'">Ver mais posts</a></div>','',$response)
        }

        return $response;
    }
}