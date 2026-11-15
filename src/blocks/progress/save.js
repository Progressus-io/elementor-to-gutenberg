import { useBlockProps } from '@wordpress/block-editor';
import { withoutEmptyValues } from '../shared/styles';

export default function save( { attributes } ) {
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

	const blockProps = useBlockProps.save();

	const containerStyle = {
		textAlign: alignment,
	};

	const titleStyle = withoutEmptyValues( {
		color: titleColor,
		fontSize: titleSize + 'px',
		marginBottom: '10px',
	} );

	const progressBarStyle = withoutEmptyValues( {
		height: barHeight + 'px',
		backgroundColor,
		borderRadius: borderRadius + 'px',
		position: 'relative',
		overflow: 'hidden',
	} );

	const progressStyle = withoutEmptyValues( {
		width: percentage + '%',
		height: '100%',
		backgroundColor: barColor,
		transition: 'width 0.3s ease-in-out',
		position: 'relative',
	} );

	const textStyle = withoutEmptyValues( {
		position: 'absolute',
		right: '10px',
		top: '50%',
		transform: 'translateY(-50%)',
		color: textColor,
		zIndex: 1,
	} );

	return (
		<div { ...blockProps }>
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
