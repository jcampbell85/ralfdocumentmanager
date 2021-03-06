<?php 
/**
 * Template for viewing finished report
 * 
 * Can be overridden with custom template file here:
 * THEME_STYLESHEET_DIRECTORY/ralfdocs-templates/page-view-report.php
 */
get_header(); ?>
<div class="page-content">
  <div class="container">
    <main class="results-list">
      <header class="page-header">
        <h1><?php echo esc_html__('Report of Activities, Associated Impacts and Resources', 'ralfdocs'); ?></h1>
      </header>

      <?php
        $article_ids = [];
        if(isset($_GET['article_ids'])){
          $article_ids = ralfdocs_convert_to_int_array($_GET['article_ids']);
        }
        elseif(get_query_var('report_id')){
          $report_id = get_query_var('report_id');
          global $wpdb;

          $report_id_field = $wpdb->get_var($wpdb->prepare("
            SELECT report_ids
            FROM emailed_reports
            WHERE ID = %d", $report_id));

          $article_ids = ralfdocs_convert_to_int_array($report_id_field);
        }
        elseif(isset($_COOKIE['STYXKEY_ralfdocs_article_ids'])){
          $article_ids_cookie = $_COOKIE['STYXKEY_ralfdocs_article_ids'];

          $article_ids = ralfdocs_convert_to_int_array($article_ids_cookie);
        }

        //$article_ids[0] == 0 happens when all items are removed from the report but user hasn't left the reports page
        if($article_ids && $article_ids[0] != 0){
          $articles_report = new WP_Query(array(
            'post_type' => array('activities', 'impacts', 'resources'),
            'posts_per_page' => -1,
            'post__in' => $article_ids,
            'orderby' => 'post_type'
          ));

          if($articles_report->have_posts()){
            while($articles_report->have_posts()){
              $articles_report->the_post();
              do_action('ralfdocs_view_report_loop');
            }
          } wp_reset_postdata();

          //global $shortcode_tags;
          //return call_user_func($shortcode_tags['email_form'], array('activity_ids' => $article_ids));
          echo do_shortcode('[email_form activity_ids="' . implode(',', $article_ids) . '"]');
        }
        else{
          include ralfdocs_get_template('loop/no-results.php');
        }
      ?>
    </main>
  </div>
</div>
<?php get_footer();