import { __ } from "@wordpress/i18n";
import { useState } from "@wordpress/element";
import {
  useBlockProps,
  InspectorControls,
  RichText,
  MediaUpload,
  MediaUploadCheck,
  BlockControls,
  AlignmentToolbar,
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
  __experimentalBoxControl as BoxControl,
} from "@wordpress/components";

export default function Edit({ attributes, setAttributes }) {
  const {
    layout = "left",
    bgImageUrl = "",
    bgImageId = 0,
    title = "",
    description = "",
    buttonText = "",
    buttonUrl = "",
    buttonTarget = false,
    buttonNofollow = false,
    alignment = "left",
    imageMinHeight = 425,
    contentBgColor = "",
    titleColor = "#000000",
    titleSize = 28,
    titleFontFamily = "",
    titleFontWeight = "",
    titleTextTransform = "",
    titleFontStyle = "",
    titleTextDecoration = "",
    titleLineHeight = "",
    titleLetterSpacing = "",
    titleWordSpacing = "",
    descriptionColor = "#666666",
    descriptionSize = 16,
    descriptionFontFamily = "",
    descriptionFontWeight = "",
    descriptionTextTransform = "",
    descriptionFontStyle = "",
    descriptionTextDecoration = "",
    descriptionLineHeight = "",
    descriptionLetterSpacing = "",
    descriptionWordSpacing = "",
    descriptionSpacing = 0,
    buttonBgColor = "#007cba",
    buttonTextColor = "#ffffff",
    buttonSize = 16,
    buttonFontFamily = "",
    buttonFontWeight = "",
    buttonTextTransform = "",
    buttonFontStyle = "",
    buttonTextDecoration = "",
    buttonLineHeight = "",
    buttonLetterSpacing = "",
    buttonWordSpacing = "",
    buttonBorderRadius = 4,
    buttonPadding = { top: 12, right: 24, bottom: 12, left: 24 },
    contentPadding = { top: 50, right: 50, bottom: 50, left: 50 },
    contentMargin = { top: 0, right: 0, bottom: 0, left: 0 },
    ribbonTitle = "",
    ribbonBgColor = "#007cba",
    ribbonTextColor = "#ffffff",
    ribbonSize = 16,
    ribbonFontFamily = "",
    ribbonFontWeight = "",
    ribbonTextTransform = "",
    ribbonFontStyle = "",
    ribbonTextDecoration = "",
    ribbonLineHeight = "",
    ribbonLetterSpacing = "",
    ribbonWordSpacing = "",
    ribbonHorizontalPosition = "left",
    ribbonDistance = 42,
  } = attributes;

  const [showContentBgColor, setShowContentBgColor] = useState(false);
  const [showTitleColor, setShowTitleColor] = useState(false);
  const [showDescColor, setShowDescColor] = useState(false);
  const [showButtonBgColor, setShowButtonBgColor] = useState(false);
  const [showButtonTextColor, setShowButtonTextColor] = useState(false);
  const [showRibbonBgColor, setShowRibbonBgColor] = useState(false);
  const [showRibbonTextColor, setShowRibbonTextColor] = useState(false);

  const blockProps = useBlockProps({
    className: `call-to-action-layout-${layout} call-to-action-align-${alignment}`,
  });

  const getAriaLabel = (url) => {
    try {
      const p = new URL(url);
      const path = p.pathname || "";
      return path.split("/").pop() || "";
    } catch (e) {
      return "";
    }
  };

  const onSelectBgImage = (media) => {
    setAttributes({
      bgImageUrl: media.url,
      bgImageId: media.id,
    });
  };

  const onRemoveBgImage = () => {
    setAttributes({
      bgImageUrl: "",
      bgImageId: 0,
    });
  };

  const alignItems =
    layout === "left" || layout === "right" ? "stretch" : "flex-start";

  const ctaStyle = {
    minHeight: `${imageMinHeight}px`,
    display: "flex",
    alignItems,
    justifyContent:
      layout === "center"
        ? "center"
        : layout === "right"
        ? "flex-end"
        : "flex-start",
    position: "relative",
    flexDirection:
      layout === "above"
        ? "column"
        : layout === "below"
        ? "column-reverse"
        : layout === "right"
        ? "row-reverse"
        : layout === "left"
        ? "row"
        : undefined,
    ...(bgImageUrl &&
      layout === "center" && {
        backgroundImage: `url(${bgImageUrl})`,
        backgroundSize: "cover",
        backgroundPosition: "center",
      }),
  };

  const contentStyle = {
    backgroundColor: contentBgColor || "rgba(255,255,255,0.9)",
    padding: `${contentPadding.top || 50}px ${contentPadding.right || 50}px ${
      contentPadding.bottom || 50
    }px ${contentPadding.left || 50}px`,
    margin: `${contentMargin.top || 0}px ${contentMargin.right || 0}px ${
      contentMargin.bottom || 0
    }px ${contentMargin.left || 0}px`,
    maxWidth:
      layout === "center"
        ? "600px"
        : layout === "above" || layout === "below"
        ? "100%"
        : "50%",
    ...(layout === "left" || layout === "right"
      ? {
          flexBasis: "50%",
          display: "flex",
          flexDirection: "column",
          justifyContent: "flex-start",
        }
      : {}),
    textAlign: alignment,
  };

  return (
    <>
      <BlockControls>
        <AlignmentToolbar
          value={alignment}
          onChange={(value) => setAttributes({ alignment: value || "left" })}
        />
      </BlockControls>

      <InspectorControls>
        <PanelBody
          title={__("Block Settings", "call-to-action-block")}
          initialOpen={true}
        >
          {/* Block alignment removed — use text alignment instead */}
          <SelectControl
            label={__("Layout", "call-to-action-block")}
            value={layout}
            onChange={(value) => setAttributes({ layout: value })}
            options={[
              { label: "Left", value: "left" },
              { label: "Center", value: "center" },
              { label: "Right", value: "right" },
              { label: "Above", value: "above" },
              { label: "Below", value: "below" },
            ]}
          />
          <SelectControl
            label={__("Content Alignment", "call-to-action-block")}
            value={alignment}
            onChange={(value) => setAttributes({ alignment: value || "left" })}
            options={[
              { label: "Left", value: "left" },
              { label: "Center", value: "center" },
              { label: "Right", value: "right" },
            ]}
          />
          {/* Vertical Position removed; default is top/flex-start */}
        </PanelBody>

        <PanelBody
          title={__("Background Image", "call-to-action-block")}
          initialOpen={true}
        >
          {bgImageUrl ? (
            <div className="bg-image-preview" style={{ marginBottom: "12px" }}>
              <img
                src={bgImageUrl}
                alt=""
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
              {__("No background image selected", "call-to-action-block")}
            </div>
          )}

          <MediaUploadCheck>
            <MediaUpload
              onSelect={onSelectBgImage}
              allowedTypes={["image"]}
              value={bgImageId}
              render={({ open }) => (
                <Button variant="secondary" onClick={open}>
                  {bgImageUrl
                    ? __("Replace Background Image", "call-to-action-block")
                    : __("Upload Background Image", "call-to-action-block")}
                </Button>
              )}
            />
          </MediaUploadCheck>

          {bgImageUrl && (
            <Button
              variant="link"
              isDestructive
              onClick={onRemoveBgImage}
              style={{ marginTop: "8px" }}
            >
              {__("Remove Background Image", "call-to-action-block")}
            </Button>
          )}

          <RangeControl
            label={__("Image Min Height (px)", "call-to-action-block")}
            value={imageMinHeight}
            onChange={(value) => setAttributes({ imageMinHeight: value })}
            min={200}
            max={800}
            step={25}
          />
        </PanelBody>

        <PanelBody
          title={__("Content", "call-to-action-block")}
          initialOpen={false}
        >
          <div className="components-base-control">
            <div className="components-base-control__label">
              {__("Content Background Color", "call-to-action-block")}
            </div>
            <Button
              onClick={() => setShowContentBgColor(!showContentBgColor)}
              style={{
                backgroundColor: contentBgColor || "rgba(255,255,255,0.9)",
                width: "100%",
                justifyContent: "center",
              }}
            >
              {contentBgColor || __("Choose Color", "call-to-action-block")}
            </Button>
            {showContentBgColor && (
              <Popover
                position="bottom left"
                onClose={() => setShowContentBgColor(false)}
              >
                <ColorPicker
                  color={contentBgColor}
                  onChange={(value) => setAttributes({ contentBgColor: value })}
                  enableAlpha
                />
              </Popover>
            )}
          </div>

          <BoxControl
            label={__("Content Padding", "call-to-action-block")}
            values={contentPadding}
            onChange={(value) => setAttributes({ contentPadding: value })}
          />

          <BoxControl
            label={__("Content Margin", "call-to-action-block")}
            values={contentMargin}
            onChange={(value) => setAttributes({ contentMargin: value })}
          />
        </PanelBody>

        <PanelBody
          title={__("Button", "call-to-action-block")}
          initialOpen={false}
        >
          <TextControl
            label={__("Button URL", "call-to-action-block")}
            value={buttonUrl}
            onChange={(v) => setAttributes({ buttonUrl: v })}
            placeholder={__("https://example.com", "call-to-action-block")}
          />
          <ToggleControl
            label={__("Open in new tab", "call-to-action-block")}
            checked={!!buttonTarget}
            onChange={(v) => setAttributes({ buttonTarget: !!v })}
          />
          <ToggleControl
            label={__("Add rel=nofollow", "call-to-action-block")}
            checked={!!buttonNofollow}
            onChange={(v) => setAttributes({ buttonNofollow: !!v })}
          />

          <RangeControl
            label={__("Button Font Size", "call-to-action-block")}
            value={buttonSize}
            onChange={(v) => setAttributes({ buttonSize: v })}
            min={8}
            max={48}
          />

          <div className="components-base-control">
            <div className="components-base-control__label">
              {__("Button Background Color", "call-to-action-block")}
            </div>
            <Button
              onClick={() => setShowButtonBgColor(!showButtonBgColor)}
              style={{
                backgroundColor: buttonBgColor,
                width: "100%",
                justifyContent: "center",
              }}
            >
              {buttonBgColor || __("Choose Color", "call-to-action-block")}
            </Button>
            {showButtonBgColor && (
              <Popover
                position="bottom left"
                onClose={() => setShowButtonBgColor(false)}
              >
                <ColorPicker
                  color={buttonBgColor}
                  onChange={(value) => setAttributes({ buttonBgColor: value })}
                  enableAlpha
                />
              </Popover>
            )}
          </div>

          <div className="components-base-control">
            <div className="components-base-control__label">
              {__("Button Text Color", "call-to-action-block")}
            </div>
            <Button
              onClick={() => setShowButtonTextColor(!showButtonTextColor)}
              style={{
                backgroundColor: buttonTextColor,
                width: "100%",
                justifyContent: "center",
              }}
            >
              {buttonTextColor || __("Choose Color", "call-to-action-block")}
            </Button>
            {showButtonTextColor && (
              <Popover
                position="bottom left"
                onClose={() => setShowButtonTextColor(false)}
              >
                <ColorPicker
                  color={buttonTextColor}
                  onChange={(value) =>
                    setAttributes({ buttonTextColor: value })
                  }
                  enableAlpha
                />
              </Popover>
            )}
          </div>

          <RangeControl
            label={__("Button Border Radius", "call-to-action-block")}
            value={buttonBorderRadius}
            onChange={(v) => setAttributes({ buttonBorderRadius: v })}
            min={0}
            max={50}
          />

          <BoxControl
            label={__("Button Padding", "call-to-action-block")}
            values={buttonPadding}
            onChange={(value) => setAttributes({ buttonPadding: value })}
          />
        </PanelBody>

        {/* Title Typography Panel */}
        <PanelBody
          title={__("Title Styles", "call-to-action-block")}
          initialOpen={false}
        >
          <RangeControl
            label={__("Title Size", "call-to-action-block")}
            value={titleSize}
            onChange={(v) => setAttributes({ titleSize: v })}
            min={8}
            max={72}
          />
          <div className="components-base-control">
            <div className="components-base-control__label">
              {__("Title Color", "call-to-action-block")}
            </div>
            <Button
              onClick={() => setShowTitleColor(!showTitleColor)}
              style={{
                backgroundColor: titleColor,
                width: "100%",
                justifyContent: "center",
              }}
            >
              {titleColor || __("Choose Color", "call-to-action-block")}
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
          <TextControl
            label={__("Title Font Family", "call-to-action-block")}
            value={titleFontFamily}
            onChange={(v) => setAttributes({ titleFontFamily: v })}
          />
          <SelectControl
            label={__("Title Font Weight", "call-to-action-block")}
            value={titleFontWeight}
            options={[
              { label: __("Default"), value: "" },
              { label: __("Light"), value: "300" },
              { label: __("Normal"), value: "400" },
              { label: __("Medium"), value: "500" },
              { label: __("Semi Bold"), value: "600" },
              { label: __("Bold"), value: "700" },
              { label: __("Extra Bold"), value: "800" },
            ]}
            onChange={(v) => setAttributes({ titleFontWeight: v })}
          />
          <SelectControl
            label={__("Title Text Transform", "call-to-action-block")}
            value={titleTextTransform}
            options={[
              { label: __("Default"), value: "" },
              { label: __("Uppercase"), value: "uppercase" },
              { label: __("Lowercase"), value: "lowercase" },
              { label: __("Capitalize"), value: "capitalize" },
            ]}
            onChange={(v) => setAttributes({ titleTextTransform: v })}
          />
          <SelectControl
            label={__("Title Font Style", "call-to-action-block")}
            value={titleFontStyle}
            options={[
              { label: __("Default"), value: "" },
              { label: __("Normal"), value: "normal" },
              { label: __("Italic"), value: "italic" },
            ]}
            onChange={(v) => setAttributes({ titleFontStyle: v })}
          />
          <SelectControl
            label={__("Title Text Decoration", "call-to-action-block")}
            value={titleTextDecoration}
            options={[
              { label: __("Default"), value: "" },
              { label: __("None"), value: "none" },
              { label: __("Underline"), value: "underline" },
              { label: __("Overline"), value: "overline" },
              { label: __("Line Through"), value: "line-through" },
            ]}
            onChange={(v) => setAttributes({ titleTextDecoration: v })}
          />
          <TextControl
            label={__("Title Line Height", "call-to-action-block")}
            value={titleLineHeight}
            onChange={(v) => setAttributes({ titleLineHeight: v })}
            help={__("E.g., 1.2 or 1.2em", "call-to-action-block")}
          />
          <TextControl
            label={__("Title Letter Spacing", "call-to-action-block")}
            value={titleLetterSpacing}
            onChange={(v) => setAttributes({ titleLetterSpacing: v })}
            help={__("E.g., 2px or 0.1em", "call-to-action-block")}
          />
          <TextControl
            label={__("Title Word Spacing", "call-to-action-block")}
            value={titleWordSpacing}
            onChange={(v) => setAttributes({ titleWordSpacing: v })}
            help={__("E.g., 5px or 0.2em", "call-to-action-block")}
          />
        </PanelBody>

        {/* Description Typography Panel */}
        <PanelBody
          title={__("Description Styles", "call-to-action-block")}
          initialOpen={false}
        >
          <RangeControl
            label={__("Description Size", "call-to-action-block")}
            value={descriptionSize}
            onChange={(v) => setAttributes({ descriptionSize: v })}
            min={8}
            max={48}
          />
          <div className="components-base-control">
            <div className="components-base-control__label">
              {__("Description Color", "call-to-action-block")}
            </div>
            <Button
              onClick={() => setShowDescColor(!showDescColor)}
              style={{
                backgroundColor: descriptionColor,
                width: "100%",
                justifyContent: "center",
              }}
            >
              {descriptionColor || __("Choose Color", "call-to-action-block")}
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
          <TextControl
            label={__("Description Font Family", "call-to-action-block")}
            value={descriptionFontFamily}
            onChange={(v) => setAttributes({ descriptionFontFamily: v })}
          />
          <SelectControl
            label={__("Description Font Weight", "call-to-action-block")}
            value={descriptionFontWeight}
            options={[
              { label: __("Default"), value: "" },
              { label: __("Light"), value: "300" },
              { label: __("Normal"), value: "400" },
              { label: __("Medium"), value: "500" },
              { label: __("Semi Bold"), value: "600" },
              { label: __("Bold"), value: "700" },
            ]}
            onChange={(v) => setAttributes({ descriptionFontWeight: v })}
          />
          <SelectControl
            label={__("Description Text Transform", "call-to-action-block")}
            value={descriptionTextTransform}
            options={[
              { label: __("Default"), value: "" },
              { label: __("Uppercase"), value: "uppercase" },
              { label: __("Lowercase"), value: "lowercase" },
              { label: __("Capitalize"), value: "capitalize" },
            ]}
            onChange={(v) => setAttributes({ descriptionTextTransform: v })}
          />
          <SelectControl
            label={__("Description Font Style", "call-to-action-block")}
            value={descriptionFontStyle}
            options={[
              { label: __("Default"), value: "" },
              { label: __("Normal"), value: "normal" },
              { label: __("Italic"), value: "italic" },
            ]}
            onChange={(v) => setAttributes({ descriptionFontStyle: v })}
          />
          <SelectControl
            label={__("Description Text Decoration", "call-to-action-block")}
            value={descriptionTextDecoration}
            options={[
              { label: __("Default"), value: "" },
              { label: __("None"), value: "none" },
              { label: __("Underline"), value: "underline" },
              { label: __("Overline"), value: "overline" },
              { label: __("Line Through"), value: "line-through" },
            ]}
            onChange={(v) => setAttributes({ descriptionTextDecoration: v })}
          />
          <TextControl
            label={__("Description Line Height", "call-to-action-block")}
            value={descriptionLineHeight}
            onChange={(v) => setAttributes({ descriptionLineHeight: v })}
            help={__("E.g., 1.4 or 1.4em", "call-to-action-block")}
          />
          <TextControl
            label={__("Description Letter Spacing", "call-to-action-block")}
            value={descriptionLetterSpacing}
            onChange={(v) => setAttributes({ descriptionLetterSpacing: v })}
            help={__("E.g., 1px or 0.05em", "call-to-action-block")}
          />
          <TextControl
            label={__("Description Word Spacing", "call-to-action-block")}
            value={descriptionWordSpacing}
            onChange={(v) => setAttributes({ descriptionWordSpacing: v })}
            help={__("E.g., 4px or 0.2em", "call-to-action-block")}
          />
          <RangeControl
            label={__("Description Spacing", "call-to-action-block")}
            value={descriptionSpacing}
            onChange={(v) => setAttributes({ descriptionSpacing: v })}
            min={0}
            max={100}
          />
        </PanelBody>

        <PanelBody
          title={__("Ribbon Settings", "call-to-action-block")}
          initialOpen={false}
        >
          <TextControl
            label={__("Ribbon Title", "call-to-action-block")}
            value={ribbonTitle}
            onChange={(v) => setAttributes({ ribbonTitle: v })}
          />
          {ribbonTitle && (
            <>
              <SelectControl
                label={__("Ribbon Position", "call-to-action-block")}
                value={ribbonHorizontalPosition}
                options={[
                  { label: __("Left"), value: "left" },
                  { label: __("Right"), value: "right" },
                ]}
                onChange={(v) => setAttributes({ ribbonHorizontalPosition: v })}
              />
              <RangeControl
                label={__("Ribbon Distance", "call-to-action-block")}
                value={ribbonDistance}
                onChange={(v) => setAttributes({ ribbonDistance: v })}
                min={0}
                max={100}
              />
              <div className="color-control-wrapper">
                <label>
                  {__("Ribbon Background Color", "call-to-action-block")}
                </label>
                <Button
                  onClick={() => setShowRibbonBgColor(!showRibbonBgColor)}
                  style={{
                    backgroundColor: ribbonBgColor,
                    color: "#fff",
                    width: "100%",
                    justifyContent: "flex-start",
                  }}
                >
                  {ribbonBgColor || __("Select Color", "call-to-action-block")}
                </Button>
                {showRibbonBgColor && (
                  <Popover>
                    <ColorPicker
                      color={ribbonBgColor}
                      onChangeComplete={(color) =>
                        setAttributes({ ribbonBgColor: color.hex })
                      }
                    />
                  </Popover>
                )}
              </div>
              <div className="color-control-wrapper">
                <label>{__("Ribbon Text Color", "call-to-action-block")}</label>
                <Button
                  onClick={() => setShowRibbonTextColor(!showRibbonTextColor)}
                  style={{
                    backgroundColor: ribbonTextColor,
                    color: "#fff",
                    width: "100%",
                    justifyContent: "flex-start",
                  }}
                >
                  {ribbonTextColor ||
                    __("Select Color", "call-to-action-block")}
                </Button>
                {showRibbonTextColor && (
                  <Popover>
                    <ColorPicker
                      color={ribbonTextColor}
                      onChangeComplete={(color) =>
                        setAttributes({ ribbonTextColor: color.hex })
                      }
                    />
                  </Popover>
                )}
              </div>
              <RangeControl
                label={__("Ribbon Font Size", "call-to-action-block")}
                value={ribbonSize}
                onChange={(v) => setAttributes({ ribbonSize: v })}
                min={10}
                max={100}
              />
              <TextControl
                label={__("Ribbon Font Family", "call-to-action-block")}
                value={ribbonFontFamily}
                onChange={(v) => setAttributes({ ribbonFontFamily: v })}
              />
              <SelectControl
                label={__("Ribbon Font Weight", "call-to-action-block")}
                value={ribbonFontWeight}
                options={[
                  { label: __("Default"), value: "" },
                  { label: __("Light"), value: "300" },
                  { label: __("Normal"), value: "400" },
                  { label: __("Medium"), value: "500" },
                  { label: __("Semi Bold"), value: "600" },
                  { label: __("Bold"), value: "700" },
                  { label: __("Extra Bold"), value: "800" },
                ]}
                onChange={(v) => setAttributes({ ribbonFontWeight: v })}
              />
              <SelectControl
                label={__("Ribbon Text Transform", "call-to-action-block")}
                value={ribbonTextTransform}
                options={[
                  { label: __("Default"), value: "" },
                  { label: __("Uppercase"), value: "uppercase" },
                  { label: __("Lowercase"), value: "lowercase" },
                  { label: __("Capitalize"), value: "capitalize" },
                ]}
                onChange={(v) => setAttributes({ ribbonTextTransform: v })}
              />
              <SelectControl
                label={__("Ribbon Font Style", "call-to-action-block")}
                value={ribbonFontStyle}
                options={[
                  { label: __("Default"), value: "" },
                  { label: __("Normal"), value: "normal" },
                  { label: __("Italic"), value: "italic" },
                ]}
                onChange={(v) => setAttributes({ ribbonFontStyle: v })}
              />
              <SelectControl
                label={__("Ribbon Text Decoration", "call-to-action-block")}
                value={ribbonTextDecoration}
                options={[
                  { label: __("Default"), value: "" },
                  { label: __("None"), value: "none" },
                  { label: __("Underline"), value: "underline" },
                  { label: __("Overline"), value: "overline" },
                  { label: __("Line Through"), value: "line-through" },
                ]}
                onChange={(v) => setAttributes({ ribbonTextDecoration: v })}
              />
              <TextControl
                label={__("Ribbon Line Height", "call-to-action-block")}
                value={ribbonLineHeight}
                onChange={(v) => setAttributes({ ribbonLineHeight: v })}
                help={__("E.g., 1.5 or 1.5em", "call-to-action-block")}
              />
              <TextControl
                label={__("Ribbon Letter Spacing", "call-to-action-block")}
                value={ribbonLetterSpacing}
                onChange={(v) => setAttributes({ ribbonLetterSpacing: v })}
                help={__("E.g., 2px or 0.1em", "call-to-action-block")}
              />
              <TextControl
                label={__("Ribbon Word Spacing", "call-to-action-block")}
                value={ribbonWordSpacing}
                onChange={(v) => setAttributes({ ribbonWordSpacing: v })}
                help={__("E.g., 5px or 0.2em", "call-to-action-block")}
              />
            </>
          )}
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div className="call-to-action-container" style={ctaStyle}>
          {ribbonTitle && (
            <div
              className="call-to-action-ribbon"
              style={{
                position: "absolute",
                top: `${ribbonDistance}px`,
                ...(ribbonHorizontalPosition === "right"
                  ? { right: `${ribbonDistance}px` }
                  : { left: `${ribbonDistance}px` }),
                backgroundColor: ribbonBgColor,
                color: ribbonTextColor,
                fontSize: `${ribbonSize}px`,
                fontFamily: ribbonFontFamily || undefined,
                fontWeight: ribbonFontWeight || undefined,
                textTransform: ribbonTextTransform || undefined,
                fontStyle: ribbonFontStyle || undefined,
                textDecoration: ribbonTextDecoration || undefined,
                lineHeight: ribbonLineHeight
                  ? String(ribbonLineHeight)
                  : undefined,
                letterSpacing: ribbonLetterSpacing
                  ? String(ribbonLetterSpacing).includes("px")
                    ? ribbonLetterSpacing
                    : `${ribbonLetterSpacing}px`
                  : undefined,
                wordSpacing: ribbonWordSpacing
                  ? String(ribbonWordSpacing).includes("px")
                    ? ribbonWordSpacing
                    : `${ribbonWordSpacing}px`
                  : undefined,
                padding: "8px 16px",
                borderRadius: "4px",
                zIndex: "10",
                transform:
                  ribbonHorizontalPosition === "right"
                    ? "rotate(15deg)"
                    : "rotate(-15deg)",
              }}
            >
              {ribbonTitle}
            </div>
          )}
          {bgImageUrl &&
          (layout === "above" ||
            layout === "below" ||
            layout === "left" ||
            layout === "right") ? (
            <>
              <div
                className="call-to-action-image"
                role="img"
                aria-label={getAriaLabel(bgImageUrl)}
                style={{
                  backgroundImage: `url(${bgImageUrl})`,
                  backgroundSize: "cover",
                  backgroundPosition: "center",
                  minHeight: `${imageMinHeight}px`,
                  flexBasis:
                    layout === "left" || layout === "right" ? "50%" : undefined,
                  width:
                    layout === "above" || layout === "below"
                      ? "100%"
                      : undefined,
                }}
              />
              <div className="call-to-action-image-overlay" />
            </>
          ) : null}
          <div className="call-to-action-content" style={contentStyle}>
            {title || !title ? (
              <RichText
                tagName="h2"
                value={title}
                onChange={(v) => setAttributes({ title: v })}
                style={{
                  fontSize: `${titleSize}px`,
                  color: titleColor,
                  fontFamily: titleFontFamily || undefined,
                  fontWeight: titleFontWeight || undefined,
                  textTransform: titleTextTransform || undefined,
                  fontStyle: titleFontStyle || undefined,
                  textDecoration: titleTextDecoration || undefined,
                  lineHeight: titleLineHeight
                    ? String(titleLineHeight)
                    : undefined,
                  letterSpacing: titleLetterSpacing
                    ? String(titleLetterSpacing).includes("px")
                      ? titleLetterSpacing
                      : `${titleLetterSpacing}px`
                    : undefined,
                  wordSpacing: titleWordSpacing
                    ? String(titleWordSpacing).includes("px")
                      ? titleWordSpacing
                      : `${titleWordSpacing}px`
                    : undefined,
                  marginBottom: "16px",
                }}
                placeholder={__("Enter title...", "call-to-action-block")}
              />
            ) : null}

            {description || !description ? (
              <RichText
                tagName="p"
                value={description}
                onChange={(v) => setAttributes({ description: v })}
                style={{
                  fontSize: `${descriptionSize}px`,
                  color: descriptionColor,
                  fontFamily: descriptionFontFamily || undefined,
                  fontWeight: descriptionFontWeight || undefined,
                  textTransform: descriptionTextTransform || undefined,
                  fontStyle: descriptionFontStyle || undefined,
                  textDecoration: descriptionTextDecoration || undefined,
                  lineHeight: descriptionLineHeight
                    ? String(descriptionLineHeight)
                    : undefined,
                  letterSpacing: descriptionLetterSpacing
                    ? String(descriptionLetterSpacing).includes("px")
                      ? descriptionLetterSpacing
                      : `${descriptionLetterSpacing}px`
                    : undefined,
                  wordSpacing: descriptionWordSpacing
                    ? String(descriptionWordSpacing).includes("px")
                      ? descriptionWordSpacing
                      : `${descriptionWordSpacing}px`
                    : undefined,
                  marginBottom: `${descriptionSpacing}px`,
                }}
                placeholder={__("Enter description...", "call-to-action-block")}
              />
            ) : null}

            {buttonText || !buttonText ? (
              <RichText
                tagName="span"
                value={buttonText}
                onChange={(v) => setAttributes({ buttonText: v })}
                style={{
                  display: "inline-block",
                  fontSize: `${buttonSize}px`,
                  color: buttonTextColor,
                  backgroundColor: buttonBgColor,
                  padding: `${buttonPadding.top || 12}px ${
                    buttonPadding.right || 24
                  }px ${buttonPadding.bottom || 12}px ${
                    buttonPadding.left || 24
                  }px`,
                  borderRadius: `${buttonBorderRadius}px`,
                  fontFamily: buttonFontFamily || undefined,
                  fontWeight: buttonFontWeight || undefined,
                  textTransform: buttonTextTransform || undefined,
                  fontStyle: buttonFontStyle || undefined,
                  textDecoration: buttonTextDecoration || "none",
                  lineHeight: buttonLineHeight
                    ? String(buttonLineHeight)
                    : undefined,
                  letterSpacing: buttonLetterSpacing
                    ? String(buttonLetterSpacing).includes("px")
                      ? buttonLetterSpacing
                      : `${buttonLetterSpacing}px`
                    : undefined,
                  wordSpacing: buttonWordSpacing
                    ? String(buttonWordSpacing).includes("px")
                      ? buttonWordSpacing
                      : `${buttonWordSpacing}px`
                    : undefined,
                  border: "none",
                  cursor: "pointer",
                }}
                placeholder={__("Enter button text...", "call-to-action-block")}
              />
            ) : null}
          </div>
        </div>
      </div>
    </>
  );
}
