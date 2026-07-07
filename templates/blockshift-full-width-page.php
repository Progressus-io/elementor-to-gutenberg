<?php
/**
 * Template Name: ETG Full Width Page
 * Template Post Type: page
 *
 * @package Progressus\BlockShift
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="content" class="site-main blockshift-full-width-page" role="main">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php
get_footer();
