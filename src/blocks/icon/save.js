/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @return {Element} Element to render.
 */
export default function save( { attributes } ) {
	const {
		icon,
		size,
		color,
		backgroundColor,
		borderRadius,
		padding,
		alignment,
		hoverColor,
		hoverBackgroundColor,
		hoverEffect,
		link,
		linkTarget,
		ariaLabel
	} = attributes;

	const blockProps = useBlockProps.save( {
		className: `styled-icon-align-${alignment}`,
		style: {
			textAlign: alignment
		}
	} );

	const iconStyles = {
		fontSize: `${size}px`,
		color: color,
		backgroundColor: backgroundColor || 'transparent',
		borderRadius: borderRadius ? `${borderRadius}px` : '0',
		padding: padding ? `${padding}px` : '0',
		display: 'inline-block',
		lineHeight: 1,
		transition: 'all 0.3s ease'
	};

	const iconClasses = `dashicons dashicons-${icon} styled-icon-hover-${hoverEffect}`;

	// Create custom CSS variables for hover effects
	const customProperties = {};
	if ( hoverColor ) {
		customProperties['--styled-icon-hover-color'] = hoverColor;
	}
	if ( hoverBackgroundColor ) {
		customProperties['--styled-icon-hover-bg'] = hoverBackgroundColor;
	}

	const iconElement = (
		<span 
			className={ iconClasses }
			style={ { ...iconStyles, ...customProperties } }
			aria-label={ ariaLabel }
			data-hover-effect={ hoverEffect }
		></span>
	);

	return (
		<div { ...blockProps }>
			{ link ? (
				<a 
					href={ link }
					target={ linkTarget ? '_blank' : '_self' }
					rel={ linkTarget ? 'noopener noreferrer' : '' }
					aria-label={ ariaLabel }
				>
					{ iconElement }
				</a>
			) : (
				iconElement
			) }
		</div>
	);
}
