<?php
/*
  Plugin Name: RALF Document Manager
  Description: RALF Document Manager
  Author: The Childress Agency
  Author URI: https://childressagency.com
  Version: 2.0
  Text Domain: ralfdocs
*/

if(!defined('ABSPATH')){ exit; }

define('RALFDOCS_PLUGIN_DIR', dirname(__FILE__));
define('RALFDOCS_PLUGIN_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, 'ralfdocs_activation_tasks');

function ralfdocs_activation_tasks(){
  require_once RALFDOCS_PLUGIN_DIR . '/includes/class-ralfdocs-activator.php';
  RALFDOCS_Activator::create_emailed_reports_table();
  RALFDOCS_Activator::create_saved_reports_table();
  RALFDOCS_Activator::create_view_report_page();
  RALFDOCS_Activator::create_quick_select_results_page();
}

if(!class_exists('Ralf_Docs')){
class Ralf_Docs{

  public function __construct(){
    $this->load_dependencies();
    $this->admin_init();
    $this->public_init();
    $this->shared_init();
    $this->define_template_hooks();
  }

  public function load_dependencies(){
    require_once RALFDOCS_PLUGIN_DIR . '/vendors/advanced-custom-fields-pro/acf.php';
      add_filter('acf/settings/path', array($this, 'acf_settings_path'));
      add_filter('acf/settings/dir', array($this, 'acf_settings_dir'));

    require_once RALFDOCS_PLUGIN_DIR . '/admin/class-ralfdocs-dashboard.php';
    require_once RALFDOCS_PLUGIN_DIR . '/includes/class-ralfdocs-post-types.php';
    require_once RALFDOCS_PLUGIN_DIR . '/includes/widgets/class-ralfdocs-sectors-widget.php';
    require_once RALFDOCS_PLUGIN_DIR . '/includes/widgets/class-ralfdocs-search-history-widget.php';
    require_once RALFDOCS_PLUGIN_DIR . '/includes/widgets/class-ralfdocs-view-report-widget.php';
    require_once RALFDOCS_PLUGIN_DIR . '/admin/class-ralfdocs-background-admin-tasks.php';
    require_once RALFDOCS_PLUGIN_DIR . '/includes/class-ralfdocs-email-report.php';
    require_once RALFDOCS_PLUGIN_DIR . '/includes/ralfdocs-template-functions.php';
  }

  public function admin_init(){
    add_action('init', array($this, 'rewrite_report_url'));
    add_action('plugins_loaded', array($this, 'load_textdomain'));

    add_action('acf/init', array($this, 'admin_settings_acf_options_page'));

    $background_admin_tasks = new RALFDOCS_Background_Admin_Tasks();
    $ralfdocs_post_types = new RALFDOCS_Post_Types();

    if(is_admin()){
      $dashboard_functions = new RALFDOCS_Dashboard();
      add_action('plugins_loaded', array($dashboard_functions, 'init'));
    }
  }

  public function public_init(){
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

    // search functions
    add_filter('pre_get_posts', array($this, 'ralfdocs_search_filter'));
    add_filter('searchwp_query_join', array($this, 'searchwp_join_term_relationships'), 10, 3);
    add_filter('searchwp_weight_mods', array($this, 'searchwp_weight_priority_keywords'));

    //ajax pagination
    //doesn't work well with SearchWP but left code in for future possibilities
    //add_action('wp_ajax_nopriv_ralfdocs_ajax_pagination', array($this, 'do_ralfdocs_ajax_pagination'));
    //add_action('wp_ajax_ralfdocs_ajax_pagination', array($this, 'do_ralfdocs_ajax_pagination'));

    $email_report = new RALFDOCS_Email_Report();
  }

  public function shared_init(){
    add_action('widgets_init', array($this, 'init_widgets'));
    add_shortcode('ralfdocs_quick_select_form', array($this, 'quick_select_form'));
  }

  public function define_template_hooks(){
    $template_functions = new RALFDOCS_Template_Functions();

    add_filter('template_include', array($template_functions, 'load_template'), 99);

    add_action('ralfdocs_view_report_loop', array($template_functions, 'view_report_loop'));
    add_action('ralfdocs_article_meta', array($template_functions, 'article_meta'));
    add_action('ralfdocs_back_button', array($template_functions, 'back_button'));
    add_action('ralfdocs_related_impacts', array($template_functions, 'related_impacts'));
    add_action('ralfdocs_related_resources', array($template_functions, 'related_resources'));
    add_action('ralfdocs_related_activities', array($template_functions, 'related_activities'), 10, 2);
  }

  public function load_textdomain(){
    load_plugin_textdomain('ralfdocs', false, basename(RALFDOCS_PLUGIN_DIR) . '/languages');
  }

  public function enqueue_scripts(){
    wp_register_script(
      'js-cookie', 
      RALFDOCS_PLUGIN_URL . 'js/js-cookie.js',
      array('jquery'),
      false,
      true
    );

    wp_register_script(
      'ralfdocs-scripts',
      RALFDOCS_PLUGIN_URL . 'js/ralfdocs-scripts.js',
      array('jquery', 'js-cookie'),
      false,
      true
    );

    wp_enqueue_script('js-cookie');
    wp_enqueue_script('ralfdocs-scripts');

    global $wp_query;
    wp_localize_script('ralfdocs-scripts', 'ralfdocs_settings', array(
      'ralfdocs_ajaxurl' => admin_url('admin-ajax.php'),
      'send_label' => esc_html__('Email Report', 'ralfdocs'),
      'error' => esc_html__('Sorry, something went wrong. Please try again.', 'ralfdocs'),
      'save_to_report_label' => esc_html__('Save To Report', 'ralfdocs'),
      'remove_from_report_label' => esc_html__('Remove From Report', 'ralfdocs'),
      'added_to_report_label' => esc_html__('Added to report!', 'ralfdocs'),
      'removed_from_report_label' => esc_html__('Removed from report', 'ralfdocs'),
      'valid_email_address_error' => esc_html__('Please enter only valid email addresses.', 'ralfdocs'),
      'query_vars' => json_encode($wp_query->query)
    ));

    //styles
    //wp_register_style('ralfdocs-style', RALFDOCS_PLUGIN_URL . 'css/ralfdocs-style.css');

    //wp_enqueue_style('ralfdocs-style');
    wp_enqueue_style('dashicons');
  }

  public function rewrite_report_url(){
    add_rewrite_tag('%report_id%', '([^&]+)');
    add_rewrite_rule('^view-report/([^.]*)$', 'index.php?pagename=view-report&report_id=$matches[1]', 'top');
  }

  public function acf_settings_path($path){
    //$path = RALFDOCS_PLUGIN_URL . 'vendors/advanced-custom-fields-pro/';
    $path = plugin_dir_path(__FILE__) . 'vendors/advanced-custom-fields-pro/';

    return $path;
  }

  public function acf_settings_dir($dir){
    //$dir = RALFDOCS_PLUGIN_DIR . '/vendors/advanced-custom-fields-pro/';
    $dir = plugin_dir_url(__FILE__) . 'vendors/advanced-custom-fields-pro/';

    return $dir;
  }

  public function admin_settings_acf_options_page(){
    acf_add_options_page(array(
      'page_title' => esc_html__('RALF Documents Settings', 'ralfdocs'),
      'menu_title' => esc_html__('RALF Documents Settings', 'ralfdocs'),
      'menu_slug' => 'ralfdocs-settings',
      'capability' => 'edit_posts',
      'redirect' => false
    ));
  }

  public function init_widgets(){
    register_sidebar(array(
      'name' => esc_html__('RALF Documents Sidebar', 'ralfdocs'),
      'id' => 'ralfdocs-sidebar',
      'description' => esc_html__('Sidebar for the RALF Documents results pages.', 'ralfdocs'),
      'before_widget' => '<div class="sidebar-section">',
      'after_widget' => '</div>',
      'before_title' => '<h4>',
      'after_title' => '</h4>'
    ));

    register_widget('RALFDOCS_Sectors_Widget');
    register_widget('RALFDOCS_Search_History_Widget');
    register_widget('RALFDOCS_View_Report_Widget');
  }

  public function quick_select_form($atts){
    $number_of_options = shortcode_atts(array(
      'number_of_options' => 40
    ), $atts);
    $num_filters = $number_of_options['number_of_options'];
    ob_start();
      require_once ralfdocs_get_template('quick-select-form.php');
    return ob_get_clean();
  }

  public function ralfdocs_search_filter($query){
    if($query->is_search && !is_admin()){
      $query->set('post_type', array('activities', 'impacts', 'resources'));
    }

    return $query;
  }

  public function searchwp_join_term_relationships($sql, $post_type, $engine){
    global $wpdb;

    return "LEFT JOIN {$wpdb->prefix}term_relationships as swp_tax_rel ON swp_tax_rel.object_id = {$wpdb->prefix}posts.ID";  
  }

  public function searchwp_weight_priority_keywords($sql){
    $searched_keyword = get_search_query();
    $searched_keyword_term = get_term_by('slug', $searched_keyword, 'priority_keywords');
  
    if($searched_keyword_term != false){
      $priority_keyword_id = esc_sql($searched_keyword_term->term_id);
      $additional_weight = 1000;
  
      return $sql . " + (IF ((swp_tax_rel.term_taxonomy_id = {$priority_keyword_id}), {$additional_weight}, 0))";
    }  
  }

  public function do_ralfdocs_ajax_pagination(){
    $query_vars = json_decode(stripslashes($_POST['query_vars']), true);
    
    $tab_id = $_POST['tab_id'];
    $post_type = explode('-', $tab_id);

    $impacts_activities = new SWP_Query(array(
      'post_type' => $post_type,
      's' => $query_vars['s'],
      'engine' =>'default',
      'posts_per_page' => 10,
      'page' => $_POST['page'],
      'fields' => 'all'
    ));

    //if($impacts_activities->have_posts()): while($impacts_activities->have_posts()): $impacts_activities->the_post();
    if(!empty($impacts_activities->posts)):
      foreach($impacts_activities->posts as $post):
        setup_postdata($post);
        $article_id = $post->ID; ?>

        <div class="loop-item">
          <h2 class="loop-item-title">
            <a href="<?php echo esc_url(get_permalink($article_id)); ?>"><?php echo esc_html(get_the_title($article_id)); ?></a>
          </h2>
          <div class="loop-item-meta">
            <?php 
              if(has_term($searched_word, 'priority_keywords', $post)){
                echo '<span class="priority"></span>';
              }

              do_action('ralfdocs_article_meta', $article_id);
            ?>
          </div>
        </div>
      <?php endforeach; endif; //ralfdocs_pagination($_POST['page']); //wp_reset_postdata();    

    $big = 999999999;
    $pages = paginate_links(array(
      'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
      'format' => '?paged=%#%',
      'current' => max(1, $_POST['page']),
      'total' => $impacts_activities->max_num_pages,
      'type' => 'array'
    ));

    if(is_array($pages)){
      echo '<nav aria-label="Page navigation" class="pagination-nav"><ul class="pagination">';
      foreach($pages as $page){
        echo '<li>' . $page . '</li>';
      }
      echo '</ul></nav>';
    }

    wp_die();
  }
} // end Ralf_Docs class
}
new Ralf_Docs;