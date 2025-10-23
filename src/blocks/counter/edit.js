import { __ } from "@wordpress/i18n";
import { useBlockProps } from "@wordpress/block-editor";
import { InspectorControls } from "@wordpress/block-editor";
import {
  PanelBody,
  RangeControl,
  TextControl,
  SelectControl,
} from "@wordpress/components";
import { ColorPicker } from "@wordpress/components";

const Edit = ({ attributes, setAttributes }) => {
  const {
    startValue,
    endValue,
    duration,
    prefix,
    suffix,
    title,
    titleColor,
    numberColor,
    numberSize,
    titleSize,
    alignment,
  } = attributes;

  const blockProps = useBlockProps();

  const alignmentOptions = [
    { label: __("Left", "elementor-to-gutenberg"), value: "left" },
    { label: __("Center", "elementor-to-gutenberg"), value: "center" },
    { label: __("Right", "elementor-to-gutenberg"), value: "right" },
  ];

  const counterStyle = {
    textAlign: alignment,
    color: numberColor,
    fontSize: `${numberSize}px`,
  };

  const titleStyle = {
    color: titleColor,
    fontSize: `${titleSize}px`,
    textAlign: alignment,
  };

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__("Counter Settings", "elementor-to-gutenberg")}
          initialOpen={true}
        >
          <RangeControl
            label={__("Start Value", "elementor-to-gutenberg")}
            value={startValue}
            onChange={(value) => setAttributes({ startValue: value })}
            min={0}
            max={endValue}
          />
          <RangeControl
            label={__("End Value", "elementor-to-gutenberg")}
            value={endValue}
            onChange={(value) => setAttributes({ endValue: value })}
            min={startValue}
            max={10000}
          />
          <RangeControl
            label={__("Animation Duration (ms)", "elementor-to-gutenberg")}
            value={duration}
            onChange={(value) => setAttributes({ duration: value })}
            min={100}
            max={5000}
            step={100}
          />
          <TextControl
            label={__("Prefix", "elementor-to-gutenberg")}
            value={prefix}
            onChange={(value) => setAttributes({ prefix: value })}
          />
          <TextControl
            label={__("Suffix", "elementor-to-gutenberg")}
            value={suffix}
            onChange={(value) => setAttributes({ suffix: value })}
          />
          <TextControl
            label={__("Title", "elementor-to-gutenberg")}
            value={title}
            onChange={(value) => setAttributes({ title: value })}
          />
        </PanelBody>
        <PanelBody
          title={__("Style Settings", "elementor-to-gutenberg")}
          initialOpen={false}
        >
          <SelectControl
            label={__("Alignment", "elementor-to-gutenberg")}
            value={alignment}
            options={alignmentOptions}
            onChange={(value) => setAttributes({ alignment: value })}
          />
          <div className="components-base-control">
            <label className="components-base-control__label">
              {__("Number Color", "elementor-to-gutenberg")}
            </label>
            <ColorPicker
              color={numberColor}
              onChange={(value) => setAttributes({ numberColor: value })}
            />
          </div>
          <RangeControl
            label={__("Number Size", "elementor-to-gutenberg")}
            value={numberSize}
            onChange={(value) => setAttributes({ numberSize: value })}
            min={10}
            max={100}
          />
          <div className="components-base-control">
            <label className="components-base-control__label">
              {__("Title Color", "elementor-to-gutenberg")}
            </label>
            <ColorPicker
              color={titleColor}
              onChange={(value) => setAttributes({ titleColor: value })}
            />
          </div>
          <RangeControl
            label={__("Title Size", "elementor-to-gutenberg")}
            value={titleSize}
            onChange={(value) => setAttributes({ titleSize: value })}
            min={10}
            max={50}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <div className="counter-preview" style={counterStyle}>
          <span className="prefix">{prefix}</span>
          <span className="counter-value">{endValue}</span>
          <span className="suffix">{suffix}</span>
        </div>
        {title && (
          <h4 className="counter-title" style={titleStyle}>
            {title}
          </h4>
        )}
      </div>
    </>
  );
};

export default Edit;
