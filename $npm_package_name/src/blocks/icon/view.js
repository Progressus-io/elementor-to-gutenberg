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
	// Get all FontAwesome icon blocks
	const iconBlocks = document.querySelectorAll('.wp-block-gutenberg-icon i');
	
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
		
		// Add loading state for FontAwesome icons
		// Check if FontAwesome is loaded
		if (window.FontAwesome || document.querySelector('link[href*="font-awesome"]')) {
			icon.classList.add('fa-loaded');
		} else {
			// Add a loading indicator or fallback
			icon.style.opacity = '0.5';
			
			// Check periodically if FontAwesome has loaded
			const checkFontAwesome = setInterval(function() {
				if (window.FontAwesome || document.querySelector('link[href*="font-awesome"]')) {
					icon.style.opacity = '';
					icon.classList.add('fa-loaded');
					clearInterval(checkFontAwesome);
				}
			}, 100);
			
			// Stop checking after 5 seconds
			setTimeout(function() {
				clearInterval(checkFontAwesome);
				icon.style.opacity = '';
			}, 5000);
		}
	});
	
	// Performance optimization: Use CSS custom properties for hover effects
	// This is already handled in the save function, but we can enhance it here
	iconBlocks.forEach(function(icon) {
		const hoverEffect = icon.getAttribute('data-hover-effect');
		
		if (hoverEffect && hoverEffect !== 'none') {
			// Add a class to enable hardware acceleration
			icon.classList.add('fontawesome-icon-hw-accelerated');
		}
	});
	
	// Add intersection observer for animation optimization
	if ('IntersectionObserver' in window) {
		const observer = new IntersectionObserver(function(entries) {
			entries.forEach(function(entry) {
				const icon = entry.target;
				if (entry.isIntersecting) {
					icon.classList.add('in-viewport');
				} else {
					icon.classList.remove('in-viewport');
				}
			});
		}, {
			rootMargin: '10px'
		});
		
		iconBlocks.forEach(function(icon) {
			observer.observe(icon);
		});
	}
});

// Add CSS for hardware acceleration and viewport optimization
const style = document.createElement('style');
style.textContent = `
	.fontawesome-icon-hw-accelerated {
		will-change: transform, opacity;
		backface-visibility: hidden;
	}
	
	.wp-block-gutenberg-icon i.fa-loaded {
		opacity: 1 !important;
		transition: all 0.3s ease;
	}
	
	/* Reduce animations when not in viewport for performance */
	.wp-block-gutenberg-icon i:not(.in-viewport) {
		animation-play-state: paused;
	}
	
	.wp-block-gutenberg-icon i.in-viewport {
		animation-play-state: running;
	}
`;
document.head.appendChild(style);

// Add error handling for FontAwesome loading
window.addEventListener('error', function(e) {
	if (e.target && e.target.href && e.target.href.includes('font-awesome')) {
		console.warn('FontAwesome failed to load from CDN. Icons may not display correctly.');
		
		// Optionally, you could load a fallback or show text-based icons
		const iconBlocks = document.querySelectorAll('.wp-block-gutenberg-icon i');
		iconBlocks.forEach(function(icon) {
			const iconName = icon.getAttribute('data-icon');
			if (iconName && !icon.textContent.trim()) {
				// Show icon name as fallback
				icon.textContent = iconName.replace('fa-', '');
				icon.style.fontFamily = 'inherit';
				icon.style.fontSize = '0.8em';
				icon.style.textTransform = 'uppercase';
				icon.style.border = '1px solid currentColor';
				icon.style.padding = '2px 4px';
				icon.style.borderRadius = '2px';
			}
		});
	}
}, true);

// Preload FontAwesome if not already loaded
if (!document.querySelector('link[href*="font-awesome"]')) {
	const link = document.createElement('link');
	link.rel = 'preload';
	link.as = 'style';
	link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
	link.onload = function() {
		this.onload = null;
		this.rel = 'stylesheet';
	};
	document.head.appendChild(link);
}