<?php
/**
 * Template Name: ETG Full Width Page
 * Template Post Type: page
 *
 * Mimics Elementor's "Elementor Full Width" page template:
 *   - Keeps the active theme's header and footer.
 *   - Drops the theme's content wrapper (no `.entry-content`, no max-width,
 *     no padding/margin, no sidebar, no page title).
 *   - Outputs `the_content()` directly inside a single full-width <main>.
 *
 * The class `etg-full-width-page` lets the global stylesheet
 * (assets/css/etg-full-width-page.css) reset any leftover theme padding
 * and ensure alignfull blocks span the viewport.
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
