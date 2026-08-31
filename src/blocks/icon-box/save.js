import { RichText } from '@wordpress/block-editor';

/**
 * Turn a CSS declaration string into the style object React expects.
 *
 * @param {string} str Declarations, e.g. "width:35px;height:auto;".
 *
 * @return {Object} Style object.
 */
const parseStyleString = ( str ) => {
	return String( str )
		.split( ';' )
		.reduce( ( acc, rule ) => {
			const parts = rule.split( ':' );
			const prop = parts[ 0 ] ? parts[ 0 ].trim() : '';
			const val = parts[ 1 ] ? parts.slice( 1 ).join( ':' ).trim() : '';
			if ( ! prop || ! val ) {
				return acc;
			}
			const jsProp = prop.replace( /-([a-z])/g, function ( _, c ) {
				return c.toUpperCase();
			} );
			acc[ jsProp ] = val;
			return acc;
		}, {} );
};

/**
 * Build the style for the title or the description.
 *
 * An icon box converted from a widget Elementor left unstyled carries an empty
 * colour so the theme keeps deciding it. Passing that through would serialise as
 * a bare `color:` - a declaration the converter does not write - so the key is
 * left out entirely instead.
 *
 * @param {number} size  Font size in pixels.
 * @param {string} color Colour, or an empty string for none.
 *
 * @return {Object} Style object.
 */
const textStyle = ( size, color ) => {
	const style = { fontSize: `${ size }px` };

	if ( color ) {
		style.color = color;
	}

	return style;
};

export default function save( { attributes } ) {
	const {
		icon,
		iconStyle,
		svgUrl,
		svgStyle,
		size,
		title,
		description,
		titleSize,
		titleColor,
		descriptionSize,
		descriptionColor,
		alignment,
		className,
		anchor,
	} = attributes;

	const wrapperClasses = arrayUnique( [
		'wp-block-icon-box',
		className,
	] ).join( ' ' );

	// Built as elements rather than as a string of HTML: these values reach the
	// block from the converter and from the media library, and interpolating
	// them into attribute positions in a raw HTML string is what let a value
	// containing a quote escape its attribute. React escapes each one for the
	// context it lands in. The previous markup is preserved in deprecated.js so
	// icon boxes already in posts keep validating.
	//
	// An icon box converted from a widget that had no icon - Header Footer
	// Elementor's info card, for one - stores an empty `icon` and no `svgUrl`.
	// Drawing a star there would put an element in the markup that whoever
	// wrote the block never asked for.
	let iconElement = null;

	if ( svgUrl ) {
		iconElement = (
			<img
				src={ svgUrl }
				alt=""
				style={
					svgStyle
						? parseStyleString( svgStyle )
						: { width: `${ size }px`, height: 'auto' }
				}
				className="svg-icon"
			/>
		);
	} else if ( icon ) {
		iconElement = (
			<i
				className={ `${ iconStyle } ${ icon }` }
				style={ { fontSize: `${ size }px` } }
			/>
		);
	}

	return (
		<div
			className={ wrapperClasses }
			style={ { textAlign: alignment } }
			id={ anchor || undefined }
		>
			{ iconElement && (
				<div className="icon-box-icon">{ iconElement }</div>
			) }
			{ title && (
				<RichText.Content
					tagName="h3"
					className="icon-box-title"
					value={ title }
					style={ textStyle( titleSize, titleColor ) }
				/>
			) }
			{ description && (
				<RichText.Content
					tagName="div"
					className="icon-box-description"
					value={ description }
					style={ textStyle( descriptionSize, descriptionColor ) }
				/>
			) }
		</div>
	);
}

function arrayUnique( arr ) {
	return Array.from( new Set( ( arr || [] ).filter( Boolean ) ) );
}
