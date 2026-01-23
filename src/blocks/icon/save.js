import { useBlockProps } from "@wordpress/block-editor";

export default function save({ attributes }) {
  const {
    className,
    svg,
    svgUrl,
    svgStyle,
    size,
    alignment,
    hoverEffect,
    link,
    linkTarget,
    ariaLabel,
  } = attributes;

  const alignmentValue = alignment ? alignment : "left";
  const alignClass = `fontawesome-icon-align-${alignmentValue}`;

  const shouldAddAlignClass = !className || className.indexOf(alignClass) === -1;

  const blockProps = useBlockProps.save({
    className: shouldAddAlignClass ? alignClass : undefined,
    style: { textAlign: alignmentValue },
  });

  const parseStyleString = (str) => {
    return str.split(";").reduce((acc, rule) => {
      const parts = rule.split(":");
      const prop = parts[0] ? parts[0].trim() : "";
      const val = parts[1] ? parts.slice(1).join(":").trim() : "";
      if (!prop || !val) return acc;
      const jsProp = prop.replace(/-([a-z])/g, function(_, c) { return c.toUpperCase(); });
      acc[jsProp] = val;
      return acc;
    }, {});
  };

  let iconElement = null;

  if (svg) {
    iconElement = (
        <span
            className={`gutenberg-icon-svg fontawesome-icon-hover-${hoverEffect || "none"}`}
            dangerouslySetInnerHTML={{ __html: svg }}
            aria-hidden={ariaLabel ? "false" : "true"}
            data-hover-effect={hoverEffect || "none"}
        />
    );
  } else if (svgUrl) {
    const imgStyles = svgStyle
        ? parseStyleString(svgStyle)
        : { width: `${size || 35}px`, height: "auto", display: "inline-block" };

    iconElement = (
        <img
            src={svgUrl}
            alt={ariaLabel || ""}
            className="svg-icon"
            style={imgStyles}
        />
    );
  } else {
    iconElement = null;
  }

  const linkProps = link
      ? {
        href: link,
        target: linkTarget || undefined,
        rel: linkTarget === "_blank" ? "noopener noreferrer" : undefined,
        "aria-label": ariaLabel,
      }
      : null;

  return (
      <div {...blockProps}>
        {link ? <a {...linkProps}>{iconElement}</a> : iconElement}
      </div>
  );
}
