/**
 * Resource Library
 *
 * No jQuery. The filter UI is small enough that the dependency would be the
 * largest thing on the page.
 */
( function () {
	'use strict';

	var vars = window.reslibVars || {};

	function init( root ) {
		var state = {
			search: '',
			type: '',
			subject: '',
			year: '',
			paged: 1,
			perPage: parseInt( root.dataset.perPage, 10 ) || 12
		};

		var grid = root.querySelector( '.reslib__grid' );
		var meta = root.querySelector( '.reslib__meta' );
		var pagination = root.querySelector( '.reslib__pagination' );
		var searchInput = root.querySelector( '.reslib__search-input' );
		var clearButton = root.querySelector( '.reslib__clear' );
		var controller = null;
		var searchTimer = null;

		root.style.setProperty( '--reslib-columns', parseInt( root.dataset.columns, 10 ) || 3 );

		root.querySelectorAll( 'select[data-filter]' ).forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				var key = select.dataset.filter.replace( 'resource_', '' );
				state[ key === 'subject' ? 'subject' : key ] = select.value;
				state.paged = 1;
				load();
			} );
		} );

		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				window.clearTimeout( searchTimer );
				searchTimer = window.setTimeout( function () {
					state.search = searchInput.value.trim();
					state.paged = 1;
					load();
				}, 400 );
			} );
		}

		if ( clearButton ) {
			clearButton.addEventListener( 'click', function () {
				state.search = '';
				state.type = '';
				state.subject = '';
				state.year = '';
				state.paged = 1;

				if ( searchInput ) {
					searchInput.value = '';
				}
				root.querySelectorAll( 'select[data-filter]' ).forEach( function ( s ) {
					s.value = '';
				} );

				load();
			} );
		}

		pagination.addEventListener( 'click', function ( e ) {
			var button = e.target.closest( '[data-page]' );

			if ( ! button ) {
				return;
			}

			state.paged = parseInt( button.dataset.page, 10 );
			load();
			root.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} );

		function load() {
			// A fast typist can outrun the network, and responses do not
			// necessarily come back in the order they were sent.
			if ( controller ) {
				controller.abort();
			}
			controller = new AbortController();

			grid.setAttribute( 'aria-busy', 'true' );
			grid.innerHTML = '<div class="reslib__spinner" aria-hidden="true"></div>';

			var body = new window.FormData();
			body.append( 'action', 'reslib_filter' );
			body.append( 'nonce', vars.nonce );
			body.append( 'search', state.search );
			body.append( 'type', state.type );
			body.append( 'subject', state.subject );
			body.append( 'year', state.year );
			body.append( 'paged', state.paged );
			body.append( 'per_page', state.perPage );

			window.fetch( vars.ajaxUrl, {
				method: 'POST',
				body: body,
				credentials: 'same-origin',
				signal: controller.signal
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( payload ) {
					if ( ! payload || ! payload.success ) {
						throw new Error( 'Request failed' );
					}
					render( payload.data );
				} )
				.catch( function ( error ) {
					if ( 'AbortError' === error.name ) {
						return;
					}
					grid.innerHTML = '';
					meta.textContent = vars.i18n.error;
				} )
				.finally( function () {
					grid.setAttribute( 'aria-busy', 'false' );
				} );
		}

		function render( data ) {
			pagination.innerHTML = '';

			if ( ! data.resources.length ) {
				grid.innerHTML = '';
				meta.textContent = vars.i18n.noResults;
				return;
			}

			var first = ( data.page - 1 ) * data.perPage + 1;
			var last = Math.min( data.page * data.perPage, data.found );

			meta.textContent = vars.i18n.showing
				.replace( '%1$s', first )
				.replace( '%2$s', last )
				.replace( '%3$s', data.found );

			grid.innerHTML = '';
			data.resources.forEach( function ( item ) {
				grid.appendChild( card( item ) );
			} );

			if ( data.maxPages > 1 ) {
				renderPagination( data );
			}
		}

		/**
		 * Built with createElement rather than an HTML string, so titles and
		 * excerpts cannot inject markup.
		 */
		function card( item ) {
			var article = el( 'article', 'reslib__card' );

			if ( item.thumbnail ) {
				var media = el( 'div', 'reslib__card-media' );
				var img = document.createElement( 'img' );
				img.src = item.thumbnail;
				img.alt = '';
				img.loading = 'lazy';
				media.appendChild( img );

				if ( item.type.length ) {
					media.appendChild( el( 'span', 'reslib__badge', item.type[ 0 ] ) );
				}

				article.appendChild( media );
			}

			var bodyEl = el( 'div', 'reslib__card-body' );

			var tags = el( 'div', 'reslib__tags' );
			item.subject.forEach( function ( name ) {
				tags.appendChild( el( 'span', 'reslib__tag', name ) );
			} );
			if ( item.year ) {
				tags.appendChild( el( 'span', 'reslib__tag reslib__tag--year', String( item.year ) ) );
			}
			if ( tags.childNodes.length ) {
				bodyEl.appendChild( tags );
			}

			var heading = el( 'h3', 'reslib__card-title' );
			var link = document.createElement( 'a' );
			link.href = item.permalink;
			link.textContent = item.title;
			heading.appendChild( link );
			bodyEl.appendChild( heading );

			if ( item.excerpt ) {
				bodyEl.appendChild( el( 'p', 'reslib__card-excerpt', item.excerpt ) );
			}

			if ( item.fileUrl ) {
				var actions = el( 'div', 'reslib__actions' );

				var view = document.createElement( 'a' );
				view.className = 'reslib__btn reslib__btn--view';
				view.href = item.fileUrl;
				view.target = '_blank';
				view.rel = 'noopener noreferrer';
				view.textContent = vars.i18n.view;

				var download = document.createElement( 'a' );
				download.className = 'reslib__btn reslib__btn--download';
				download.href = item.fileUrl;
				download.setAttribute( 'download', '' );
				download.textContent = vars.i18n.download;

				actions.appendChild( view );
				actions.appendChild( download );
				bodyEl.appendChild( actions );
			}

			article.appendChild( bodyEl );
			return article;
		}

		function renderPagination( data ) {
			var pages = pageNumbers( data.page, data.maxPages );

			pages.forEach( function ( page ) {
				if ( '...' === page ) {
					pagination.appendChild( el( 'span', 'reslib__gap', '…' ) );
					return;
				}

				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'reslib__page' + ( page === data.page ? ' is-current' : '' );
				button.dataset.page = page;
				button.textContent = page;

				if ( page === data.page ) {
					button.setAttribute( 'aria-current', 'page' );
				}

				pagination.appendChild( button );
			} );
		}

		/**
		 * First, last, and a window around the current page. Keeps the control
		 * usable when a library grows past a handful of pages.
		 */
		function pageNumbers( current, total ) {
			if ( total <= 7 ) {
				return range( 1, total );
			}

			if ( current <= 4 ) {
				return range( 1, 5 ).concat( [ '...', total ] );
			}

			if ( current >= total - 3 ) {
				return [ 1, '...' ].concat( range( total - 4, total ) );
			}

			return [ 1, '...' ].concat( range( current - 1, current + 1 ), [ '...', total ] );
		}

		function range( from, to ) {
			var out = [];
			for ( var i = from; i <= to; i++ ) {
				out.push( i );
			}
			return out;
		}

		function el( tag, className, text ) {
			var node = document.createElement( tag );
			node.className = className;
			if ( undefined !== text ) {
				node.textContent = text;
			}
			return node;
		}

		load();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.reslib' ).forEach( init );
	} );
} )();
