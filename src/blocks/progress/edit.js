import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	TextControl,
	ToggleControl,
	ColorPicker,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const {
		title,
		percentage,
		innerText,
		barColor,
		backgroundColor,
		titleColor,
		titleSize,
		barHeight,
		alignment,
		showPercentage,
		showTitle,
		borderRadius,
		textColor,
	} = attributes;

	const blockProps = useBlockProps();

	const containerStyle = {
		textAlign: alignment,
	};

	const titleStyle = {
		color: titleColor,
		fontSize: titleSize + 'px',
		marginBottom: '10px',
	};

	const progressBarStyle = {
		height: barHeight + 'px',
		backgroundColor,
		borderRadius: borderRadius + 'px',
		position: 'relative',
		overflow: 'hidden',
	};

	const progressStyle = {
		width: percentage + '%',
		height: '100%',
		backgroundColor: barColor,
		transition: 'width 0.3s ease-in-out',
		position: 'relative',
	};

	const textStyle = {
		position: 'absolute',
		right: '10px',
		top: '50%',
		transform: 'translateY(-50%)',
		color: textColor,
		zIndex: 1,
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Progress Bar Settings',
						'blockshift'
					) }
				>
					<TextControl
						label={ __( 'Title', 'blockshift' ) }
						value={ title }
						onChange={ ( value ) =>
							setAttributes( { title: value } )
						}
					/>
					<RangeControl
						label={ __( 'Percentage', 'blockshift' ) }
						value={ percentage }
						onChange={ ( value ) =>
							setAttributes( { percentage: value } )
						}
						min={ 0 }
						max={ 100 }
					/>
					<TextControl
						label={ __( 'Inner Text', 'blockshift' ) }
						value={ innerText }
						onChange={ ( value ) =>
							setAttributes( { innerText: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Show Percentage',
							'blockshift'
						) }
						checked={ showPercentage }
						onChange={ ( value ) =>
							setAttributes( { showPercentage: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show Title', 'blockshift' ) }
						checked={ showTitle }
						onChange={ ( value ) =>
							setAttributes( { showTitle: value } )
						}
					/>
					<RangeControl
						label={ __( 'Title Size', 'blockshift' ) }
						value={ titleSize }
						onChange={ ( value ) =>
							setAttributes( { titleSize: value } )
						}
						min={ 10 }
						max={ 50 }
					/>
					<RangeControl
						label={ __( 'Bar Height', 'blockshift' ) }
						value={ barHeight }
						onChange={ ( value ) =>
							setAttributes( { barHeight: value } )
						}
						min={ 1 }
						max={ 50 }
					/>
					<RangeControl
						label={ __( 'Border Radius', 'blockshift' ) }
						value={ borderRadius }
						onChange={ ( value ) =>
							setAttributes( { borderRadius: value } )
						}
						min={ 0 }
						max={ 50 }
					/>
					<div>
						{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
						<label>
							{ __(
								'Progress Text Color',
								'blockshift'
							) }
						</label>
						<ColorPicker
							color={ textColor }
							onChange={ ( value ) =>
								setAttributes( { textColor: value } )
							}
							enableAlpha
						/>
					</div>
					<RangeControl
						label={ __( 'Bar Color', 'blockshift' ) }
						value={ barColor }
						onChange={ ( value ) =>
							setAttributes( { barColor: value } )
						}
						enableAlpha
					/>
					<div>
						{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
						<label>
							{ __( 'Bar Color', 'blockshift' ) }
						</label>
						<ColorPicker
							color={ barColor }
							onChange={ ( value ) =>
								setAttributes( { barColor: value } )
							}
							enableAlpha
						/>
					</div>
					<div>
						{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
						<label>
							{ __( 'Background Color', 'blockshift' ) }
						</label>
						<ColorPicker
							color={ backgroundColor }
							onChange={ ( value ) =>
								setAttributes( { backgroundColor: value } )
							}
							enableAlpha
						/>
					</div>
					<div>
						{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
						<label>
							{ __( 'Title Color', 'blockshift' ) }
						</label>
						<ColorPicker
							color={ titleColor }
							onChange={ ( value ) =>
								setAttributes( { titleColor: value } )
							}
							enableAlpha
						/>
					</div>
				</PanelBody>
			</InspectorControls>

			<div className="blockshift-progress-bar" style={ containerStyle }>
				{ showTitle && <h4 style={ titleStyle }>{ title }</h4> }
				<div
					className="blockshift-progress-bar-container"
					style={ progressBarStyle }
				>
					<div
						className="blockshift-progress-bar-fill"
						style={ progressStyle }
					>
						<div style={ textStyle }>
							{ innerText }
							{ showPercentage && (
								<span className="blockshift-progress-percentage">
									{ percentage }%
								</span>
							) }
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}
