/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @return {Element} Element to render.
 */

import { useBlockProps } from "@wordpress/block-editor";

export default function save({ attributes }) {
  const {
    icon,
    svg,
    svgUrl,
    svgStyle,
    iconStyle,
    size,
    color,
    backgroundColor,
    borderRadius,
    padding,
    alignment,
    hoverColor,
    hoverBackgroundColor,
    hoverEffect,
    link,
    linkTarget,
    ariaLabel,
  } = attributes;

  const blockProps = useBlockProps.save({
    className: `fontawesome-icon-align-${alignment}`,
    style: {
      textAlign: alignment,
    },
  });

  const iconStyles = {
    fontSize: `${size}px`,
    color: color,
    backgroundColor: backgroundColor || "transparent",
    borderRadius: borderRadius ? `${borderRadius}px` : "0",
    padding: padding ? `${padding}px` : "0",
    display: "inline-block",
    lineHeight: 1,
    transition: "all 0.3s ease",
    width: "auto",
    height: "auto",
  };

  // Create custom CSS variables for hover effects
  const customProperties = { ...iconStyles };
  if (hoverColor) {
    customProperties["--fontawesome-icon-hover-color"] = hoverColor;
  }
  if (hoverBackgroundColor) {
    customProperties["--fontawesome-icon-hover-bg"] = hoverBackgroundColor;
  }

  let iconElement = null;

  if (svg) {
    iconElement = (
      <span
        dangerouslySetInnerHTML={{ __html: svg }}
        aria-label={ariaLabel}
        aria-hidden={!ariaLabel ? "true" : "false"}
        data-hover-effect={hoverEffect}
      />
    );
  } else if (svgUrl) {
    if (svgStyle) {
      const parseStyleString = (str) => {
        return str.split(";").reduce((acc, rule) => {
          const [prop, val] = rule.split(":").map((s) => s && s.trim());
          if (!prop || !val) return acc;
          const jsProp = prop.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
          acc[jsProp] = val;
          return acc;
        }, {});
      };

      const parsedStyle = parseStyleString(svgStyle);

      iconElement = (
        <img
          src={svgUrl}
          alt={ariaLabel || ""}
          className="svg-icon"
          style={parsedStyle}
        />
      );
    } else {
      const imgStyles = {
        width: `${size}px`,
        height: "auto",
        display: "inline-block",
      };

      iconElement = (
        <img
          src={svgUrl}
          alt={ariaLabel || ""}
          className="svg-icon"
          style={imgStyles}
        />
      );
    }
  } else {
    iconElement = (
      <i
        className={`${iconStyle} ${icon} fontawesome-icon-hover-${hoverEffect}`}
        style={customProperties}
        aria-label={ariaLabel}
        aria-hidden={!ariaLabel ? "true" : "false"}
        data-hover-effect={hoverEffect}
        data-icon={icon}
        data-icon-style={iconStyle}
      />
    );
  }

  return (
    <div {...blockProps}>
      {link ? (
        <a
          href={link}
          target={linkTarget ? "_blank" : "_self"}
          rel={linkTarget ? "noopener noreferrer" : ""}
          aria-label={ariaLabel}
        >
          {iconElement}
        </a>
      ) : (
        iconElement
      )}
    </div>
  );
}
