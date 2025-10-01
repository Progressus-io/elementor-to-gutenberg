/**
 * Use this file for JavaScript code that you want to run in the front-end
 * on posts/pages that contain this block.
 *
 * When this file is defined as the value of the `viewScript` property
 * in `block.json` it will be enqueued on the front end of the site.
 *
 * Example:
 *
 * ```js
 * {
 *   "viewScript": "file:./view.js"
 * }
 * ```
 *
 * If you're not making any changes to this file because your project doesn't need any
 * JavaScript running in the front-end, then you should delete this file and remove
 * the `viewScript` property from `block.json`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

document.addEventListener('DOMContentLoaded', function() {
	// Get all styled icon blocks
	const iconBlocks = document.querySelectorAll('.wp-block-progressus-icon .dashicons');
	
	iconBlocks.forEach(function(icon) {
		// Add enhanced accessibility
		if (icon.hasAttribute('aria-label') && !icon.getAttribute('aria-label')) {
			// If aria-label exists but is empty, hide from screen readers
			icon.setAttribute('aria-hidden', 'true');
		}
		
		// Enhance keyboard navigation for linked icons
		const link = icon.closest('a');
		if (link) {
			// Ensure link is keyboard focusable
			if (!link.hasAttribute('tabindex')) {
				link.setAttribute('tabindex', '0');
			}
			
			// Add keyboard activation
			link.addEventListener('keydown', function(e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					link.click();
				}
			});
		}
		
		// Add smooth transitions if not already present
		if (!icon.style.transition) {
			icon.style.transition = 'all 0.3s ease';
		}
		
		// Optional: Add touch feedback for mobile devices
		icon.addEventListener('touchstart', function() {
			icon.style.opacity = '0.7';
		});
		
		icon.addEventListener('touchend', function() {
			setTimeout(function() {
				icon.style.opacity = '';
			}, 150);
		});
	});
	
	// Performance optimization: Use CSS custom properties for hover effects
	// This is already handled in the save function, but we can enhance it here
	iconBlocks.forEach(function(icon) {
		const hoverEffect = icon.getAttribute('data-hover-effect');
		
		if (hoverEffect && hoverEffect !== 'none') {
			// Add a class to enable hardware acceleration
			icon.classList.add('styled-icon-hw-accelerated');
		}
	});
});

// Add CSS for hardware acceleration
const style = document.createElement('style');
style.textContent = `
	.styled-icon-hw-accelerated {
		will-change: transform, opacity;
		backface-visibility: hidden;
	}
`;
document.head.appendChild(style);
