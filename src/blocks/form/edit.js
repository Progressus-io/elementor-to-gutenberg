import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	ToggleControl,
	RangeControl,
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalBoxControl as BoxControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

const Edit = ( { attributes, setAttributes } ) => {
	const {
		formName,
		formFields,
		inputSize,
		buttonText,
		buttonAlign,
		successMessage,
		errorMessage,
		requiredFieldMessage,
		columnGap,
		rowGap,
		labelSpacing,
		labelTypography,
		buttonBackgroundColor,
		buttonTextColor,
		buttonBorderRadius,
		buttonPadding,
		_margin,
		_padding,
		customId,
		customClass,
	} = attributes;

	const [ editingField, setEditingField ] = useState( 0 );

	const blockProps = useBlockProps();

	const addField = () => {
		const newFields = [
			...formFields,
			{
				customId: `field-${ formFields.length + 1 }`,
				fieldType: 'text',
				required: false,
				fieldLabel: 'Field Label',
				placeholder: 'Placeholder',
			},
		];
		setAttributes( { formFields: newFields } );
	};

	const removeField = ( index ) => {
		const newFields = formFields.filter( ( _, i ) => i !== index );
		setAttributes( { formFields: newFields } );
		if ( editingField >= newFields.length ) {
			setEditingField( Math.max( 0, newFields.length - 1 ) );
		}
	};

	const updateField = ( index, key, value ) => {
		const newFields = [ ...formFields ];
		newFields[ index ][ key ] = value;
		setAttributes( { formFields: newFields } );
	};

	const formStyle = {
		margin: `${ _margin.top }px ${ _margin.right }px ${ _margin.bottom }px ${ _margin.left }px`,
		padding: `${ _padding.top }px ${ _padding.right }px ${ _padding.bottom }px ${ _padding.left }px`,
	};

	const buttonStyle = {
		backgroundColor: buttonBackgroundColor,
		color: buttonTextColor,
		borderRadius: `${ buttonBorderRadius.top }px ${ buttonBorderRadius.right }px ${ buttonBorderRadius.bottom }px ${ buttonBorderRadius.left }px`,
		padding: `${ buttonPadding.top }px ${ buttonPadding.right }px ${ buttonPadding.bottom }px ${ buttonPadding.left }px`,
		border: 'none',
		cursor: 'pointer',
	};

	const labelStyle = {
		fontFamily: labelTypography.fontFamily,
		fontWeight: labelTypography.fontWeight,
		letterSpacing: `${ labelTypography.letterSpacing }px`,
		wordSpacing: `${ labelTypography.wordSpacing }px`,
		marginBottom: `${ labelSpacing }px`,
		display: 'block',
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Form Settings', 'migrate-elementor-to-gutenberg' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Form Name', 'migrate-elementor-to-gutenberg' ) }
						value={ formName }
						onChange={ ( value ) =>
							setAttributes( { formName: value } )
						}
					/>
					<SelectControl
						label={ __( 'Input Size', 'migrate-elementor-to-gutenberg' ) }
						value={ inputSize }
						options={ [
							{
								label: __( 'Small', 'migrate-elementor-to-gutenberg' ),
								value: 'sm',
							},
							{
								label: __( 'Medium', 'migrate-elementor-to-gutenberg' ),
								value: 'md',
							},
							{
								label: __( 'Large', 'migrate-elementor-to-gutenberg' ),
								value: 'lg',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { inputSize: value } )
						}
					/>
					<RangeControl
						label={ __( 'Column Gap', 'migrate-elementor-to-gutenberg' ) }
						value={ columnGap }
						onChange={ ( value ) =>
							setAttributes( { columnGap: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
					<RangeControl
						label={ __( 'Row Gap', 'migrate-elementor-to-gutenberg' ) }
						value={ rowGap }
						onChange={ ( value ) =>
							setAttributes( { rowGap: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Label Style', 'migrate-elementor-to-gutenberg' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Label Spacing', 'migrate-elementor-to-gutenberg' ) }
						value={ labelSpacing }
						onChange={ ( value ) =>
							setAttributes( { labelSpacing: value } )
						}
						min={ 0 }
						max={ 50 }
					/>
					<TextControl
						label={ __( 'Font Family', 'migrate-elementor-to-gutenberg' ) }
						value={ labelTypography.fontFamily }
						onChange={ ( value ) =>
							setAttributes( {
								labelTypography: {
									...labelTypography,
									fontFamily: value,
								},
							} )
						}
					/>
					<SelectControl
						label={ __( 'Font Weight', 'migrate-elementor-to-gutenberg' ) }
						value={ labelTypography.fontWeight }
						options={ [
							{
								label: __( 'Normal', 'migrate-elementor-to-gutenberg' ),
								value: 'normal',
							},
							{
								label: __( 'Bold', 'migrate-elementor-to-gutenberg' ),
								value: 'bold',
							},
							{ label: '100', value: '100' },
							{ label: '200', value: '200' },
							{ label: '300', value: '300' },
							{ label: '400', value: '400' },
							{ label: '500', value: '500' },
							{ label: '600', value: '600' },
							{ label: '700', value: '700' },
							{ label: '800', value: '800' },
							{ label: '900', value: '900' },
						] }
						onChange={ ( value ) =>
							setAttributes( {
								labelTypography: {
									...labelTypography,
									fontWeight: value,
								},
							} )
						}
					/>
					<RangeControl
						label={ __(
							'Letter Spacing (px)',
							'migrate-elementor-to-gutenberg'
						) }
						value={ labelTypography.letterSpacing }
						onChange={ ( value ) =>
							setAttributes( {
								labelTypography: {
									...labelTypography,
									letterSpacing: value,
								},
							} )
						}
						min={ -5 }
						max={ 10 }
						step={ 0.1 }
					/>
					<RangeControl
						label={ __(
							'Word Spacing (px)',
							'migrate-elementor-to-gutenberg'
						) }
						value={ labelTypography.wordSpacing }
						onChange={ ( value ) =>
							setAttributes( {
								labelTypography: {
									...labelTypography,
									wordSpacing: value,
								},
							} )
						}
						min={ -10 }
						max={ 50 }
						step={ 0.1 }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Button Style', 'migrate-elementor-to-gutenberg' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Button Text', 'migrate-elementor-to-gutenberg' ) }
						value={ buttonText }
						onChange={ ( value ) =>
							setAttributes( { buttonText: value } )
						}
					/>
					<SelectControl
						label={ __(
							'Button Alignment',
							'migrate-elementor-to-gutenberg'
						) }
						value={ buttonAlign }
						options={ [
							{
								label: __( 'Start', 'migrate-elementor-to-gutenberg' ),
								value: 'start',
							},
							{
								label: __( 'Center', 'migrate-elementor-to-gutenberg' ),
								value: 'center',
							},
							{
								label: __( 'End', 'migrate-elementor-to-gutenberg' ),
								value: 'end',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { buttonAlign: value } )
						}
					/>
					<TextControl
						label={ __(
							'Background Color',
							'migrate-elementor-to-gutenberg'
						) }
						value={ buttonBackgroundColor }
						onChange={ ( value ) =>
							setAttributes( { buttonBackgroundColor: value } )
						}
						type="color"
					/>
					<TextControl
						label={ __( 'Text Color', 'migrate-elementor-to-gutenberg' ) }
						value={ buttonTextColor }
						onChange={ ( value ) =>
							setAttributes( { buttonTextColor: value } )
						}
						type="color"
					/>
					<BoxControl
						label={ __( 'Border Radius', 'migrate-elementor-to-gutenberg' ) }
						values={ buttonBorderRadius }
						onChange={ ( value ) =>
							setAttributes( { buttonBorderRadius: value } )
						}
					/>
					<BoxControl
						label={ __( 'Padding', 'migrate-elementor-to-gutenberg' ) }
						values={ buttonPadding }
						onChange={ ( value ) =>
							setAttributes( { buttonPadding: value } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Messages', 'migrate-elementor-to-gutenberg' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __(
							'Success Message',
							'migrate-elementor-to-gutenberg'
						) }
						value={ successMessage }
						onChange={ ( value ) =>
							setAttributes( { successMessage: value } )
						}
					/>
					<TextControl
						label={ __( 'Error Message', 'migrate-elementor-to-gutenberg' ) }
						value={ errorMessage }
						onChange={ ( value ) =>
							setAttributes( { errorMessage: value } )
						}
					/>
					<TextControl
						label={ __(
							'Required Field Message',
							'migrate-elementor-to-gutenberg'
						) }
						value={ requiredFieldMessage }
						onChange={ ( value ) =>
							setAttributes( { requiredFieldMessage: value } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Spacing', 'migrate-elementor-to-gutenberg' ) }
					initialOpen={ false }
				>
					<BoxControl
						label={ __( 'Margin', 'migrate-elementor-to-gutenberg' ) }
						values={ _margin }
						onChange={ ( value ) =>
							setAttributes( { _margin: value } )
						}
					/>
					<BoxControl
						label={ __( 'Padding', 'migrate-elementor-to-gutenberg' ) }
						values={ _padding }
						onChange={ ( value ) =>
							setAttributes( { _padding: value } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Form Fields', 'migrate-elementor-to-gutenberg' ) }
					initialOpen={ true }
				>
					<div style={ { marginBottom: '16px' } }>
						{ formFields.map( ( field, index ) => (
							<div
								key={ index }
								style={ {
									marginBottom: '12px',
									padding: '12px',
									border:
										editingField === index
											? '2px solid #007cba'
											: '1px solid #ddd',
									borderRadius: '4px',
									backgroundColor: '#fff',
								} }
							>
								<div
									style={ {
										display: 'flex',
										justifyContent: 'space-between',
										alignItems: 'center',
										marginBottom: '8px',
									} }
								>
									<strong>
										{ field.fieldLabel ||
											`Field ${ index + 1 }` }
										{ field.required && ' *' }
									</strong>
									<div
										style={ {
											display: 'flex',
											gap: '4px',
										} }
									>
										<Button
											isSmall
											onClick={ () =>
												setEditingField( index )
											}
											variant={
												editingField === index
													? 'primary'
													: 'secondary'
											}
										>
											{ __(
												'Edit',
												'migrate-elementor-to-gutenberg'
											) }
										</Button>
										<Button
											isSmall
											isDestructive
											onClick={ () =>
												removeField( index )
											}
										>
											{ __(
												'Remove',
												'migrate-elementor-to-gutenberg'
											) }
										</Button>
									</div>
								</div>
								<small style={ { color: '#666' } }>
									{ field.fieldType } - { field.customId }
								</small>
							</div>
						) ) }
					</div>
					<Button
						isPrimary
						onClick={ addField }
						style={ { width: '100%' } }
					>
						{ __( 'Add Field', 'migrate-elementor-to-gutenberg' ) }
					</Button>
				</PanelBody>

				{ formFields[ editingField ] && (
					<PanelBody
						title={ __( 'Edit Field', 'migrate-elementor-to-gutenberg' ) }
						initialOpen={ true }
					>
						<TextControl
							label={ __( 'Field ID', 'migrate-elementor-to-gutenberg' ) }
							value={ formFields[ editingField ].customId }
							onChange={ ( value ) =>
								updateField( editingField, 'customId', value )
							}
						/>
						<SelectControl
							label={ __( 'Field Type', 'migrate-elementor-to-gutenberg' ) }
							value={ formFields[ editingField ].fieldType }
							options={ [
								{
									label: __( 'Text', 'migrate-elementor-to-gutenberg' ),
									value: 'text',
								},
								{
									label: __(
										'Email',
										'migrate-elementor-to-gutenberg'
									),
									value: 'email',
								},
								{
									label: __( 'Tel', 'migrate-elementor-to-gutenberg' ),
									value: 'tel',
								},
								{
									label: __(
										'Number',
										'migrate-elementor-to-gutenberg'
									),
									value: 'number',
								},
								{
									label: __( 'URL', 'migrate-elementor-to-gutenberg' ),
									value: 'url',
								},
								{
									label: __(
										'Textarea',
										'migrate-elementor-to-gutenberg'
									),
									value: 'textarea',
								},
							] }
							onChange={ ( value ) =>
								updateField( editingField, 'fieldType', value )
							}
						/>
						<TextControl
							label={ __(
								'Field Label',
								'migrate-elementor-to-gutenberg'
							) }
							value={ formFields[ editingField ].fieldLabel }
							onChange={ ( value ) =>
								updateField( editingField, 'fieldLabel', value )
							}
						/>
						<TextControl
							label={ __(
								'Placeholder',
								'migrate-elementor-to-gutenberg'
							) }
							value={ formFields[ editingField ].placeholder }
							onChange={ ( value ) =>
								updateField(
									editingField,
									'placeholder',
									value
								)
							}
						/>
						<ToggleControl
							label={ __( 'Required', 'migrate-elementor-to-gutenberg' ) }
							checked={ formFields[ editingField ].required }
							onChange={ ( value ) =>
								updateField( editingField, 'required', value )
							}
						/>
					</PanelBody>
				) }

				<PanelBody
					title={ __( 'Advanced', 'migrate-elementor-to-gutenberg' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Custom ID', 'migrate-elementor-to-gutenberg' ) }
						value={ customId }
						onChange={ ( value ) =>
							setAttributes( { customId: value } )
						}
					/>
					<TextControl
						label={ __( 'Custom Class', 'migrate-elementor-to-gutenberg' ) }
						value={ customClass }
						onChange={ ( value ) =>
							setAttributes( { customClass: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps } style={ formStyle }>
				<form
					className="progressus-form"
					data-form-name={ formName }
					data-success-message={ successMessage }
					data-error-message={ errorMessage }
					style={ { display: 'grid', gap: `${ rowGap }px` } }
				>
					{ formFields.map( ( field, index ) => (
						<div key={ index } className="form-field">
							<label
								htmlFor={ field.customId }
								style={ labelStyle }
							>
								{ field.fieldLabel }
								{ field.required && ' *' }
							</label>
							{ field.fieldType === 'textarea' ? (
								<textarea
									id={ field.customId }
									placeholder={ field.placeholder }
									className={ `form-input size-${ inputSize }` }
									disabled
								/>
							) : (
								<input
									type={ field.fieldType }
									id={ field.customId }
									placeholder={ field.placeholder }
									className={ `form-input size-${ inputSize }` }
									disabled
								/>
							) }
						</div>
					) ) }
					<div
						className="form-button-wrapper"
						style={ {
							display: 'flex',
							justifyContent: buttonAlign,
						} }
					>
						<button
							type="button"
							className="form-submit-button"
							style={ buttonStyle }
							disabled
						>
							{ buttonText }
						</button>
					</div>
				</form>
			</div>
		</>
	);
};

export default Edit;
