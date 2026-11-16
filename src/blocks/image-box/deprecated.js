import { RichText } from '@wordpress/block-editor';
import metadata from './block.json';

/**
 * Version 1 of the Image Box save output.
 *
 * It left the title outside the link, which is how the converter wrote image boxes up to 1.1.0. Keeping it lets those validate and gain the linked title on their next save.
 *
 * Do not tidy this function: it has to reproduce the old markup exactly.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 *
 * @return {Element} The v1 markup.
 */
function v1Save( { attributes } ) {
	const {
		imageUrl,
		imageAlt,
		imageWidth,
		imageHeight,
		imageBorderRadius,
		imageSpace,
		wrapperPadding,
		wrapperMargin,
		title,
		description,
		titleSize,
		titleColor,
		titleFontFamily,
		titleFontWeight,
		titleTextTransform,
		titleFontStyle,
		titleTextDecoration,
		titleLineHeight,
		titleLetterSpacing,
		titleWordSpacing,
		descriptionSize,
		descriptionColor,
		descriptionFontFamily,
		descriptionFontWeight,
		descriptionTextTransform,
		descriptionFontStyle,
		descriptionTextDecoration,
		descriptionLineHeight,
		descriptionLetterSpacing,
		descriptionWordSpacing,
		objectFit,
		objectPosition,
		align,
		alignment,
		className,
		anchor,
	} = attributes;

	const effectiveAlignment = alignment || align || 'left';

	// Helper: ensures a spacing value has a px unit.
	const withPx = ( v ) => {
		if ( ! v ) {
			return undefined;
		}
		return String( v ).includes( 'px' ) ? v : `${ v }px`;
	};

	const wrapperClasses = arrayUnique( [ 'wp-block-image-box', className ] )
		.filter( Boolean )
		.join( ' ' );

	// Build title styles
	const titleStyles = {
		fontSize: `${ titleSize }px`,
		color: titleColor,
		fontFamily: titleFontFamily || undefined,
		fontWeight: titleFontWeight || undefined,
		textTransform: titleTextTransform || undefined,
		fontStyle: titleFontStyle || undefined,
		textDecoration: titleTextDecoration || undefined,
		lineHeight: titleLineHeight ? String( titleLineHeight ) : undefined,
		letterSpacing: withPx( titleLetterSpacing ),
		wordSpacing: withPx( titleWordSpacing ),
	};

	const titleElement = title && (
		<RichText.Content
			tagName="h3"
			className="image-box-title"
			value={ title }
			style={ titleStyles }
		/>
	);

	const imageElement = imageUrl && (
		<div
			className="image-box-image"
			style={ {
				display: 'flex',
				justifyContent:
					{ center: 'center', right: 'flex-end' }[
						effectiveAlignment
					] || 'flex-start',
				marginBottom: imageSpace || undefined,
				width: '100%',
			} }
		>
			<img
				src={ imageUrl }
				alt={ imageAlt || '' }
				style={ {
					width: `${ imageWidth }px`,
					height: `${ imageHeight }px`,
					borderRadius: imageBorderRadius || undefined,
					objectFit: objectFit || 'cover',
					objectPosition: objectPosition || 'center center',
					display: 'block',
				} }
			/>
		</div>
	);

	return (
		<div
			className={ wrapperClasses }
			style={ {
				textAlign: effectiveAlignment,
				padding: wrapperPadding || undefined,
				margin: wrapperMargin || undefined,
			} }
			id={ anchor || undefined }
		>
			{ imageElement }
			{ titleElement }
			{ description && (
				<RichText.Content
					tagName="div"
					className="image-box-description"
					value={ description }
					style={ {
						fontSize: `${ descriptionSize }px`,
						color: descriptionColor,
						fontFamily: descriptionFontFamily || undefined,
						fontWeight: descriptionFontWeight || undefined,
						textTransform: descriptionTextTransform || undefined,
						fontStyle: descriptionFontStyle || undefined,
						textDecoration: descriptionTextDecoration || undefined,
						lineHeight: descriptionLineHeight
							? String( descriptionLineHeight )
							: undefined,
						letterSpacing: withPx( descriptionLetterSpacing ),
						wordSpacing: withPx( descriptionWordSpacing ),
					} }
				/>
			) }
		</div>
	);
}

function arrayUnique( arr ) {
	return Array.from( new Set( ( arr || [] ).filter( Boolean ) ) );
}

export default [
	{
		apiVersion: metadata.apiVersion,
		attributes: metadata.attributes,
		supports: metadata.supports,
		save: v1Save,
	},
];
