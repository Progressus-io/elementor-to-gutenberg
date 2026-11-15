import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';

/**
 * Version 1 of the Form save output.
 *
 * It rendered every field as a plain input, laid the form out as a grid and
 * wrote empty style declarations. Keeping it lets forms converted by earlier
 * releases validate and move to the current markup on their next save.
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
		formName,
		formFields,
		inputSize,
		buttonText,
		buttonAlign,
		successMessage,
		errorMessage,
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

	const blockProps = useBlockProps.save( {
		className: customClass,
		id: customId,
	} );

	const formStyle = {
		margin: `${ _margin.top }px ${ _margin.right }px ${ _margin.bottom }px ${ _margin.left }px`,
		padding: `${ _padding.top }px ${ _padding.right }px ${ _padding.bottom }px ${ _padding.left }px`,
	};

	const buttonStyle = {
		backgroundColor: buttonBackgroundColor,
		color: buttonTextColor,
		borderRadius: `${ buttonBorderRadius.top }px ${ buttonBorderRadius.right }px ${ buttonBorderRadius.bottom }px ${ buttonBorderRadius.left }px`,
		padding: `${ buttonPadding.top }px ${ buttonPadding.right }px ${ buttonPadding.bottom }px ${ buttonPadding.left }px`,
	};

	const labelStyle = {
		fontFamily: labelTypography.fontFamily,
		fontWeight: labelTypography.fontWeight,
		letterSpacing: `${ labelTypography.letterSpacing }px`,
		wordSpacing: `${ labelTypography.wordSpacing }px`,
		marginBottom: `${ labelSpacing }px`,
	};

	return (
		<div { ...blockProps } style={ formStyle }>
			<form
				className="blockshift-form"
				data-form-name={ formName }
				data-success-message={ successMessage }
				data-error-message={ errorMessage }
				style={ { display: 'grid', gap: `${ rowGap }px` } }
			>
				{ formFields.map( ( field, index ) => (
					<div key={ index } className="form-field">
						<label htmlFor={ field.customId } style={ labelStyle }>
							{ field.fieldLabel }
							{ field.required && ' *' }
						</label>
						{ field.fieldType === 'textarea' ? (
							<textarea
								id={ field.customId }
								name={ field.customId }
								placeholder={ field.placeholder }
								required={ field.required }
								className={ `form-input size-${ inputSize }` }
							/>
						) : (
							<input
								type={ field.fieldType }
								id={ field.customId }
								name={ field.customId }
								placeholder={ field.placeholder }
								required={ field.required }
								className={ `form-input size-${ inputSize }` }
							/>
						) }
					</div>
				) ) }
				<div
					className="form-button-wrapper"
					style={ { display: 'flex', justifyContent: buttonAlign } }
				>
					<button
						type="submit"
						className="form-submit-button"
						style={ buttonStyle }
					>
						{ buttonText }
					</button>
				</div>
				<div
					className="form-message"
					style={ { display: 'none' } }
				></div>
			</form>
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
