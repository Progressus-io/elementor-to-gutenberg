/**
 * Form submission handler with loader and AJAX
 */
document.addEventListener("DOMContentLoaded", function () {
  const forms = document.querySelectorAll(".progressus-form");

  forms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      const submitButton = form.querySelector(".form-submit-button");
      const messageContainer = form.querySelector(".form-message");
      const formData = new FormData(form);
      const formName = form.dataset.formName;
      const successMessage = form.dataset.successMessage;
      const errorMessage = form.dataset.errorMessage;

      // Add action and nonce
      formData.append("action", "progressus_form_submit");
      formData.append("nonce", progressusFormData.nonce);
      formData.append("form_name", formName);

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
				${originalButtonText}
			`;

      // Hide previous messages
      messageContainer.style.display = "none";
      messageContainer.className = "form-message";

      // Submit form via AJAX
      fetch(progressusFormData.ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          // Remove loader and restore button
          submitButton.disabled = false;
          submitButton.innerHTML = originalButtonText;

          // Show message
          messageContainer.style.display = "block";
          if (data.success) {
            messageContainer.className = "form-message success-message";
            messageContainer.textContent = data.data.message || successMessage;
            form.reset();
          } else {
            messageContainer.className = "form-message error-message";
            messageContainer.textContent = data.data.message || errorMessage;
          }

          // Auto-hide message after 5 seconds
          setTimeout(() => {
            messageContainer.style.display = "none";
          }, 5000);
        })
        .catch((error) => {
          console.error("Form submission error:", error);

          // Remove loader and restore button
          submitButton.disabled = false;
          submitButton.innerHTML = originalButtonText;

          // Show error message
          messageContainer.style.display = "block";
          messageContainer.className = "form-message error-message";
          messageContainer.textContent = errorMessage;

          setTimeout(() => {
            messageContainer.style.display = "none";
          }, 5000);
        });
    });
  });
});
