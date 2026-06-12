import { useBlockProps } from '@wordpress/block-editor';
import { RawHTML } from '@wordpress/element';

const Save = ( { attributes } ) => {
	const { location, address, lat, lng, zoom, height } = attributes;

	// Prefer `location` attribute when present (Elementor-style)
	const locAddress =
		location && location.address ? location.address : address || '';
	let locLat = null;
	if ( location && location.lat !== null ) {
		locLat = location.lat;
	} else if ( lat !== null ) {
		locLat = lat;
	}
	let locLng = null;
	if ( location && location.lng !== null ) {
		locLng = location.lng;
	} else if ( lng !== null ) {
		locLng = lng;
	}

	// Prefer coordinates when provided, otherwise fall back to address.
	let src = '';
	if ( locLat !== null && locLng !== null ) {
		src = `https://maps.google.com/maps?q=${ encodeURIComponent(
			locLat
		) },${ encodeURIComponent( locLng ) }&z=${ encodeURIComponent(
			zoom
		) }&output=embed`;
	} else if ( locAddress ) {
		src = `https://maps.google.com/maps?q=${ encodeURIComponent(
			locAddress
		) }&z=${ encodeURIComponent( zoom ) }&output=embed`;
	}

	// Helper to prefer `attributes.style.spacing` (canonical) and fall back to legacy shapes.
	const getSpacing = ( name ) => {
		if ( attributes?.style?.spacing && attributes.style.spacing[ name ] ) {
			return attributes.style.spacing[ name ];
		}
		if ( attributes && attributes[ `_${ name }` ] ) {
			return attributes[ `_${ name }` ];
		}
		if ( attributes && attributes[ name ] ) {
			return attributes[ name ];
		}
		return null;
	};

	const normalizeSideValue = ( v ) => {
		if ( v === undefined || v === null || v === '' ) {
			return 0;
		}
		// Numeric values -> px
		if ( typeof v === 'number' ) {
			return v;
		}
		// Strings that already contain a unit (e.g., '2px' or '1.5%') -> try to extract numeric part
		if ( typeof v === 'string' ) {
			const t = v.trim();
			const m = t.match( /^([0-9.+-]+)/ );
			if ( m ) {
				return parseInt( m[ 1 ], 10 );
			}
			return 0;
		}
		// Elementor unit object shape: { unit: 'px', top: '2', ... }
		if ( typeof v === 'object' && v.unit ) {
			const top = v.top || 0;
			return parseInt( top, 10 ) || 0;
		}
		return Number( v ) || 0;
	};

	const buildShorthand = ( boxName ) => {
		const box = getSpacing( boxName );
		if ( ! box ) {
			return null;
		}
		// unit-object shape
		if ( box.unit ) {
			const unit = box.unit || 'px';
			const top = box.top || 0;
			const right = box.right || 0;
			const bottom = box.bottom || 0;
			const left = box.left || 0;
			return `${ top }${ unit } ${ right }${ unit } ${ bottom }${ unit } ${ left }${ unit }`;
		}
		const top = normalizeSideValue(
			box.top ?? box.top === 0 ? box.top : null
		);
		const right = normalizeSideValue(
			box.right ?? box.right === 0 ? box.right : null
		);
		const bottom = normalizeSideValue(
			box.bottom ?? box.bottom === 0 ? box.bottom : null
		);
		const left = normalizeSideValue(
			box.left ?? box.left === 0 ? box.left : null
		);
		return `${ top }px ${ right }px ${ bottom }px ${ left }px`;
	};

	const marginShort = buildShorthand( 'margin' );
	const paddingShort = buildShorthand( 'padding' );

	// Build shorthand style with margin first then padding to match existing converted posts
	const styleSegments = [];
	if ( marginShort ) {
		styleSegments.push( `margin:${ marginShort }` );
	}
	if ( paddingShort ) {
		styleSegments.push( `padding:${ paddingShort }` );
	}
	const wrapperStyleString = styleSegments.join( ';' );

	const blockProps = useBlockProps.save();
	const className = blockProps.className || '';

	const inner = src
		? `<iframe src="${ src }" style="width:100%;height:${ height }px;border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>`
		: `<div style="height:${ height }px;background:#f3f3f3;border:1px solid #ddd"></div>`;

	const output = `<div class="${ className }" style="${ wrapperStyleString }">${ inner }</div>`;

	return <RawHTML>{ output }</RawHTML>;
};

export default Save;
