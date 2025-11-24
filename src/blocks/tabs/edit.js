import { __ } from "@wordpress/i18n";
import {
  useBlockProps,
  InspectorControls,
  InspectorAdvancedControls,
  PanelColorSettings,
  BlockControls,
  AlignmentToolbar,
  FontSizePicker,
} from "@wordpress/block-editor";
import {
  PanelBody,
  TextControl,
  SelectControl,
  Button,
  TextareaControl,
  RangeControl,
  __experimentalBoxControl as BoxControl,
  __experimentalBorderControl as BorderControl,
  ToolbarGroup,
  ToolbarButton,
  Flex,
  FlexItem,
} from "@wordpress/components";
import { useState } from "@wordpress/element";

const Edit = ({ attributes, setAttributes }) => {
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

  const [editingTab, setEditingTab] = useState(activeTab);
  const blockProps = useBlockProps();

  const addTab = () => {
    const newTabs = [
      ...tabs,
      {
        title: `Tab ${tabs.length + 1}`,
        content: `Content for tab ${tabs.length + 1}`,
      },
    ];
    setAttributes({ tabs: newTabs });
  };

  const removeTab = (index) => {
    if (tabs.length > 1) {
      const newTabs = tabs.filter((_, i) => i !== index);
      setAttributes({
        tabs: newTabs,
        activeTab: Math.min(activeTab, newTabs.length - 1),
      });
    }
  };

  const updateTab = (index, field, value) => {
    const newTabs = [...tabs];
    newTabs[index][field] = value;
    setAttributes({ tabs: newTabs });
  };

  const tabsStyle = {
    display: tabStyle === "vertical" ? "flex" : "block",
    flexDirection: tabStyle === "vertical" ? "row" : "column",
  };

  const tabHeaderStyle = {
    backgroundColor: tabColor,
    borderColor: borderColor,
    color: tabTextColor,
    padding: `${tabsPadding.top}px ${tabsPadding.right}px ${tabsPadding.bottom}px ${tabsPadding.left}px`,
    margin: `${tabsMargin.top}px ${tabsMargin.right}px ${tabsMargin.bottom}px ${tabsMargin.left}px`,
    cursor: "pointer",
    border: `${borderWidth}px ${borderStyle} ${borderColor || "#ddd"}`,
    borderRadius: `${borderRadius}px`,
    fontSize: tabTypography.fontSize
      ? `${tabTypography.fontSize}px`
      : undefined,
    fontWeight: tabTypography.fontWeight || undefined,
    lineHeight: tabTypography.lineHeight || undefined,
    fontFamily: tabTypography.fontFamily || undefined,
  };

  const activeTabHeaderStyle = {
    ...tabHeaderStyle,
    backgroundColor: activeTabColor,
    color: activeTabTextColor || tabTextColor,
  };

  const contentStyle = {
    backgroundColor: contentBackgroundColor,
    color: contentTextColor,
    padding: `${contentPadding.top}px ${contentPadding.right}px ${contentPadding.bottom}px ${contentPadding.left}px`,
    margin: `${contentMargin.top}px ${contentMargin.right}px ${contentMargin.bottom}px ${contentMargin.left}px`,
    border: `${borderWidth}px ${borderStyle} ${borderColor || "#ddd"}`,
    borderRadius: `${borderRadius}px`,
    borderTop:
      tabStyle === "horizontal"
        ? "none"
        : `${borderWidth}px ${borderStyle} ${borderColor || "#ddd"}`,
    fontSize: contentTypography.fontSize
      ? `${contentTypography.fontSize}px`
      : undefined,
    fontWeight: contentTypography.fontWeight || undefined,
    lineHeight: contentTypography.lineHeight || undefined,
    fontFamily: contentTypography.fontFamily || undefined,
  };

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__("Settings", "progressus-gutenberg")}
          initialOpen={true}
        >
          <SelectControl
            label={__("Tab Style", "progressus-gutenberg")}
            value={tabStyle}
            options={[
              {
                label: __("Horizontal", "progressus-gutenberg"),
                value: "horizontal",
              },
              {
                label: __("Vertical", "progressus-gutenberg"),
                value: "vertical",
              },
            ]}
            onChange={(value) => setAttributes({ tabStyle: value })}
          />
        </PanelBody>

        <PanelColorSettings
          title={__("Background Colors", "progressus-gutenberg")}
          initialOpen={false}
          colorSettings={[
            {
              value: tabColor,
              onChange: (color) => setAttributes({ tabColor: color }),
              label: __("Tab Background", "progressus-gutenberg"),
            },
            {
              value: activeTabColor,
              onChange: (color) => setAttributes({ activeTabColor: color }),
              label: __("Active Tab Background", "progressus-gutenberg"),
            },
            {
              value: contentBackgroundColor,
              onChange: (color) =>
                setAttributes({ contentBackgroundColor: color }),
              label: __("Content Background", "progressus-gutenberg"),
            },
          ]}
        />

        <PanelColorSettings
          title={__("Text Colors", "progressus-gutenberg")}
          initialOpen={false}
          colorSettings={[
            {
              value: tabTextColor,
              onChange: (color) => setAttributes({ tabTextColor: color }),
              label: __("Tab Text", "progressus-gutenberg"),
            },
            {
              value: activeTabTextColor,
              onChange: (color) => setAttributes({ activeTabTextColor: color }),
              label: __("Active Tab Text", "progressus-gutenberg"),
            },
            {
              value: contentTextColor,
              onChange: (color) => setAttributes({ contentTextColor: color }),
              label: __("Content Text", "progressus-gutenberg"),
            },
          ]}
        />

        <PanelBody
          title={__("Typography", "progressus-gutenberg")}
          initialOpen={false}
        >
          <div style={{ marginBottom: "20px" }}>
            <h3
              style={{
                margin: "0 0 12px 0",
                fontSize: "13px",
                fontWeight: "500",
                color: "#1e1e1e",
              }}
            >
              {__("Tab Typography", "progressus-gutenberg")}
            </h3>

            <FontSizePicker
              value={tabTypography.fontSize}
              onChange={(value) =>
                setAttributes({
                  tabTypography: { ...tabTypography, fontSize: value },
                })
              }
              __nextHasNoMarginBottom
            />

            <TextControl
              label={__("Font Family", "progressus-gutenberg")}
              value={tabTypography.fontFamily}
              onChange={(value) =>
                setAttributes({
                  tabTypography: { ...tabTypography, fontFamily: value },
                })
              }
              placeholder={__("Default", "progressus-gutenberg")}
              __nextHasNoMarginBottom
            />

            <SelectControl
              label={__("Font Weight", "progressus-gutenberg")}
              value={tabTypography.fontWeight}
              options={[
                { label: __("Default", "progressus-gutenberg"), value: "" },
                {
                  label: __("Normal", "progressus-gutenberg"),
                  value: "normal",
                },
                { label: __("Bold", "progressus-gutenberg"), value: "bold" },
                { label: "100", value: "100" },
                { label: "200", value: "200" },
                { label: "300", value: "300" },
                { label: "400", value: "400" },
                { label: "500", value: "500" },
                { label: "600", value: "600" },
                { label: "700", value: "700" },
                { label: "800", value: "800" },
                { label: "900", value: "900" },
              ]}
              onChange={(value) =>
                setAttributes({
                  tabTypography: { ...tabTypography, fontWeight: value },
                })
              }
              __nextHasNoMarginBottom
            />

            <RangeControl
              label={__("Line Height", "progressus-gutenberg")}
              value={tabTypography.lineHeight}
              onChange={(value) =>
                setAttributes({
                  tabTypography: { ...tabTypography, lineHeight: value },
                })
              }
              min={1}
              max={3}
              step={0.1}
              __nextHasNoMarginBottom
            />
          </div>

          <div>
            <h3
              style={{
                margin: "0 0 12px 0",
                fontSize: "13px",
                fontWeight: "500",
                color: "#1e1e1e",
              }}
            >
              {__("Content Typography", "progressus-gutenberg")}
            </h3>

            <FontSizePicker
              value={contentTypography.fontSize}
              onChange={(value) =>
                setAttributes({
                  contentTypography: { ...contentTypography, fontSize: value },
                })
              }
              __nextHasNoMarginBottom
            />

            <TextControl
              label={__("Font Family", "progressus-gutenberg")}
              value={contentTypography.fontFamily}
              onChange={(value) =>
                setAttributes({
                  contentTypography: {
                    ...contentTypography,
                    fontFamily: value,
                  },
                })
              }
              placeholder={__("Default", "progressus-gutenberg")}
              __nextHasNoMarginBottom
            />

            <SelectControl
              label={__("Font Weight", "progressus-gutenberg")}
              value={contentTypography.fontWeight}
              options={[
                { label: __("Default", "progressus-gutenberg"), value: "" },
                {
                  label: __("Normal", "progressus-gutenberg"),
                  value: "normal",
                },
                { label: __("Bold", "progressus-gutenberg"), value: "bold" },
                { label: "100", value: "100" },
                { label: "200", value: "200" },
                { label: "300", value: "300" },
                { label: "400", value: "400" },
                { label: "500", value: "500" },
                { label: "600", value: "600" },
                { label: "700", value: "700" },
                { label: "800", value: "800" },
                { label: "900", value: "900" },
              ]}
              onChange={(value) =>
                setAttributes({
                  contentTypography: {
                    ...contentTypography,
                    fontWeight: value,
                  },
                })
              }
              __nextHasNoMarginBottom
            />

            <RangeControl
              label={__("Line Height", "progressus-gutenberg")}
              value={contentTypography.lineHeight}
              onChange={(value) =>
                setAttributes({
                  contentTypography: {
                    ...contentTypography,
                    lineHeight: value,
                  },
                })
              }
              min={1}
              max={3}
              step={0.1}
              __nextHasNoMarginBottom
            />
          </div>
        </PanelBody>

        <PanelBody
          title={__("Border", "progressus-gutenberg")}
          initialOpen={false}
        >
          <BorderControl
            label={__("Border", "progressus-gutenberg")}
            value={{
              color: borderColor,
              style: borderStyle,
              width: borderWidth + "px",
            }}
            onChange={(value) => {
              setAttributes({
                borderColor: value?.color || "",
                borderStyle: value?.style || "solid",
                borderWidth: parseInt(value?.width) || 1,
              });
            }}
            __nextHasNoMarginBottom
          />

          <RangeControl
            label={__("Border Radius", "progressus-gutenberg")}
            value={borderRadius}
            onChange={(value) => setAttributes({ borderRadius: value })}
            min={0}
            max={50}
            __nextHasNoMarginBottom
          />
        </PanelBody>

        <PanelBody
          title={__("Dimensions", "progressus-gutenberg")}
          initialOpen={false}
        >
          <BoxControl
            label={__("Tab Padding", "progressus-gutenberg")}
            values={{
              top: tabsPadding.top + "px",
              right: tabsPadding.right + "px",
              bottom: tabsPadding.bottom + "px",
              left: tabsPadding.left + "px",
            }}
            onChange={(value) => {
              setAttributes({
                tabsPadding: {
                  top: parseInt(value.top) || 0,
                  right: parseInt(value.right) || 0,
                  bottom: parseInt(value.bottom) || 0,
                  left: parseInt(value.left) || 0,
                },
              });
            }}
            __nextHasNoMarginBottom
          />

          <BoxControl
            label={__("Content Padding", "progressus-gutenberg")}
            values={{
              top: contentPadding.top + "px",
              right: contentPadding.right + "px",
              bottom: contentPadding.bottom + "px",
              left: contentPadding.left + "px",
            }}
            onChange={(value) => {
              setAttributes({
                contentPadding: {
                  top: parseInt(value.top) || 0,
                  right: parseInt(value.right) || 0,
                  bottom: parseInt(value.bottom) || 0,
                  left: parseInt(value.left) || 0,
                },
              });
            }}
            __nextHasNoMarginBottom
          />

          <BoxControl
            label={__("Tab Margin", "progressus-gutenberg")}
            values={{
              top: tabsMargin.top + "px",
              right: tabsMargin.right + "px",
              bottom: tabsMargin.bottom + "px",
              left: tabsMargin.left + "px",
            }}
            onChange={(value) => {
              setAttributes({
                tabsMargin: {
                  top: parseInt(value.top) || 0,
                  right: parseInt(value.right) || 0,
                  bottom: parseInt(value.bottom) || 0,
                  left: parseInt(value.left) || 0,
                },
              });
            }}
            __nextHasNoMarginBottom
          />

          <BoxControl
            label={__("Content Margin", "progressus-gutenberg")}
            values={{
              top: contentMargin.top + "px",
              right: contentMargin.right + "px",
              bottom: contentMargin.bottom + "px",
              left: contentMargin.left + "px",
            }}
            onChange={(value) => {
              setAttributes({
                contentMargin: {
                  top: parseInt(value.top) || 0,
                  right: parseInt(value.right) || 0,
                  bottom: parseInt(value.bottom) || 0,
                  left: parseInt(value.left) || 0,
                },
              });
            }}
            __nextHasNoMarginBottom
          />
        </PanelBody>

        <PanelBody
          title={__("Content", "progressus-gutenberg")}
          initialOpen={false}
        >
          <div style={{ marginBottom: "16px" }}>
            <Button isPrimary onClick={addTab} style={{ width: "100%" }}>
              {__("Add Tab", "progressus-gutenberg")}
            </Button>
          </div>

          {tabs.map((tab, index) => (
            <div
              key={index}
              style={{
                marginBottom: "16px",
                padding: "12px",
                backgroundColor: "#f9f9f9",
                borderRadius: "4px",
                border:
                  index === activeTab ? "2px solid #007cba" : "1px solid #ddd",
              }}
            >
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  alignItems: "center",
                  marginBottom: "8px",
                }}
              >
                <strong style={{ fontSize: "13px", color: "#1e1e1e" }}>
                  {__("Tab", "progressus-gutenberg")} {index + 1}
                  {index === activeTab && (
                    <span style={{ color: "#007cba", marginLeft: "8px" }}>
                      ({__("Active", "progressus-gutenberg")})
                    </span>
                  )}
                </strong>
                <Button
                  isDestructive
                  variant="tertiary"
                  size="small"
                  onClick={() => removeTab(index)}
                  disabled={tabs.length <= 1}
                >
                  {__("Remove", "progressus-gutenberg")}
                </Button>
              </div>

              <TextControl
                label={__("Title", "progressus-gutenberg")}
                value={tab.title}
                onChange={(value) => updateTab(index, "title", value)}
                __nextHasNoMarginBottom
              />

              <TextareaControl
                label={__("Content", "progressus-gutenberg")}
                value={tab.content}
                onChange={(value) => updateTab(index, "content", value)}
                rows={3}
                __nextHasNoMarginBottom
              />
            </div>
          ))}
        </PanelBody>
      </InspectorControls>

      <BlockControls>
        <ToolbarGroup>
          <ToolbarButton
            icon="admin-page"
            label={__("Add Tab", "progressus-gutenberg")}
            onClick={addTab}
          />
        </ToolbarGroup>
      </BlockControls>

      <div {...blockProps}>
        <div className="progressus-tabs" style={tabsStyle}>
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
                onClick={() => setAttributes({ activeTab: index })}
              >
                {tab.title}
              </div>
            ))}
          </div>

          <div className="progressus-tabs-content" style={contentStyle}>
            <div className="progressus-tab-content">
              {tabs[activeTab]?.content || ""}
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default Edit;
