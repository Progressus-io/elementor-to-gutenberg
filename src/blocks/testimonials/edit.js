import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	InspectorAdvancedControls,
	PanelColorSettings,
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
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalBoxControl as BoxControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

const Edit = ( { attributes, setAttributes } ) => {
	const {
		slides,
		layout,
		alignment,
		slidesPerView,
		slidesToScroll,
		width,
		spaceBetween,
		slideBackgroundColor,
		slideBorderSize,
		slideBorderRadius,
		slideBorderColor,
		slidePadding,
		contentGap,
		contentColor,
		contentTypography,
		nameColor,
		titleColor,
		imageSize,
		imageGap,
		imageBorderRadius,
		arrowsSize,
		arrowsColor,
		paginationGap,
		paginationSize,
		paginationColorInactive,
		_margin,
		_padding,
		customId,
		customClass,
	} = attributes;

	const [ editingSlide, setEditingSlide ] = useState( 0 );
	const blockProps = useBlockProps();

	const addSlide = () => {
		const newSlides = [
			...slides,
			{
				content: 'New testimonial content',
				name: 'Name',
				title: 'Title',
				imageUrl: '',
				imageId: 0,
			},
		];
		setAttributes( { slides: newSlides } );
	};

	const removeSlide = ( index ) => {
		if ( slides.length > 1 ) {
			const newSlides = slides.filter( ( _, i ) => i !== index );
			setAttributes( { slides: newSlides } );
			setEditingSlide( Math.min( editingSlide, newSlides.length - 1 ) );
		}
	};

	const updateSlide = ( index, field, value ) => {
		const newSlides = [ ...slides ];
		newSlides[ index ][ field ] = value;
		setAttributes( { slides: newSlides } );
	};

	const slideStyle = {
		backgroundColor: slideBackgroundColor,
		border: `${ slideBorderSize.top }px solid ${
			slideBorderColor || '#ddd'
		}`,
		borderRadius: `${ slideBorderRadius }px`,
		padding: `${ slidePadding.top }px ${ slidePadding.right }px ${ slidePadding.bottom }px ${ slidePadding.left }px`,
		margin: `${ _margin.top }px ${ _margin.right }px ${ _margin.bottom }px ${ _margin.left }px`,
		textAlign: alignment,
		maxWidth: `${ width }%`,
	};

	const contentStyle = {
		color: contentColor,
		fontSize: `${ contentTypography.fontSize }px`,
		fontWeight: contentTypography.fontWeight,
		fontStyle: contentTypography.fontStyle,
		textDecoration: contentTypography.textDecoration,
		lineHeight: `${ contentTypography.lineHeight }px`,
		letterSpacing: `${ contentTypography.letterSpacing }px`,
		wordSpacing: `${ contentTypography.wordSpacing }px`,
		fontFamily: contentTypography.fontFamily,
		marginBottom: `${ contentGap }px`,
	};

	const getImageStyle = () => {
		const baseStyle = {
			width: `${ imageSize }px`,
			height: `${ imageSize }px`,
			borderRadius: `${ imageBorderRadius }%`,
			objectFit: 'cover',
		};

		if ( layout === 'image_above' ) {
			baseStyle.marginBottom = `${ imageGap }px`;
		} else if ( layout === 'image_inline' ) {
			baseStyle.marginRight = `${ imageGap }px`;
		} else if ( layout === 'image_stacked' ) {
			baseStyle.marginTop = `${ contentGap }px`;
			baseStyle.marginBottom = `${ imageGap }px`;
		} else if ( layout === 'image_left' ) {
			baseStyle.marginRight = `${ imageGap }px`;
		} else if ( layout === 'image_right' ) {
			baseStyle.marginLeft = `${ imageGap }px`;
		}

		return baseStyle;
	};

	const imageStyle = getImageStyle();

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Layout Settings', 'layoutbridge-block-migration' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Layout', 'layoutbridge-block-migration' ) }
						value={ layout }
						options={ [
							{
								label: __(
									'Image Above',
									'layoutbridge-block-migration'
								),
								value: 'image_above',
							},
							{
								label: __(
									'Image Inline',
									'layoutbridge-block-migration'
								),
								value: 'image_inline',
							},
							{
								label: __(
									'Image Stacked',
									'layoutbridge-block-migration'
								),
								value: 'image_stacked',
							},
							{
								label: __(
									'Image Left',
									'layoutbridge-block-migration'
								),
								value: 'image_left',
							},
							{
								label: __(
									'Image Right',
									'layoutbridge-block-migration'
								),
								value: 'image_right',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
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
						onChange={ ( value ) =>
							setAttributes( { alignment: value } )
						}
					/>
					<RangeControl
						label={ __(
							'Slides Per View',
							'layoutbridge-block-migration'
						) }
						value={ slidesPerView }
						onChange={ ( value ) =>
							setAttributes( { slidesPerView: value } )
						}
						min={ 1 }
						max={ 6 }
					/>
					<RangeControl
						label={ __(
							'Slides To Scroll',
							'layoutbridge-block-migration'
						) }
						value={ slidesToScroll }
						onChange={ ( value ) =>
							setAttributes( { slidesToScroll: value } )
						}
						min={ 1 }
						max={ 6 }
					/>
					<RangeControl
						label={ __( 'Width (%)', 'layoutbridge-block-migration' ) }
						value={ width }
						onChange={ ( value ) =>
							setAttributes( { width: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
					<RangeControl
						label={ __( 'Space Between', 'layoutbridge-block-migration' ) }
						value={ spaceBetween }
						onChange={ ( value ) =>
							setAttributes( { spaceBetween: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Slide Style', 'layoutbridge-block-migration' ) }
					initialOpen={ false }
				>
					<BoxControl
						label={ __( 'Border Size', 'layoutbridge-block-migration' ) }
						values={ slideBorderSize }
						onChange={ ( value ) =>
							setAttributes( { slideBorderSize: value } )
						}
					/>
					<RangeControl
						label={ __( 'Border Radius', 'layoutbridge-block-migration' ) }
						value={ slideBorderRadius }
						onChange={ ( value ) =>
							setAttributes( { slideBorderRadius: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
					<BoxControl
						label={ __( 'Slide Padding', 'layoutbridge-block-migration' ) }
						values={ slidePadding }
						onChange={ ( value ) =>
							setAttributes( { slidePadding: value } )
						}
					/>
				</PanelBody>

				<PanelColorSettings
					title={ __( 'Slide Colors', 'layoutbridge-block-migration' ) }
					colorSettings={ [
						{
							value: slideBackgroundColor,
							onChange: ( value ) =>
								setAttributes( {
									slideBackgroundColor: value,
								} ),
							label: __(
								'Background Color',
								'layoutbridge-block-migration'
							),
						},
						{
							value: slideBorderColor,
							onChange: ( value ) =>
								setAttributes( { slideBorderColor: value } ),
							label: __( 'Border Color', 'layoutbridge-block-migration' ),
						},
					] }
				/>

				<PanelBody
					title={ __( 'Content Style', 'layoutbridge-block-migration' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Content Gap', 'layoutbridge-block-migration' ) }
						value={ contentGap }
						onChange={ ( value ) =>
							setAttributes( { contentGap: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
					<RangeControl
						label={ __( 'Font Size', 'layoutbridge-block-migration' ) }
						value={ contentTypography.fontSize }
						onChange={ ( value ) =>
							setAttributes( {
								contentTypography: {
									...contentTypography,
									fontSize: value,
								},
							} )
						}
						min={ 10 }
						max={ 50 }
					/>
					<SelectControl
						label={ __( 'Font Weight', 'layoutbridge-block-migration' ) }
						value={ contentTypography.fontWeight }
						options={ [
							{ label: 'Normal', value: 'normal' },
							{ label: 'Bold', value: 'bold' },
							{ label: '300', value: '300' },
							{ label: '400', value: '400' },
							{ label: '500', value: '500' },
							{ label: '600', value: '600' },
							{ label: '700', value: '700' },
						] }
						onChange={ ( value ) =>
							setAttributes( {
								contentTypography: {
									...contentTypography,
									fontWeight: value,
								},
							} )
						}
					/>
					<TextControl
						label={ __( 'Font Family', 'layoutbridge-block-migration' ) }
						value={ contentTypography.fontFamily }
						onChange={ ( value ) =>
							setAttributes( {
								contentTypography: {
									...contentTypography,
									fontFamily: value,
								},
							} )
						}
					/>
					<RangeControl
						label={ __( 'Line Height', 'layoutbridge-block-migration' ) }
						value={ contentTypography.lineHeight }
						onChange={ ( value ) =>
							setAttributes( {
								contentTypography: {
									...contentTypography,
									lineHeight: value,
								},
							} )
						}
						min={ 1 }
						max={ 60 }
						step={ 1 }
					/>
					<RangeControl
						label={ __( 'Letter Spacing', 'layoutbridge-block-migration' ) }
						value={ contentTypography.letterSpacing }
						onChange={ ( value ) =>
							setAttributes( {
								contentTypography: {
									...contentTypography,
									letterSpacing: value,
								},
							} )
						}
						min={ -5 }
						max={ 10 }
						step={ 0.1 }
					/>
					<RangeControl
						label={ __( 'Word Spacing', 'layoutbridge-block-migration' ) }
						value={ contentTypography.wordSpacing }
						onChange={ ( value ) =>
							setAttributes( {
								contentTypography: {
									...contentTypography,
									wordSpacing: value,
								},
							} )
						}
						min={ 0 }
						max={ 20 }
						step={ 1 }
					/>
				</PanelBody>

				<PanelColorSettings
					title={ __( 'Content Colors', 'layoutbridge-block-migration' ) }
					colorSettings={ [
						{
							value: contentColor,
							onChange: ( value ) =>
								setAttributes( { contentColor: value } ),
							label: __(
								'Content Color',
								'layoutbridge-block-migration'
							),
						},
						{
							value: nameColor,
							onChange: ( value ) =>
								setAttributes( { nameColor: value } ),
							label: __( 'Name Color', 'layoutbridge-block-migration' ),
						},
						{
							value: titleColor,
							onChange: ( value ) =>
								setAttributes( { titleColor: value } ),
							label: __( 'Title Color', 'layoutbridge-block-migration' ),
						},
					] }
				/>

				<PanelBody
					title={ __( 'Image Settings', 'layoutbridge-block-migration' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Image Size', 'layoutbridge-block-migration' ) }
						value={ imageSize }
						onChange={ ( value ) =>
							setAttributes( { imageSize: value } )
						}
						min={ 20 }
						max={ 200 }
					/>
					<RangeControl
						label={ __( 'Image Gap', 'layoutbridge-block-migration' ) }
						value={ imageGap }
						onChange={ ( value ) =>
							setAttributes( { imageGap: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
					<RangeControl
						label={ __(
							'Image Border Radius (%)',
							'layoutbridge-block-migration'
						) }
						value={ imageBorderRadius }
						onChange={ ( value ) =>
							setAttributes( { imageBorderRadius: value } )
						}
						min={ 0 }
						max={ 50 }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Navigation', 'layoutbridge-block-migration' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Arrows Size', 'layoutbridge-block-migration' ) }
						value={ arrowsSize }
						onChange={ ( value ) =>
							setAttributes( { arrowsSize: value } )
						}
						min={ 10 }
						max={ 50 }
					/>
					<RangeControl
						label={ __( 'Pagination Gap', 'layoutbridge-block-migration' ) }
						value={ paginationGap }
						onChange={ ( value ) =>
							setAttributes( { paginationGap: value } )
						}
						min={ 0 }
						max={ 50 }
					/>
					<RangeControl
						label={ __(
							'Pagination Size',
							'layoutbridge-block-migration'
						) }
						value={ paginationSize }
						onChange={ ( value ) =>
							setAttributes( { paginationSize: value } )
						}
						min={ 5 }
						max={ 30 }
					/>
				</PanelBody>

				<PanelColorSettings
					title={ __( 'Navigation Colors', 'layoutbridge-block-migration' ) }
					colorSettings={ [
						{
							value: arrowsColor,
							onChange: ( value ) =>
								setAttributes( { arrowsColor: value } ),
							label: __( 'Arrows Color', 'layoutbridge-block-migration' ),
						},
						{
							value: paginationColorInactive,
							onChange: ( value ) =>
								setAttributes( {
									paginationColorInactive: value,
								} ),
							label: __(
								'Pagination Color',
								'layoutbridge-block-migration'
							),
						},
					] }
				/>

				<PanelBody
					title={ __( 'Spacing', 'layoutbridge-block-migration' ) }
					initialOpen={ false }
				>
					<BoxControl
						label={ __( 'Margin', 'layoutbridge-block-migration' ) }
						values={ _margin }
						onChange={ ( value ) =>
							setAttributes( { _margin: value } )
						}
					/>
					<BoxControl
						label={ __( 'Padding', 'layoutbridge-block-migration' ) }
						values={ _padding }
						onChange={ ( value ) =>
							setAttributes( { _padding: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<InspectorAdvancedControls>
				<TextControl
					label={ __( 'Custom ID', 'layoutbridge-block-migration' ) }
					value={ customId }
					onChange={ ( value ) =>
						setAttributes( { customId: value } )
					}
				/>
				<TextControl
					label={ __( 'Custom Class', 'layoutbridge-block-migration' ) }
					value={ customClass }
					onChange={ ( value ) =>
						setAttributes( { customClass: value } )
					}
				/>
			</InspectorAdvancedControls>

			<div { ...blockProps }>
				<div className="blockshift-testimonials-editor">
					<div className="testimonial-slides">
						{ slides.map( ( slide, index ) => {
							const renderSlideImage = () => {
								if ( ! slide.imageUrl ) {
									return null;
								}
								return (
									<img
										src={ slide.imageUrl }
										alt={ slide.name }
										style={ imageStyle }
									/>
								);
							};

							const renderSlideContent = () => (
								<div
									className="testimonial-content"
									style={ contentStyle }
								>
									{ slide.content }
								</div>
							);

							const renderSlideNameTitle = () => (
								<>
									<div
										className="testimonial-name"
										style={ { color: nameColor } }
									>
										{ slide.name }
									</div>
									<div
										className="testimonial-title"
										style={ { color: titleColor } }
									>
										{ slide.title }
									</div>
								</>
							);

							const getSlideLayout = () => {
								if ( layout === 'image_above' ) {
									return (
										<>
											{ renderSlideImage() }
											{ renderSlideContent() }
											{ renderSlideNameTitle() }
										</>
									);
								} else if ( layout === 'image_inline' ) {
									return (
										<>
											{ renderSlideContent() }
											<div
												style={ {
													display: 'flex',
													alignItems: 'center',
												} }
											>
												{ renderSlideImage() }
												<div>
													{ renderSlideNameTitle() }
												</div>
											</div>
										</>
									);
								} else if ( layout === 'image_stacked' ) {
									return (
										<>
											{ renderSlideContent() }
											{ renderSlideImage() }
											{ renderSlideNameTitle() }
										</>
									);
								} else if ( layout === 'image_left' ) {
									return (
										<div style={ { display: 'flex' } }>
											{ renderSlideImage() }
											<div style={ { flex: 1 } }>
												{ renderSlideContent() }
												{ renderSlideNameTitle() }
											</div>
										</div>
									);
								} else if ( layout === 'image_right' ) {
									return (
										<div style={ { display: 'flex' } }>
											<div style={ { flex: 1 } }>
												{ renderSlideContent() }
												{ renderSlideNameTitle() }
											</div>
											{ renderSlideImage() }
										</div>
									);
								}
							};

							return (
								<div
									key={ index }
									className={ `testimonial-slide ${
										editingSlide === index ? 'editing' : ''
									}` }
									style={ slideStyle }
								>
									{ getSlideLayout() }
									<div className="slide-controls">
										<Button
											isSmall
											onClick={ () =>
												setEditingSlide( index )
											}
											variant={
												editingSlide === index
													? 'primary'
													: 'secondary'
											}
										>
											{ __(
												'Edit',
												'layoutbridge-block-migration'
											) }
										</Button>
										<Button
											isSmall
											isDestructive
											onClick={ () =>
												removeSlide( index )
											}
										>
											{ __(
												'Remove',
												'layoutbridge-block-migration'
											) }
										</Button>
									</div>
								</div>
							);
						} ) }
					</div>

					<Button isPrimary onClick={ addSlide }>
						{ __( 'Add Testimonial', 'layoutbridge-block-migration' ) }
					</Button>

					{ slides[ editingSlide ] && (
						<div className="testimonial-editor">
							<h3>
								{ __(
									'Edit Testimonial',
									'layoutbridge-block-migration'
								) }
							</h3>
							<TextareaControl
								label={ __(
									'Content',
									'layoutbridge-block-migration'
								) }
								value={ slides[ editingSlide ].content }
								onChange={ ( value ) =>
									updateSlide(
										editingSlide,
										'content',
										value
									)
								}
							/>
							<TextControl
								label={ __( 'Name', 'layoutbridge-block-migration' ) }
								value={ slides[ editingSlide ].name }
								onChange={ ( value ) =>
									updateSlide( editingSlide, 'name', value )
								}
							/>
							<TextControl
								label={ __( 'Title', 'layoutbridge-block-migration' ) }
								value={ slides[ editingSlide ].title }
								onChange={ ( value ) =>
									updateSlide( editingSlide, 'title', value )
								}
							/>
							<MediaUploadCheck>
								<MediaUpload
									onSelect={ ( media ) => {
										updateSlide(
											editingSlide,
											'imageUrl',
											media.url
										);
										updateSlide(
											editingSlide,
											'imageId',
											media.id
										);
									} }
									allowedTypes={ [ 'image' ] }
									value={ slides[ editingSlide ].imageId }
									render={ ( { open } ) => (
										<Button
											onClick={ open }
											variant="secondary"
										>
											{ slides[ editingSlide ].imageUrl
												? __(
														'Change Image',
														'layoutbridge-block-migration'
												  )
												: __(
														'Select Image',
														'layoutbridge-block-migration'
												  ) }
										</Button>
									) }
								/>
							</MediaUploadCheck>
						</div>
					) }
				</div>
			</div>
		</>
	);
};

export default Edit;
