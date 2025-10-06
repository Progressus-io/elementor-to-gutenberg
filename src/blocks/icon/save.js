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
		iconStyle,
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
		className: `fontawesome-icon-align-${alignment}`,
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
		transition: 'all 0.3s ease',
		width: 'auto',
		height: 'auto'
	};

	// Create custom CSS variables for hover effects
	const customProperties = { ...iconStyles };
	if ( hoverColor ) {
		customProperties['--fontawesome-icon-hover-color'] = hoverColor;
	}
	if ( hoverBackgroundColor ) {
		customProperties['--fontawesome-icon-hover-bg'] = hoverBackgroundColor;
	}

	const iconElement = (
		<i 
			className={ `${iconStyle} ${icon} fontawesome-icon-hover-${hoverEffect}` }
			style={ customProperties }
			aria-label={ ariaLabel }
			aria-hidden={ !ariaLabel ? 'true' : 'false' }
			data-hover-effect={ hoverEffect }
			data-icon={ icon }
			data-icon-style={ iconStyle }
		/>
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