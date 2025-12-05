import { useBlockProps } from "@wordpress/block-editor";

const Save = ({ attributes }) => {
  const {
    tabs,
    activeTab,
    tabStyle,
    tabColor,
    activeTabColor,
    contentBackgroundColor,
    borderColor,
    borderWidth,
    borderStyle,
    borderRadius,
    tabsPadding,
    contentPadding,
    tabsMargin,
    contentMargin,
    tabTextColor,
    activeTabTextColor,
    contentTextColor,
    tabTypography,
    contentTypography,
  } = attributes;

  const blockProps = useBlockProps.save();

  const tabsStyle = {
    display: tabStyle === "vertical" ? "flex" : "block",
    flexDirection: tabStyle === "vertical" ? "row" : "column",
  };

  const tabHeaderStyle = {
    backgroundColor: tabColor,
    borderColor: borderColor,
    color: tabTextColor,
    padding: `${tabsPadding?.top || 12}px ${tabsPadding?.right || 16}px ${
      tabsPadding?.bottom || 12
    }px ${tabsPadding?.left || 16}px`,
    margin: `${tabsMargin?.top || 0}px ${tabsMargin?.right || 2}px ${
      tabsMargin?.bottom || 0
    }px ${tabsMargin?.left || 0}px`,
    cursor: "pointer",
    border: `${borderWidth || 1}px ${borderStyle || "solid"} ${
      borderColor || "#ddd"
    }`,
    borderRadius: `${borderRadius || 4}px`,
    fontSize: tabTypography?.fontSize
      ? `${tabTypography.fontSize}px`
      : undefined,
    fontWeight: tabTypography?.fontWeight || undefined,
    lineHeight: tabTypography?.lineHeight || undefined,
    fontFamily: tabTypography?.fontFamily || undefined,
  };

  const activeTabHeaderStyle = {
    ...tabHeaderStyle,
    backgroundColor: activeTabColor,
    color: activeTabTextColor || tabTextColor,
  };

  const contentStyle = {
    backgroundColor: contentBackgroundColor,
    color: contentTextColor,
    padding: `${contentPadding?.top || 20}px ${contentPadding?.right || 20}px ${
      contentPadding?.bottom || 20
    }px ${contentPadding?.left || 20}px`,
    margin: `${contentMargin?.top || 0}px ${contentMargin?.right || 0}px ${
      contentMargin?.bottom || 0
    }px ${contentMargin?.left || 0}px`,
    border: `${borderWidth || 1}px ${borderStyle || "solid"} ${
      borderColor || "#ddd"
    }`,
    borderRadius: `${borderRadius || 4}px`,
    borderTop:
      tabStyle === "horizontal"
        ? "none"
        : `${borderWidth || 1}px ${borderStyle || "solid"} ${
            borderColor || "#ddd"
          }`,
    fontSize: contentTypography?.fontSize
      ? `${contentTypography.fontSize}px`
      : undefined,
    fontWeight: contentTypography?.fontWeight || undefined,
    lineHeight: contentTypography?.lineHeight || undefined,
    fontFamily: contentTypography?.fontFamily || undefined,
  };

  return (
    <div {...blockProps}>
      <div
        className="progressus-tabs"
        style={tabsStyle}
        data-tab-style={tabStyle}
        data-active-tab={activeTab}
      >
        <div
          className="progressus-tabs-headers"
          style={{
            display: "flex",
            flexDirection: tabStyle === "vertical" ? "column" : "row",
          }}
        >
          {tabs.map((tab, index) => (
            <div
              key={index}
              className={`progressus-tab-header ${
                index === activeTab ? "active" : ""
              }`}
              style={
                index === activeTab ? activeTabHeaderStyle : tabHeaderStyle
              }
              data-tab-index={index}
            >
              {tab.title}
            </div>
          ))}
        </div>

        <div className="progressus-tabs-content" style={contentStyle}>
          {tabs.map((tab, index) => (
            <div
              key={index}
              className={`progressus-tab-content ${
                index === activeTab ? "active" : ""
              }`}
              style={{ display: index === activeTab ? "block" : "none" }}
            >
              {tab.content}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default Save;
