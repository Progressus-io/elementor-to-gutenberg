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
					title={ __( 'Layout Settings', 'migrate-off-elementor' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Layout', 'migrate-off-elementor' ) }
						value={ layout }
						options={ [
							{
								label: __(
									'Image Above',
									'migrate-off-elementor'
								),
								value: 'image_above',
							},
							{
								label: __(
									'Image Inline',
									'migrate-off-elementor'
								),
								value: 'image_inline',
							},
							{
								label: __(
									'Image Stacked',
									'migrate-off-elementor'
								),
								value: 'image_stacked',
							},
							{
								label: __(
									'Image Left',
									'migrate-off-elementor'
								),
								value: 'image_left',
							},
							{
								label: __(
									'Image Right',
									'migrate-off-elementor'
								),
								value: 'image_right',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
					/>
					<SelectControl
						label={ __( 'Alignment', 'migrate-off-elementor' ) }
						value={ alignment }
						options={ [
							{
								label: __( 'Left', 'migrate-off-elementor' ),
								value: 'left',
							},
							{
								label: __( 'Center', 'migrate-off-elementor' ),
								value: 'center',
							},
							{
								label: __( 'Right', 'migrate-off-elementor' ),
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
							'migrate-off-elementor'
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
							'migrate-off-elementor'
						) }
						value={ slidesToScroll }
						onChange={ ( value ) =>
							setAttributes( { slidesToScroll: value } )
						}
						min={ 1 }
						max={ 6 }
					/>
					<RangeControl
						label={ __( 'Width (%)', 'migrate-off-elementor' ) }
						value={ width }
						onChange={ ( value ) =>
							setAttributes( { width: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
					<RangeControl
						label={ __( 'Space Between', 'migrate-off-elementor' ) }
						value={ spaceBetween }
						onChange={ ( value ) =>
							setAttributes( { spaceBetween: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Slide Style', 'migrate-off-elementor' ) }
					initialOpen={ false }
				>
					<BoxControl
						label={ __( 'Border Size', 'migrate-off-elementor' ) }
						values={ slideBorderSize }
						onChange={ ( value ) =>
							setAttributes( { slideBorderSize: value } )
						}
					/>
					<RangeControl
						label={ __( 'Border Radius', 'migrate-off-elementor' ) }
						value={ slideBorderRadius }
						onChange={ ( value ) =>
							setAttributes( { slideBorderRadius: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
					<BoxControl
						label={ __( 'Slide Padding', 'migrate-off-elementor' ) }
						values={ slidePadding }
						onChange={ ( value ) =>
							setAttributes( { slidePadding: value } )
						}
					/>
				</PanelBody>

				<PanelColorSettings
					title={ __( 'Slide Colors', 'migrate-off-elementor' ) }
					colorSettings={ [
						{
							value: slideBackgroundColor,
							onChange: ( value ) =>
								setAttributes( {
									slideBackgroundColor: value,
								} ),
							label: __(
								'Background Color',
								'migrate-off-elementor'
							),
						},
						{
							value: slideBorderColor,
							onChange: ( value ) =>
								setAttributes( { slideBorderColor: value } ),
							label: __( 'Border Color', 'migrate-off-elementor' ),
						},
					] }
				/>

				<PanelBody
					title={ __( 'Content Style', 'migrate-off-elementor' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Content Gap', 'migrate-off-elementor' ) }
						value={ contentGap }
						onChange={ ( value ) =>
							setAttributes( { contentGap: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
					<RangeControl
						label={ __( 'Font Size', 'migrate-off-elementor' ) }
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
						label={ __( 'Font Weight', 'migrate-off-elementor' ) }
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
						label={ __( 'Font Family', 'migrate-off-elementor' ) }
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
						label={ __( 'Line Height', 'migrate-off-elementor' ) }
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
						label={ __( 'Letter Spacing', 'migrate-off-elementor' ) }
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
						label={ __( 'Word Spacing', 'migrate-off-elementor' ) }
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
					title={ __( 'Content Colors', 'migrate-off-elementor' ) }
					colorSettings={ [
						{
							value: contentColor,
							onChange: ( value ) =>
								setAttributes( { contentColor: value } ),
							label: __(
								'Content Color',
								'migrate-off-elementor'
							),
						},
						{
							value: nameColor,
							onChange: ( value ) =>
								setAttributes( { nameColor: value } ),
							label: __( 'Name Color', 'migrate-off-elementor' ),
						},
						{
							value: titleColor,
							onChange: ( value ) =>
								setAttributes( { titleColor: value } ),
							label: __( 'Title Color', 'migrate-off-elementor' ),
						},
					] }
				/>

				<PanelBody
					title={ __( 'Image Settings', 'migrate-off-elementor' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Image Size', 'migrate-off-elementor' ) }
						value={ imageSize }
						onChange={ ( value ) =>
							setAttributes( { imageSize: value } )
						}
						min={ 20 }
						max={ 200 }
					/>
					<RangeControl
						label={ __( 'Image Gap', 'migrate-off-elementor' ) }
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
							'migrate-off-elementor'
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
					title={ __( 'Navigation', 'migrate-off-elementor' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Arrows Size', 'migrate-off-elementor' ) }
						value={ arrowsSize }
						onChange={ ( value ) =>
							setAttributes( { arrowsSize: value } )
						}
						min={ 10 }
						max={ 50 }
					/>
					<RangeControl
						label={ __( 'Pagination Gap', 'migrate-off-elementor' ) }
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
							'migrate-off-elementor'
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
					title={ __( 'Navigation Colors', 'migrate-off-elementor' ) }
					colorSettings={ [
						{
							value: arrowsColor,
							onChange: ( value ) =>
								setAttributes( { arrowsColor: value } ),
							label: __( 'Arrows Color', 'migrate-off-elementor' ),
						},
						{
							value: paginationColorInactive,
							onChange: ( value ) =>
								setAttributes( {
									paginationColorInactive: value,
								} ),
							label: __(
								'Pagination Color',
								'migrate-off-elementor'
							),
						},
					] }
				/>

				<PanelBody
					title={ __( 'Spacing', 'migrate-off-elementor' ) }
					initialOpen={ false }
				>
					<BoxControl
						label={ __( 'Margin', 'migrate-off-elementor' ) }
						values={ _margin }
						onChange={ ( value ) =>
							setAttributes( { _margin: value } )
						}
					/>
					<BoxControl
						label={ __( 'Padding', 'migrate-off-elementor' ) }
						values={ _padding }
						onChange={ ( value ) =>
							setAttributes( { _padding: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<InspectorAdvancedControls>
				<TextControl
					label={ __( 'Custom ID', 'migrate-off-elementor' ) }
					value={ customId }
					onChange={ ( value ) =>
						setAttributes( { customId: value } )
					}
				/>
				<TextControl
					label={ __( 'Custom Class', 'migrate-off-elementor' ) }
					value={ customClass }
					onChange={ ( value ) =>
						setAttributes( { customClass: value } )
					}
				/>
			</InspectorAdvancedControls>

			<div { ...blockProps }>
				<div className="progressus-testimonials-editor">
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
												'migrate-off-elementor'
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
												'migrate-off-elementor'
											) }
										</Button>
									</div>
								</div>
							);
						} ) }
					</div>

					<Button isPrimary onClick={ addSlide }>
						{ __( 'Add Testimonial', 'migrate-off-elementor' ) }
					</Button>

					{ slides[ editingSlide ] && (
						<div className="testimonial-editor">
							<h3>
								{ __(
									'Edit Testimonial',
									'migrate-off-elementor'
								) }
							</h3>
							<TextareaControl
								label={ __(
									'Content',
									'migrate-off-elementor'
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
								label={ __( 'Name', 'migrate-off-elementor' ) }
								value={ slides[ editingSlide ].name }
								onChange={ ( value ) =>
									updateSlide( editingSlide, 'name', value )
								}
							/>
							<TextControl
								label={ __( 'Title', 'migrate-off-elementor' ) }
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
														'migrate-off-elementor'
												  )
												: __(
														'Select Image',
														'migrate-off-elementor'
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
