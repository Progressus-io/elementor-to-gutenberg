import { useBlockProps } from "@wordpress/block-editor";

export default function save({ attributes }) {
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

  const blockProps = useBlockProps.save();

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
