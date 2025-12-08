import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
  PanelBody,
  TextControl,
  SelectControl,
  ToggleControl,
  RangeControl,
  Button,
  __experimentalBoxControl as BoxControl,
} from "@wordpress/components";
import { useState } from "@wordpress/element";

const Edit = ({ attributes, setAttributes }) => {
  const {
    formName,
    formFields,
    inputSize,
    buttonText,
    buttonAlign,
    successMessage,
    errorMessage,
    requiredFieldMessage,
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

  const [editingField, setEditingField] = useState(0);

  const blockProps = useBlockProps();

  const addField = () => {
    const newFields = [
      ...formFields,
      {
        customId: `field-${formFields.length + 1}`,
        fieldType: "text",
        required: false,
        fieldLabel: "Field Label",
        placeholder: "Placeholder",
      },
    ];
    setAttributes({ formFields: newFields });
  };

  const removeField = (index) => {
    const newFields = formFields.filter((_, i) => i !== index);
    setAttributes({ formFields: newFields });
    if (editingField >= newFields.length) {
      setEditingField(Math.max(0, newFields.length - 1));
    }
  };

  const updateField = (index, key, value) => {
    const newFields = [...formFields];
    newFields[index][key] = value;
    setAttributes({ formFields: newFields });
  };

  const formStyle = {
    margin: `${_margin.top}px ${_margin.right}px ${_margin.bottom}px ${_margin.left}px`,
    padding: `${_padding.top}px ${_padding.right}px ${_padding.bottom}px ${_padding.left}px`,
  };

  const buttonStyle = {
    backgroundColor: buttonBackgroundColor,
    color: buttonTextColor,
    borderRadius: `${buttonBorderRadius.top}px ${buttonBorderRadius.right}px ${buttonBorderRadius.bottom}px ${buttonBorderRadius.left}px`,
    padding: `${buttonPadding.top}px ${buttonPadding.right}px ${buttonPadding.bottom}px ${buttonPadding.left}px`,
    border: "none",
    cursor: "pointer",
  };

  const labelStyle = {
    fontFamily: labelTypography.fontFamily,
    fontWeight: labelTypography.fontWeight,
    letterSpacing: `${labelTypography.letterSpacing}px`,
    wordSpacing: `${labelTypography.wordSpacing}px`,
    marginBottom: `${labelSpacing}px`,
    display: "block",
  };

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__("Form Settings", "progressus-gutenberg")}
          initialOpen={true}
        >
          <TextControl
            label={__("Form Name", "progressus-gutenberg")}
            value={formName}
            onChange={(value) => setAttributes({ formName: value })}
          />
          <SelectControl
            label={__("Input Size", "progressus-gutenberg")}
            value={inputSize}
            options={[
              { label: __("Small", "progressus-gutenberg"), value: "sm" },
              { label: __("Medium", "progressus-gutenberg"), value: "md" },
              { label: __("Large", "progressus-gutenberg"), value: "lg" },
            ]}
            onChange={(value) => setAttributes({ inputSize: value })}
          />
          <RangeControl
            label={__("Column Gap", "progressus-gutenberg")}
            value={columnGap}
            onChange={(value) => setAttributes({ columnGap: value })}
            min={0}
            max={100}
          />
          <RangeControl
            label={__("Row Gap", "progressus-gutenberg")}
            value={rowGap}
            onChange={(value) => setAttributes({ rowGap: value })}
            min={0}
            max={100}
          />
        </PanelBody>

        <PanelBody
          title={__("Label Style", "progressus-gutenberg")}
          initialOpen={false}
        >
          <RangeControl
            label={__("Label Spacing", "progressus-gutenberg")}
            value={labelSpacing}
            onChange={(value) => setAttributes({ labelSpacing: value })}
            min={0}
            max={50}
          />
          <TextControl
            label={__("Font Family", "progressus-gutenberg")}
            value={labelTypography.fontFamily}
            onChange={(value) =>
              setAttributes({
                labelTypography: { ...labelTypography, fontFamily: value },
              })
            }
          />
          <SelectControl
            label={__("Font Weight", "progressus-gutenberg")}
            value={labelTypography.fontWeight}
            options={[
              { label: __("Normal", "progressus-gutenberg"), value: "normal" },
              { label: __("Bold", "progressus-gutenberg"), value: "bold" },
              { label: "100", value: "100" },
              { label: "200", value: "200" },
              { label: "300", value: "300" },
              { label: "400", value: "400" },
              { label: "500", value: "500" },
              { label: "600", value: "600" },
              { label: "700", value: "700" },
              { label: "800", value: "800" },
              { label: "900", value: "900" },
            ]}
            onChange={(value) =>
              setAttributes({
                labelTypography: { ...labelTypography, fontWeight: value },
              })
            }
          />
          <RangeControl
            label={__("Letter Spacing (px)", "progressus-gutenberg")}
            value={labelTypography.letterSpacing}
            onChange={(value) =>
              setAttributes({
                labelTypography: { ...labelTypography, letterSpacing: value },
              })
            }
            min={-5}
            max={10}
            step={0.1}
          />
          <RangeControl
            label={__("Word Spacing (px)", "progressus-gutenberg")}
            value={labelTypography.wordSpacing}
            onChange={(value) =>
              setAttributes({
                labelTypography: { ...labelTypography, wordSpacing: value },
              })
            }
            min={-10}
            max={50}
            step={0.1}
          />
        </PanelBody>

        <PanelBody
          title={__("Button Style", "progressus-gutenberg")}
          initialOpen={false}
        >
          <TextControl
            label={__("Button Text", "progressus-gutenberg")}
            value={buttonText}
            onChange={(value) => setAttributes({ buttonText: value })}
          />
          <SelectControl
            label={__("Button Alignment", "progressus-gutenberg")}
            value={buttonAlign}
            options={[
              { label: __("Start", "progressus-gutenberg"), value: "start" },
              { label: __("Center", "progressus-gutenberg"), value: "center" },
              { label: __("End", "progressus-gutenberg"), value: "end" },
            ]}
            onChange={(value) => setAttributes({ buttonAlign: value })}
          />
          <TextControl
            label={__("Background Color", "progressus-gutenberg")}
            value={buttonBackgroundColor}
            onChange={(value) =>
              setAttributes({ buttonBackgroundColor: value })
            }
            type="color"
          />
          <TextControl
            label={__("Text Color", "progressus-gutenberg")}
            value={buttonTextColor}
            onChange={(value) => setAttributes({ buttonTextColor: value })}
            type="color"
          />
          <BoxControl
            label={__("Border Radius", "progressus-gutenberg")}
            values={buttonBorderRadius}
            onChange={(value) => setAttributes({ buttonBorderRadius: value })}
          />
          <BoxControl
            label={__("Padding", "progressus-gutenberg")}
            values={buttonPadding}
            onChange={(value) => setAttributes({ buttonPadding: value })}
          />
        </PanelBody>

        <PanelBody
          title={__("Messages", "progressus-gutenberg")}
          initialOpen={false}
        >
          <TextControl
            label={__("Success Message", "progressus-gutenberg")}
            value={successMessage}
            onChange={(value) => setAttributes({ successMessage: value })}
          />
          <TextControl
            label={__("Error Message", "progressus-gutenberg")}
            value={errorMessage}
            onChange={(value) => setAttributes({ errorMessage: value })}
          />
          <TextControl
            label={__("Required Field Message", "progressus-gutenberg")}
            value={requiredFieldMessage}
            onChange={(value) => setAttributes({ requiredFieldMessage: value })}
          />
        </PanelBody>

        <PanelBody
          title={__("Spacing", "progressus-gutenberg")}
          initialOpen={false}
        >
          <BoxControl
            label={__("Margin", "progressus-gutenberg")}
            values={_margin}
            onChange={(value) => setAttributes({ _margin: value })}
          />
          <BoxControl
            label={__("Padding", "progressus-gutenberg")}
            values={_padding}
            onChange={(value) => setAttributes({ _padding: value })}
          />
        </PanelBody>

        <PanelBody
          title={__("Form Fields", "progressus-gutenberg")}
          initialOpen={true}
        >
          <div style={{ marginBottom: "16px" }}>
            {formFields.map((field, index) => (
              <div
                key={index}
                style={{
                  marginBottom: "12px",
                  padding: "12px",
                  border:
                    editingField === index
                      ? "2px solid #007cba"
                      : "1px solid #ddd",
                  borderRadius: "4px",
                  backgroundColor: "#fff",
                }}
              >
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    marginBottom: "8px",
                  }}
                >
                  <strong>
                    {field.fieldLabel || `Field ${index + 1}`}
                    {field.required && " *"}
                  </strong>
                  <div style={{ display: "flex", gap: "4px" }}>
                    <Button
                      isSmall
                      onClick={() => setEditingField(index)}
                      variant={editingField === index ? "primary" : "secondary"}
                    >
                      {__("Edit", "progressus-gutenberg")}
                    </Button>
                    <Button
                      isSmall
                      isDestructive
                      onClick={() => removeField(index)}
                    >
                      {__("Remove", "progressus-gutenberg")}
                    </Button>
                  </div>
                </div>
                <small style={{ color: "#666" }}>
                  {field.fieldType} - {field.customId}
                </small>
              </div>
            ))}
          </div>
          <Button isPrimary onClick={addField} style={{ width: "100%" }}>
            {__("Add Field", "progressus-gutenberg")}
          </Button>
        </PanelBody>

        {formFields[editingField] && (
          <PanelBody
            title={__("Edit Field", "progressus-gutenberg")}
            initialOpen={true}
          >
            <TextControl
              label={__("Field ID", "progressus-gutenberg")}
              value={formFields[editingField].customId}
              onChange={(value) => updateField(editingField, "customId", value)}
            />
            <SelectControl
              label={__("Field Type", "progressus-gutenberg")}
              value={formFields[editingField].fieldType}
              options={[
                { label: __("Text", "progressus-gutenberg"), value: "text" },
                {
                  label: __("Email", "progressus-gutenberg"),
                  value: "email",
                },
                { label: __("Tel", "progressus-gutenberg"), value: "tel" },
                {
                  label: __("Number", "progressus-gutenberg"),
                  value: "number",
                },
                { label: __("URL", "progressus-gutenberg"), value: "url" },
                {
                  label: __("Textarea", "progressus-gutenberg"),
                  value: "textarea",
                },
              ]}
              onChange={(value) =>
                updateField(editingField, "fieldType", value)
              }
            />
            <TextControl
              label={__("Field Label", "progressus-gutenberg")}
              value={formFields[editingField].fieldLabel}
              onChange={(value) =>
                updateField(editingField, "fieldLabel", value)
              }
            />
            <TextControl
              label={__("Placeholder", "progressus-gutenberg")}
              value={formFields[editingField].placeholder}
              onChange={(value) =>
                updateField(editingField, "placeholder", value)
              }
            />
            <ToggleControl
              label={__("Required", "progressus-gutenberg")}
              checked={formFields[editingField].required}
              onChange={(value) => updateField(editingField, "required", value)}
            />
          </PanelBody>
        )}

        <PanelBody
          title={__("Advanced", "progressus-gutenberg")}
          initialOpen={false}
        >
          <TextControl
            label={__("Custom ID", "progressus-gutenberg")}
            value={customId}
            onChange={(value) => setAttributes({ customId: value })}
          />
          <TextControl
            label={__("Custom Class", "progressus-gutenberg")}
            value={customClass}
            onChange={(value) => setAttributes({ customClass: value })}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps} style={formStyle}>
        <form
          className="progressus-form"
          data-form-name={formName}
          data-success-message={successMessage}
          data-error-message={errorMessage}
          style={{ display: "grid", gap: `${rowGap}px` }}
        >
          {formFields.map((field, index) => (
            <div key={index} className="form-field">
              <label htmlFor={field.customId} style={labelStyle}>
                {field.fieldLabel}
                {field.required && " *"}
              </label>
              {field.fieldType === "textarea" ? (
                <textarea
                  id={field.customId}
                  placeholder={field.placeholder}
                  className={`form-input size-${inputSize}`}
                  disabled
                />
              ) : (
                <input
                  type={field.fieldType}
                  id={field.customId}
                  placeholder={field.placeholder}
                  className={`form-input size-${inputSize}`}
                  disabled
                />
              )}
            </div>
          ))}
          <div
            className="form-button-wrapper"
            style={{ display: "flex", justifyContent: buttonAlign }}
          >
            <button
              type="button"
              className="form-submit-button"
              style={buttonStyle}
              disabled
            >
              {buttonText}
            </button>
          </div>
        </form>
      </div>
    </>
  );
};

export default Edit;
