import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
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
		link,
		linkTarget,
		nofollow,
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
			{ link && ( title || imageUrl ) ? (
				// eslint-disable-next-line react/jsx-no-target-blank
				<a
					href={ link }
					target={ linkTarget ? '_blank' : undefined }
					rel={
						[
							nofollow ? 'nofollow' : null,
							linkTarget ? 'noreferrer' : null,
						]
							.filter( Boolean )
							.join( ' ' ) || undefined
					}
					aria-label={ title }
				>
					{ titleElement }
				</a>
			) : (
				titleElement
			) }
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
