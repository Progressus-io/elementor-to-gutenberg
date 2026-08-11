import { RichText } from '@wordpress/block-editor';
import metadata from './block.json';

/**
 * Version 1 of the Icon Box save output.
 *
 * It built the icon as a string of HTML and handed it to
 * dangerouslySetInnerHTML, interpolating attribute values straight into
 * attribute positions. The current save() builds the same nodes as React
 * elements instead, which serialises slightly differently, so this deprecation
 * keeps every Icon Box already in a post validating - and migrates it silently
 * the next time that post is saved.
 *
 * Do not "tidy" this function: it has to reproduce the old markup exactly.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 *
 * @return {Element} The v1 markup.
 */
const v1Save = ( { attributes } ) => {
	const {
		icon,
		iconStyle,
		svgUrl,
		svgStyle,
		size,
		title,
		description,
		titleSize,
		titleColor,
		descriptionSize,
		descriptionColor,
		alignment,
		className,
		anchor,
	} = attributes;

	const wrapperClasses = arrayUnique( [
		'wp-block-icon-box',
		className,
	] ).join( ' ' );

	const iconHtml = svgUrl
		? `<img src="${ svgUrl }" alt="" style="${
				svgStyle ? svgStyle : `width:${ size }px;height:auto;`
		  }" class="svg-icon" />`
		: `<i class="${ iconStyle } ${ icon }" style="font-size:${ size }px;"></i>`;

	return (
		<div
			className={ wrapperClasses }
			style={ { textAlign: alignment } }
			id={ anchor || undefined }
		>
			<div
				className="icon-box-icon"
				dangerouslySetInnerHTML={ { __html: iconHtml } }
			/>
			{ title && (
				<RichText.Content
					tagName="h3"
					className="icon-box-title"
					value={ title }
					style={ {
						fontSize: `${ titleSize }px`,
						color: titleColor,
					} }
				/>
			) }
			{ description && (
				<RichText.Content
					tagName="div"
					className="icon-box-description"
					value={ description }
					style={ {
						fontSize: `${ descriptionSize }px`,
						color: descriptionColor,
					} }
				/>
			) }
		</div>
	);
};

function arrayUnique( arr ) {
	return Array.from( new Set( ( arr || [] ).filter( Boolean ) ) );
}

export default [
	{
		attributes: metadata.attributes,
		supports: metadata.supports,
		save: v1Save,
	},
];
