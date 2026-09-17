#!/usr/bin/env node
/**
 * Reports blocks the WordPress editor would refuse to validate.
 *
 * The editor compares the markup saved in a post with the markup the block's
 * `save()` produces; when they differ it shows "This block contains unexpected
 * or invalid content". This runs that same comparison from the command line so
 * a conversion can be checked without opening the editor.
 *
 * Usage:
 *   node validate.js [options] <file|directory>…
 *
 * Options:
 *   --wp <path>       WordPress install to take the editor scripts from.
 *                     Defaults to the install this plugin sits in.
 *   --blocks <path>   The plugin's built blocks (default: ../../build/blocks).
 *   --json            Emit the report as JSON.
 *   --quiet           Only list invalid blocks, no per-file summary.
 *   --verbose         Report each editor script as it loads.
 *
 * Exits non-zero when any block is invalid.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { createEditorWindow, registerPluginBlocks } = require( './wp-environment' );

const PLUGIN_DIR = path.resolve( __dirname, '..', '..' );

/**
 * Reads the command line.
 *
 * @param {string[]} argv Arguments after the script name.
 * @return {Object} The parsed options.
 */
function parseArguments( argv ) {
	const options = {
		wpRoot: path.resolve( PLUGIN_DIR, '..', '..', '..' ),
		blocksDir: path.join( PLUGIN_DIR, 'build', 'blocks' ),
		json: false,
		quiet: false,
		verbose: false,
		targets: [],
	};

	for ( let i = 0; i < argv.length; i++ ) {
		switch ( argv[ i ] ) {
			case '--wp':
				options.wpRoot = path.resolve( argv[ ++i ] );
				break;
			case '--blocks':
				options.blocksDir = path.resolve( argv[ ++i ] );
				break;
			case '--json':
				options.json = true;
				break;
			case '--quiet':
				options.quiet = true;
				break;
			case '--verbose':
				options.verbose = true;
				break;
			default:
				options.targets.push( argv[ i ] );
		}
	}

	return options;
}

/**
 * Expands directories into the files inside them.
 *
 * @param {string[]} targets Files and directories named on the command line.
 * @return {string[]} Files to check.
 */
function collectFiles( targets ) {
	const files = [];

	targets.forEach( ( target ) => {
		const resolved = path.resolve( target );
		if ( ! fs.existsSync( resolved ) ) {
			throw new Error( `No such file: ${ target }` );
		}
		if ( fs.statSync( resolved ).isDirectory() ) {
			fs.readdirSync( resolved )
				.filter( ( name ) => /\.(html|txt)$/i.test( name ) )
				.sort()
				.forEach( ( name ) => files.push( path.join( resolved, name ) ) );
		} else {
			files.push( resolved );
		}
	} );

	return files;
}

/**
 * Renders one of the editor's validation messages.
 *
 * The editor logs them through a printf-style helper, so the first argument is
 * a format string and the rest fill in its `%s` placeholders.
 *
 * @param {Object} issue An entry from a block's `validationIssues`.
 * @return {string} The readable message.
 */
function formatIssue( issue ) {
	const [ template, ...values ] = issue.args || [ '' ];
	let index = 0;

	return String( template ).replace( /%[sdo]/g, () => {
		const value = values[ index++ ];
		const text =
			typeof value === 'string' ? value : safeStringify( value );
		return text.length > 200 ? `${ text.slice( 0, 200 ) }…` : text;
	} );
}

/**
 * Stringifies a value that may hold React elements or circular references.
 *
 * @param {*} value The value.
 * @return {string} A short readable form.
 */
function safeStringify( value ) {
	try {
		return JSON.stringify( value );
	} catch ( error ) {
		return String( value );
	}
}

/**
 * Drops the summary line the editor logs alongside the real differences.
 *
 * It repeats the whole block type definition, which is thousands of characters
 * of noise once the individual mismatches are already listed.
 *
 * @param {string} message A formatted issue.
 * @return {boolean} Whether the message is worth showing.
 */
function isUsefulIssue( message ) {
	return ! message.startsWith( 'Block validation failed for' );
}

/**
 * Walks a parsed tree and collects every block the editor marked invalid.
 *
 * @param {Object[]} blocks Parsed blocks.
 * @param {Object}   wp     The `wp` global from the booted editor.
 * @param {string}   trail  Position of the parent, for reporting.
 * @return {Object[]} One entry per invalid block.
 */
function findInvalidBlocks( blocks, wp, trail = '' ) {
	const invalid = [];

	blocks.forEach( ( block, index ) => {
		const position = trail ? `${ trail } > ${ index }` : String( index );

		if ( block.isValid === false ) {
			invalid.push( {
				name: block.name,
				position,
				issues: ( block.validationIssues || [] )
					.map( formatIssue )
					.filter( isUsefulIssue ),
				actual: block.originalContent || '',
				expected: expectedMarkup( block, wp ),
			} );
		}

		if ( block.innerBlocks && block.innerBlocks.length ) {
			invalid.push(
				...findInvalidBlocks( block.innerBlocks, wp, position )
			);
		}
	} );

	return invalid;
}

/**
 * Asks a block what its own `save()` would have written.
 *
 * @param {Object} block A parsed block.
 * @param {Object} wp    The `wp` global from the booted editor.
 * @return {string} The markup the editor expected, or an empty string.
 */
function expectedMarkup( block, wp ) {
	try {
		return wp.blocks.getSaveContent(
			block.name,
			block.attributes,
			block.innerBlocks
		);
	} catch ( error ) {
		return '';
	}
}

/**
 * Cuts long markup down to something readable in a terminal.
 *
 * @param {string} markup The markup.
 * @return {string} At most a few hundred characters of it.
 */
function truncate( markup ) {
	const collapsed = markup.replace( /\s+/g, ' ' ).trim();
	return collapsed.length > 400
		? `${ collapsed.slice( 0, 400 ) }…`
		: collapsed;
}

/**
 * Runs the check.
 */
function main() {
	const options = parseArguments( process.argv.slice( 2 ) );

	if ( ! options.targets.length ) {
		process.stderr.write(
			'Usage: node validate.js [--wp <path>] [--blocks <path>] <file|directory>…\n'
		);
		process.exit( 2 );
	}

	const files = collectFiles( options.targets );
	const environment = createEditorWindow( {
		wpRoot: options.wpRoot,
		verbose: options.verbose,
	} );
	const { wp } = environment;

	const pluginBlocks = registerPluginBlocks(
		environment,
		options.blocksDir,
		options.wpRoot
	);

	if ( ! options.quiet && ! options.json ) {
		const names = wp.blocks.getBlockTypes().map( ( type ) => type.name );
		const plugin = names.filter( ( name ) =>
			name.startsWith( 'blockshift/' )
		);
		process.stdout.write(
			`WordPress: ${ options.wpRoot }\n` +
				`Registered: ${ names.length - plugin.length } core block(s), ` +
				`${ plugin.length } plugin block(s)\n\n`
		);
	}

	const report = [];
	let total = 0;

	files.forEach( ( file ) => {
		const content = fs.readFileSync( file, 'utf8' );
		const invalid = findInvalidBlocks( wp.blocks.parse( content ), wp );
		total += invalid.length;
		report.push( { file, invalid } );
	} );

	if ( options.json ) {
		process.stdout.write( `${ JSON.stringify( report, null, '\t' ) }\n` );
		process.exit( total ? 1 : 0 );
	}

	report.forEach( ( { file, invalid } ) => {
		if ( ! invalid.length ) {
			if ( ! options.quiet ) {
				process.stdout.write( `${ path.basename( file ) }: valid\n` );
			}
			return;
		}

		process.stdout.write(
			`${ path.basename( file ) }: ${ invalid.length } invalid block(s)\n`
		);
		invalid.forEach( ( block ) => {
			process.stdout.write( `\n  ${ block.name } (at ${ block.position })\n` );
			block.issues.forEach( ( issue ) =>
				process.stdout.write( `    ! ${ issue }\n` )
			);
			process.stdout.write( `    saved:    ${ truncate( block.actual ) }\n` );
			process.stdout.write( `    expected: ${ truncate( block.expected ) }\n` );
		} );
		process.stdout.write( '\n' );
	} );

	process.stdout.write( `\n${ total } invalid block(s) in ${ files.length } file(s)\n` );
	process.exit( total ? 1 : 0 );
}

main();
