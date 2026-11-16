/**
 * Drop declarations whose value is empty, so a style object never serializes `color:;`.
 *
 * @param {Object} style Style object.
 * @return {Object} Style object without empty values.
 */
export const withoutEmptyValues = ( style ) =>
	Object.fromEntries(
		Object.entries( style ).filter(
			( [ , value ] ) =>
				undefined !== value && null !== value && '' !== value
		)
	);
