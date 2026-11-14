import {
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

const Edit = ( { clientId, context } ) => {
	const index = useSelect(
		( select ) => select( blockEditorStore ).getBlockIndex( clientId ),
		[ clientId ]
	);
	const activeTab = context[ 'blockshift/activeTab' ] || 0;

	const blockProps = useBlockProps( {
		className: 'blockshift-tab-content',
		style: { display: index === activeTab ? 'block' : 'none' },
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: [ [ 'core/paragraph' ] ],
	} );

	return <div { ...innerBlocksProps } />;
};

export default Edit;
