<?php
/**
 * The redirects list table.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class GRMLT_Redirects_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'redirect',
				'plural'   => 'redirects',
				'ajax'     => false,
				'screen'   => 'grmlt-redirects',
			)
		);
	}

	/**
	 * Current status filter.
	 *
	 * @return string
	 */
	private function status_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$status = isset( $_GET['redirect_status'] ) ? sanitize_key( wp_unslash( $_GET['redirect_status'] ) ) : '';
		return 'problems' === $status ? 'problems' : '';
	}

	/**
	 * Current search term.
	 *
	 * @return string
	 */
	private function search_term() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- navigation only; sanitized by sanitize_path_input() which keeps %xx sequences.
		return isset( $_GET['s'] ) ? GRMLT_Redirects::sanitize_path_input( wp_unslash( $_GET['s'] ) ) : '';
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'old_path'    => __( 'Old permalink', 'greek-multi-tool' ),
			'destination' => __( 'Destination', 'greek-multi-tool' ),
			'hits'        => __( 'Visits', 'greek-multi-tool' ),
			'last_hit'    => __( 'Last visit', 'greek-multi-tool' ),
			'status'      => __( 'Status', 'greek-multi-tool' ),
			'created_at'  => __( 'Created', 'greek-multi-tool' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'hits'       => array( 'hits', false ),
			'created_at' => array( 'created_at', true ),
			'last_hit'   => array( 'last_hit', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'greek-multi-tool' ) );
	}

	/**
	 * Views: all / with problems.
	 *
	 * @return array
	 */
	protected function get_views() {
		$all      = GRMLT_Redirects::count_all(); // The rows listed below, all of them.
		$problems = GRMLT_Redirects::problem_count();
		$current  = $this->status_filter();
		$base     = GRMLT_Admin::tab_url( 'conversion' );
		return array(
			'all'      => sprintf( '<a href="%s"%s>%s <span class="count">(%s)</span></a>', esc_url( $base . '#grmlt-redirects' ), '' === $current ? ' class="current" aria-current="page"' : '', esc_html__( 'All', 'greek-multi-tool' ), number_format_i18n( $all ) ),
			'problems' => sprintf( '<a href="%s"%s>%s <span class="count">(%s)</span></a>', esc_url( add_query_arg( 'redirect_status', 'problems', $base ) . '#grmlt-redirects' ), 'problems' === $current ? ' class="current" aria-current="page"' : '', esc_html__( 'With problems', 'greek-multi-tool' ), number_format_i18n( $problems ) ),
		);
	}

	/**
	 * Loads the rows.
	 */
	public function prepare_items() {
		global $wpdb;
		$table    = GRMLT_Redirects::table();
		$per_page = 20;
		$paged    = max( 1, $this->get_pagenum() );
		$where    = array( '1=1' );
		$params   = array();

		if ( 'problems' === $this->status_filter() ) {
			$where[] = "status IN ('missing','chain','loop')";
		}
		$search = $this->search_term();
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( GRMLT_Redirects::normalize_path( $search ) ) . '%';
			$where[]  = '(old_path LIKE %s OR new_path LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		if ( ! in_array( $orderby, array( 'hits', 'created_at', 'last_hit' ), true ) ) {
			$orderby = 'created_at';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$order = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';

		$where_sql = implode( ' AND ', $where );
		// $orderby and $order are validated against allow-lists above; the table name is bound with %i.
		$sql_count = "SELECT COUNT(*) FROM %i WHERE {$where_sql}";
		$sql_rows  = "SELECT * FROM %i WHERE {$where_sql} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total = (int) $wpdb->get_var( $wpdb->prepare( $sql_count, array_merge( array( $table ), $params ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$this->items = $wpdb->get_results( $wpdb->prepare( $sql_rows, array_merge( array( $table ), $params, array( $per_page, ( $paged - 1 ) * $per_page ) ) ) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'old_path' );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="redirect[]" value="%d" aria-label="%s" />', (int) $item->id, esc_attr( sprintf( /* translators: %s: path */ __( 'Select redirect %s', 'greek-multi-tool' ), '/' . $item->old_path . '/' ) ) );
	}

	/**
	 * Old path column with row actions.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_old_path( $item ) {
		$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=grmlt_redirect_delete&id=' . (int) $item->id ), 'grmlt_redirect_delete_' . (int) $item->id );
		$actions    = array(
			'edit'   => sprintf( '<button type="button" class="button-link grmlt-edit-dest" data-id="%d">%s</button>', (int) $item->id, esc_html__( 'Edit destination', 'greek-multi-tool' ) ),
			'delete' => sprintf( '<a href="%s" class="submitdelete" data-grmlt-confirm="%s">%s</a>', esc_url( $delete_url ), esc_attr__( 'Delete this redirect?', 'greek-multi-tool' ), esc_html__( 'Delete', 'greek-multi-tool' ) ),
		);
		return '<code class="grmlt-path">' . esc_html( '/' . $item->old_path . '/' ) . '</code>' . $this->row_actions( $actions );
	}

	/**
	 * Destination column (resolved today).
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_destination( $item ) {
		$destination = GRMLT_Redirects::destination( $item );
		if ( ! $destination['valid'] ) {
			return sprintf(
				'<span class="grmlt-dest" data-id="%d" data-path="%s"><code class="grmlt-path grmlt-dest__path">&mdash;</code> <span class="grmlt-dest__note">%s</span></span>',
				(int) $item->id,
				esc_attr( '' === $item->new_path ? '' : '/' . $item->new_path . '/' ),
				esc_html__( 'no public address', 'greek-multi-tool' )
			);
		}
		if ( ! empty( $destination['home'] ) ) {
			return sprintf(
				'<span class="grmlt-dest" data-id="%d" data-path="%s"><a class="grmlt-dest__path" href="%s">%s</a> <span class="grmlt-dest__note">%s</span></span>',
				(int) $item->id,
				esc_attr( '/' . $item->new_path . '/' ),
				esc_url( home_url( '/' ) ),
				esc_html__( 'Home page', 'greek-multi-tool' ),
				esc_html__( 'the page is the static front page', 'greek-multi-tool' )
			);
		}
		$path = '/' . $destination['path'] . '/';
		$note = 'object' === $destination['source']
			? sprintf( /* translators: %s: post or term */ __( 'current link of the %s', 'greek-multi-tool' ), 'post' === $item->object_type ? __( 'post', 'greek-multi-tool' ) : __( 'term', 'greek-multi-tool' ) )
			: __( 'fixed path', 'greek-multi-tool' );
		return sprintf(
			'<span class="grmlt-dest" data-id="%d" data-path="%s"><code class="grmlt-path grmlt-dest__path">%s</code> <span class="grmlt-dest__note">%s</span></span>',
			(int) $item->id,
			esc_attr( '/' . $item->new_path . '/' ),
			esc_html( $path ),
			esc_html( $note )
		);
	}

	/**
	 * Status column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_status( $item ) {
		$labels = array(
			'ok'      => __( 'Working', 'greek-multi-tool' ),
			'missing' => __( 'No destination', 'greek-multi-tool' ),
			'chain'   => __( 'Chain', 'greek-multi-tool' ),
			'loop'    => __( 'Loop', 'greek-multi-tool' ),
		);
		$status = (string) $item->status;
		$target = GRMLT_Redirects::destination( $item );
		if ( ! $target['valid'] ) {
			$status = 'missing'; // Known without a check.
		}
		if ( ! isset( $labels[ $status ] ) ) {
			return '<span class="grmlt-status grmlt-status--unchecked">' . esc_html__( 'Not checked', 'greek-multi-tool' ) . '</span>';
		}
		return '<span class="grmlt-status grmlt-status--' . esc_attr( $status ) . '">' . esc_html( $labels[ $status ] ) . '</span>';
	}

	/**
	 * Default column rendering.
	 *
	 * @param object $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'hits':
				return esc_html( number_format_i18n( (int) $item->hits ) );
			case 'last_hit':
			case 'created_at':
				$value = $item->{$column_name};
				if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
					return '&mdash;';
				}
				return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $value . ' UTC' ) ) );
		}
		return '';
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		if ( '' !== $this->search_term() || '' !== $this->status_filter() ) {
			esc_html_e( 'No redirects match.', 'greek-multi-tool' );
			return;
		}
		esc_html_e( 'There are no redirects yet. They are created automatically when you convert old permalinks.', 'greek-multi-tool' );
	}
}
