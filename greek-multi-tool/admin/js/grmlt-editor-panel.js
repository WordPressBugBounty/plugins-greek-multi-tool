/* global wp */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.components || ! wp.data || ! wp.apiFetch ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var registerPlugin = wp.plugins.registerPlugin;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;
	var __ = wp.i18n.__;
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;

	var PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	function AccentCheckPanel() {
		var state = useState( { status: 'idle', result: null, error: '' } );
		var data = state[ 0 ];
		var setData = state[ 1 ];

		function run() {
			var editor = wp.data.select( 'core/editor' );
			var content = editor.getEditedPostContent();
			var postId = editor.getCurrentPostId();
			setData( { status: 'loading', result: null, error: '' } );
			wp.apiFetch( {
				path: '/grmlt/v1/analyze',
				method: 'POST',
				data: { content: content, post_id: postId || 0 }
			} ).then( function ( result ) {
				setData( { status: 'done', result: result, error: '' } );
			} ).catch( function ( error ) {
				setData( { status: 'error', result: null, error: ( error && error.message ) || __( 'The check failed. Please try again.', 'greek-multi-tool' ) } );
			} );
		}

		var children = [
			el( 'p', { key: 'intro' }, __( 'Finds words whose accent is probably wrong.', 'greek-multi-tool' ) ),
			el( Button, { key: 'button', variant: 'secondary', isSecondary: true, onClick: run, disabled: 'loading' === data.status }, __( 'Check text', 'greek-multi-tool' ) )
		];

		if ( 'loading' === data.status ) {
			children.push( el( Spinner, { key: 'spinner' } ) );
		}
		if ( 'error' === data.status ) {
			children.push( el( Notice, { key: 'error', status: 'error', isDismissible: false }, data.error ) );
		}
		if ( 'done' === data.status && data.result ) {
			var issues = data.result.issues || [];
			var stats = data.result.stats || {};
			children.push(
				el( 'p', { key: 'stats', className: 'description' },
					sprintf(
						/* translators: 1: words, 2: Greek characters percentage */
						__( '%1$s words, %2$s%% Greek characters.', 'greek-multi-tool' ),
						stats.total_words || 0,
						stats.percent_greek || 0
					)
				)
			);
			if ( ! issues.length ) {
				children.push( el( Notice, { key: 'ok', status: 'success', isDismissible: false }, __( 'No accent problems were found.', 'greek-multi-tool' ) ) );
			} else {
				children.push(
					el( 'p', { key: 'count' },
						el( 'strong', null, sprintf( _n( '%s possible problem', '%s possible problems', issues.length, 'greek-multi-tool' ), issues.length ) )
					)
				);
				children.push(
					el( 'ul', { key: 'list', className: 'grmlt-accent-issues' },
						issues.map( function ( issue, index ) {
							return el( 'li', { key: index, style: { marginBottom: '8px' } },
								el( 'strong', null, issue.word ),
								issue.suggestion ? ' → ' + issue.suggestion : null,
								el( 'br' ),
								el( 'span', { className: 'description', style: { fontSize: '12px' } }, issue.message )
							);
						} )
					)
				);
			}
		}

		return el( PluginDocumentSettingPanel, { name: 'grmlt-accent-check', title: __( 'Accent check', 'greek-multi-tool' ), className: 'grmlt-accent-check' }, children );
	}

	registerPlugin( 'grmlt-accent-check', { render: AccentCheckPanel } );
}( window.wp ) );
