/* global grmltAdmin, wp */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.apiFetch ) {
		return;
	}

	var __ = wp.i18n.__;
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;
	var apiFetch = wp.apiFetch;
	var config = window.grmltAdmin || {};

	function $( selector, root ) {
		return ( root || document ).querySelector( selector );
	}

	function $$( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	/* Tab bar: on a narrow screen the bar scrolls; the active tab is shown. */
	( function () {
		var bar = $( '.grmlt-tabs' );
		var active = bar && $( '.grmlt-tab.is-active', bar );
		if ( ! active || bar.scrollWidth <= bar.clientWidth ) {
			return;
		}
		var right = active.offsetLeft + active.offsetWidth - bar.offsetLeft;
		if ( right > bar.clientWidth ) {
			bar.scrollLeft = right - bar.clientWidth + 16;
		}
	}() );

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			if ( 'text' === key ) {
				node.textContent = attrs[ key ];
			} else if ( 'className' === key ) {
				node.className = attrs[ key ];
			} else if ( 'hidden' === key ) {
				node.hidden = !! attrs[ key ];
			} else {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( null === child || undefined === child ) {
				return;
			}
			node.appendChild( 'string' === typeof child ? document.createTextNode( child ) : child );
		} );
		return node;
	}

	function clear( node ) {
		while ( node && node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function notice( type, children ) {
		var box = el( 'div', { className: 'notice notice-' + type + ' inline', role: 'error' === type ? 'alert' : 'status' } );
		var p = el( 'p' );
		( Array.isArray( children ) ? children : [ children ] ).forEach( function ( child ) {
			p.appendChild( 'string' === typeof child ? document.createTextNode( child ) : child );
		} );
		box.appendChild( p );
		return box;
	}

	function errorMessage( error ) {
		if ( error && error.message ) {
			return error.message;
		}
		return __( 'Something went wrong. Please try again.', 'greek-multi-tool' );
	}

	function spinner( button, on ) {
		var s = button && button.parentNode ? button.parentNode.querySelector( '.spinner' ) : null;
		if ( s ) {
			s.classList.toggle( 'is-active', !! on );
		}
		if ( button ) {
			button.disabled = !! on || button.dataset.keepDisabled === '1';
		}
	}

	function debounce( fn, wait ) {
		var timer;
		return function () {
			var args = arguments;
			clearTimeout( timer );
			timer = setTimeout( function () {
				fn.apply( null, args );
			}, wait );
		};
	}

	/* Inline confirmation (two steps, no window.confirm) ------------------- */

	function confirmInline( trigger, text, onConfirm ) {
		if ( trigger.dataset.confirming === '1' ) {
			return;
		}
		trigger.dataset.confirming = '1';
		var box = el( 'span', { className: 'grmlt-confirm', role: 'group' } );
		var question = el( 'span', { text: text } );
		var yes = el( 'button', { type: 'button', className: 'button button-primary button-small', text: __( 'Confirm', 'greek-multi-tool' ) } );
		var no = el( 'button', { type: 'button', className: 'button button-small', text: __( 'Cancel', 'greek-multi-tool' ) } );
		box.appendChild( question );
		box.appendChild( yes );
		box.appendChild( no );
		trigger.hidden = true;
		trigger.parentNode.insertBefore( box, trigger.nextSibling );
		yes.focus();

		function restore() {
			box.parentNode.removeChild( box );
			trigger.hidden = false;
			trigger.dataset.confirming = '0';
			trigger.focus();
		}
		no.addEventListener( 'click', restore );
		box.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				restore();
			}
		} );
		yes.addEventListener( 'click', function () {
			box.parentNode.removeChild( box );
			trigger.hidden = false;
			trigger.dataset.confirming = '0';
			onConfirm();
		} );
	}

	$$( '[data-grmlt-confirm]' ).forEach( function ( trigger ) {
		if ( 'grmlt-undo-btn' === trigger.id ) {
			return; // Handled by the undo module.
		}
		trigger.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			confirmInline( trigger, trigger.dataset.grmltConfirm, function () {
				if ( 'A' === trigger.tagName ) {
					window.location.href = trigger.href;
				} else if ( trigger.form ) {
					if ( trigger.form.requestSubmit ) {
						trigger.form.requestSubmit( trigger );
					} else {
						trigger.form.submit();
					}
				}
			} );
		} );
	} );

	/* Feature switches: dim the card body when off ------------------------ */

	$$( 'input[data-grmlt-feature]' ).forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			var card = input.closest( '.grmlt-card' );
			if ( ! card ) {
				return;
			}
			card.classList.toggle( 'grmlt-card--off', ! input.checked );
			var body = card.querySelector( '.grmlt-card__body' );
			if ( body && card.classList.contains( 'grmlt-card--feature' ) ) {
				if ( input.checked ) {
					body.removeAttribute( 'inert' );
				} else {
					body.setAttribute( 'inert', '' );
				}
			}
		} );
	} );

	/* Radio cards --------------------------------------------------------- */

	$$( '.grmlt-radio-cards' ).forEach( function ( group ) {
		group.addEventListener( 'change', function () {
			$$( '.grmlt-radio-card', group ).forEach( function ( card ) {
				var radio = card.querySelector( 'input' );
				card.classList.toggle( 'is-selected', !! ( radio && radio.checked ) );
			} );
		} );
	} );

	var legacyWarning = $( '#grmlt-legacy-warning' );
	if ( legacyWarning ) {
		$$( 'input[name="grmlt_diphthongs"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				var selected = $( 'input[name="grmlt_diphthongs"]:checked' );
				legacyWarning.hidden = ! selected || 'simple-legacy' === selected.value;
			} );
		} );
	}

	/* Dirty tracking + save bars ------------------------------------------ */

	var dirtyForms = {};

	function setDirty( form, dirty ) {
		dirtyForms[ form.id ] = dirty;
		var bar = $( '[data-grmlt-savebar="' + form.id + '"]' );
		if ( bar ) {
			bar.hidden = false === dirty && bar.dataset.autohide === '1';
			bar.classList.toggle( 'is-dirty', dirty );
			var status = bar.querySelector( '.grmlt-savebar__status' );
			if ( status ) {
				status.textContent = dirty ? status.dataset.unsaved : '';
			}
		}
	}

	$$( 'form[data-grmlt-track]' ).forEach( function ( form ) {
		var bar = $( '[data-grmlt-savebar="' + form.id + '"]' );
		if ( bar && bar.hidden ) {
			bar.dataset.autohide = '1';
		}
		form.addEventListener( 'input', function () {
			setDirty( form, true );
		} );
		form.addEventListener( 'change', function () {
			setDirty( form, true );
		} );
		form.addEventListener( 'submit', function () {
			setDirty( form, false );
		} );
	} );

	window.addEventListener( 'beforeunload', function ( e ) {
		var dirty = Object.keys( dirtyForms ).some( function ( id ) {
			return dirtyForms[ id ];
		} );
		if ( dirty ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	/* Chips for the excluded words ---------------------------------------- */

	$$( '.grmlt-chips-source' ).forEach( function ( source ) {
		var words = source.value.split( ',' ).map( function ( w ) {
			return w.trim();
		} ).filter( Boolean );
		var wrap = el( 'div', { className: 'grmlt-chips' } );
		var input = el( 'input', { type: 'text', className: 'grmlt-chips__input', 'aria-label': __( 'Add a word and press Enter', 'greek-multi-tool' ), placeholder: source.getAttribute( 'placeholder' ) || '' } );

		function sync() {
			source.value = words.join( ', ' );
			source.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}

		function render() {
			clear( wrap );
			words.forEach( function ( word, index ) {
				var chip = el( 'span', { className: 'grmlt-chip' }, [ word ] );
				var remove = el( 'button', { type: 'button', className: 'grmlt-chip__remove', 'aria-label': sprintf( /* translators: %s: word */ __( 'Remove “%s”', 'greek-multi-tool' ), word ), text: '×' } );
				remove.addEventListener( 'click', function () {
					words.splice( index, 1 );
					render();
					sync();
					input.focus();
				} );
				chip.appendChild( remove );
				wrap.appendChild( chip );
			} );
			wrap.appendChild( input );
		}

		function add( raw ) {
			raw.split( ',' ).forEach( function ( w ) {
				w = w.trim();
				if ( w && words.indexOf( w ) === -1 ) {
					words.push( w );
				}
			} );
			input.value = '';
			render();
			sync();
			input.focus();
		}

		input.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key || ',' === e.key ) {
				e.preventDefault();
				if ( input.value.trim() ) {
					add( input.value );
				}
			} else if ( 'Backspace' === e.key && '' === input.value && words.length ) {
				words.pop();
				render();
				sync();
				input.focus();
			}
		} );
		input.addEventListener( 'blur', function () {
			if ( input.value.trim() ) {
				add( input.value );
			}
		} );

		source.type = 'hidden';
		source.parentNode.insertBefore( wrap, source.nextSibling );
		render();
	} );

	/* Live preview -------------------------------------------------------- */

	var preview = $( '[data-grmlt-preview]' );
	if ( preview ) {
		var titleIn = $( '#grmlt-try-title' );
		var fileIn = $( '#grmlt-try-file' );
		var titleOut = $( '#grmlt-try-title-out' );
		var fileOut = $( '#grmlt-try-file-out' );
		var status = $( '.grmlt-try__status', preview );
		var settingsForm = $( '#grmlt-settings-form' );

		function formValues() {
			var mode = settingsForm ? settingsForm.querySelector( 'input[name="grmlt_diphthongs"]:checked' ) : null;
			var one = settingsForm ? settingsForm.querySelector( '#grmlt_one_letter_words' ) : null;
			var two = settingsForm ? settingsForm.querySelector( '#grmlt_two_letter_words' ) : null;
			var stop = settingsForm ? settingsForm.querySelector( '#grmlt_stwords' ) : null;
			return {
				title: titleIn.value,
				filename: fileIn.value,
				mode: mode ? mode.value : '',
				one_letter: !! ( one && one.checked ),
				two_letter: !! ( two && two.checked ),
				stopwords: stop ? stop.value : ''
			};
		}

		var requestNo = 0;
		var run = debounce( function () {
			status.textContent = '';
			var mine = ++requestNo;
			apiFetch( { path: '/grmlt/v1/preview', method: 'POST', data: formValues() } ).then( function ( r ) {
				if ( mine !== requestNo ) {
					return; // A newer request is in flight; never let an older answer win.
				}
				clear( titleOut );
				titleOut.appendChild( el( 'code', { className: 'grmlt-path', text: r.url || '—' } ) );
				clear( fileOut );
				fileOut.appendChild( el( 'code', { className: 'grmlt-path', text: r.filename || '—' } ) );
			} ).catch( function ( error ) {
				if ( mine === requestNo ) {
					status.textContent = errorMessage( error );
				}
			} );
		}, 250 );

		[ titleIn, fileIn ].forEach( function ( input ) {
			input.addEventListener( 'input', run );
		} );
		if ( settingsForm ) {
			settingsForm.addEventListener( 'change', run );
			settingsForm.addEventListener( 'input', run );
		}
	}

	/* Copy buttons -------------------------------------------------------- */

	$$( '.grmlt-copy' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var target = document.getElementById( button.dataset.copyTarget );
			if ( ! target || ! navigator.clipboard ) {
				return;
			}
			var label = button.textContent;
			navigator.clipboard.writeText( target.textContent ).then( function () {
				button.textContent = __( 'Copied', 'greek-multi-tool' );
				setTimeout( function () {
					button.textContent = label;
				}, 1500 );
			} );
		} );
	} );

	/* Progress helper ----------------------------------------------------- */

	function progressUI( root ) {
		var bar = root.querySelector( 'progress' );
		var text = root.querySelector( '.grmlt-progress__text' );
		return {
			show: function () {
				root.hidden = false;
			},
			hide: function () {
				root.hidden = true;
			},
			set: function ( done, total, label ) {
				if ( total > 0 ) {
					bar.value = Math.min( 100, Math.round( done / total * 100 ) );
				} else {
					bar.removeAttribute( 'value' );
				}
				text.textContent = label;
			}
		};
	}

	/* Conversion ---------------------------------------------------------- */

	var conversion = $( '[data-grmlt-conversion]' );
	if ( conversion ) {
		var previewBtn = $( '#grmlt-preview-btn' );
		var previewResult = $( '#grmlt-preview-result' );
		var convertBtn = $( '#grmlt-convert-btn' );
		var convertHint = $( '#grmlt-convert-hint' );
		var backup = $( '#grmlt-backup-confirm' );
		var progressRoot = $( '#grmlt-convert-progress' );
		var progress = progressUI( progressRoot );
		var cancelBtn = progressRoot.querySelector( '[data-action="cancel"]' );
		var continueBtn = progressRoot.querySelector( '[data-action="continue"]' );
		var convertResult = $( '#grmlt-convert-result' );
		var previewed = false;
		var previewTotal = 0;
		var cancelled = false;
		var cancelPending = false;
		var batchInFlight = false;

		function targets() {
			var postTypes = $$( 'input[name="post_types[]"]:checked', conversion ).map( function ( i ) {
				return i.value;
			} );
			var taxonomies = $$( 'input[name="taxonomies[]"]:checked', conversion ).map( function ( i ) {
				return i.value;
			} );
			var attachments = $( 'input[name="include_attachments"]', conversion );
			var includeAttachments = !! ( attachments && attachments.checked );
			if ( includeAttachments ) {
				postTypes.push( 'attachment' );
			}
			return { post_types: postTypes, taxonomies: taxonomies, include_attachments: includeAttachments };
		}

		function updateConvertState() {
			var ready = previewed && previewTotal > 0 && backup.checked;
			convertBtn.disabled = ! ready || convertBtn.dataset.keepDisabled === '1';
			if ( ready ) {
				convertHint.textContent = '';
			} else if ( ! previewed ) {
				convertHint.textContent = __( 'Run the preview and confirm the backup first.', 'greek-multi-tool' );
			} else if ( 0 === previewTotal ) {
				convertHint.textContent = __( 'Nothing to convert with the current selection.', 'greek-multi-tool' );
			} else {
				convertHint.textContent = __( 'Confirm the database backup to enable the conversion.', 'greek-multi-tool' );
			}
		}

		$( '#grmlt-conversion-targets' ).addEventListener( 'change', function () {
			previewed = false;
			previewTotal = 0;
			clear( previewResult );
			updateConvertState();
		} );
		backup.addEventListener( 'change', updateConvertState );

		function renderPreview( r ) {
			clear( previewResult );
			var summary = el( 'div', { className: 'grmlt-summary' } );
			summary.appendChild( el( 'p', { text: sprintf( /* translators: 1: number, 2: standard */ __( '%1$s permalinks will change, using the %2$s standard.', 'greek-multi-tool' ), r.total, r.mode_label ) } ) );
			var list = el( 'ul' );
			Object.keys( r.post_types ).forEach( function ( type ) {
				list.appendChild( el( 'li', { text: type + ': ' + r.post_types[ type ] } ) );
			} );
			Object.keys( r.taxonomies ).forEach( function ( tax ) {
				list.appendChild( el( 'li', { text: tax + ': ' + r.taxonomies[ tax ] } ) );
			} );
			if ( r.suffix_estimate > 0 ) {
				var suffixText = r.approximate
					? sprintf( /* translators: %s: number */ __( 'About %s permalinks will get a numeric suffix because the Latin slug already exists (estimated from the sample).', 'greek-multi-tool' ), r.suffix_estimate )
					: sprintf( /* translators: %s: number */ _n( '%s permalink will get a numeric suffix because the Latin slug already exists.', '%s permalinks will get a numeric suffix because the Latin slug already exists.', r.suffix_estimate, 'greek-multi-tool' ), r.suffix_estimate );
				list.appendChild( el( 'li', { text: suffixText } ) );
			}
			if ( list.childNodes.length ) {
				summary.appendChild( list );
			}
			previewResult.appendChild( summary );

			if ( r.sample && r.sample.length ) {
				var table = el( 'table', { className: 'grmlt-preview-table' } );
				var thead = el( 'thead' );
				var hr = el( 'tr' );
				[ __( 'Type', 'greek-multi-tool' ), __( 'Title', 'greek-multi-tool' ), __( 'Current permalink', 'greek-multi-tool' ), __( 'New permalink', 'greek-multi-tool' ) ].forEach( function ( h ) {
					hr.appendChild( el( 'th', { scope: 'col', text: h } ) );
				} );
				thead.appendChild( hr );
				table.appendChild( thead );
				var tbody = el( 'tbody' );
				r.sample.forEach( function ( row ) {
					var tr = el( 'tr' );
					tr.appendChild( el( 'td', { text: row.type } ) );
					tr.appendChild( el( 'td', { text: row.title } ) );
					tr.appendChild( el( 'td' ) );
					tr.lastChild.appendChild( el( 'code', { className: 'grmlt-path', text: '/' + row.current + '/' } ) );
					tr.appendChild( el( 'td' ) );
					tr.lastChild.appendChild( el( 'code', { className: 'grmlt-path grmlt-path--new', text: '/' + row.new + '/' } ) );
					tbody.appendChild( tr );
				} );
				table.appendChild( tbody );
				var wrap = el( 'div', { className: 'grmlt-table-wrap' } );
				wrap.appendChild( table );
				previewResult.appendChild( wrap );
				if ( r.total > r.sample.length ) {
					previewResult.appendChild( el( 'p', { className: 'description', text: sprintf( /* translators: 1: shown, 2: total */ __( 'Showing a sample of %1$s of %2$s.', 'greek-multi-tool' ), r.sample.length, r.total ) } ) );
				}
			}
		}

		previewBtn.addEventListener( 'click', function () {
			spinner( previewBtn, true );
			clear( previewResult );
			apiFetch( { path: '/grmlt/v1/conversion/preview', method: 'POST', data: targets() } ).then( function ( r ) {
				previewed = true;
				previewTotal = r.total;
				renderPreview( r );
				updateConvertState();
			} ).catch( function ( error ) {
				previewResult.appendChild( notice( 'error', errorMessage( error ) ) );
			} ).finally( function () {
				spinner( previewBtn, false );
				updateConvertState();
			} );
		} );

		var runNotice = $( '#grmlt-run-notice' );
		var runText = $( '#grmlt-run-text' );
		var resumeBtn = runNotice ? runNotice.querySelector( '[data-action="resume"]' ) : null;
		var discardBtn = runNotice ? runNotice.querySelector( '[data-action="discard"]' ) : null;
		var runTotal = 0;

		function showRunNotice( run ) {
			if ( ! runNotice || ! run ) {
				return;
			}
			if ( run.active ) {
				runText.textContent = sprintf( /* translators: %s: number */ __( 'A conversion is running right now in another window or in WP-CLI (%s permalinks converted so far). Wait for it to finish, or take it over with “Continue”.', 'greek-multi-tool' ), run.converted );
			} else {
				runText.textContent = sprintf( /* translators: %s: number */ __( 'A conversion was interrupted after converting %s permalinks. Continue it, or discard it: what was converted stays converted and can be undone.', 'greek-multi-tool' ), run.converted );
			}
			runNotice.hidden = false;
			convertBtn.dataset.keepDisabled = '1';
			convertBtn.disabled = true;
		}

		function hideRunNotice() {
			if ( runNotice ) {
				runNotice.hidden = true;
			}
			delete convertBtn.dataset.keepDisabled;
			updateConvertState();
		}

		function finishConversion( r ) {
			progress.hide();
			clear( convertResult );
			var parts = [ sprintf( /* translators: 1: posts, 2: terms, 3: skipped */ __( 'Done: %1$s posts and %2$s terms were converted, %3$s were skipped.', 'greek-multi-tool' ), r.state.posts, r.state.terms, r.state.skipped ) ];
			if ( r.seo_plugin ) {
				parts.push( ' ' + sprintf( /* translators: %s: plugin name */ __( '%s is active: run its indexing or optimization so that canonical URLs and sitemaps pick up the new permalinks.', 'greek-multi-tool' ), r.seo_plugin ) );
			}
			var box = notice( 'success', parts );
			var reload = el( 'a', { href: config.tabUrl || window.location.href, text: __( 'Reload the page to see the redirects and the undo option.', 'greek-multi-tool' ) } );
			box.appendChild( el( 'p', {}, [ reload ] ) );
			convertResult.appendChild( box );
			previewed = false;
			previewTotal = 0;
			updateConvertState();
		}

		function showCancelled( converted ) {
			progress.hide();
			cancelBtn.disabled = false;
			clear( convertResult );
			var box = notice( 'warning', sprintf( /* translators: %s: number */ __( 'The conversion was cancelled after converting %s permalinks. They stay converted and can be undone from the “Last conversion” card.', 'greek-multi-tool' ), converted ) );
			box.appendChild( el( 'p', {}, [ el( 'a', { href: config.tabUrl || window.location.href, text: __( 'Reload the page', 'greek-multi-tool' ) } ) ] ) );
			convertResult.appendChild( box );
		}

		function step( data ) {
			// After Cancel, only a request that completes the cancel is sent: the
			// server finishes it before the next object and answers "cancelled".
			if ( cancelled && ! cancelPending ) {
				return;
			}
			batchInFlight = true;
			apiFetch( { path: '/grmlt/v1/conversion/run', method: 'POST', data: data } ).then( function ( r ) {
				batchInFlight = false;
				if ( r.cancelled ) {
					cancelPending = false;
					showCancelled( r.state.posts + r.state.terms );
					return;
				}
				if ( cancelled && r.done ) {
					// The last batch finished before it saw the cancel: the
					// conversion is simply complete.
					cancelPending = false;
					cancelBtn.disabled = false;
					finishConversion( r );
					return;
				}
				if ( cancelled ) {
					if ( cancelPending ) {
						step( { step: 'continue' } );
					}
					return;
				}
				var total = runTotal || previewTotal;
				progress.set( r.state.processed, total, sprintf( /* translators: 1: done, 2: total */ __( 'Converted %1$s of %2$s', 'greek-multi-tool' ), r.state.posts + r.state.terms, total ) );
				if ( r.done ) {
					finishConversion( r );
				} else {
					step( { step: 'continue' } );
				}
			} ).catch( function ( error ) {
				batchInFlight = false;
				if ( cancelled ) {
					if ( error && 'no_run' === error.code ) {
						// Nothing left to cancel: the run finished or was ended in
						// between. The page shows the final state.
						window.location.href = config.tabUrl || window.location.href;
						return;
					}
					// The cancel was completed in between (the batch saw it was
					// superseded): the cancel response already told the user, or
					// the next request will.
					if ( cancelPending && error && 'grmlt_locked' === error.code ) {
						window.setTimeout( function () {
							step( { step: 'continue' } );
						}, 1000 );
					}
					return;
				}
				if ( error && error.data && error.data.run && 'grmlt_running' === error.code ) {
					progress.hide();
					showRunNotice( error.data.run );
					return;
				}
				progress.set( 0, 0, errorMessage( error ) );
				continueBtn.hidden = false;
			} );
		}

		convertBtn.addEventListener( 'click', function () {
			clear( convertResult );
			cancelled = false;
			cancelPending = false;
			runTotal = previewTotal;
			convertBtn.disabled = true;
			continueBtn.hidden = true;
			progress.show();
			progress.set( 0, previewTotal, __( 'Starting…', 'greek-multi-tool' ) );
			step( Object.assign( { step: 'start', backup_confirmed: true }, targets() ) );
		} );

		continueBtn.addEventListener( 'click', function () {
			continueBtn.hidden = true;
			cancelled = false;
			cancelPending = false;
			step( { step: 'continue' } );
		} );

		cancelBtn.addEventListener( 'click', function () {
			if ( cancelled ) {
				return;
			}
			cancelled = true;
			cancelBtn.disabled = true;
			progress.set( 0, 0, __( 'Cancelling after the current permalink…', 'greek-multi-tool' ) );
			apiFetch( { path: '/grmlt/v1/conversion/run', method: 'POST', data: { step: 'cancel' } } ).then( function ( r ) {
				if ( r.pending ) {
					// A batch is running: it completes the cancel itself, and its
					// response (or the follow-up request) shows the result.
					cancelPending = true;
					if ( ! batchInFlight ) {
						step( { step: 'continue' } );
					}
					return;
				}
				showCancelled( r.converted );
			} ).catch( function ( error ) {
				cancelled = false;
				cancelPending = false;
				cancelBtn.disabled = false;
				progress.set( 0, 0, errorMessage( error ) );
				continueBtn.hidden = false;
			} );
		} );

		if ( resumeBtn ) {
			resumeBtn.addEventListener( 'click', function () {
				hideRunNotice();
				cancelled = false;
				cancelPending = false;
				runTotal = 0;
				progress.show();
				progress.set( 0, 0, __( 'Continuing…', 'greek-multi-tool' ) );
				step( { step: 'continue' } );
			} );
		}
		if ( discardBtn ) {
			discardBtn.addEventListener( 'click', function () {
				spinner( discardBtn, true );
				apiFetch( { path: '/grmlt/v1/conversion/run', method: 'POST', data: { step: 'cancel' } } ).then( function () {
					window.location.href = config.tabUrl || window.location.href;
				} ).catch( function ( error ) {
					spinner( discardBtn, false );
					runText.textContent = errorMessage( error );
				} );
			} );
		}

		// Never cancel anything automatically on page load: another window or
		// WP-CLI may be in the middle of it. Just say so and offer the choice.
		if ( config.run ) {
			showRunNotice( config.run );
		}
	}

	/* Undo ---------------------------------------------------------------- */

	var undo = $( '[data-grmlt-undo]' );
	if ( undo ) {
		var undoBtn = $( '#grmlt-undo-btn' );
		var undoProgress = progressUI( $( '#grmlt-undo-progress' ) );
		var undoResult = $( '#grmlt-undo-result' );
		var restored = 0;
		var skipped = 0;

		function undoStep( cursor ) {
			apiFetch( { path: '/grmlt/v1/conversion/undo', method: 'POST', data: { cursor: cursor } } ).then( function ( r ) {
				restored += r.restored;
				skipped += r.skipped;
				undoProgress.set( restored + skipped, r.total, sprintf( /* translators: 1: done, 2: total */ __( 'Restored %1$s of %2$s', 'greek-multi-tool' ), restored + skipped, r.total ) );
				if ( r.done ) {
					undoProgress.hide();
					clear( undoResult );
					undoResult.appendChild( notice( 'success', sprintf( /* translators: 1: restored, 2: skipped */ __( 'Done: %1$s permalinks were restored, %2$s were skipped because they had changed in the meantime. Reload the page to update the lists.', 'greek-multi-tool' ), restored, skipped ) ) );
				} else {
					undoStep( r.cursor );
				}
			} ).catch( function ( error ) {
				undoProgress.hide();
				clear( undoResult );
				undoResult.appendChild( notice( 'error', errorMessage( error ) ) );
				spinner( undoBtn, false );
			} );
		}

		undoBtn.addEventListener( 'click', function () {
			confirmInline( undoBtn, undoBtn.dataset.grmltConfirm, function () {
				spinner( undoBtn, true );
				restored = 0;
				skipped = 0;
				undoProgress.show();
				undoProgress.set( 0, 0, __( 'Starting…', 'greek-multi-tool' ) );
				undoStep( 0 );
			} );
		} );
	}

	/* Redirect check ------------------------------------------------------ */

	var checkBtn = $( '#grmlt-check-btn' );
	if ( checkBtn ) {
		var checkProgress = progressUI( $( '#grmlt-check-progress' ) );
		function checkStep( offset ) {
			apiFetch( { path: '/grmlt/v1/redirects/check', method: 'POST', data: { offset: offset } } ).then( function ( r ) {
				checkProgress.set( r.offset, r.total, sprintf( /* translators: 1: done, 2: total */ __( 'Checked %1$s of %2$s', 'greek-multi-tool' ), r.offset, r.total ) );
				if ( r.done ) {
					checkProgress.set( r.total, r.total, __( 'Check complete. Reloading…', 'greek-multi-tool' ) );
					window.location.reload();
				} else {
					checkStep( r.offset );
				}
			} ).catch( function ( error ) {
				checkProgress.set( 0, 0, errorMessage( error ) );
				spinner( checkBtn, false );
			} );
		}
		checkBtn.addEventListener( 'click', function () {
			spinner( checkBtn, true );
			checkProgress.show();
			checkProgress.set( 0, 0, __( 'Starting…', 'greek-multi-tool' ) );
			checkStep( 0 );
		} );
	}

	/* Inline edit of a redirect destination ------------------------------- */

	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest( '.grmlt-edit-dest' );
		if ( ! button ) {
			return;
		}
		var id = button.dataset.id;
		var dest = $( '.grmlt-dest[data-id="' + id + '"]' );
		if ( ! dest || dest.dataset.editing === '1' ) {
			return;
		}
		dest.dataset.editing = '1';
		var original = dest.dataset.path;
		var editor = el( 'span', { className: 'grmlt-dest-edit' } );
		var input = el( 'input', { type: 'text', value: original, 'aria-label': __( 'Destination path', 'greek-multi-tool' ) } );
		var save = el( 'button', { type: 'button', className: 'button button-small button-primary', text: __( 'Save', 'greek-multi-tool' ) } );
		var cancel = el( 'button', { type: 'button', className: 'button button-small', text: __( 'Cancel', 'greek-multi-tool' ) } );
		var message = el( 'span', { className: 'description', role: 'status' } );
		editor.appendChild( input );
		editor.appendChild( save );
		editor.appendChild( cancel );
		editor.appendChild( message );
		var children = Array.prototype.slice.call( dest.childNodes );
		children.forEach( function ( c ) {
			c.hidden = true;
			if ( c.nodeType === 3 ) {
				c.textContent = '';
			}
		} );
		dest.appendChild( editor );
		input.focus();

		function close() {
			dest.removeChild( editor );
			children.forEach( function ( c ) {
				c.hidden = false;
			} );
			dest.dataset.editing = '0';
			button.focus();
		}
		cancel.addEventListener( 'click', close );
		input.addEventListener( 'keydown', function ( ev ) {
			if ( 'Escape' === ev.key ) {
				close();
			} else if ( 'Enter' === ev.key ) {
				ev.preventDefault();
				save.click();
			}
		} );
		save.addEventListener( 'click', function () {
			save.disabled = true;
			apiFetch( { path: '/grmlt/v1/redirects/update', method: 'POST', data: { id: id, new_path: input.value } } ).then( function ( r ) {
				dest.dataset.path = r.new_path;
				var code = dest.querySelector( '.grmlt-dest__path' );
				var note = dest.querySelector( '.grmlt-dest__note' );
				if ( code ) {
					code.textContent = r.new_path;
				}
				if ( note ) {
					note.textContent = __( 'fixed path', 'greek-multi-tool' );
				}
				close();
			} ).catch( function ( error ) {
				message.textContent = errorMessage( error );
				save.disabled = false;
			} );
		} );
	} );
}() );
