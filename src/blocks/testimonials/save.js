import { useBlockProps } from "@wordpress/block-editor";

const Save = ({ attributes }) => {
  const {
    slides,
    layout,
    alignment,
    slidesPerView,
    spaceBetween,
    width,
    slideBackgroundColor,
    slideBorderSize,
    slideBorderRadius,
    slideBorderColor,
    slidePadding,
    contentGap,
    contentColor,
    contentTypography,
    nameColor,
    titleColor,
    imageSize,
    imageGap,
    imageBorderRadius,
    arrowsSize,
    arrowsColor,
    paginationGap,
    paginationSize,
    paginationColorInactive,
    _margin,
    _padding,
    customId,
    customClass,
  } = attributes;

  const blockProps = useBlockProps.save({
    className: customClass,
    id: customId,
  });

  const containerStyle = {
    margin: `${_margin.top}px ${_margin.right}px ${_margin.bottom}px ${_margin.left}px`,
    padding: `${_padding.top}px ${_padding.right}px ${_padding.bottom}px ${_padding.left}px`,
  };

  return (
    <div {...blockProps} style={containerStyle}>
      <div
        className="progressus-testimonials-carousel"
        data-slides-per-view={slidesPerView}
        data-space-between={spaceBetween}
        data-arrows-size={arrowsSize}
        data-arrows-color={arrowsColor}
        data-pagination-gap={paginationGap}
        data-pagination-size={paginationSize}
        data-pagination-color={paginationColorInactive}
      >
        <div className="swiper">
          <div className="swiper-wrapper">
            {slides.map((slide, index) => {
              const getImageStyle = () => {
                const baseStyle = {
                  width: `${imageSize}px`,
                  height: `${imageSize}px`,
                  borderRadius: `${imageBorderRadius}%`,
                  objectFit: "cover",
                };

                if (layout === "image_above") {
                  baseStyle.marginBottom = `${imageGap}px`;
                } else if (layout === "image_inline") {
                  baseStyle.marginRight = `${imageGap}px`;
                } else if (layout === "image_stacked") {
                  baseStyle.marginTop = `${contentGap}px`;
                  baseStyle.marginBottom = `${imageGap}px`;
                } else if (layout === "image_left") {
                  baseStyle.marginRight = `${imageGap}px`;
                } else if (layout === "image_right") {
                  baseStyle.marginLeft = `${imageGap}px`;
                }

                return baseStyle;
              };

              const renderImage = () => {
                if (!slide.imageUrl) return null;
                return (
                  <img
                    src={slide.imageUrl}
                    alt={slide.name}
                    style={getImageStyle()}
                  />
                );
              };

              const renderNameTitle = () => (
                <>
                  <div
                    className="testimonial-name"
                    style={{
                      color: nameColor,
                      fontWeight: "bold",
                      marginBottom: "5px",
                    }}
                  >
                    {slide.name}
                  </div>
                  <div
                    className="testimonial-title"
                    style={{
                      color: titleColor,
                      fontSize: "14px",
                    }}
                  >
                    {slide.title}
                  </div>
                </>
              );

              const renderContent = () => (
                <div
                  className="testimonial-content"
                  style={{
                    color: contentColor,
                    fontSize: `${contentTypography.fontSize}px`,
                    fontWeight: contentTypography.fontWeight,
                    fontStyle: contentTypography.fontStyle,
                    textDecoration: contentTypography.textDecoration,
                    lineHeight: `${contentTypography.lineHeight}px`,
                    letterSpacing: `${contentTypography.letterSpacing}px`,
                    wordSpacing: `${contentTypography.wordSpacing}px`,
                    fontFamily: contentTypography.fontFamily,
                    marginBottom: `${contentGap}px`,
                  }}
                >
                  {slide.content}
                </div>
              );

              const getSlideContent = () => {
                if (layout === "image_above") {
                  return (
                    <>
                      {renderImage()}
                      {renderContent()}
                      {renderNameTitle()}
                    </>
                  );
                } else if (layout === "image_inline") {
                  return (
                    <>
                      {renderContent()}
                      <div style={{ display: "flex", alignItems: "center" }}>
                        {renderImage()}
                        <div>{renderNameTitle()}</div>
                      </div>
                    </>
                  );
                } else if (layout === "image_stacked") {
                  return (
                    <>
                      {renderContent()}
                      {renderImage()}
                      {renderNameTitle()}
                    </>
                  );
                } else if (layout === "image_left") {
                  return (
                    <div style={{ display: "flex" }}>
                      {renderImage()}
                      <div style={{ flex: 1 }}>
                        {renderContent()}
                        {renderNameTitle()}
                      </div>
                    </div>
                  );
                } else if (layout === "image_right") {
                  return (
                    <div style={{ display: "flex" }}>
                      <div style={{ flex: 1 }}>
                        {renderContent()}
                        {renderNameTitle()}
                      </div>
                      {renderImage()}
                    </div>
                  );
                }
              };

              return (
                <div key={index} className="swiper-slide">
                  <div
                    className={`testimonial-slide layout-${layout}`}
                    style={{
                      backgroundColor: slideBackgroundColor,
                      border: `${slideBorderSize.top}px solid ${
                        slideBorderColor || "#ddd"
                      }`,
                      borderRadius: `${slideBorderRadius}px`,
                      padding: `${slidePadding.top}px ${slidePadding.right}px ${slidePadding.bottom}px ${slidePadding.left}px`,
                      textAlign: alignment,
                      maxWidth: `${width}%`,
                      margin: "0 auto",
                    }}
                  >
                    {getSlideContent()}
                  </div>
                </div>
              );
            })}
          </div>
          <div className="swiper-button-prev"></div>
          <div className="swiper-button-next"></div>
          <div className="swiper-pagination"></div>
        </div>
      </div>
    </div>
  );
};

export default Save;
