const GROUP_TYPES = [ 'radio', 'checkbox' ];

/**
 * Turn a stored field width into the CSS width of the field.
 *
 * @param {number|string} width Width in percent.
 * @return {string|undefined} CSS width, or undefined for a full-width field.
 */
const getFieldWidth = ( width ) => {
	const value = parseFloat( width );
	if ( ! value || value >= 100 ) {
		return undefined;
	}
	if ( 33 === value ) {
		return '33.333%';
	}
	if ( 66 === value ) {
		return '66.666%';
	}

	return `${ value }%`;
};

/**
 * Render the control of one field.
 *
 * @param {Object}  field     Field definition.
 * @param {string}  inputSize Input size slug.
 * @param {boolean} preview   Whether the control is an editor preview.
 * @return {Element} The control.
 */
const renderControl = ( field, inputSize, preview ) => {
	const id = field.customId;
	const className = `form-input size-${ inputSize }`;
	const options = Array.isArray( field.options ) ? field.options : [];
	const disabled = preview || undefined;

	if ( 'textarea' === field.fieldType ) {
		return (
			<textarea
				id={ id }
				name={ id }
				placeholder={ field.placeholder }
				required={ field.required }
				className={ className }
				rows={ field.rows || undefined }
				disabled={ disabled }
			/>
		);
	}

	if ( 'select' === field.fieldType ) {
		return (
			<select
				id={ id }
				name={ field.multiple ? `${ id }[]` : id }
				required={ field.required }
				multiple={ field.multiple || undefined }
				className={ className }
				disabled={ disabled }
			>
				{ options.map( ( option, index ) => (
					<option key={ index } value={ option.value }>
						{ option.label }
					</option>
				) ) }
			</select>
		);
	}

	if ( GROUP_TYPES.includes( field.fieldType ) ) {
		const isRadio = 'radio' === field.fieldType;

		return (
			<div
				className={ `form-options${
					field.inline ? ' is-inline' : ''
				}` }
			>
				{ options.map( ( option, index ) => (
					<label key={ index } className="form-option">
						<input
							type={ field.fieldType }
							name={ isRadio ? id : `${ id }[]` }
							value={ option.value }
							required={ ( isRadio && field.required ) || undefined }
							disabled={ disabled }
						/>
						<span>{ option.label }</span>
					</label>
				) ) }
			</div>
		);
	}

	if ( 'acceptance' === field.fieldType ) {
		return (
			<div className="form-options">
				<label className="form-option">
					<input
						type="checkbox"
						id={ id }
						name={ id }
						value="on"
						required={ field.required || undefined }
						disabled={ disabled }
					/>
					<span
						dangerouslySetInnerHTML={ {
							__html: field.acceptanceText || '',
						} }
					/>
				</label>
			</div>
		);
	}

	return (
		<input
			type={ field.fieldType }
			id={ id }
			name={ id }
			placeholder={ field.placeholder }
			required={ field.required }
			className={ className }
			value={ field.value || undefined }
			disabled={ disabled }
		/>
	);
};

/**
 * Render one field with its label and wrapper.
 *
 * @param {Object}  field      Field definition.
 * @param {number}  index      Field position.
 * @param {string}  inputSize  Input size slug.
 * @param {Object}  labelStyle Label style object.
 * @param {boolean} preview    Whether the field is an editor preview.
 * @return {Element|null} The field, or null for a step marker.
 */
const renderField = ( field, index, inputSize, labelStyle, preview ) => {
	const id = field.customId;

	if ( 'step' === field.fieldType ) {
		return null;
	}

	if ( 'hidden' === field.fieldType ) {
		if ( preview ) {
			return null;
		}

		return (
			<input
				key={ index }
				type="hidden"
				name={ id }
				value={ field.value || '' }
			/>
		);
	}

	if ( 'honeypot' === field.fieldType ) {
		if ( preview ) {
			return null;
		}

		return (
			<div
				key={ index }
				className="form-field form-field-honeypot"
				aria-hidden="true"
			>
				<input
					type="text"
					name={ id }
					tabIndex={ -1 }
					autoComplete="off"
				/>
			</div>
		);
	}

	const width = getFieldWidth( field.width );
	const style = width ? { '--blockshift-field-width': width } : undefined;

	if ( 'html' === field.fieldType ) {
		return (
			<div
				key={ index }
				className="form-field form-field-html"
				style={ style }
				dangerouslySetInnerHTML={ { __html: field.html || '' } }
			/>
		);
	}

	const isGroup = GROUP_TYPES.includes( field.fieldType );
	const marker = field.required && ' *';
	let label = null;

	if ( isGroup ) {
		label = (
			<span className="form-label" style={ labelStyle }>
				{ field.fieldLabel }
				{ marker }
			</span>
		);
	} else if ( 'acceptance' !== field.fieldType ) {
		label = (
			<label htmlFor={ id } style={ labelStyle }>
				{ field.fieldLabel }
				{ marker }
			</label>
		);
	}

	return (
		<div
			key={ index }
			className={ `form-field form-field-${ field.fieldType }` }
			style={ style }
		>
			{ label }
			{ renderControl( field, inputSize, preview ) }
		</div>
	);
};

/**
 * Render every field, grouped into steps when the form declares any.
 *
 * @param {Object}  props            Render options.
 * @param {Array}   props.formFields Field definitions.
 * @param {string}  props.inputSize  Input size slug.
 * @param {Object}  props.labelStyle Label style object.
 * @param {boolean} props.preview    Whether the fields are an editor preview.
 * @return {Array} Fields or step groups.
 */
export const renderFields = ( {
	formFields,
	inputSize,
	labelStyle,
	preview = false,
} ) => {
	if ( ! hasFormSteps( formFields ) ) {
		return formFields.map( ( field, index ) =>
			renderField( field, index, inputSize, labelStyle, preview )
		);
	}

	const steps = [];
	formFields.forEach( ( field, index ) => {
		if ( 'step' === field.fieldType || 0 === steps.length ) {
			steps.push( {
				label: 'step' === field.fieldType ? field.fieldLabel : '',
				fields: [],
			} );
		}
		if ( 'step' !== field.fieldType ) {
			steps[ steps.length - 1 ].fields.push(
				renderField( field, index, inputSize, labelStyle, preview )
			);
		}
	} );

	return steps.map( ( step, index ) => (
		<div
			key={ `step-${ index }` }
			className="form-step"
			data-step-label={ step.label }
		>
			{ step.fields }
		</div>
	) );
};

/**
 * Whether the form is split into steps.
 *
 * @param {Array} formFields Field definitions.
 * @return {boolean} True when at least one step marker exists.
 */
export const hasFormSteps = ( formFields ) =>
	formFields.some( ( field ) => 'step' === field.fieldType );
