import { __ } from "@wordpress/i18n";
import { useState } from "@wordpress/element";
import {
  useBlockProps,
  InspectorControls,
  RichText,
  MediaUpload,
  MediaUploadCheck,
  BlockControls,
  BlockAlignmentToolbar,
} from "@wordpress/block-editor";
import {
  PanelBody,
  Button,
  RangeControl,
  Popover,
  ColorPicker,
  TextControl,
  ToggleControl,
  SelectControl,
} from "@wordpress/components";
export default function Edit({ attributes, setAttributes }) {
  const {
    imageUrl = "",
    imageId = 0,
    imageAlt = "",
    imageWidth = 100,
    imageHeight = 100,
    objectFit = "cover",
    objectPosition = "center center",
    align = "left",
    title = "",
    description = "",
    titleSize = 20,
    titleColor = "#000000",
    titleFontFamily = "",
    titleFontWeight = "",
    titleTextTransform = "",
    titleFontStyle = "",
    titleTextDecoration = "",
    titleLineHeight = "",
    titleLetterSpacing = "",
    titleWordSpacing = "",
    descriptionSize = 14,
    descriptionColor = "#666666",
    descriptionFontFamily = "",
    descriptionFontWeight = "",
    descriptionTextTransform = "",
    descriptionFontStyle = "",
    descriptionTextDecoration = "",
    descriptionLineHeight = "",
    descriptionLetterSpacing = "",
    descriptionWordSpacing = "",
    link = "",
    linkTarget = false,
    nofollow = false,
  } = attributes;

  const [showTitleColor, setShowTitleColor] = useState(false);
  const [showDescColor, setShowDescColor] = useState(false);

  // Get block props with alignment support
  const blockProps = useBlockProps({
    style: {
      textAlign: align,
    },
  });

  // Add align class if set
  if (align && blockProps.className) {
    blockProps.className += ` align${align}`;
  } else if (align) {
    blockProps.className = `align${align}`;
  }

  const onSelectImage = (media) => {
    setAttributes({
      imageUrl: media.url,
      imageId: media.id,
      imageAlt: media.alt || "",
    });
  };

  const onRemoveImage = () => {
    setAttributes({
      imageUrl: "",
      imageId: 0,
      imageAlt: "",
    });
  };

  return (
    <>
      <BlockControls>
        <BlockAlignmentToolbar
          value={align}
          onChange={(value) => setAttributes({ align: value })}
        />
      </BlockControls>

      <InspectorControls>
        <PanelBody
          title={__("Block Settings", "image-box-block")}
          initialOpen={true}
        >
          <SelectControl
            label={__("Block Alignment", "image-box-block")}
            value={align || ""}
            onChange={(value) => setAttributes({ align: value || left })}
            options={[
              { label: "None", value: "" },
              { label: "Left", value: "left" },
              { label: "Center", value: "center" },
              { label: "Right", value: "right" },
              { label: "Wide Width", value: "wide" },
              { label: "Full Width", value: "full" },
            ]}
            help={__(
              "Controls where the entire block is positioned on the page",
              "image-box-block"
            )}
          />
        </PanelBody>

        <PanelBody
          title={__("Image Settings", "image-box-block")}
          initialOpen={true}
        >
          <div className="image-box-image-upload">
            {imageUrl ? (
              <div className="image-preview" style={{ marginBottom: "12px" }}>
                <img
                  src={imageUrl}
                  alt={imageAlt}
                  style={{
                    width: "100%",
                    height: "auto",
                    maxWidth: "200px",
                    display: "block",
                  }}
                />
              </div>
            ) : (
              <div
                style={{
                  marginBottom: "12px",
                  padding: "20px",
                  border: "2px dashed #ccc",
                  textAlign: "center",
                  color: "#999",
                }}
              >
                {__("No image selected", "image-box-block")}
              </div>
            )}

            <MediaUploadCheck>
              <MediaUpload
                onSelect={onSelectImage}
                allowedTypes={["image"]}
                value={imageId}
                render={({ open }) => (
                  <Button variant="secondary" onClick={open}>
                    {imageUrl
                      ? __("Replace Image", "image-box-block")
                      : __("Upload Image", "image-box-block")}
                  </Button>
                )}
              />
            </MediaUploadCheck>

            {imageUrl && (
              <Button
                variant="link"
                isDestructive
                onClick={onRemoveImage}
                style={{ marginTop: "8px" }}
              >
                {__("Remove Image", "image-box-block")}
              </Button>
            )}

            <TextControl
              label={__("Image Alt Text", "image-box-block")}
              value={imageAlt}
              onChange={(value) => setAttributes({ imageAlt: value })}
              help={__("Alternative text for accessibility", "image-box-block")}
            />

            <RangeControl
              label={__("Image Width (px)", "image-box-block")}
              value={imageWidth}
              onChange={(value) => setAttributes({ imageWidth: value })}
              min={50}
              max={500}
              step={10}
            />

            <RangeControl
              label={__("Image Height (px)", "image-box-block")}
              value={imageHeight}
              onChange={(value) => setAttributes({ imageHeight: value })}
              min={50}
              max={500}
              step={10}
            />

            <SelectControl
              label={__("Object Fit", "image-box-block")}
              value={objectFit}
              onChange={(v) => setAttributes({ objectFit: v })}
              options={[
                { label: "Cover", value: "cover" },
                { label: "Contain", value: "contain" },
                { label: "Fill", value: "fill" },
                { label: "None", value: "none" },
                { label: "Scale-Down", value: "scale-down" },
              ]}
            />

            <TextControl
              label={__("Object Position", "image-box-block")}
              value={objectPosition}
              onChange={(v) => setAttributes({ objectPosition: v })}
              help={__(
                "CSS object-position value, e.g. 'center center' or 'left top'",
                "image-box-block"
              )}
            />
          </div>
        </PanelBody>

        <PanelBody title={__("Link", "image-box-block")} initialOpen={false}>
          <TextControl
            label={__("URL", "image-box-block")}
            value={link}
            onChange={(v) => setAttributes({ link: v })}
            placeholder={__("https://example.com", "image-box-block")}
          />
          <ToggleControl
            label={__("Open in new tab", "image-box-block")}
            checked={!!linkTarget}
            onChange={(v) => setAttributes({ linkTarget: !!v })}
          />
          <ToggleControl
            label={__("Add rel=nofollow", "image-box-block")}
            checked={!!nofollow}
            onChange={(v) => setAttributes({ nofollow: !!v })}
          />
        </PanelBody>

        <PanelBody
          title={__("Title Styles", "image-box-block")}
          initialOpen={false}
        >
          <RangeControl
            label={__("Title Size", "image-box-block")}
            value={titleSize}
            onChange={(v) => setAttributes({ titleSize: v })}
            min={8}
            max={72}
          />
          <div className="components-base-control">
            <div className="components-base-control__label">
              {__("Title Color", "image-box-block")}
            </div>
            <Button
              className="image-box-color-button"
              onClick={() => setShowTitleColor(!showTitleColor)}
              style={{
                backgroundColor: titleColor,
                width: "100%",
                justifyContent: "center",
              }}
            >
              {titleColor || __("Choose Color", "image-box-block")}
            </Button>
            {showTitleColor && (
              <Popover
                position="bottom left"
                onClose={() => setShowTitleColor(false)}
              >
                <ColorPicker
                  color={titleColor}
                  onChange={(value) => setAttributes({ titleColor: value })}
                  enableAlpha
                />
              </Popover>
            )}
          </div>
          <SelectControl
            label={__("Font Family", "image-box-block")}
            value={titleFontFamily}
            onChange={(v) => setAttributes({ titleFontFamily: v })}
            options={[
              { label: "Default", value: "" },
              { label: "Arial", value: "Arial, sans-serif" },
              { label: "Georgia", value: "Georgia, serif" },
              { label: "Times New Roman", value: "Times New Roman, serif" },
              { label: "Helvetica", value: "Helvetica, Arial, sans-serif" },
              { label: "Verdana", value: "Verdana, sans-serif" },
              { label: "Courier New", value: "Courier New, monospace" },
              { label: "Impact", value: "Impact, sans-serif" },
              { label: "Comic Sans MS", value: "Comic Sans MS, cursive" },
              { label: "Trebuchet MS", value: "Trebuchet MS, sans-serif" },
              { label: "Roboto", value: "Roboto, sans-serif" },
              { label: "Roboto Flex", value: "Roboto Flex, sans-serif" },
              { label: "Open Sans", value: "Open Sans, sans-serif" },
              { label: "Lato", value: "Lato, sans-serif" },
              { label: "Montserrat", value: "Montserrat, sans-serif" },
            ]}
          />
          <SelectControl
            label={__("Font Weight", "image-box-block")}
            value={titleFontWeight}
            onChange={(v) => setAttributes({ titleFontWeight: v })}
            options={[
              { label: "", value: "" },
              { label: "100", value: "100" },
              { label: "200", value: "200" },
              { label: "300", value: "300" },
              { label: "400 (normal)", value: "400" },
              { label: "500", value: "500" },
              { label: "600", value: "600" },
              { label: "700 (bold)", value: "700" },
              { label: "800", value: "800" },
              { label: "900", value: "900" },
            ]}
          />
          <SelectControl
            label={__("Text Transform", "image-box-block")}
            value={titleTextTransform}
            onChange={(v) => setAttributes({ titleTextTransform: v })}
            options={[
              { label: "", value: "" },
              { label: "None", value: "none" },
              { label: "Uppercase", value: "uppercase" },
              { label: "Lowercase", value: "lowercase" },
              { label: "Capitalize", value: "capitalize" },
            ]}
          />
          <SelectControl
            label={__("Font Style", "image-box-block")}
            value={titleFontStyle}
            onChange={(v) => setAttributes({ titleFontStyle: v })}
            options={[
              { label: "", value: "" },
              { label: "Normal", value: "normal" },
              { label: "Italic", value: "italic" },
            ]}
          />
          <SelectControl
            label={__("Text Decoration", "image-box-block")}
            value={titleTextDecoration}
            onChange={(v) => setAttributes({ titleTextDecoration: v })}
            options={[
              { label: "", value: "" },
              { label: "None", value: "none" },
              { label: "Underline", value: "underline" },
              { label: "Line-through", value: "line-through" },
            ]}
          />
          <RangeControl
            label={__("Line Height", "image-box-block")}
            value={titleLineHeight}
            onChange={(v) => setAttributes({ titleLineHeight: v })}
            min={0}
            max={100}
          />
          <RangeControl
            label={__("Letter Spacing (px)", "image-box-block")}
            value={titleLetterSpacing}
            onChange={(v) => setAttributes({ titleLetterSpacing: v })}
            min={-10}
            max={50}
          />
          <RangeControl
            label={__("Word Spacing (px)", "image-box-block")}
            value={titleWordSpacing}
            onChange={(v) => setAttributes({ titleWordSpacing: v })}
            min={-10}
            max={50}
          />
        </PanelBody>

        <PanelBody
          title={__("Description Styles", "image-box-block")}
          initialOpen={false}
        >
          <RangeControl
            label={__("Description Size", "image-box-block")}
            value={descriptionSize}
            onChange={(v) => setAttributes({ descriptionSize: v })}
            min={8}
            max={48}
          />
          <div className="components-base-control">
            <div className="components-base-control__label">
              {__("Description Color", "image-box-block")}
            </div>
            <Button
              className="image-box-color-button"
              onClick={() => setShowDescColor(!showDescColor)}
              style={{
                backgroundColor: descriptionColor,
                width: "100%",
                justifyContent: "center",
              }}
            >
              {descriptionColor || __("Choose Color", "image-box-block")}
            </Button>
            {showDescColor && (
              <Popover
                position="bottom left"
                onClose={() => setShowDescColor(false)}
              >
                <ColorPicker
                  color={descriptionColor}
                  onChange={(value) =>
                    setAttributes({ descriptionColor: value })
                  }
                  enableAlpha
                />
              </Popover>
            )}
          </div>
          <SelectControl
            label={__("Font Family", "image-box-block")}
            value={descriptionFontFamily}
            onChange={(v) => setAttributes({ descriptionFontFamily: v })}
            options={[
              { label: "Default", value: "" },
              { label: "Arial", value: "Arial, sans-serif" },
              { label: "Georgia", value: "Georgia, serif" },
              { label: "Times New Roman", value: "Times New Roman, serif" },
              { label: "Helvetica", value: "Helvetica, Arial, sans-serif" },
              { label: "Verdana", value: "Verdana, sans-serif" },
              { label: "Courier New", value: "Courier New, monospace" },
              { label: "Impact", value: "Impact, sans-serif" },
              { label: "Comic Sans MS", value: "Comic Sans MS, cursive" },
              { label: "Trebuchet MS", value: "Trebuchet MS, sans-serif" },
              { label: "Roboto", value: "Roboto, sans-serif" },
              { label: "Roboto Flex", value: "Roboto Flex, sans-serif" },
              { label: "Roboto Serif", value: "Roboto Serif, serif" },
              { label: "Open Sans", value: "Open Sans, sans-serif" },
              { label: "Lato", value: "Lato, sans-serif" },
              { label: "Montserrat", value: "Montserrat, sans-serif" },
            ]}
          />
          <SelectControl
            label={__("Font Weight", "image-box-block")}
            value={descriptionFontWeight}
            onChange={(v) => setAttributes({ descriptionFontWeight: v })}
            options={[
              { label: "", value: "" },
              { label: "100", value: "100" },
              { label: "200", value: "200" },
              { label: "300", value: "300" },
              { label: "400 (normal)", value: "400" },
              { label: "500", value: "500" },
              { label: "600", value: "600" },
              { label: "700 (bold)", value: "700" },
              { label: "800", value: "800" },
              { label: "900", value: "900" },
            ]}
          />
          <SelectControl
            label={__("Text Transform", "image-box-block")}
            value={descriptionTextTransform}
            onChange={(v) => setAttributes({ descriptionTextTransform: v })}
            options={[
              { label: "", value: "" },
              { label: "None", value: "none" },
              { label: "Uppercase", value: "uppercase" },
              { label: "Lowercase", value: "lowercase" },
              { label: "Capitalize", value: "capitalize" },
            ]}
          />
          <SelectControl
            label={__("Font Style", "image-box-block")}
            value={descriptionFontStyle}
            onChange={(v) => setAttributes({ descriptionFontStyle: v })}
            options={[
              { label: "", value: "" },
              { label: "Normal", value: "normal" },
              { label: "Italic", value: "italic" },
            ]}
          />
          <SelectControl
            label={__("Text Decoration", "image-box-block")}
            value={descriptionTextDecoration}
            onChange={(v) => setAttributes({ descriptionTextDecoration: v })}
            options={[
              { label: "", value: "" },
              { label: "None", value: "none" },
              { label: "Underline", value: "underline" },
              { label: "Line-through", value: "line-through" },
            ]}
          />
          <RangeControl
            label={__("Line Height", "image-box-block")}
            value={descriptionLineHeight}
            onChange={(v) => setAttributes({ descriptionLineHeight: v })}
            min={0}
            max={100}
          />
          <RangeControl
            label={__("Letter Spacing (px)", "image-box-block")}
            value={descriptionLetterSpacing}
            onChange={(v) => setAttributes({ descriptionLetterSpacing: v })}
            min={-10}
            max={50}
          />
          <RangeControl
            label={__("Word Spacing (px)", "image-box-block")}
            value={descriptionWordSpacing}
            onChange={(v) => setAttributes({ descriptionWordSpacing: v })}
            min={-10}
            max={50}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps} style={{ textAlign: align }}>
        <div
          className="image-box__inner"
          style={{
            display: "flex",
            flexDirection: "column",
            alignItems:
              align === "center"
                ? "center"
                : align === "right"
                ? "flex-end"
                : "flex-start",
            gap: 12,
          }}
        >
          <div className="image-box__image">
            {imageUrl ? (
              <img
                src={imageUrl}
                alt={imageAlt}
                style={{
                  width: `${imageWidth}px`,
                  height: `${imageHeight}px`,
                  objectFit: "cover",
                  display: "block",
                }}
              />
            ) : (
              <div
                style={{
                  width: `${imageWidth}px`,
                  height: `${imageHeight}px`,
                  border: "2px dashed #ccc",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  color: "#999",
                  fontSize: "14px",
                }}
              >
                {__("Upload Image", "image-box-block")}
              </div>
            )}
          </div>

          <div className="image-box__content" style={{ width: "100%" }}>
            <RichText
              tagName="h3"
              value={title}
              onChange={(v) => setAttributes({ title: v })}
              style={{
                textAlign: align || "left",
                fontSize: `${titleSize}px`,
                color: titleColor,
                fontFamily: titleFontFamily || undefined,
                fontWeight: titleFontWeight || undefined,
                textTransform: titleTextTransform || undefined,
                fontStyle: titleFontStyle || undefined,
                textDecoration: titleTextDecoration || undefined,
                lineHeight: titleLineHeight
                  ? titleLineHeight.toString().includes("px")
                    ? titleLineHeight
                    : `${titleLineHeight}px`
                  : undefined,
                letterSpacing: titleLetterSpacing
                  ? titleLetterSpacing.toString().includes("px")
                    ? titleLetterSpacing
                    : `${titleLetterSpacing}px`
                  : undefined,
                wordSpacing: titleWordSpacing
                  ? titleWordSpacing.toString().includes("px")
                    ? titleWordSpacing
                    : `${titleWordSpacing}px`
                  : undefined,
                margin: "0 0 8px 0",
              }}
              placeholder={__("Enter title...", "image-box-block")}
            />
            <RichText
              tagName="p"
              value={description}
              onChange={(v) => setAttributes({ description: v })}
              style={{
                textAlign: align || "left",
                fontSize: `${descriptionSize}px`,
                color: descriptionColor,
                fontFamily: descriptionFontFamily || undefined,
                fontWeight: descriptionFontWeight || undefined,
                textTransform: descriptionTextTransform || undefined,
                fontStyle: descriptionFontStyle || undefined,
                textDecoration: descriptionTextDecoration || undefined,
                lineHeight: descriptionLineHeight
                  ? descriptionLineHeight.toString().includes("px")
                    ? descriptionLineHeight
                    : `${descriptionLineHeight}px`
                  : undefined,
                letterSpacing: descriptionLetterSpacing
                  ? descriptionLetterSpacing.toString().includes("px")
                    ? descriptionLetterSpacing
                    : `${descriptionLetterSpacing}px`
                  : undefined,
                wordSpacing: descriptionWordSpacing
                  ? descriptionWordSpacing.toString().includes("px")
                    ? descriptionWordSpacing
                    : `${descriptionWordSpacing}px`
                  : undefined,
                margin: 0,
              }}
              placeholder={__("Enter description...", "image-box-block")}
            />
          </div>
        </div>
      </div>
    </>
  );
}
