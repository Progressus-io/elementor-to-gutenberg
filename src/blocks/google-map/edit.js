import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	RangeControl,
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalBoxControl as BoxControl,
} from '@wordpress/components';
import { Fragment, useState, useEffect, useRef } from '@wordpress/element';

const mapTypes = [
	{ label: __( 'Roadmap', 'migrate-off-elementor' ), value: 'roadmap' },
	{ label: __( 'Satellite', 'migrate-off-elementor' ), value: 'satellite' },
	{ label: __( 'Hybrid', 'migrate-off-elementor' ), value: 'hybrid' },
	{ label: __( 'Terrain', 'migrate-off-elementor' ), value: 'terrain' },
];

const Edit = ( { attributes, setAttributes } ) => {
	const locationAttr = attributes.location || {};
	const addressAttr = attributes.address;
	const latAttr = attributes.lat;
	const lngAttr = attributes.lng;
	const { zoom, height, mapType } = attributes;

	// Location preference: use `location` attribute when available (Elementor-style)
	const locAddress =
		locationAttr && locationAttr.address
			? locationAttr.address
			: addressAttr || '';
	let locLat = null;
	if ( locationAttr && locationAttr.lat !== null ) {
		locLat = locationAttr.lat;
	} else if ( latAttr !== null ) {
		locLat = latAttr;
	}
	let locLng = null;
	if ( locationAttr && locationAttr.lng !== null ) {
		locLng = locationAttr.lng;
	} else if ( lngAttr !== null ) {
		locLng = lngAttr;
	}

	const blockProps = useBlockProps();
	const mapContainerRef = useRef( null );
	const mapRef = useRef( null );
	const markerRef = useRef( null );

	const [ query, setQuery ] = useState( locAddress || '' );
	// Helper to convert stored spacing values to CSS strings for BoxControl display.
	const valueToCss = ( v ) => {
		if ( v === undefined || v === null || v === '' ) {
			return '0px';
		}
		if ( typeof v === 'number' ) {
			return v + 'px';
		}
		if ( typeof v === 'string' ) {
			const trimmed = v.trim();
			// If it already ends with a unit, return as-is.
			if ( /[a-z%]$/i.test( trimmed ) ) {
				return trimmed;
			}
			// If it's numeric-like, append px.
			if ( ! isNaN( parseFloat( trimmed ) ) ) {
				return trimmed + 'px';
			}
			return trimmed;
		}
		return String( v );
	};
	const padding = attributes?.style?.spacing?.padding ||
		attributes.padding || {
			top: 0,
			right: 0,
			bottom: 0,
			left: 0,
		};
	const margin = attributes?.style?.spacing?.margin ||
		attributes.margin || {
			top: 0,
			right: 0,
			bottom: 0,
			left: 0,
		};
	useEffect( () => {
		setQuery( locAddress || '' );
	}, [ locAddress ] );

	const onAddressChange = ( value ) => {
		setQuery( value );
		// When user types a new address, reset coordinates to force selection
		setAttributes( {
			location: { address: value, lat: null, lng: null },
			address: value,
			lat: null,
			lng: null,
		} );
	};

	const previewStyle = {
		height: `${ height }px`,
		background: '#f3f3f3',
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		color: '#555',
		border: '1px solid #ddd',
	};

	// Initialize Google Maps (editor preview) when API is available.
	useEffect( () => {
		if (
			typeof window === 'undefined' ||
			! window.google ||
			! window.google.maps
		) {
			return;
		}
		if ( ! mapContainerRef.current ) {
			return;
		}

		// Create map
		try {
			const center =
				locLat !== null && locLng !== null
					? { lat: parseFloat( locLat ), lng: parseFloat( locLng ) }
					: null;

			mapRef.current = new window.google.maps.Map(
				mapContainerRef.current,
				{
					zoom: zoom || 14,
					mapTypeId: mapType || 'roadmap',
				}
			);

			if ( center ) {
				mapRef.current.setCenter( center );
			} else if ( locAddress ) {
				// Geocode address to center map in the editor preview
				try {
					const geocoder = new window.google.maps.Geocoder();
					geocoder.geocode(
						{ address: locAddress },
						( results, status ) => {
							if ( status === 'OK' && results && results[ 0 ] ) {
								mapRef.current.setCenter(
									results[ 0 ].geometry.location
								);
							}
						}
					);
				} catch ( e ) {
					// ignore geocode failures in editor
				}
			}
		} catch ( e ) {
			// ignore initialization failures in editor
		}

		return () => {
			try {
				if ( markerRef.current ) {
					markerRef.current = null;
				}
			} catch ( e ) {}
			try {
				if ( mapRef.current ) {
					mapRef.current = null;
				}
			} catch ( e ) {}
		};
	}, [ locLat, locLng, locAddress, zoom, mapType ] );

	// Prepare iframe src like save.js
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

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody
					title={ __( 'Map Settings', 'migrate-off-elementor' ) }
					initialOpen={ true }
				>
					<p style={ { marginTop: 0, marginBottom: 8 } }>
						{ __(
							"Set your Google Maps API Key in the plugin's Integrations Settings page.",
							'migrate-off-elementor'
						) }{ ' ' }
						<a
							href="/wp-admin/admin.php?page=blockshift-settings"
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Open Settings', 'migrate-off-elementor' ) }
						</a>{ ' ' }
						{ __( 'Create your key', 'migrate-off-elementor' ) }{ ' ' }
						<a
							href="https://developers.google.com/maps/documentation/embed/get-api-key"
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'here.', 'migrate-off-elementor' ) }
						</a>
					</p>
					<div style={ { marginBottom: 8 } }>
						<label
							htmlFor="google-map-address-input"
							className="components-base-control__label"
						>
							{ __( 'Address', 'migrate-off-elementor' ) }
						</label>
						<input
							id="google-map-address-input"
							type="text"
							className="components-text-control__input"
							value={ query }
							onChange={ ( e ) =>
								onAddressChange( e.target.value )
							}
							placeholder={ __(
								'Enter an address',
								'migrate-off-elementor'
							) }
							style={ { width: '100%' } }
						/>
					</div>

					<TextControl
						label={ __(
							'Latitude (optional)',
							'migrate-off-elementor'
						) }
						value={ locLat === null ? '' : String( locLat ) }
						onChange={ ( value ) =>
							setAttributes( {
								lat: value === '' ? null : parseFloat( value ),
							} )
						}
					/>
					<TextControl
						label={ __(
							'Longitude (optional)',
							'migrate-off-elementor'
						) }
						value={ locLng === null ? '' : String( locLng ) }
						onChange={ ( value ) =>
							setAttributes( {
								lng: value === '' ? null : parseFloat( value ),
							} )
						}
					/>
					<RangeControl
						label={ __( 'Zoom', 'migrate-off-elementor' ) }
						value={ zoom }
						onChange={ ( value ) =>
							setAttributes( { zoom: value } )
						}
						min={ 1 }
						max={ 20 }
					/>
					<RangeControl
						label={ __( 'Height (px)', 'migrate-off-elementor' ) }
						value={ height }
						onChange={ ( value ) =>
							setAttributes( { height: value } )
						}
						min={ 100 }
						max={ 1200 }
					/>
					{ /* Show Marker removed */ }
					<SelectControl
						label={ __( 'Map Type', 'migrate-off-elementor' ) }
						value={ mapType }
						options={ mapTypes }
						onChange={ ( value ) =>
							setAttributes( { mapType: value } )
						}
					/>
					<PanelBody
						title={ __( 'Dimensions', 'migrate-off-elementor' ) }
						initialOpen={ false }
					>
						{ /** Normalize values for BoxControl display: accept '2px' or numeric 2 */ }
						<BoxControl
							label={ __( 'Padding', 'migrate-off-elementor' ) }
							values={ {
								top: valueToCss( padding.top ),
								right: valueToCss( padding.right ),
								bottom: valueToCss( padding.bottom ),
								left: valueToCss( padding.left ),
							} }
							onChange={ ( value ) => {
								const parsed = {
									top: parseInt( value.top ) || 0,
									right: parseInt( value.right ) || 0,
									bottom: parseInt( value.bottom ) || 0,
									left: parseInt( value.left ) || 0,
								};
								// keep legacy fields and canonical style.spacing in sync
								const newStyle = {
									...( attributes.style || {} ),
									spacing: {
										...( attributes.style?.spacing || {} ),
										...( attributes.style?.spacing?.padding
											? { padding: parsed }
											: { padding: parsed } ),
									},
								};
								setAttributes( {
									padding: parsed,
									_padding: parsed,
									style: newStyle,
								} );
							} }
							__nextHasNoMarginBottom
						/>

						<BoxControl
							label={ __( 'Margin', 'migrate-off-elementor' ) }
							values={ {
								top: valueToCss( margin.top ),
								right: valueToCss( margin.right ),
								bottom: valueToCss( margin.bottom ),
								left: valueToCss( margin.left ),
							} }
							onChange={ ( value ) => {
								const parsed = {
									top: parseInt( value.top ) || 0,
									right: parseInt( value.right ) || 0,
									bottom: parseInt( value.bottom ) || 0,
									left: parseInt( value.left ) || 0,
								};
								const newStyle = {
									...( attributes.style || {} ),
									spacing: {
										...( attributes.style?.spacing || {} ),
										...( attributes.style?.spacing?.margin
											? { margin: parsed }
											: { margin: parsed } ),
									},
								};
								// keep legacy fields and canonical style.spacing in sync
								setAttributes( {
									margin: parsed,
									_margin: parsed,
									style: newStyle,
								} );
							} }
							__nextHasNoMarginBottom
						/>
					</PanelBody>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div style={ previewStyle }>
					{ ( () => {
						const hasGoogleMaps =
							typeof window !== 'undefined' &&
							window.google &&
							window.google.maps;
						if ( hasGoogleMaps ) {
							return (
								<div
									ref={ mapContainerRef }
									style={ { width: '100%', height: '100%' } }
								/>
							);
						}
						if ( src ) {
							return (
								<iframe
									src={ src }
									title={ __(
										'Google Map',
										'migrate-off-elementor'
									) }
									style={ {
										width: '100%',
										height: '100%',
										border: 0,
									} }
									loading="lazy"
								/>
							);
						}
						return (
							<div>
								{ __(
									'Enter an address or coordinates to preview',
									'migrate-off-elementor'
								) }
							</div>
						);
					} )() }
				</div>
			</div>
		</Fragment>
	);
};

export default Edit;
