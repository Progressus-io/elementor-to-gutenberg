import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
  PanelBody,
  RangeControl,
  TextControl,
  ToggleControl,
  __experimentalUnitControl as UnitControl,
} from "@wordpress/components";
import { ColorPicker } from "@wordpress/components";

export default function Edit({ attributes, setAttributes }) {
  const {
    title,
    percentage,
    innerText,
    barColor,
    backgroundColor,
    titleColor,
    titleSize,
    barHeight,
    alignment,
    showPercentage,
    showTitle,
    borderRadius,
    textColor,
  } = attributes;

  const blockProps = useBlockProps();

  const containerStyle = {
    textAlign: alignment,
  };

  const titleStyle = {
    color: titleColor,
    fontSize: titleSize + "px",
    marginBottom: "10px",
  };

  const progressBarStyle = {
    height: barHeight + "px",
    backgroundColor: backgroundColor,
    borderRadius: borderRadius + "px",
    position: "relative",
    overflow: "hidden",
  };

  const progressStyle = {
    width: percentage + "%",
    height: "100%",
    backgroundColor: barColor,
    transition: "width 0.3s ease-in-out",
    position: "relative",
  };

  const textStyle = {
    position: "absolute",
    right: "10px",
    top: "50%",
    transform: "translateY(-50%)",
    color: textColor,
    zIndex: 1,
  };

  return (
    <div {...blockProps}>
      <InspectorControls>
        <PanelBody title={__("Progress Bar Settings", "progressus-gutenberg")}>
          <TextControl
            label={__("Title", "progressus-gutenberg")}
            value={title}
            onChange={(value) => setAttributes({ title: value })}
          />
          <RangeControl
            label={__("Percentage", "progressus-gutenberg")}
            value={percentage}
            onChange={(value) => setAttributes({ percentage: value })}
            min={0}
            max={100}
          />
          <TextControl
            label={__("Inner Text", "progressus-gutenberg")}
            value={innerText}
            onChange={(value) => setAttributes({ innerText: value })}
          />
          <ToggleControl
            label={__("Show Percentage", "progressus-gutenberg")}
            checked={showPercentage}
            onChange={(value) => setAttributes({ showPercentage: value })}
          />
          <ToggleControl
            label={__("Show Title", "progressus-gutenberg")}
            checked={showTitle}
            onChange={(value) => setAttributes({ showTitle: value })}
          />
          <RangeControl
            label={__("Title Size", "progressus-gutenberg")}
            value={titleSize}
            onChange={(value) => setAttributes({ titleSize: value })}
            min={10}
            max={50}
          />
          <RangeControl
            label={__("Bar Height", "progressus-gutenberg")}
            value={barHeight}
            onChange={(value) => setAttributes({ barHeight: value })}
            min={1}
            max={50}
          />
          <RangeControl
            label={__("Border Radius", "progressus-gutenberg")}
            value={borderRadius}
            onChange={(value) => setAttributes({ borderRadius: value })}
            min={0}
            max={50}
          />
          <div>
            <label>{__("Progress Text Color", "progressus-gutenberg")}</label>
            <ColorPicker
              color={textColor}
              onChange={(value) => setAttributes({ textColor: value })}
              enableAlpha
            />
          </div>
          <RangeControl
            label={__("Bar Color", "progressus-gutenberg")}
            value={barColor}
            onChange={(value) => setAttributes({ barColor: value })}
            enableAlpha
          />
          <div>
            <label>{__("Bar Color", "progressus-gutenberg")}</label>
            <ColorPicker
              color={barColor}
              onChange={(value) => setAttributes({ barColor: value })}
              enableAlpha
            />
          </div>
          <div>
            <label>{__("Background Color", "progressus-gutenberg")}</label>
            <ColorPicker
              color={backgroundColor}
              onChange={(value) => setAttributes({ backgroundColor: value })}
              enableAlpha
            />
          </div>
          <div>
            <label>{__("Title Color", "progressus-gutenberg")}</label>
            <ColorPicker
              color={titleColor}
              onChange={(value) => setAttributes({ titleColor: value })}
              enableAlpha
            />
          </div>
        </PanelBody>
      </InspectorControls>

      <div className="progressus-progress-bar" style={containerStyle}>
        {showTitle && <h4 style={titleStyle}>{title}</h4>}
        <div
          className="progressus-progress-bar-container"
          style={progressBarStyle}
        >
          <div className="progressus-progress-bar-fill" style={progressStyle}>
            <div style={textStyle}>
              {innerText}
              {showPercentage && (
                <span className="progressus-progress-percentage">
                  {percentage}%
                </span>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
