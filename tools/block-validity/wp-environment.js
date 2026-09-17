/**
 * Boots a headless copy of the WordPress block editor's JavaScript.
 *
 * The editor decides whether a block is valid by running the block's own
 * `save()` and comparing the result with the markup stored in the post. Only
 * JavaScript can do that, so this module loads the real script bundles that
 * ship with WordPress (`wp-includes/js/dist`) inside jsdom and hands back the
 * resulting `window`, with every core block registered.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const { JSDOM, VirtualConsole } = require( 'jsdom' );

/**
 * Dependencies WordPress declares in script-loader.php rather than in the
 * generated packages file, so the vendor bundles still load in the right order.
 *
 * @type {Object<string,string[]>}
 */
const VENDOR_DEPENDENCIES = {
	react: [ 'wp-polyfill' ],
	'react-dom': [ 'react' ],
	'react-jsx-runtime': [ 'react' ],
};

/**
 * Reads the handle => dependencies map WordPress generates for its own bundles.
 *
 * The file is a plain PHP array literal, e.g.
 *
 *     'blocks.js' => array(
 *         'dependencies' => array( 'wp-autop', 'wp-blob' ),
 *         'version' => 'abc123'
 *     ),
 *
 * @param {string} wpRoot Path to the WordPress install.
 * @return {Object<string,string[]>} Script handle mapped to its dependencies.
 */
function readPackageDependencies( wpRoot ) {
	const file = path.join(
		wpRoot,
		'wp-includes',
		'assets',
		'script-loader-packages.php'
	);

	if ( ! fs.existsSync( file ) ) {
		throw new Error(
			`Could not find ${ file }. Pass --wp with the path to a WordPress install.`
		);
	}

	const php = fs.readFileSync( file, 'utf8' );
	const dependencies = { ...VENDOR_DEPENDENCIES };
	const entry = /^\t'([^']+)\.js' => array\($/gm;

	let match;
	while ( ( match = entry.exec( php ) ) !== null ) {
		dependencies[ `wp-${ match[ 1 ] }` ] = readDependencyList(
			php,
			entry.lastIndex
		);
	}

	return dependencies;
}

/**
 * Pulls the `'dependencies' => array( … )` list that follows a given offset.
 *
 * @param {string} php    The PHP source.
 * @param {number} offset Where to start looking.
 * @return {string[]} The handles listed in that array.
 */
function readDependencyList( php, offset ) {
	const start = php.indexOf( "'dependencies' => array(", offset );
	if ( start === -1 ) {
		return [];
	}

	// Walk to the closing bracket so a nested `module_dependencies` array
	// further down the entry cannot leak into the result.
	let depth = 0;
	let end = php.indexOf( '(', start );
	for ( let i = end; i < php.length; i++ ) {
		if ( php[ i ] === '(' ) {
			depth++;
		} else if ( php[ i ] === ')' ) {
			depth--;
			if ( depth === 0 ) {
				end = i;
				break;
			}
		}
	}

	return ( php.slice( start, end ).match( /'([^']+)'/g ) || [] )
		.map( ( quoted ) => quoted.slice( 1, -1 ) )
		.filter( ( handle ) => handle !== 'dependencies' );
}

/**
 * Turns a script handle into the file that provides it.
 *
 * @param {string} handle Script handle, e.g. `wp-blocks` or `react-dom`.
 * @param {string} wpRoot Path to the WordPress install.
 * @return {string|null} Absolute path, or null when WordPress does not ship one.
 */
function resolveHandle( handle, wpRoot ) {
	const dist = path.join( wpRoot, 'wp-includes', 'js', 'dist' );
	const vendor = path.join( dist, 'vendor' );

	const candidates = [];
	if ( handle.startsWith( 'wp-' ) ) {
		candidates.push( path.join( dist, `${ handle.slice( 3 ) }.js` ) );
	}
	candidates.push( path.join( vendor, `${ handle }.js` ) );
	candidates.push( path.join( wpRoot, 'wp-includes', 'js', `${ handle }.js` ) );

	return candidates.find( ( file ) => fs.existsSync( file ) ) || null;
}

/**
 * Orders handles so every dependency is loaded before the script needing it.
 *
 * @param {string[]}                 roots        Handles to load.
 * @param {Object<string,string[]>}  dependencies Handle => dependencies map.
 * @return {string[]} Handles in load order.
 */
function sortByDependency( roots, dependencies ) {
	const ordered = [];
	const seen = new Set();

	const visit = ( handle, trail ) => {
		if ( seen.has( handle ) || trail.includes( handle ) ) {
			return;
		}
		( dependencies[ handle ] || [] ).forEach( ( dependency ) =>
			visit( dependency, trail.concat( handle ) )
		);
		seen.add( handle );
		ordered.push( handle );
	};

	roots.forEach( ( handle ) => visit( handle, [] ) );

	return ordered;
}

/**
 * Fills in the browser APIs jsdom leaves out but the editor bundles reach for.
 *
 * @param {Window} win The jsdom window.
 */
function addMissingBrowserApis( win ) {
	win.matchMedia =
		win.matchMedia ||
		( ( query ) => ( {
			media: query,
			matches: false,
			onchange: null,
			addListener() {},
			removeListener() {},
			addEventListener() {},
			removeEventListener() {},
			dispatchEvent: () => false,
		} ) );

	win.ResizeObserver =
		win.ResizeObserver ||
		class {
			observe() {}
			unobserve() {}
			disconnect() {}
		};

	win.IntersectionObserver =
		win.IntersectionObserver ||
		class {
			observe() {}
			unobserve() {}
			disconnect() {}
			takeRecords() {
				return [];
			}
		};

	win.CSS = win.CSS || {};
	win.CSS.supports = win.CSS.supports || ( () => false );
	win.CSS.escape =
		win.CSS.escape ||
		( ( value ) => String( value ).replace( /[^\w-]/g, '\\$&' ) );

	win.scrollTo = win.scrollTo || ( () => {} );

	// Nothing here should reach the network; fail loudly rather than hang.
	win.fetch =
		win.fetch ||
		( () => Promise.reject( new Error( 'Network access is disabled.' ) ) );
}

/**
 * Creates a window with WordPress's editor JavaScript loaded into it.
 *
 * @param {Object}   options             Options.
 * @param {string}   options.wpRoot      Path to the WordPress install.
 * @param {string[]} [options.roots]     Extra script handles to load.
 * @param {boolean}  [options.verbose]   Report each script as it loads.
 * @return {{ window: Window, wp: Object, failures: Array }} The booted environment.
 */
function createEditorWindow( { wpRoot, roots = [], verbose = false } ) {
	const dependencies = readPackageDependencies( wpRoot );
	const handles = sortByDependency(
		[ 'wp-blocks', 'wp-block-library', 'wp-block-editor' ].concat( roots ),
		dependencies
	);

	// The editor's stylesheets use `@layer`, which jsdom's CSS parser rejects,
	// and every block reports its API version on registration. Neither says
	// anything about the markup, so keep them out of the report.
	const virtualConsole = new VirtualConsole();
	virtualConsole.on( 'jsdomError', () => {} );
	if ( verbose ) {
		virtualConsole.on( 'error', ( message ) =>
			process.stderr.write( `${ message }\n` )
		);
	}

	const dom = new JSDOM(
		'<!doctype html><html><head></head><body></body></html>',
		{
			url: 'http://localhost/wp-admin/post.php?post=1&action=edit',
			runScripts: 'dangerously',
			pretendToBeVisual: true,
			virtualConsole,
		}
	);

	const win = dom.window;
	addMissingBrowserApis( win );
	win.wp = win.wp || {};
	win.wpApiSettings = { root: 'http://localhost/wp-json/', nonce: '0' };

	const context = dom.getInternalVMContext();
	const failures = [];
	const loaded = new Set();

	handles.forEach( ( handle ) => {
		const file = resolveHandle( handle, wpRoot );
		if ( ! file ) {
			// jQuery, Underscore and friends are not needed to run save().
			return;
		}
		try {
			new vm.Script( fs.readFileSync( file, 'utf8' ), {
				filename: file,
			} ).runInContext( context );
			loaded.add( handle );
			if ( verbose ) {
				process.stderr.write( `loaded ${ handle }\n` );
			}
		} catch ( error ) {
			failures.push( { handle, file, error } );
		}
	} );

	if ( ! win.wp.blocks || ! win.wp.blockLibrary ) {
		const reason = failures
			.map( ( failure ) => `${ failure.handle }: ${ failure.error.message }` )
			.join( '\n' );
		throw new Error(
			`WordPress editor scripts failed to load from ${ wpRoot }.\n${ reason }`
		);
	}

	win.wp.blockLibrary.registerCoreBlocks();

	return { window: win, wp: win.wp, context, failures, loaded };
}

/**
 * Feeds a block.json to the editor the way `register_block_type()` does.
 *
 * @param {Object} environment A window from createEditorWindow().
 * @param {string} file        Path to the block's block.json.
 */
function bootstrapBlockMetadata( environment, file ) {
	if ( ! fs.existsSync( file ) ) {
		return;
	}

	const bootstrap =
		environment.wp.blocks.unstable__bootstrapServerSideBlockDefinitions;
	if ( typeof bootstrap !== 'function' ) {
		return;
	}

	const metadata = JSON.parse( fs.readFileSync( file, 'utf8' ) );
	bootstrap( { [ metadata.name ]: metadata } );
}

/**
 * Registers the plugin's own blocks from a built `build/blocks` directory.
 *
 * @param {Object} environment       A window from createEditorWindow().
 * @param {string} blocksDir         Path to `build/blocks`.
 * @param {string} wpRoot            Path to the WordPress install.
 * @return {string[]} The block names that registered successfully.
 */
function registerPluginBlocks( environment, blocksDir, wpRoot ) {
	if ( ! fs.existsSync( blocksDir ) ) {
		return [];
	}

	const dependencies = readPackageDependencies( wpRoot );
	const registered = [];

	fs.readdirSync( blocksDir, { withFileTypes: true } )
		.filter( ( entry ) => entry.isDirectory() )
		.forEach( ( entry ) => {
			const dir = path.join( blocksDir, entry.name );
			const script = path.join( dir, 'index.js' );
			const asset = path.join( dir, 'index.asset.php' );
			if ( ! fs.existsSync( script ) ) {
				return;
			}

			// Load whatever the block's editor script depends on first. A
			// bundle that is already in the window must be left alone:
			// running it twice replaces the module and empties the block
			// registry with it.
			if ( fs.existsSync( asset ) ) {
				const php = fs.readFileSync( asset, 'utf8' );
				const needed = readDependencyList( php, 0 );
				sortByDependency( needed, dependencies ).forEach( ( handle ) => {
					if ( environment.loaded.has( handle ) ) {
						return;
					}
					const file = resolveHandle( handle, wpRoot );
					if ( ! file ) {
						return;
					}
					try {
						new vm.Script( fs.readFileSync( file, 'utf8' ), {
							filename: file,
						} ).runInContext( environment.context );
						environment.loaded.add( handle );
					} catch ( error ) {
						process.stderr.write(
							`Could not load ${ handle }: ${ error.message }\n`
						);
					}
				} );
			}

			// WordPress hands the editor each block's block.json from PHP;
			// without it registerBlockType() rejects the block for having no
			// title.
			bootstrapBlockMetadata( environment, path.join( dir, 'block.json' ) );

			try {
				new vm.Script( fs.readFileSync( script, 'utf8' ), {
					filename: script,
				} ).runInContext( environment.context );
				registered.push( entry.name );
			} catch ( error ) {
				process.stderr.write(
					`Could not register block ${ entry.name }: ${ error.message }\n`
				);
			}
		} );

	return registered;
}

module.exports = {
	createEditorWindow,
	registerPluginBlocks,
	readPackageDependencies,
	resolveHandle,
	sortByDependency,
};
