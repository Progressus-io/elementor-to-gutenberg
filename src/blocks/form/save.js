import { useBlockProps } from '@wordpress/block-editor';
import { withoutEmptyValues } from '../shared/styles';
import { hasFormSteps, renderFields } from './fields';

const Save = ( { attributes } ) => {
	const {
		formName,
		formId,
		formFields,
		inputSize,
		buttonText,
		buttonAlign,
		successMessage,
		errorMessage,
		redirectUrl,
		nextButtonText,
		previousButtonText,
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

	const blockProps = useBlockProps.save( {
		className: customClass,
		id: customId,
	} );

	const formStyle = {
		margin: `${ _margin.top }px ${ _margin.right }px ${ _margin.bottom }px ${ _margin.left }px`,
		padding: `${ _padding.top }px ${ _padding.right }px ${ _padding.bottom }px ${ _padding.left }px`,
	};

	const buttonStyle = withoutEmptyValues( {
		backgroundColor: buttonBackgroundColor,
		color: buttonTextColor,
		borderRadius: `${ buttonBorderRadius.top }px ${ buttonBorderRadius.right }px ${ buttonBorderRadius.bottom }px ${ buttonBorderRadius.left }px`,
		padding: `${ buttonPadding.top }px ${ buttonPadding.right }px ${ buttonPadding.bottom }px ${ buttonPadding.left }px`,
	} );

	const labelStyle = withoutEmptyValues( {
		fontFamily: labelTypography.fontFamily,
		fontWeight: labelTypography.fontWeight,
		letterSpacing: `${ labelTypography.letterSpacing }px`,
		wordSpacing: `${ labelTypography.wordSpacing }px`,
		marginBottom: `${ labelSpacing }px`,
	} );

	const hasSteps = hasFormSteps( formFields );

	return (
		<div { ...blockProps } style={ formStyle }>
			<form
				className="blockshift-form"
				data-form-name={ formName }
				data-form-id={ formId || undefined }
				data-success-message={ successMessage }
				data-error-message={ errorMessage }
				data-redirect={ redirectUrl || undefined }
				data-next-text={ hasSteps ? nextButtonText : undefined }
				data-previous-text={ hasSteps ? previousButtonText : undefined }
				style={ {
					'--blockshift-form-gap': `${ columnGap }px`,
					rowGap: `${ rowGap }px`,
				} }
			>
				{ renderFields( { formFields, inputSize, labelStyle } ) }
				<div
					className="form-field form-field-honeypot"
					aria-hidden="true"
				>
					<input
						type="text"
						name="blockshift_hp"
						tabIndex={ -1 }
						autoComplete="off"
					/>
				</div>
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

export default Save;
