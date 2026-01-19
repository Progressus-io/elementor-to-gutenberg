import { useBlockProps, RichText } from "@wordpress/block-editor";

export default function save({ attributes }) {
  const {
    imageUrl,
    imageAlt,
    imageWidth,
    imageHeight,
    title,
    description,
    titleSize,
    titleColor,
    titleFontFamily,
    titleFontWeight,
    titleTextTransform,
    titleFontStyle,
    titleTextDecoration,
    titleLineHeight,
    titleLetterSpacing,
    titleWordSpacing,
    descriptionSize,
    descriptionColor,
    descriptionFontFamily,
    descriptionFontWeight,
    descriptionTextTransform,
    descriptionFontStyle,
    descriptionTextDecoration,
    descriptionLineHeight,
    descriptionLetterSpacing,
    descriptionWordSpacing,
    objectFit,
    objectPosition,
    link,
    linkTarget,
    nofollow,
    align,
    className,
    anchor,
  } = attributes;

  const wrapper_classes = arrayUnique([
    "wp-block-image-box",
    className,
  ])
    .filter(Boolean)
    .join(" ");

  // Build title styles
  const titleStyles = {
    fontSize: `${titleSize}px`,
    color: titleColor,
    fontFamily: titleFontFamily || undefined,
    fontWeight: titleFontWeight || undefined,
    textTransform: titleTextTransform || undefined,
    fontStyle: titleFontStyle || undefined,
    textDecoration: titleTextDecoration || undefined,
    lineHeight: titleLineHeight ? String(titleLineHeight) : undefined,
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
  };

  const titleElement = title && (
    <RichText.Content
      tagName="h3"
      className="image-box-title"
      value={title}
      style={titleStyles}
    />
  );

  const imageElement = imageUrl && (
    <div className="image-box-image">
      <img
        src={imageUrl}
        alt={imageAlt || ""}
        style={{
          width: `${imageWidth}px`,
          height: `${imageHeight}px`,
          objectFit: objectFit || "cover",
          objectPosition: objectPosition || "center center",
          display: "block",
        }}
      />
    </div>
  );

  return (
    <div
      className={wrapper_classes}
      style={{ textAlign: align || "left" }}
      id={anchor || undefined}
    >
      {imageElement}
      {link && (title || imageUrl) ? (
        <a
          href={link}
          target={linkTarget ? "_blank" : undefined}
          rel={nofollow ? "nofollow" : undefined}
          aria-label={title}
        >
          {titleElement}
        </a>
      ) : (
        titleElement
      )}
      {description && (
        <RichText.Content
          tagName="div"
          className="image-box-description"
          value={description}
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
          }}
        />
      )}
    </div>
  );
}

function arrayUnique(arr) {
  return Array.from(new Set((arr || []).filter(Boolean)));
}
