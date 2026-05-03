<?php
/**
 * Template Name: ETG Full Width Page
 * Template Post Type: page
 *
 *
 * @package Progressus\Gutenberg
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="content" class="site-main etg-full-width-page" role="main">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php
get_footer();
