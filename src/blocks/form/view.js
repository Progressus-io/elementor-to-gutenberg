/* global blockshiftFormData */
/**
 * Form submission handler with loader and AJAX
 */
document.addEventListener( 'DOMContentLoaded', function () {
	const forms = document.querySelectorAll( '.blockshift-form' );

	forms.forEach( ( form ) => {
		setUpSteps( form );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			const submitButton = form.querySelector( '.form-submit-button' );
			const messageContainer = form.querySelector( '.form-message' );
			const formData = new FormData( form );
			const formName = form.dataset.formName;
			const successMessage = form.dataset.successMessage;
			const errorMessage = form.dataset.errorMessage;

			// Add action and nonce
			formData.append( 'action', 'blockshift_form_submit' );
			formData.append( 'nonce', blockshiftFormData.nonce );
			formData.append( 'form_name', formName );
			formData.append( 'form_id', form.dataset.formId || '' );
			formData.append( 'page_url', window.location.href );

			// Save original button text
			const originalButtonText = submitButton.innerHTML;

			// Add loader to button
			submitButton.disabled = true;
			submitButton.innerHTML = `
				<svg class="form-loader" width="20" height="20" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg">
					<circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-dasharray="31.4 31.4" stroke-linecap="round">
						<animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/>
					</circle>
				</svg>
				${ originalButtonText }
			`;

			// Hide previous messages
			messageContainer.style.display = 'none';
			messageContainer.className = 'form-message';

			// Submit form via AJAX
			fetch( blockshiftFormData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} )
				.then( ( response ) => response.json() )
				.then( ( data ) => {
					const result = data.data || {};

					// Remove loader and restore button
					submitButton.disabled = false;
					submitButton.innerHTML = originalButtonText;

					// Show message
					messageContainer.style.display = 'block';
					if ( data.success ) {
						messageContainer.className =
							'form-message success-message';
						messageContainer.textContent =
							result.message || successMessage;
						form.reset();

						if ( result.redirect ) {
							window.location.href = result.redirect;
							return;
						}
					} else {
						messageContainer.className =
							'form-message error-message';
						messageContainer.textContent =
							result.message || errorMessage;
					}

					// Auto-hide message after 5 seconds
					setTimeout( () => {
						messageContainer.style.display = 'none';
					}, 5000 );
				} )
				.catch( ( error ) => {
					// eslint-disable-next-line no-console
					console.error( 'Form submission error:', error );

					// Remove loader and restore button
					submitButton.disabled = false;
					submitButton.innerHTML = originalButtonText;

					// Show error message
					messageContainer.style.display = 'block';
					messageContainer.className = 'form-message error-message';
					messageContainer.textContent = errorMessage;

					setTimeout( () => {
						messageContainer.style.display = 'none';
					}, 5000 );
				} );
		} );
	} );

	/**
	 * Show a multi-step form one step at a time, with previous and next buttons.
	 *
	 * @param {HTMLFormElement} form The form element.
	 */
	function setUpSteps( form ) {
		const steps = Array.from(
			form.querySelectorAll( ':scope > .form-step' )
		);
		if ( steps.length < 2 ) {
			return;
		}

		const submitWrapper = form.querySelector( '.form-button-wrapper' );
		const nav = document.createElement( 'div' );
		const previous = document.createElement( 'button' );
		const next = document.createElement( 'button' );
		let current = 0;

		nav.className = 'form-step-nav';
		previous.type = 'button';
		previous.className = 'form-step-previous';
		previous.textContent = form.dataset.previousText || 'Previous';
		next.type = 'button';
		next.className = 'form-step-next';
		next.textContent = form.dataset.nextText || 'Next';
		nav.append( previous, next );
		form.insertBefore( nav, submitWrapper );
		form.classList.add( 'has-steps' );

		const show = ( index ) => {
			current = index;
			steps.forEach( ( step, i ) =>
				step.classList.toggle( 'is-active', i === index )
			);
			previous.hidden = 0 === index;
			next.hidden = index === steps.length - 1;
			submitWrapper.style.display =
				index === steps.length - 1 ? 'flex' : 'none';
		};

		previous.addEventListener( 'click', () => show( current - 1 ) );
		next.addEventListener( 'click', () => {
			const invalid = Array.from(
				steps[ current ].querySelectorAll( 'input, select, textarea' )
			).find( ( control ) => ! control.checkValidity() );

			if ( invalid ) {
				invalid.reportValidity();
				return;
			}

			show( current + 1 );
		} );
		form.addEventListener( 'reset', () => show( 0 ) );

		show( 0 );
	}
} );
