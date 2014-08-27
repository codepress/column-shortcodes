<?php

/*
Plugin Name: 	Column Shortcodes
Version: 		0.6.6
Description: 	Adds shortcodes to easily create columns in your posts or pages
Author: 		Codepress
Author URI: 	http://www.codepresshq.com/
Plugin URI: 	http://www.codepresshq.com/wordpress-plugins/shortcode-columns/
Text Domain: 	column-shortcodes
Domain Path: 	/languages
License:		GPLv2

Copyright 2011-2014  Codepress  info@codepress.nl

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2 as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'CPSH_VERSION', 	'0.6.6' );
define( 'CPSH_URL', 		plugins_url( '', __FILE__ ) );
define( 'CPSH_TEXTDOMAIN', 	'column-shortcodes' );

// Long posts should require a higher limit, see http://core.trac.wordpress.org/ticket/8553
@ini_set( 'pcre.backtrack_limit', 500000 );

/**
 * Column Shortcodes
 *
 * @since 0.1
 */
class Codepress_Column_Shortcodes {

	/**
	 * Prefix
	 *
	 * @since 0.6.3
	 */
	private $prefix;

	/**
	 * Constructor
	 *
	 * @since 0.1
	 */
	function __construct() {

		add_action( 'wp_loaded', array( $this, 'init') );
	}

	/**
	 * Initialize plugin.
	 *
	 * @since 0.1
	 */
	public function init() {

		$this->prefix = trim( apply_filters( 'cpsh_prefix', '' ) );

		add_action( 'admin_init', array( $this, 'add_editor_buttons' ) );
		add_action( 'admin_footer', array( $this, 'popup' ) );

		// styling
		add_action( 'admin_print_styles', array( $this, 'admin_styles') );
		add_action( 'wp_enqueue_scripts',  array( $this, 'frontend_styles') );

		// scripts, only load when editor is available
		add_filter( 'tiny_mce_plugins', array( $this, 'admin_scripts') );

		// translations
		load_plugin_textdomain( CPSH_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		$this->add_shortcodes();
	}

	/**
	 * Register admin css
	 *
	 * @since 0.1
	 */
	public function admin_styles() {
		if ( $this->has_permissions() && $this->is_edit_screen() ) {

			wp_enqueue_style( 'cpsh-admin', CPSH_URL . '/assets/css/admin.css', array(), CPSH_VERSION, 'all' );

			if ( is_rtl() ) {
				wp_enqueue_style( 'cpsh-admin-rtl', CPSH_URL . '/assets/css/admin-rtl.css', array(), CPSH_VERSION, 'all' );
			}
		}
	}

	/**
	 * Register admin scripts
	 *
	 * @since 0.1
	 */
	public function admin_scripts( $plugins ) {
		if ( $this->has_permissions() && $this->is_edit_screen() ) {
			wp_enqueue_script( 'cpsh-admin', CPSH_URL . '/assets/js/admin.js', array( 'jquery' ), CPSH_VERSION );
			wp_enqueue_script( 'jquery-cookie', CPSH_URL . '/assets/js/jquery.ck.js', array( 'jquery' ), CPSH_VERSION );
		}

		return $plugins;
	}

	/**
	 * Register frontend styles
	 *
	 * @since 0.1
	 */
	public function frontend_styles() {
		if ( apply_filters( 'cpsh_load_styles', true ) ) {
			if ( ! is_rtl() ) {
				wp_enqueue_style( 'cpsh-shortcodes', CPSH_URL.'/assets/css/shortcodes.css', array(), CPSH_VERSION, 'all' );
			} else {
				wp_enqueue_style( 'cpsh-shortcodes-rtl', CPSH_URL.'/assets/css/shortcodes-rtl.css', array(), CPSH_VERSION, 'all' );
			}
		}
	}

	/**
	 * Add shortcodes
	 *
	 * @since 0.1
	 */
	private function add_shortcodes() {
		foreach ( $this->get_shortcodes() as $shortcode ) {
			add_shortcode( $shortcode['name'], array( $this, 'columns' ) );
		}
	}

	/**
	 * Insert Markup
	 *
	 * @since 0.1
	 *
	 * @param array $atts
	 * @param string $content
	 * @param string $name
	 * @return string $ouput Column HTML output
	 */
	function columns( $atts, $content = null, $name='' ) {

		$atts = shortcode_atts( array(
			"id" 		=> '',
			"class" 	=> '',
			"padding"	=> '',
		), $atts );

		$id		 = sanitize_text_field( $atts['id'] );
		$class	 = sanitize_text_field( $atts['class'] );
		$padding = sanitize_text_field( $atts['padding'] );

		$id		 = ( $id <> '' ) ? " id='" . esc_attr( $id ) . "'" : '';
		$class	 = ( $class <> '' ) ? esc_attr( ' ' . $class ) : '';

		$content = $this->content_helper( $content );

		// padding generator
		if ( $padding <> '' ) {
			$parts = explode(" ", $padding);

			// check for '0' values. if true we will split padding attributes into top,right,bottom and left.
			if ( $parts && in_array( '0', $parts ) ) {
				$padding  = !empty( $parts[0] ) ? "padding-top:{$parts[0]};" 	: '';
				$padding .= !empty( $parts[1] ) ? "padding-right:{$parts[1]};" 	: '';
				$padding .= !empty( $parts[2] ) ? "padding-bottom:{$parts[2]};"	: '';
				$padding .= !empty( $parts[3] ) ? "padding-left:{$parts[3]};" 	: '';
			}
			else {
				$padding = "padding:{$padding};";
			}

			// wraps the content in an extra div with padding applied
			$content = '<div style="' . esc_attr( $padding ) . '">' . $content . '</div>';
		}

		// last class
		$pos = strpos( $name, '_last' );

		if ( false !== $pos ) {
			$name = str_replace( '_last', ' last_column', $name );
		}

		// remove prefix from classname
		// @todo: prefix css instead of removing the prefix from class attr
		if ( $this->prefix ) {
			$name = str_replace( $this->prefix, '', $name );
		}

		$output = "<div{$id} class='content-column {$name}{$class}'>{$content}</div>";

		if ( false !== $pos ) {
			$output .= "<div class='clear_column'></div>";
		}

		return $output;
	}

	/**
	 * Is edit screen
	 *
	 * @since 0.4
	 */
	private function is_edit_screen() {
		global $pagenow;

		$allowed_screens = apply_filters( 'cpsh_allowed_screens', array( 'post-new.php', 'page-new.php', 'post.php', 'page.php', 'profile.php', 'user-edit.php', 'user-new.php' ) );

		if ( in_array( $pagenow, $allowed_screens ) ) {
			return true;
		}

		return false;
	}

	/**
	 * has permissions
	 *
	 * @since 0.4
	 */
	private function has_permissions() {
		if ( current_user_can( 'edit_posts' ) && current_user_can( 'edit_pages' ) )
			return true;

		return false;
	}

	/**
	 * Add buttons to TimyMCE
	 *
	 * @since 0.1
	 */
	function add_editor_buttons() {

		if ( ! $this->has_permissions() || ! $this->is_edit_screen() )
			return false;

		// add html buttons, when using this filter
		if( apply_filters( 'add_shortcode_html_buttons', false ) ) {
			add_action( 'admin_head', array( $this, 'add_html_buttons' ) );
		}

		// add shortcode button
		add_action( 'media_buttons', array( $this, 'add_shortcode_button' ), 100 );
	}

	/**
	 * Add shortcode button to TimyMCE
	 *
	 * @since 0.1
	 *
	 * @param string $page
	 * @param string $target
	 */
	public function add_shortcode_button( $page = null, $target = null ) {
		?>
			<a href="#TB_inline?width=640&amp;height=600&amp;inlineId=cpsh-wrap" class="thickbox button" title="<?php _e( 'Select shortcode', CPSH_TEXTDOMAIN ); ?>" data-page="<?php echo $page; ?>" data-target="<?php echo $target; ?>">
				<img src="<?php echo CPSH_URL . "/assets/images/shortcode.png";?>" alt="" />
			</a>
		<?php
	}

	/**
	 * TB window Popup
	 *
	 * @since 0.1
	 */
	public function popup() {
		$buttons = $this->get_shortcodes();

		// buttons
		$select = '';
		foreach ( $buttons as $button ) {

			$open_tag 	= str_replace( '\n', '', $button['options']['open_tag'] );
			$close_tag 	= str_replace( '\n', '', $button['options']['close_tag'] );

			$select .= "
				<a href='javascript:;' rel='{$open_tag}{$close_tag}' data-tag='{$open_tag}{$close_tag}' class='cp-{$button['class']} columns insert-shortcode'>
					{$button['options']['display_name']}
				</a>";
		}

		?>

		<div id="cpsh-wrap" style="display:none">
			<div id="cpsh">
				<div id="cpsh-generator-shell">

					<div id="cpsh-generator-header">

						<div class="cpsh-shortcodes">
							<h2 class="cpsh-title"><?php _e( "Column shortcodes", CPSH_TEXTDOMAIN ); ?></h2>
							<?php echo $select; ?>
						</div><!--.cpsh-shortcodes-->

					<?php if ( ! apply_filters( 'cpsh_hide_padding_settings', false ) ) : ?>

						<div class="cpsh-settings">
							<h2 class="cpsh-title"><?php _e( "Column padding ( optional )", CPSH_TEXTDOMAIN ); ?></h2>
							<p class="description">
								<?php _e( "Use the input fields below to customize the padding of your column shortcode.", CPSH_TEXTDOMAIN ); ?>
								<?php _e( "Enter padding first, then select your column shortcode.", CPSH_TEXTDOMAIN ); ?>
							</p>

							<div id="preview-padding">
								<div class="column-container">
									<div class="column-inner">
									</div>
									<div class="padding-fields">
										<input id="padding-top" placeholder="0" value=""/>
										<input id="padding-right" placeholder="0" value=""/>
										<input id="padding-bottom" placeholder="0" value=""/>
										<input id="padding-left" placeholder="0" value=""/>
									</div>
								</div>

								<a class="padding-reset" href="javascript:;"><?php _e( "reset", CPSH_TEXTDOMAIN ); ?></a>
							</div>
						</div><!--.cpsh-settings-->

					<?php endif; ?>

					</div><!--cpsh-generator-header-->

				</div><!--cpsh-generator-shell-->

				<p class='description'>Checkout <a href="http://www.codepresshq.com">other plugins by Codepress</a>.</p>
			</div>
		</div>

		<?php
	}

	/**
	 * get shortcodes
	 *
	 * @since 0.1
	 */
	function get_shortcodes() {
		static $shortcodes;

		if ( ! empty( $shortcodes ) )
			return $shortcodes;

		// define column shortcodes
		$column_shortcodes = apply_filters( 'cpsh_column_shortcodes', array(
			'full_width' 	=> array( 'display_name' => __('full width', CPSH_TEXTDOMAIN ) ),
			'one_half' 		=> array( 'display_name' => __('one half', CPSH_TEXTDOMAIN ) ),
			'one_third' 	=> array( 'display_name' => __('one third', CPSH_TEXTDOMAIN ) ),
			'one_fourth' 	=> array( 'display_name' => __('one fourth', CPSH_TEXTDOMAIN ) ),
			'two_third' 	=> array( 'display_name' => __('two third', CPSH_TEXTDOMAIN ) ),
			'three_fourth' 	=> array( 'display_name' => __('three fourth', CPSH_TEXTDOMAIN ) ),
			'one_fifth' 	=> array( 'display_name' => __('one fifth', CPSH_TEXTDOMAIN ) ),
			'two_fifth' 	=> array( 'display_name' => __('two fifth', CPSH_TEXTDOMAIN ) ),
			'three_fifth' 	=> array( 'display_name' => __('three fifth', CPSH_TEXTDOMAIN ) ),
			'four_fifth' 	=> array( 'display_name' => __('four fifth', CPSH_TEXTDOMAIN ) ),
			'one_sixth' 	=> array( 'display_name' => __('one sixth', CPSH_TEXTDOMAIN ) ),
			'five_sixth' 	=> array( 'display_name' => __('five sixth', CPSH_TEXTDOMAIN ) )
		));

		if ( ! $column_shortcodes )
			return array();

		foreach ( $column_shortcodes as $short => $options ) {

			// add prefix
			$shortcode = $this->prefix . $short;

			$shortcodes[] =	array(
				'name' 		=> $shortcode,
				'class'		=> $short,
				'options' 	=> array(
					'display_name' 	=> $options['display_name'],
					'open_tag' 		=> '\n'."[{$shortcode}]",
					'close_tag' 	=> "[/{$shortcode}]".'\n',
					'key' 			=> ''
				)
			);

			if ( 'full_width' == $short ) continue;

			$shortcodes[] =	array(
				'name' 		=> "{$shortcode}_last",
				'class'		=> "{$short}_last",
				'options' 	=> array(
					'display_name' 	=> $options['display_name'] . ' (' . __('last', CPSH_TEXTDOMAIN) . ')',
					'open_tag' 		=> '\n'."[{$shortcode}_last]",
					'close_tag' 	=> "[/{$shortcode}_last]".'\n',
					'key' 			=> ''
				)
			);
		}

		return $shortcodes;
	}

	/**
	 * Add buttons to TimyMCE HTML tab
	 *
	 * @since 0.1
	 */
	function add_html_buttons() {
		wp_print_scripts( 'quicktags' );

		$shortcodes = $this->get_shortcodes();

		// output script
		$script = '';
		foreach ( $shortcodes as $shortcode ) {
			$options = $shortcode['options'];

			$script .= "edButtons[edButtons.length] = new edButton('ed_{$shortcode['name']}'
				,'{$shortcode['name']}'
				,'{$options['open_tag']}'
				,'{$options['close_tag']}'
				,'{$options['key']}'
			); \n";
		}

		$script = "
			<script type='text/javascript'>\n
				/* <![CDATA[ */ \n
				{$script}
				\n /* ]]> */ \n
			</script>
		";

		echo $script;
	}

	/**
	 * Content Helper
	 *
	 * @since 0.1
	 *
	 * @param string $content
	 * @param bool $paragraph_tag Filter p-tags
	 * @param bool $br_tag Filter br-tags
	 * @return string Shortcode
	 */
	function content_helper( $content, $paragraph_tag = false, $br_tag = false ) {
		$content = preg_replace( '#^<\/p>|^<br \/>|<p>$#', '', $content );

		if ( $br_tag ) {
			$content = preg_replace( '#<br \/>#', '', $content );
		}

		if ( $paragraph_tag ) {
			$content = preg_replace( '#<p>|</p>#', '', $content );
		}

		return do_shortcode( shortcode_unautop( trim( $content ) ) );
	}
}

new Codepress_Column_Shortcodes();
