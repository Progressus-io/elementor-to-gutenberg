/**
 * Save component for progressus/testimonial block.
 *
 * IMPORTANT: The HTML structure produced here must exactly match what
 * class-testimonial-widget-handler.php generates when converting from Elementor.
 * Any structural change here requires an equivalent change in the PHP handler.
 */

/**
 * Format a TRBL object (with .top/.right/.bottom/.left/.unit) into a CSS shorthand string.
 * Mirrors the PHP resolve_trbl_css() helper.
 *
 * Returns empty string when obj is falsy.
 */
function formatTRBL(obj) {
  if (!obj) return "";
  const unit = obj.unit || "px";
  const top = String(obj.top ?? "0");
  const right = String(obj.right ?? "0");
  const bottom = String(obj.bottom ?? "0");
  const left = String(obj.left ?? "0");
  const vals = [
    `${top}${unit}`,
    `${right}${unit}`,
    `${bottom}${unit}`,
    `${left}${unit}`,
  ];
  const allSame = vals.every((v) => v === vals[0]);
  if (allSame) return vals[0];
  if (vals[0] === vals[2] && vals[1] === vals[3])
    return `${vals[0]} ${vals[1]}`;
  return vals.join(" ");
}

export default function save({ attributes }) {
  const {
    content,
    name,
    job,
    alignment,
    imageUrl,
    imageSize,
    imageBorderRadius,
    imageBorderWidth,
    imageBorderColor,
    customId,
    customClass,
  } = attributes;

  // ── Wrapper classes (order must match PHP handler) ──────────────────────
  const wrapperClasses = [
    "wp-block-progressus-testimonial",
    "testimonial-widget",
    `has-text-align-${alignment}`,
    customClass,
  ]
    .filter(Boolean)
    .join(" ");

  // ── Image styles (order must match PHP handler) ─────────────────────────
  const imageStyle = {
    width: `${imageSize}px`,
    height: `${imageSize}px`,
    objectFit: "cover",
    display: "block",
    flexShrink: "0",
  };

  const borderRadiusCss = formatTRBL(imageBorderRadius);
  if (borderRadiusCss && borderRadiusCss !== "0px") {
    imageStyle.borderRadius = borderRadiusCss;
  }

  const borderWidthCss = formatTRBL(imageBorderWidth);
  const hasBorder = borderWidthCss && borderWidthCss !== "0px";
  if (hasBorder) {
    imageStyle.borderWidth = borderWidthCss;
    imageStyle.borderStyle = "solid";
    if (imageBorderColor) {
      imageStyle.borderColor = imageBorderColor;
    }
  }

  // ── Author / meta visibility flags ──────────────────────────────────────
  const hasAuthor = imageUrl || name || job;
  const hasMeta = name || job;

  return (
    <div
      className={wrapperClasses}
      id={customId || undefined}
      style={{ textAlign: alignment }}
    >
      {/* Quote content */}
      {content && (
        <div className="testimonial-content">
          <p>{content}</p>
        </div>
      )}

      {/* Author row: avatar beside name / job */}
      {hasAuthor && (
        <div
          className="testimonial-author"
          style={{
            display: "flex",
            flexDirection: "row",
            alignItems: "center",
            gap: "12px",
            marginTop: "16px",
          }}
        >
          {imageUrl && (
            <img
              src={imageUrl}
              alt={name}
              className="testimonial-image"
              style={imageStyle}
            />
          )}

          {hasMeta && (
            <div
              className="testimonial-meta"
              style={{
                display: "flex",
                flexDirection: "column",
                justifyContent: "center",
                gap: "2px",
              }}
            >
              {name && <strong className="testimonial-name">{name}</strong>}
              {job && <span className="testimonial-job">{job}</span>}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
