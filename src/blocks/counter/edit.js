import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	TextControl,
	SelectControl,
	ColorPicker,
} from '@wordpress/components';

const Edit = ( { attributes, setAttributes } ) => {
	const {
		startValue,
		endValue,
		duration,
		prefix,
		suffix,
		title,
		titleColor,
		numberColor,
		numberSize,
		titleSize,
		alignment,
	} = attributes;

	const blockProps = useBlockProps();

	const alignmentOptions = [
		{ label: __( 'Left', 'migrate-off-elementor' ), value: 'left' },
		{ label: __( 'Center', 'migrate-off-elementor' ), value: 'center' },
		{ label: __( 'Right', 'migrate-off-elementor' ), value: 'right' },
	];

	const counterStyle = {
		textAlign: alignment,
		color: numberColor,
		fontSize: `${ numberSize }px`,
	};

	const titleStyle = {
		color: titleColor,
		fontSize: `${ titleSize }px`,
		textAlign: alignment,
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Counter Settings', 'migrate-off-elementor' ) }
					initialOpen={ true }
				>
					<RangeControl
						label={ __( 'Start Value', 'migrate-off-elementor' ) }
						value={ startValue }
						onChange={ ( value ) =>
							setAttributes( { startValue: value } )
						}
						min={ 0 }
						max={ endValue }
					/>
					<RangeControl
						label={ __( 'End Value', 'migrate-off-elementor' ) }
						value={ endValue }
						onChange={ ( value ) =>
							setAttributes( { endValue: value } )
						}
						min={ startValue }
						max={ 10000 }
					/>
					<RangeControl
						label={ __(
							'Animation Duration (ms)',
							'migrate-off-elementor'
						) }
						value={ duration }
						onChange={ ( value ) =>
							setAttributes( { duration: value } )
						}
						min={ 100 }
						max={ 5000 }
						step={ 100 }
					/>
					<TextControl
						label={ __( 'Prefix', 'migrate-off-elementor' ) }
						value={ prefix }
						onChange={ ( value ) =>
							setAttributes( { prefix: value } )
						}
					/>
					<TextControl
						label={ __( 'Suffix', 'migrate-off-elementor' ) }
						value={ suffix }
						onChange={ ( value ) =>
							setAttributes( { suffix: value } )
						}
					/>
					<TextControl
						label={ __( 'Title', 'migrate-off-elementor' ) }
						value={ title }
						onChange={ ( value ) =>
							setAttributes( { title: value } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Style Settings', 'migrate-off-elementor' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Alignment', 'migrate-off-elementor' ) }
						value={ alignment }
						options={ alignmentOptions }
						onChange={ ( value ) =>
							setAttributes( { alignment: value } )
						}
					/>
					<div className="components-base-control">
						{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
						<label className="components-base-control__label">
							{ __( 'Number Color', 'migrate-off-elementor' ) }
						</label>
						<ColorPicker
							color={ numberColor }
							onChange={ ( value ) =>
								setAttributes( { numberColor: value } )
							}
						/>
					</div>
					<RangeControl
						label={ __( 'Number Size', 'migrate-off-elementor' ) }
						value={ numberSize }
						onChange={ ( value ) =>
							setAttributes( { numberSize: value } )
						}
						min={ 10 }
						max={ 100 }
					/>
					<div className="components-base-control">
						{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
						<label className="components-base-control__label">
							{ __( 'Title Color', 'migrate-off-elementor' ) }
						</label>
						<ColorPicker
							color={ titleColor }
							onChange={ ( value ) =>
								setAttributes( { titleColor: value } )
							}
						/>
					</div>
					<RangeControl
						label={ __( 'Title Size', 'migrate-off-elementor' ) }
						value={ titleSize }
						onChange={ ( value ) =>
							setAttributes( { titleSize: value } )
						}
						min={ 10 }
						max={ 50 }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="counter-preview" style={ counterStyle }>
					<span className="prefix">{ prefix }</span>
					<span className="counter-value">{ endValue }</span>
					<span className="suffix">{ suffix }</span>
				</div>
				{ title && (
					<h4 className="counter-title" style={ titleStyle }>
						{ title }
					</h4>
				) }
			</div>
		</>
	);
};

export default Edit;
