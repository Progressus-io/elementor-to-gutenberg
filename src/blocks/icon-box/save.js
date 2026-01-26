import { useBlockProps, RichText } from "@wordpress/block-editor";

export default function save({ attributes }) {
  const {
    icon,
    iconStyle,
    svgUrl,
    svgStyle,
    size,
    title,
    description,
    titleSize,
    titleColor,
    descriptionSize,
    descriptionColor,
    alignment,
    className,
    anchor,
  } = attributes;

  const wrapper_classes = arrayUnique(["wp-block-icon-box", className]).join(
    " "
  );

  const icon_html = svgUrl
      ? `<img src="${svgUrl}" alt="" style="${svgStyle ? svgStyle : `width:${size}px;height:auto;`}" class="svg-icon" />`
      : `<i class="${iconStyle} ${icon}" style="font-size:${size}px;"></i>`;

  return (
    <div
      className={wrapper_classes}
      style={{ textAlign: alignment }}
      id={anchor || undefined}
    >
      <div
        className="icon-box-icon"
        dangerouslySetInnerHTML={{ __html: icon_html }}
      />
      {title && (
        <RichText.Content
          tagName="h3"
          className="icon-box-title"
          value={title}
          style={{ fontSize: `${titleSize}px`, color: titleColor }}
        />
      )}
      {description && (
        <RichText.Content
          tagName="div"
          className="icon-box-description"
          value={description}
          style={{ fontSize: `${descriptionSize}px`, color: descriptionColor }}
        />
      )}
    </div>
  );
}

function arrayUnique(arr) {
  return Array.from(new Set((arr || []).filter(Boolean)));
}
