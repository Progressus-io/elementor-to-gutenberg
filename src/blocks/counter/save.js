import { useBlockProps } from "@wordpress/block-editor";

const Save = ({ attributes }) => {
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

  const blockProps = useBlockProps.save();

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
    <div {...blockProps}>
      <div
        className="counter-preview"
        style={counterStyle}
        data-start={startValue}
        data-end={endValue}
        data-duration={duration}
      >
        <span className="prefix">{prefix}</span>
        <span className="counter-value">{startValue}</span>
        <span className="suffix">{suffix}</span>
      </div>
      {title && (
        <h4 className="counter-title" style={titleStyle}>
          {title}
        </h4>
      )}
    </div>
  );
};

export default Save;
