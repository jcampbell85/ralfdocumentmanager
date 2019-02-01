<?php
/**
 * Template for single questions in question tree
 * 
 * Can be overridden with custom template file here:
 * THEME_STYLESHEET_DIRECTORY/ralfdocs-templates/single-questions.php
 */

get_header();

$page_id = get_the_ID();
$question_sectors = wp_get_post_terms($page_id, 'sectors');
$sector_id = '';
foreach($question_sectors as $sector){
  if($sector->parent == 0){
   $sector_id = $sector->term_id;
  }
}
$sector_image = get_field('question_tree_background_image', 'sectors_' . $sector_id);
$sector_image_css = get_field('question_tree_background_image_css', 'sectors_' . $sector_id);
?>

<div id="question-tree" style="background-image:url(<?php echo esc_url($sector_image['url']); ?>); <?php echo esc_html($sector_image_css); ?>">
  <div class="container">
    <?php if(have_posts()): while(have_posts()): the_post(); ?>
      <article>
        <h3><?php the_title(); ?></h3>
        <?php if(have_rows('answers')): ?>

          <ul class="qt-options list-unstyled">
            <?php while(have_rows('answers')): the_row(); ?>
              <?php
                $answer = get_sub_field('answer_link');
                $answer_link = '';
                $next_type = '';
                
                if($answer):

                  if($answer[0]->post_type == 'questions'){
                    $answer_link = get_permalink($answer[0]->ID);
                    $next_type = 'Next';
                  }
                  elseif($answer[0]->post_type == 'prepared_reports'){
                    $reports = get_field('report_articles', $answer[0]->ID);
                    $report_ids = array();
                    foreach($reports as $report){
                      $report_ids[] = $report->ID;
                    }
                    $article_ids = implode(',', $report_ids);
                    $answer_link = add_query_arg('article_ids', $article_ids, home_url('view-report'));
                    $next_type = 'View Report';
                  }
              ?>
              <li class="radio">
                <label>
                  <input type="radio" name="qt-answers" value="<?php echo esc_url($answer_link); ?>" data-next_type="<?php echo $next_type; ?>" />
                  <?php echo esc_html(get_sub_field('answer')); ?>
                  <span class="radio-btn"></span>
                </label>
              </li>
            <?php endif; endwhile; ?>
          </ul>
          <a href="#" id="qt-btn" class="btn-main btn-hide">Next</a>

        <?php endif; ?>
      </article>
    <?php endwhile; endif; ?>
  </div>
  <div class="full-page-overlay"></div>
</div>

<?php get_footer();