import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	InspectorAdvancedControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	SelectControl,
	RangeControl,
	Button,
	ColorPicker,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalBoxControl as BoxControl,
} from '@wordpress/components';

/**
 * Format a TRBL object (with .top, .right, .bottom, .left, .unit) into a CSS shorthand string.
 * Returns empty string when all sides are zero/absent.
 * @param {Object} obj
 */
function formatTRBL( obj ) {
	if ( ! obj ) {
		return '';
	}
	const unit = obj.unit || 'px';
	const top = String( obj.top ?? '0' );
	const right = String( obj.right ?? '0' );
	const bottom = String( obj.bottom ?? '0' );
	const left = String( obj.left ?? '0' );
	const vals = [
		`${ top }${ unit }`,
		`${ right }${ unit }`,
		`${ bottom }${ unit }`,
		`${ left }${ unit }`,
	];
	const allSame = vals.every( ( v ) => v === vals[ 0 ] );
	if ( allSame ) {
		return vals[ 0 ];
	}
	if ( vals[ 0 ] === vals[ 2 ] && vals[ 1 ] === vals[ 3 ] ) {
		return `${ vals[ 0 ] } ${ vals[ 1 ] }`;
	}
	return vals.join( ' ' );
}

/**
 * Convert a BoxControl value object (with top/right/bottom/left as numbers) to our TRBL attribute format.
 * @param {Object} boxValue
 * @param {string} unit
 */
function boxControlToTRBL( boxValue, unit = 'px' ) {
	return {
		top: String( boxValue?.top ?? '0' ),
		right: String( boxValue?.right ?? '0' ),
		bottom: String( boxValue?.bottom ?? '0' ),
		left: String( boxValue?.left ?? '0' ),
		unit,
	};
}

/**
 * Convert our TRBL attribute format to a BoxControl value object.
 * @param {Object} trbl
 */
function trblToBoxControl( trbl ) {
	return {
		top: trbl?.top ?? '0',
		right: trbl?.right ?? '0',
		bottom: trbl?.bottom ?? '0',
		left: trbl?.left ?? '0',
	};
}

const Edit = ( { attributes, setAttributes } ) => {
	const {
		content,
		name,
		job,
		alignment,
		imageUrl,
		imageId,
		imageSize,
		imageBorderRadius,
		imageBorderWidth,
		imageBorderColor,
		customId,
		customClass,
	} = attributes;

	const blockProps = useBlockProps( {
		className: [
			'testimonial-widget',
			`has-text-align-${ alignment }`,
			customClass,
		]
			.filter( Boolean )
			.join( ' ' ),
		style: { textAlign: alignment },
	} );

	// Compute image styles for the editor preview.
	const imageStyle = {
		width: `${ imageSize }px`,
		height: `${ imageSize }px`,
		objectFit: 'cover',
		display: 'block',
		flexShrink: '0',
	};
	const borderRadiusCss = formatTRBL( imageBorderRadius );
	if ( borderRadiusCss && borderRadiusCss !== '0px' ) {
		imageStyle.borderRadius = borderRadiusCss;
	}
	const borderWidthCss = formatTRBL( imageBorderWidth );
	const hasBorder = borderWidthCss && borderWidthCss !== '0px';
	if ( hasBorder ) {
		imageStyle.borderWidth = borderWidthCss;
		imageStyle.borderStyle = 'solid';
		if ( imageBorderColor ) {
			imageStyle.borderColor = imageBorderColor;
		}
	}

	const hasAuthor = imageUrl || name || job;
	const hasMeta = name || job;

	return (
		<>
			<InspectorControls>
				{ /* ── Content Panel ─────────────────────────────────── */ }
				<PanelBody
					title={ __( 'Content', 'layoutbridge-block-migration' ) }
					initialOpen={ true }
				>
					<TextareaControl
						label={ __(
							'Quote / Testimonial',
							'layoutbridge-block-migration'
						) }
						value={ content }
						onChange={ ( val ) =>
							setAttributes( { content: val } )
						}
						rows={ 4 }
					/>
					<TextControl
						label={ __( 'Client Name', 'layoutbridge-block-migration' ) }
						value={ name }
						onChange={ ( val ) => setAttributes( { name: val } ) }
					/>
					<TextControl
						label={ __( 'Job / Title', 'layoutbridge-block-migration' ) }
						value={ job }
						onChange={ ( val ) => setAttributes( { job: val } ) }
					/>
					<SelectControl
						label={ __( 'Alignment', 'layoutbridge-block-migration' ) }
						value={ alignment }
						options={ [
							{
								label: __( 'Left', 'layoutbridge-block-migration' ),
								value: 'left',
							},
							{
								label: __( 'Center', 'layoutbridge-block-migration' ),
								value: 'center',
							},
							{
								label: __( 'Right', 'layoutbridge-block-migration' ),
								value: 'right',
							},
						] }
						onChange={ ( val ) =>
							setAttributes( { alignment: val } )
						}
					/>
				</PanelBody>

				{ /* ── Avatar / Image Panel ───────────────────────────── */ }
				<PanelBody
					title={ __( 'Avatar Image', 'layoutbridge-block-migration' ) }
					initialOpen={ false }
				>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ ( media ) =>
								setAttributes( {
									imageUrl: media.url,
									imageId: media.id,
								} )
							}
							allowedTypes={ [ 'image' ] }
							value={ imageId }
							render={ ( { open } ) => (
								<>
									{ imageUrl && (
										<img
											src={ imageUrl }
											alt={ name }
											style={ {
												width: '80px',
												height: '80px',
												objectFit: 'cover',
												borderRadius:
													borderRadiusCss ||
													undefined,
												marginBottom: '8px',
												display: 'block',
											} }
										/>
									) }
									<Button
										variant="secondary"
										onClick={ open }
									>
										{ imageUrl
											? __(
													'Replace Image',
													'layoutbridge-block-migration'
											  )
											: __(
													'Upload Image',
													'layoutbridge-block-migration'
											  ) }
									</Button>
									{ imageUrl && (
										<Button
											isDestructive
											variant="link"
											onClick={ () =>
												setAttributes( {
													imageUrl: '',
													imageId: 0,
												} )
											}
											style={ { marginLeft: '8px' } }
										>
											{ __(
												'Remove',
												'layoutbridge-block-migration'
											) }
										</Button>
									) }
								</>
							) }
						/>
					</MediaUploadCheck>

					<RangeControl
						label={ __(
							'Image Size (px)',
							'layoutbridge-block-migration'
						) }
						value={ imageSize }
						onChange={ ( val ) =>
							setAttributes( { imageSize: val } )
						}
						min={ 20 }
						max={ 200 }
					/>

					<BoxControl
						label={ __( 'Border Radius', 'layoutbridge-block-migration' ) }
						values={ trblToBoxControl( imageBorderRadius ) }
						onChange={ ( nextVal ) =>
							setAttributes( {
								imageBorderRadius: boxControlToTRBL(
									nextVal,
									imageBorderRadius?.unit || 'px'
								),
							} )
						}
					/>

					<BoxControl
						label={ __( 'Border Width', 'layoutbridge-block-migration' ) }
						values={ trblToBoxControl( imageBorderWidth ) }
						onChange={ ( nextVal ) =>
							setAttributes( {
								imageBorderWidth: boxControlToTRBL(
									nextVal,
									imageBorderWidth?.unit || 'px'
								),
							} )
						}
					/>

					<p style={ { fontWeight: 600, marginBottom: '8px' } }>
						{ __( 'Border Color', 'layoutbridge-block-migration' ) }
					</p>
					<ColorPicker
						color={ imageBorderColor }
						onChange={ ( val ) =>
							setAttributes( { imageBorderColor: val } )
						}
						enableAlpha
					/>
				</PanelBody>
			</InspectorControls>

			{ /* ── Advanced Controls ──────────────────────────────────── */ }
			<InspectorAdvancedControls>
				<TextControl
					label={ __( 'HTML Anchor (ID)', 'layoutbridge-block-migration' ) }
					value={ customId }
					onChange={ ( val ) => setAttributes( { customId: val } ) }
				/>
				<TextControl
					label={ __(
						'Additional CSS Class(es)',
						'layoutbridge-block-migration'
					) }
					value={ customClass }
					onChange={ ( val ) =>
						setAttributes( { customClass: val } )
					}
				/>
			</InspectorAdvancedControls>

			{ /* ── Editor Preview ─────────────────────────────────────── */ }
			<div { ...blockProps }>
				{ content && (
					<div className="testimonial-content">
						<p>{ content }</p>
					</div>
				) }

				{ hasAuthor && (
					<div
						className="testimonial-author"
						style={ {
							display: 'flex',
							flexDirection: 'row',
							alignItems: 'center',
							gap: '12px',
							marginTop: '16px',
						} }
					>
						{ imageUrl && (
							<img
								src={ imageUrl }
								alt={ name }
								className="testimonial-image"
								style={ imageStyle }
							/>
						) }
						{ hasMeta && (
							<div
								className="testimonial-meta"
								style={ {
									display: 'flex',
									flexDirection: 'column',
									justifyContent: 'center',
									gap: '2px',
								} }
							>
								{ name && (
									<strong className="testimonial-name">
										{ name }
									</strong>
								) }
								{ job && (
									<span className="testimonial-job">
										{ job }
									</span>
								) }
							</div>
						) }
					</div>
				) }
			</div>
		</>
	);
};

export default Edit;
