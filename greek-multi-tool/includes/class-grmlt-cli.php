<?php
/**
 * WP-CLI commands: wp grmlt convert | undo-conversion | redirects.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

/**
 * Greek Multi Tool commands.
 */
class GRMLT_CLI_Command extends WP_CLI_Command {

	/**
	 * Converts existing Greek permalinks to Latin, with 301 redirects.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would change and write nothing.
	 *
	 * [--post-types=<list>]
	 * : Comma separated post types. Default: every convertible public post type.
	 *
	 * [--taxonomies=<list>]
	 * : Comma separated taxonomies. Default: every public taxonomy.
	 *
	 * [--include-attachments]
	 * : Also convert attachment slugs (required to list "attachment" in --post-types).
	 *
	 * [--batch-size=<n>]
	 * : Objects per batch. Default 100.
	 *
	 * [--resume]
	 * : Continue an interrupted conversion instead of starting a new one.
	 *
	 * [--yes]
	 * : Skip the confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp grmlt convert --dry-run
	 *     wp grmlt convert --post-types=post,page --yes
	 *     wp grmlt convert --resume
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 */
	public function convert( $args, $assoc_args ) {
		$include    = ! empty( $assoc_args['include-attachments'] );
		$post_types = isset( $assoc_args['post-types'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc_args['post-types'] ) ) ) : GRMLT_Converter::get_post_types( $include );
		$taxonomies = isset( $assoc_args['taxonomies'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc_args['taxonomies'] ) ) ) : GRMLT_Converter::get_taxonomies();
		$modes      = grmlt_get_modes();
		$mode       = grmlt_get_option( 'grmlt_diphthongs' );

		if ( in_array( 'attachment', $post_types, true ) && ! $include ) {
			WP_CLI::error( 'Attachment pages are only converted when --include-attachments is given.' );
		}
		$unknown = array_diff( $post_types, GRMLT_Converter::get_post_types( true ) );
		if ( $unknown ) {
			WP_CLI::error( sprintf( 'Not a convertible post type: %s', implode( ', ', $unknown ) ) );
		}
		$unknown = array_diff( $taxonomies, GRMLT_Converter::get_taxonomies() );
		if ( $unknown ) {
			WP_CLI::error( sprintf( 'Not a convertible taxonomy: %s', implode( ', ', $unknown ) ) );
		}
		if ( isset( $assoc_args['batch-size'] ) ) {
			$size = max( 1, (int) $assoc_args['batch-size'] );
			add_filter(
				'grmlt_conversion_batch_size',
				function () use ( $size ) {
					return $size;
				}
			);
		}

		WP_CLI::log( sprintf( 'Transliteration standard: %s (%s)', isset( $modes[ $mode ] ) ? $modes[ $mode ] : $mode, $mode ) );
		if ( ! grmlt_is_on( 'grmlt_redirect' ) ) {
			WP_CLI::warning( 'Redirects are disabled in the settings: they are still recorded, but old URLs return 404 until you enable them.' );
		}

		$state = GRMLT_Converter::get_state();
		if ( $state && empty( $assoc_args['resume'] ) && empty( $assoc_args['dry-run'] ) ) {
			if ( GRMLT_Converter::is_active( $state ) ) {
				WP_CLI::error( sprintf( 'A conversion is running right now (%d objects converted so far). Wait for it, or re-run with --resume.', (int) $state['posts'] + (int) $state['terms'] ) );
			}
			WP_CLI::warning( sprintf( 'An interrupted conversion was found (%d objects converted). Use --resume to continue it, or confirm to discard it; what it converted stays converted and can be undone.', (int) $state['posts'] + (int) $state['terms'] ) );
			if ( empty( $assoc_args['yes'] ) ) {
				WP_CLI::confirm( 'Discard the interrupted conversion and start a new one?' );
			}
			$cancelled = GRMLT_Converter::cancel_run();
			if ( is_wp_error( $cancelled ) ) {
				WP_CLI::error( $cancelled->get_error_message() );
			}
			if ( ! empty( $cancelled['pending'] ) ) {
				WP_CLI::error( 'A batch of that conversion is running right now in another process; it will stop after its current object. Try again in a moment.' );
			}
			$state = null;
		}

		if ( $state && ! empty( $assoc_args['resume'] ) ) {
			if ( GRMLT_Converter::is_active( $state ) ) {
				WP_CLI::error( 'A conversion is running right now in another process.' );
			}
			WP_CLI::log( sprintf( 'Resuming the interrupted conversion (%d objects converted so far).', (int) $state['posts'] + (int) $state['terms'] ) );
		} else {
			$preview = GRMLT_Converter::preview( $post_types, $taxonomies, 20 );
			foreach ( $preview['post_types'] as $type => $count ) {
				WP_CLI::log( sprintf( '  %s: %d', $type, $count ) );
			}
			foreach ( $preview['taxonomies'] as $taxonomy => $count ) {
				WP_CLI::log( sprintf( '  %s: %d', $taxonomy, $count ) );
			}
			WP_CLI::log( sprintf( 'Total: %d slugs would change, %s%d would get a uniqueness suffix.', $preview['total'], $preview['approximate'] ? 'about ' : '', $preview['suffix_estimate'] ) );
			if ( $preview['sample'] ) {
				\WP_CLI\Utils\format_items( 'table', $preview['sample'], array( 'type', 'title', 'current', 'new' ) );
			}
			if ( ! empty( $assoc_args['dry-run'] ) ) {
				WP_CLI::success( 'Dry run complete. Nothing was written.' );
				return;
			}
			if ( 0 === $preview['total'] ) {
				WP_CLI::success( 'Nothing to convert.' );
				return;
			}
			if ( empty( $assoc_args['yes'] ) ) {
				WP_CLI::confirm( 'Back up your database first. Convert these slugs now?' );
			}
			$state = GRMLT_Converter::start_run( $post_types, $taxonomies, $include );
			if ( is_wp_error( $state ) ) {
				WP_CLI::error( $state->get_error_message() );
			}
		}

		// Progress counts objects of the selected types only: for a new run the
		// preview total, for a resumed one what is done plus what is left.
		if ( isset( $preview ) ) {
			$total = (int) $preview['total'];
		} else {
			$counts = GRMLT_Converter::count_candidates( true );
			$total  = (int) $state['processed'];
			foreach ( (array) $state['post_types'] as $type ) {
				$total += isset( $counts['post_types'][ $type ] ) ? (int) $counts['post_types'][ $type ] : 0;
			}
			foreach ( (array) $state['taxonomies'] as $taxonomy ) {
				$total += isset( $counts['taxonomies'][ $taxonomy ] ) ? (int) $counts['taxonomies'][ $taxonomy ] : 0;
			}
		}
		$progress = \WP_CLI\Utils\make_progress_bar( 'Converting', max( 1, $total ) );
		$last     = (int) $state['processed'];
		do {
			$result = GRMLT_Converter::run_batch();
			if ( is_wp_error( $result ) ) {
				$progress->finish();
				WP_CLI::error( $result->get_error_message() . ' Re-run with --resume to continue.' );
			}
			$state = $result['state'];
			$progress->tick( max( 0, (int) $state['processed'] - $last ) );
			$last = (int) $state['processed'];
		} while ( ! $result['done'] );
		$progress->finish();
		if ( ! empty( $result['cancelled'] ) ) {
			WP_CLI::warning( sprintf( 'The conversion was cancelled from wp-admin after converting %d posts and %d terms. They stay converted; "wp grmlt undo-conversion" undoes them.', $state['posts'], $state['terms'] ) );
			return;
		}
		WP_CLI::success( sprintf( 'Converted %d posts and %d terms (%d skipped). Run "wp grmlt undo-conversion" to undo.', $state['posts'], $state['terms'], $state['skipped'] ) );
		$seo = GRMLT_Converter::seo_plugin();
		if ( $seo ) {
			WP_CLI::warning( sprintf( '%s is active: run its indexing/optimization so canonical URLs and sitemaps pick up the new permalinks.', $seo ) );
		}
	}

	/**
	 * Undoes the last conversion.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation.
	 *
	 * @subcommand undo-conversion
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 */
	public function undo_conversion( $args, $assoc_args ) {
		if ( GRMLT_Converter::get_state() ) {
			WP_CLI::error( 'A conversion is in progress or was interrupted. Finish it with "wp grmlt convert --resume" or discard it with "wp grmlt convert --yes" before undoing.' );
		}
		$last = GRMLT_Converter::get_last_conversion();
		if ( ! $last ) {
			WP_CLI::error( 'There is no conversion to undo.' );
		}
		WP_CLI::log( sprintf( 'Last conversion: %s, %d posts, %d terms%s.', gmdate( 'Y-m-d H:i', (int) $last['time'] ), (int) $last['posts'], (int) $last['terms'], empty( $last['complete'] ) ? ' (cancelled before it finished)' : '' ) );
		if ( 'legacy' === $last['run_id'] ) {
			WP_CLI::log( 'This conversion was made by version 3.x; its redirects are kept, only the slugs are restored.' );
		}
		if ( empty( $assoc_args['yes'] ) ) {
			WP_CLI::confirm( 'Restore the previous slugs?' );
		}
		$cursor   = 0;
		$restored = 0;
		$skipped  = 0;
		do {
			$result = GRMLT_Converter::undo_batch( $cursor );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
			$cursor    = $result['cursor'];
			$restored += $result['restored'];
			$skipped  += $result['skipped'];
		} while ( ! $result['done'] );
		WP_CLI::success( sprintf( 'Restored %d slugs. %d were skipped because they had changed since the conversion.', $restored, $skipped ) );
	}
}

/**
 * Redirect commands.
 */
class GRMLT_CLI_Redirects_Command extends WP_CLI_Command {

	/**
	 * Checks every redirect and stores its status (ok, missing, chain, loop).
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 */
	public function check( $args, $assoc_args ) {
		$total = GRMLT_Redirects::count_all();
		if ( 0 === $total ) {
			WP_CLI::success( 'There are no redirects.' );
			return;
		}
		$progress = \WP_CLI\Utils\make_progress_bar( 'Checking', $total );
		$offset   = 0;
		do {
			$result = GRMLT_Redirects::check_batch( $offset, 200 );
			$offset = $result['offset'];
			$progress->tick( $result['processed'] );
		} while ( ! $result['done'] );
		$progress->finish();
		$counts = GRMLT_Redirects::status_counts();
		WP_CLI::success( sprintf( 'ok %d, missing %d, chain %d, loop %d.', $counts['ok'], $counts['missing'], $counts['chain'], $counts['loop'] ) );
	}

	/**
	 * Exports the redirects to a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Destination file.
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 */
	public function export( $args, $assoc_args ) {
		$handle = fopen( $args[0], 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			WP_CLI::error( 'The file could not be written.' );
		}
		GRMLT_Redirects::export_csv( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		WP_CLI::success( sprintf( 'Exported %d redirects to %s.', GRMLT_Redirects::count_all(), $args[0] ) );
	}

	/**
	 * Imports redirects from a CSV file (columns old_path, new_path).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Source file.
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 */
	public function import( $args, $assoc_args ) {
		if ( ! is_readable( $args[0] ) ) {
			WP_CLI::error( 'The file could not be read.' );
		}
		$summary = GRMLT_Redirects::import_csv( $args[0] );
		if ( is_wp_error( $summary ) ) {
			WP_CLI::error( $summary->get_error_message() );
		}
		foreach ( $summary['skipped'] as $skip ) {
			WP_CLI::warning( sprintf( 'Line %d (%s) not imported: %s', $skip['line'], '' === $skip['old'] ? '-' : $skip['old'], GRMLT_Redirects::reason_label( $skip['reason'] ) ) );
		}
		WP_CLI::success( sprintf( 'Imported %d of %d rows.', $summary['imported'], $summary['total'] ) );
	}
}

WP_CLI::add_command( 'grmlt', 'GRMLT_CLI_Command' );
WP_CLI::add_command( 'grmlt redirects', 'GRMLT_CLI_Redirects_Command' );
