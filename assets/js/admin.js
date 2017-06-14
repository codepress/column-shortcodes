(function( $ ) {

	$( document ).ready( function() {
		codepressShortcodes();
		codepressPaddingGenerator();
		codepressSidebarFeedback();
	} );

	/**
	 * @since @NEWVERSION
	 */
	function codepressSidebarFeedback() {
		var sidebox = $( '.sidebox#cpsh-sidebox-feedback' );

		sidebox.find( '#feedback-choice a.no' ).click( function( e ) {
			e.preventDefault();

			sidebox.find( '#feedback-choice' ).slideUp();
			sidebox.find( '#feedback-support' ).slideDown();
		} );

		sidebox.find( '#feedback-choice a.yes' ).click( function( e ) {
			e.preventDefault();

			sidebox.find( '#feedback-choice' ).slideUp();
			sidebox.find( '#feedback-rate' ).slideDown();
		} );
	}

	/**
	 * Send shortcode to editor
	 *
	 */
	function codepressShortcodes() {
		$( '#cpsh .insert-shortcode' ).live( 'click', function( e ) {

			var shortcode = $( this ).attr( 'rel' );
			window.send_to_editor( shortcode );

			e.preventDefault();
			return false;
		} );
	}

	/**
	 * Padding generator
	 *
	 */
	function codepressPaddingGenerator() {

		if ( $( '#preview-padding' ).length === 0 )
			return;

		var fields = $( "#preview-padding .padding-fields input" );

		// init: restore previous settings
		var positions = [ 'top', 'right', 'bottom', 'left' ];

		for ( var p in positions ) {
			var pos = positions[ p ];
			if ( $.cookie( 'cpsh-' + pos ) ) {
				updatePreviewAndShortcode( pos, $.cookie( 'cpsh-' + pos ) );
			}
		}

		// event: user input
		fields.bind( "keyup change", function() {

			var value = $( this ).val();
			var pos = $( this ).attr( 'id' ).replace( 'padding-', '' );

			updatePreviewAndShortcode( pos, value );
		} );

		fields.on( 'blur', function() {
			var value = $( this ).val();
			var isnum = /^\d+$/.test( value );

			if ( isnum ) {
				$( this ).val( value + 'px' ).trigger( 'change' );
			}
		} );

		// event: reset all values
		$( '.padding-reset' ).click( function( e ) {

			fields.val( '' ).trigger( 'change' );
			e.preventDefault();
		} );

		/**
		 * Update preview and shortcode
		 *
		 */
		function updatePreviewAndShortcode( pos, value ) {
			var $field = $( '#padding-' + pos );
			value = suffixPaddingValue( value );

			// inputs
			if ( !$field.val() )
				$field.val( value );

			var preview = $( '#preview-padding .column-container .column-inner' );

			// preview: margins
			preview.css( 'margin-' + pos, value );

			// preview: hide border when margin is zero
			if ( '0px' === preview.css( 'margin-' + pos ) ) {
				preview.css( 'border-' + pos, '0px solid #fff' );
			}
			else {
				preview.css( 'border-' + pos, '1px dashed #097bb4' );
			}

			// shortcode attributes
			var attr_padding = '';
			var has_padding = false;
			for ( var p in positions ) {

				var _input_val = $( '#padding-' + positions[ p ] ).val();
				var attr_val = '0';

				// has padding?
				if ( _input_val !== "" ) {
					attr_val = suffixPaddingValue( _input_val );
					has_padding = true;
				}

				attr_padding += ' ' + attr_val;
			}

			// update: shortcode rel
			$( '.cpsh-shortcodes .columns' ).each( function() {
				if ( !has_padding ) {
					$( this ).attr( 'rel', $( this ).attr( 'data-tag' ) );
				}
				else {
					$( this ).attr( 'rel', $( this ).attr( 'data-tag' ).replace( '][/', ' padding="' + attr_padding.trim() + '"][/' ) );
				}
			} );

			// store settings in cookie
			$.cookie( 'cpsh-' + pos, value );
		}

		/**
		 * When user input is an integer, suffix 'px'
		 */
		function suffixPaddingValue( value ) {

			if ( ( Math.floor( value ) === value ) && $.isNumeric( value ) ) {
				value = value + 'px';
			}

			return value;
		}

	}

})( jQuery );