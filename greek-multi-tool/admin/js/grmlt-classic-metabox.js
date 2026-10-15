/* global wp, tinyMCE */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.apiFetch ) {
		return;
	}

	var __ = wp.i18n.__;
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;

	function editorContent() {
		if ( 'undefined' !== typeof tinyMCE && tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden() ) {
			return tinyMCE.activeEditor.getContent();
		}
		var textarea = document.getElementById( 'content' );
		return textarea ? textarea.value : '';
	}

	function text( tag, value, className ) {
		var node = document.createElement( tag );
		node.textContent = value;
		if ( className ) {
			node.className = className;
		}
		return node;
	}

	document.querySelectorAll( '.grmlt-classic-metabox' ).forEach( function ( box ) {
		var button = box.querySelector( '.grmlt-classic-metabox__run' );
		var result = box.querySelector( '.grmlt-classic-metabox__result' );
		var postId = parseInt( box.dataset.postId, 10 ) || 0;

		button.addEventListener( 'click', function () {
			button.disabled = true;
			result.textContent = __( 'Checking…', 'greek-multi-tool' );
			wp.apiFetch( {
				path: '/grmlt/v1/analyze',
				method: 'POST',
				data: { content: editorContent(), post_id: postId }
			} ).then( function ( r ) {
				result.textContent = '';
				var issues = r.issues || [];
				if ( ! issues.length ) {
					result.appendChild( text( 'p', __( 'No accent problems were found.', 'greek-multi-tool' ) ) );
					return;
				}
				/* translators: %s: number of possible problems. */
				result.appendChild( text( 'p', sprintf( _n( '%s possible problem', '%s possible problems', issues.length, 'greek-multi-tool' ), issues.length ) ) );
				var list = document.createElement( 'ul' );
				issues.forEach( function ( issue ) {
					var li = document.createElement( 'li' );
					li.appendChild( text( 'strong', issue.word ) );
					if ( issue.suggestion ) {
						li.appendChild( document.createTextNode( ' → ' + issue.suggestion ) );
					}
					li.appendChild( document.createElement( 'br' ) );
					li.appendChild( text( 'span', issue.message, 'description' ) );
					list.appendChild( li );
				} );
				result.appendChild( list );
			} ).catch( function ( error ) {
				result.textContent = ( error && error.message ) || __( 'The check failed. Please try again.', 'greek-multi-tool' );
			} ).finally( function () {
				button.disabled = false;
			} );
		} );
	} );
}() );
