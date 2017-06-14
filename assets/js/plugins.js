(function( $ ) {

	$( document ).ready( function() {

		if ( window.location.hash ) {

			if ( 'install_admin_columns' === window.location.hash.substring( 1 ) ) {
				setTimeout( function() {
					jQuery( '.plugin-card-codepress-admin-columns .thickbox' ).trigger( 'click' );
				}, 1000 );
			}

		}

	} );

})( jQuery );