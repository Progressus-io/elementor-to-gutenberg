/**
 * Sanitize SVG markup before it is stored in a block attribute.
 *
 * The icon blocks fetch the text of a selected .svg media item and keep it in an
 * attribute that is later emitted with dangerouslySetInnerHTML. Cleaning it here,
 * on the way in, means the markup that reaches post content is already safe and
 * the save() output does not have to change for content that is already stored.
 *
 * Removes scripting elements, event handler attributes, and script-bearing link
 * targets, and leaves everything else - shapes, paths, gradients, styling - alone
 * so the icon still looks the way the user chose it.
 */

const FORBIDDEN_ELEMENTS = [
	'script',
	'foreignobject',
	'iframe',
	'embed',
	'object',
	'handler',
];

const LINK_ATTRIBUTES = [ 'href', 'xlink:href' ];

const DANGEROUS_SCHEMES = /^(?:javascript|vbscript|data:text\/html)/i;

/**
 * Whether a link value points at something that can execute.
 *
 * @param {string} value Attribute value.
 *
 * @return {boolean} True when the value must be dropped.
 */
const isDangerousLink = ( value ) => {
	// Drop whitespace and control characters first: they are otherwise usable
	// to break up a scheme name, as in "java\tscript:".
	// eslint-disable-next-line no-control-regex
	const normalized = String( value ).replace( /[\s\u0000-\u001f]/g, '' );

	return DANGEROUS_SCHEMES.test( normalized );
};

/**
 * Clean an element and, recursively, its children.
 *
 * @param {Element} element Element to clean.
 */
const cleanElement = ( element ) => {
	Array.from( element.children ).forEach( ( child ) => {
		if ( FORBIDDEN_ELEMENTS.includes( child.nodeName.toLowerCase() ) ) {
			child.remove();
			return;
		}

		cleanElement( child );
	} );

	Array.from( element.attributes ).forEach( ( attribute ) => {
		const name = attribute.name.toLowerCase();

		if ( name.startsWith( 'on' ) ) {
			element.removeAttribute( attribute.name );
			return;
		}

		if (
			LINK_ATTRIBUTES.includes( name ) &&
			isDangerousLink( attribute.value )
		) {
			element.removeAttribute( attribute.name );
		}
	} );
};

/**
 * Sanitize a string of SVG markup.
 *
 * @param {string} markup Raw SVG markup.
 *
 * @return {string} Sanitized markup, or an empty string when the input is not SVG.
 */
export default function sanitizeSvg( markup ) {
	if ( typeof markup !== 'string' || ! markup.trim() ) {
		return '';
	}

	if ( typeof window === 'undefined' || ! window.DOMParser ) {
		return '';
	}

	const doc = new window.DOMParser().parseFromString(
		markup,
		'image/svg+xml'
	);

	if ( doc.getElementsByTagName( 'parsererror' ).length ) {
		return '';
	}

	const svg = doc.documentElement;

	if ( ! svg || svg.nodeName.toLowerCase() !== 'svg' ) {
		return '';
	}

	cleanElement( svg );

	return new window.XMLSerializer().serializeToString( svg );
}
