import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';

/**
 * Version 1 of the Counter save output.
 *
 * It wrote empty style declarations such as an empty color. Keeping it lets counters saved by earlier releases validate and move to the current markup on their next save.
 *
 * Do not tidy this function: it has to reproduce the old markup exactly.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 *
 * @return {Element} The v1 markup.
 */
const v1Save = ( { attributes } ) => {
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

	const blockProps = useBlockProps.save();

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
		<div { ...blockProps }>
			<div
				className="counter-preview"
				style={ counterStyle }
				data-start={ startValue }
				data-end={ endValue }
				data-duration={ duration }
			>
				<span className="prefix">{ prefix }</span>
				<span className="counter-value">{ startValue }</span>
				<span className="suffix">{ suffix }</span>
			</div>
			{ title && (
				<h4 className="counter-title" style={ titleStyle }>
					{ title }
				</h4>
			) }
		</div>
	);
};

export default [
	{
		attributes: metadata.attributes,
		supports: metadata.supports,
		save: v1Save,
	},
];
