import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';

/**
 * Version 1 of the Progress save output.
 *
 * It wrote empty style declarations such as an empty color. Keeping it lets progress bars saved by earlier releases validate and move to the current markup on their next save.
 *
 * Do not tidy this function: it has to reproduce the old markup exactly.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 *
 * @return {Element} The v1 markup.
 */
function v1Save( { attributes } ) {
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

export default [
	{
		attributes: metadata.attributes,
		supports: metadata.supports,
		save: v1Save,
	},
];
