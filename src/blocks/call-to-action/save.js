import { RichText } from '@wordpress/block-editor';

function arrayUnique( array ) {
	return array.filter( ( item, index ) => array.indexOf( item ) === index );
}

export default function save( { attributes } ) {
	const {
		layout,
		bgImageUrl,
		title,
		description,
		buttonText,
		buttonUrl,
		buttonTarget,
		buttonNofollow,
		alignment,
		imageMinHeight,
		contentBgColor,
		titleColor,
		titleSize,
		titleFontFamily,
		titleFontWeight,
		titleTextTransform,
		titleFontStyle,
		titleTextDecoration,
		titleLineHeight,
		titleLetterSpacing,
		titleWordSpacing,
		descriptionColor,
		descriptionSize,
		descriptionFontFamily,
		descriptionFontWeight,
		descriptionTextTransform,
		descriptionFontStyle,
		descriptionTextDecoration,
		descriptionLineHeight,
		descriptionLetterSpacing,
		descriptionWordSpacing,
		descriptionSpacing,
		buttonBgColor,
		buttonTextColor,
		buttonSize,
		buttonFontFamily,
		buttonFontWeight,
		buttonTextTransform,
		buttonFontStyle,
		buttonLineHeight,
		buttonLetterSpacing,
		buttonWordSpacing,
		buttonBorderRadius,
		buttonPadding,
		contentPadding,
		contentMargin,
		ribbonTitle,
		ribbonBgColor,
		ribbonTextColor,
		ribbonSize,
		ribbonFontFamily,
		ribbonFontWeight,
		ribbonTextTransform,
		ribbonFontStyle,
		ribbonTextDecoration,
		ribbonLineHeight,
		ribbonLetterSpacing,
		ribbonWordSpacing,
		ribbonHorizontalPosition,
		ribbonDistance,
		className,
		anchor,
	} = attributes;
	const getAriaLabel = ( url ) => {
		try {
			const p = new URL( url );
			const path = p.pathname || '';
			return path.split( '/' ).pop() || '';
		} catch ( e ) {
			return '';
		}
	};

	const wrapperClasses = arrayUnique( [
		'wp-block-call-to-action',
		alignment ? `has-text-align-${ alignment }` : '',
		`call-to-action-layout-${ layout }`,
		className,
	] )
		.filter( Boolean )
		.join( ' ' );

	// Helper: ensures a spacing value has a px unit.
	const withPx = ( v ) => {
		if ( ! v ) {
			return undefined;
		}
		return String( v ).includes( 'px' ) ? v : `${ v }px`;
	};

	// Lookup maps to avoid nested ternaries.
	const justifyContentMap = { center: 'center', right: 'flex-end' };
	const flexDirectionMap = {
		above: 'column',
		below: 'column-reverse',
		right: 'row-reverse',
		left: 'row',
	};
	const maxWidthMap = { center: '600px', above: '100%', below: '100%' };

	// Build title styles
	const titleStyles = {
		fontSize: `${ titleSize }px`,
		color: titleColor,
		marginBottom: '16px',
		fontFamily: titleFontFamily || undefined,
		fontWeight: titleFontWeight || undefined,
		textTransform: titleTextTransform || undefined,
		fontStyle: titleFontStyle || undefined,
		textDecoration: titleTextDecoration || undefined,
		lineHeight: titleLineHeight ? String( titleLineHeight ) : undefined,
		letterSpacing: withPx( titleLetterSpacing ),
		wordSpacing: withPx( titleWordSpacing ),
	};

	// Build description styles
	const descriptionStyles = {
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
		marginBottom: `${ descriptionSpacing }px`,
	};

	// Build button styles
	const buttonStyles = {
		display: 'inline-block',
		fontSize: `${ buttonSize }px`,
		color: buttonTextColor,
		backgroundColor: buttonBgColor,
		padding: `${ buttonPadding?.top || 0 }px ${
			buttonPadding?.right || 0
		}px ${ buttonPadding?.bottom || 0 }px ${ buttonPadding?.left || 0 }px`,
		borderRadius: `${ buttonBorderRadius || 4 }px`,
		textDecoration: 'none',
		cursor: 'pointer',
		border: 'none',
		fontFamily: buttonFontFamily || undefined,
		fontWeight: buttonFontWeight || undefined,
		textTransform: buttonTextTransform || undefined,
		fontStyle: buttonFontStyle || undefined,
		lineHeight: buttonLineHeight ? String( buttonLineHeight ) : undefined,
		letterSpacing: withPx( buttonLetterSpacing ),
		wordSpacing: withPx( buttonWordSpacing ),
	};

	const alignItems =
		layout === 'left' || layout === 'right' ? 'stretch' : 'flex-start';

	const ctaStyle = {
		minHeight: `${ imageMinHeight }px`,
		display: 'flex',
		position: 'relative',
		alignItems,
		justifyContent: justifyContentMap[ layout ] || 'flex-start',
		flexDirection: flexDirectionMap[ layout ],
		...( bgImageUrl &&
			layout === 'center' && {
				backgroundImage: `url(${ bgImageUrl })`,
				backgroundSize: 'cover',
				backgroundPosition: 'center',
			} ),
	};

	const contentStyle = {
		backgroundColor: contentBgColor || 'rgba(255,255,255,0.9)',
		padding: `${ contentPadding?.top || 0 }px ${
			contentPadding?.right || 0
		}px ${ contentPadding?.bottom || 0 }px ${
			contentPadding?.left || 0
		}px`,
		margin: `${ contentMargin?.top || 0 }px ${
			contentMargin?.right || 0
		}px ${ contentMargin?.bottom || 0 }px ${ contentMargin?.left || 0 }px`,
		maxWidth: maxWidthMap[ layout ] || '50%',
		...( layout === 'left' || layout === 'right'
			? {
					flexBasis: '50%',
					display: 'flex',
					flexDirection: 'column',
					justifyContent: 'flex-start',
			  }
			: {} ),
	};

	// Build ribbon styles
	const ribbonStyles = {
		position: 'absolute',
		top: `${ ribbonDistance }px`,
		...( ribbonHorizontalPosition === 'right'
			? { right: `${ ribbonDistance }px` }
			: { left: `${ ribbonDistance }px` } ),
		backgroundColor: ribbonBgColor,
		color: ribbonTextColor,
		fontSize: `${ ribbonSize }px`,
		fontFamily: ribbonFontFamily || undefined,
		fontWeight: ribbonFontWeight || undefined,
		textTransform: ribbonTextTransform || undefined,
		fontStyle: ribbonFontStyle || undefined,
		textDecoration: ribbonTextDecoration || undefined,
		lineHeight: ribbonLineHeight ? String( ribbonLineHeight ) : undefined,
		letterSpacing: withPx( ribbonLetterSpacing ),
		wordSpacing: withPx( ribbonWordSpacing ),
		padding: '8px 16px',
		borderRadius: '4px',
		zIndex: '10',
		transform:
			ribbonHorizontalPosition === 'right'
				? 'rotate(15deg)'
				: 'rotate(-15deg)',
	};

	const titleElement = title && (
		<RichText.Content
			tagName="h2"
			className="call-to-action-title"
			value={ title }
			style={ titleStyles }
		/>
	);

	const descriptionElement = description && (
		<RichText.Content
			tagName="p"
			className="call-to-action-description"
			value={ description }
			style={ descriptionStyles }
		/>
	);

	const buttonElement =
		buttonText &&
		( buttonUrl ? (
			// eslint-disable-next-line react/jsx-no-target-blank
			<a
				href={ buttonUrl }
				className="call-to-action-button"
				target={ buttonTarget ? '_blank' : undefined }
				rel={
					[
						buttonNofollow ? 'nofollow' : null,
						buttonTarget ? 'noreferrer' : null,
					]
						.filter( Boolean )
						.join( ' ' ) || undefined
				}
				style={ buttonStyles }
			>
				<RichText.Content tagName="span" value={ buttonText } />
			</a>
		) : (
			<RichText.Content
				tagName="span"
				className="call-to-action-button"
				value={ buttonText }
				style={ buttonStyles }
			/>
		) );

	const ribbonElement = ribbonTitle && (
		<div className="call-to-action-ribbon" style={ ribbonStyles }>
			<RichText.Content value={ ribbonTitle } />
		</div>
	);

	const imageElement =
		bgImageUrl &&
		( layout === 'above' ||
			layout === 'below' ||
			layout === 'left' ||
			layout === 'right' ) ? (
			<>
				<div
					className="call-to-action-image"
					role="img"
					aria-label={ getAriaLabel( bgImageUrl ) }
					style={ {
						backgroundImage: `url(${ bgImageUrl })`,
						backgroundSize: 'cover',
						backgroundPosition: 'center',
						minHeight: `${ imageMinHeight }px`,
						flexBasis:
							layout === 'left' || layout === 'right'
								? '50%'
								: undefined,
						width:
							layout === 'above' || layout === 'below'
								? '100%'
								: undefined,
					} }
				/>
				<div className="call-to-action-image-overlay" />
			</>
		) : null;

	return (
		<div
			className={ wrapperClasses }
			style={ { textAlign: alignment } }
			id={ anchor || undefined }
		>
			<div className="call-to-action-container" style={ ctaStyle }>
				{ ribbonElement }
				{ imageElement }
				<div className="call-to-action-content" style={ contentStyle }>
					{ titleElement }
					{ descriptionElement }
					{ buttonElement }
				</div>
			</div>
		</div>
	);
}
