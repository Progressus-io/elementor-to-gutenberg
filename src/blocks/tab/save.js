import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const Save = () => {
	const blockProps = useBlockProps.save( {
		className: 'blockshift-tab-content',
	} );

	return <div { ...useInnerBlocksProps.save( blockProps ) } />;
};

export default Save;
