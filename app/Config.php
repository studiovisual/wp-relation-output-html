<?php

namespace WpRlout;

Class Config {
    
    private $name_plugin;
    
    private $repeat_files_rlout;
    
    public function __construct(){
        
        // plugin name
        $this->name_plugin = 'Relation Output HTML';
        
        // Repeat Files
        $this->repeat_files_rlout = array();
        
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ));
        add_action('admin_bar_menu', array($this, 'add_toolbar_items'), 100);
        
        //removendo Infos Header
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
        
        add_action('admin_enqueue_scripts', array($this, 'my_enqueue') );
        add_action( 'admin_enqueue_scripts', array($this,'rudr_select2_enqueue') );

		add_filter( 'update_footer', array($this, 'config_admin_var') );
        
        if(!empty($_POST['salvar_rlout'])){
            
            unset($_POST['salvar_rlout']);
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
    }
    
    public function my_enqueue($hook) {

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
                
                wp_enqueue_script('my_custom_script_relation_output', site_url() . '/wp-content/plugin/wp-relation-output-html/resources/inc/js/myscript.js');
            }
        }
    }
    
    public function rudr_select2_enqueue(){
        
        if($_GET['page']=='relation-output-html-config'){
            wp_enqueue_style('select2', site_url() . '/wp-content/plugins/wp-relation-output-html/resources/inc/css/lib/select2.min.css' );
            wp_enqueue_script('select2', site_url() . '/wp-content/plugins/wp-relation-output-html/resources/inc/js/lib/select2.min.js', array('jquery') );
            
            wp_enqueue_script('my_custom_script_relation_output', site_url() . '/wp-content/plugins/wp-relation-output-html/resources/inc/js/select2.js');
        }
    }
    
    public function config_admin_var(){
        echo '<style>#loading_rlout h2{text-align:center;} #loading_rlout{display:none;position:fixed;left:0;top:0;width:100%;height:100%;z-index: 99999;background:rgba(255,255,255,0.9);} #loading_rlout .loader_rlout{position: relative;margin: 60px auto;display: block;top: 33%;border:16px solid #f3f3f3;border-radius:50%;border-top:16px solid #3498db;width:120px;height:120px;-webkit-animation:spin 2s linear infinite;animation:spin 2s linear infinite}@-webkit-keyframes spin{0%{-webkit-transform:rotate(0)}100%{-webkit-transform:rotate(360deg)}}@keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}</style>';
        echo '<div id="loading_rlout"><div class="loader_rlout"></div><h2>Por favor aguarde um instante, estamos processando o HTML.</h2></div>';
        echo '<script>jQuery(function(){ jQuery("#wp-admin-bar-relation-output-html-rlout li a").click(function(){jQuery("#loading_rlout").fadeIn();}); });</script>';
    }
    
    public function add_toolbar_items($admin_bar){
        
        $admin_bar->add_menu(array(
            'id'    => 'relation-output-html-rlout',
            'title' => 'Relation Output HTML',
            'parent' => null,
            'href'  => '',
            'meta' => ['title' => 'Limpeza e estatização dos principais arquivos e arquios ignorados']
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
        include WP_PLUGIN_DIR . "/wp-relation-output-html/resources/home.php";
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
        
        $fields['ftp_rlout'] = array('type'=>'label','label'=>'FTP SERVER');
        $fields['ftp_host_rlout'] = array('type'=>'text','label'=>'FTP Host');
        $fields['ftp_user_rlout'] = array('type'=>'text','label'=>'FTP User');
        $fields['ftp_passwd_rlout'] = array('type'=>'text','label'=>'FTP Password');
        $fields['ftp_folder_rlout'] = array('type'=>'text','label'=>'FTP Pasta 
        <br> <small>Sempre inserir <u>/</u> (barra) no final</small>');
        
        $fields['git_rlout'] = array('type'=>'label','label'=>'GITHUB PAGES');
        $fields['git_repository_rlout'] = array('type'=>'text', 'label'=>'URL Repository github');
        
        include WP_PLUGIN_DIR . "/wp-relation-output-html/resources/configuracoes.php";
    }  
}