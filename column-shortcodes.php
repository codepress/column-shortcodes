<?php

/*
Plugin Name: 	Column Shortcodes
Version: 		1.0.1
Description: 	Adds shortcodes to easily create columns in your posts or pages
Author: 		Codepress
Author URI: 	https://www.admincolumns.com/
Plugin URI: 	https://wordpress.org/plugins/column-shortcodes
Text Domain: 	column-shortcodes
Domain Path: 	/languages
License:		GPLv2

Copyright 2011-2023  Codepress  info@codepress.nl

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

// Long posts should require a higher limit, see http://core.trac.wordpress.org/ticket/8553
@ini_set( 'pcre.backtrack_limit', 500000 );

/**
 * Column Shortcodes
 *
 * @since 0.1
 */
class Codepress_Column_Shortcodes {

	/**
	 * @var string
	 */
	private $version;

	/**
	 * Constructor
	 *
	 * @since 0.1
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'add_editor_buttons' ) );
		add_action( 'admin_footer', array( $this, 'popup' ) );
		add_action( 'plugins_loaded', array( $this, 'translations' ) );
		add_action( 'wp_loaded', array( $this, 'add_shortcodes' ) );

		// styling
		add_action( 'admin_print_styles', array( $this, 'admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_plugins_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_styles' ) );

		// scripts, only load when editor is available
		add_filter( 'tiny_mce_plugins', array( $this, 'admin_scripts' ) );
	}

	/**
	 * @since 1.0
	 */
	public function translations() {
		load_plugin_textdomain( 'column-shortcodes', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * @return string
	 */
	private function get_url() {
		return plugin_dir_url( __FILE__ );
	}

	/**
	 * @return string
	 */
	public function get_version() {
		if ( null === $this->version ) {
			$this->version = $this->get_plugin_version( __FILE__ );
		}

		return $this->version;
	}

	/**
	 * @param string $file
	 *
	 * @since 3.0
	 */
	private function get_plugin_version( $file ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin = get_plugin_data( $file, false, false );

		return isset( $plugin['Version'] ) ? $plugin['Version'] : false;
	}

	/**
	 * Prefix
	 *
	 * @since 0.6.3
	 */
	private function get_prefix() {
		return apply_filters( 'cpsh_prefix', '' );
	}

	/**
	 * Register admin css
	 *
	 * @since 0.1
	 */
	public function admin_styles() {
		if ( $this->has_permissions() && $this->is_edit_screen() ) {
			wp_enqueue_style( 'cpsh-admin', $this->get_url() . 'assets/css/admin.css', array(), $this->get_version(), 'all' );

			if ( is_rtl() ) {
				wp_enqueue_style( 'cpsh-admin-rtl', $this->get_url() . 'assets/css/admin-rtl.css', array(), $this->get_version(), 'all' );
			}
		}
	}

	/**
	 * Register admin scripts for the editor
	 *
	 * @param array $plugins
	 *
	 * @since 0.1
	 */
	public function admin_scripts( $plugins ) {
		if ( $this->has_permissions() && $this->is_edit_screen() ) {
			wp_enqueue_script( 'cpsh-admin', $this->get_url() . 'assets/js/admin.js', array( 'jquery' ), $this->get_version() );
			wp_enqueue_script( 'jquery-cookie', $this->get_url() . 'assets/js/jquery.ck.js', array( 'jquery' ), $this->get_version() );
		}

		return $plugins;
	}

	/**
	 * Register admin scripts for the plugins page
	 *
	 * @since 1.0
	 */
	public function admin_plugins_scripts() {
		if ( $this->is_plugins_screen() ) {
			wp_enqueue_script( 'cpsh-admin-plugins', $this->get_url() . 'assets/js/plugins.js', array( 'jquery' ), $this->get_version() );
		}
	}

	/**
	 * Register frontend styles
	 *
	 * @since 0.1
	 */
	public function frontend_styles() {
		if ( apply_filters( 'cpsh_load_styles', true ) ) {
			if ( is_rtl() ) {
				wp_enqueue_style( 'cpsh-shortcodes-rtl', $this->get_url() . 'assets/css/shortcodes-rtl.css', array(), $this->get_version(), 'all' );
			} else {
				wp_enqueue_style( 'cpsh-shortcodes', $this->get_url() . 'assets/css/shortcodes.css', array(), $this->get_version(), 'all' );
			}
		}
	}

	/**
	 * Add shortcodes
	 *
	 * @since 0.1
	 */
	public function add_shortcodes() {
		foreach ( $this->get_shortcodes() as $shortcode ) {
			add_shortcode( $shortcode['name'], array( $this, 'columns' ) );
		}
	}

	/**
	 * Insert Markup
	 *
	 * @since 0.1
	 *
	 * @param array  $atts
	 * @param string $content
	 * @param string $name
	 *
	 * @return string Column HTML output
	 */
	public function columns( $atts, $content = null, $name = '' ) {

		$atts = shortcode_atts( array(
			"id"      => '',
			"class"   => '',
			"padding" => '',
		), $atts );

		$id = sanitize_text_field( $atts['id'] );
		$class = sanitize_text_field( $atts['class'] );
		$padding = sanitize_text_field( $atts['padding'] );

		$id = ( $id <> '' ) ? " id='" . esc_attr( $id ) . "'" : '';
		$class = ( $class <> '' ) ? esc_attr( ' ' . $class ) : '';

		$content = $this->content_helper( $content );

		// padding generator
		if ( $padding <> '' ) {
			$parts = explode( " ", $padding );

			// check for '0' values. if true we will split padding attributes into top,right,bottom and left.
			if ( $parts && in_array( '0', $parts ) ) {
				$padding = ! empty( $parts[0] ) ? "padding-top:{$parts[0]};" : '';
				$padding .= ! empty( $parts[1] ) ? "padding-right:{$parts[1]};" : '';
				$padding .= ! empty( $parts[2] ) ? "padding-bottom:{$parts[2]};" : '';
				$padding .= ! empty( $parts[3] ) ? "padding-left:{$parts[3]};" : '';
			} else {
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

		// remove prefix from class name
		if ( $this->get_prefix() ) {
			$name = str_replace( $this->get_prefix(), '', $name );
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
	private function is_plugins_screen() {
		global $pagenow;

		return 'plugin-install.php' === $pagenow;
	}

	/**
	 * Is edit screen
	 *
	 * @since 0.4
	 */
	private function is_edit_screen() {
		global $pagenow;

		$allowed_screens = apply_filters( 'cpsh_allowed_screens', array( 'post-new.php', 'page-new.php', 'post.php', 'page.php', 'profile.php', 'user-edit.php', 'user-new.php' ) );

		return in_array( $pagenow, $allowed_screens );
	}

	/**
	 * has permissions
	 *
	 * @since 0.4
	 */
	private function has_permissions() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * Add buttons to TinyMCE
	 *
	 * @since 0.1
	 */
	public function add_editor_buttons() {
		if ( $this->has_permissions() && $this->is_edit_screen() ) {

			if ( apply_filters( 'add_shortcode_html_buttons', false ) ) {
				add_action( 'admin_head', array( $this, 'add_html_buttons' ) );
			}

			add_action( 'media_buttons', array( $this, 'add_shortcode_button' ), 100 );
		}
	}

	/**
	 * Add shortcode button to TinyMCE
	 *
	 * @since 0.1
	 *
	 * @param string $page
	 * @param string $target
	 */
	public function add_shortcode_button( $page = null, $target = null ) {
		?>
		<a href="#TB_inline?width=753&amp;height=573&amp;inlineId=cpsh-wrap" class="thickbox button" title="<?php _e( 'Select shortcode', 'column-shortcodes' ); ?>" data-page="<?php echo $page; ?>" data-target="<?php echo $target; ?>">
			<img src="<?php echo $this->get_url() . "/assets/images/shortcode.png"; ?>" alt=""/>
		</a>
		<?php
	}

	/**
	 * @since 1.0
	 */
	private function display_shortcode_buttons() {
		foreach ( $this->get_shortcodes() as $button ) {
			$open_tag = str_replace( '\n', '', $button['options']['open_tag'] );
			$close_tag = str_replace( '\n', '', $button['options']['close_tag'] );

			?>
			<a href='javascript:;' rel='<?php echo esc_attr( $open_tag . $close_tag ); ?>' data-tag='<?php echo esc_attr( $open_tag . $close_tag ); ?>' class='cp-<?php echo esc_attr( $button['class'] ); ?> columns insert-shortcode'>
				<?php echo esc_html( $button['options']['display_name'] ); ?>
			</a>
			<?php
		}
	}

	/**
	 * TB window Popup
	 *
	 * @since 0.1
	 */
	public function popup() {
		?>
		<div id="cpsh-wrap" style="display:none">
			<div id="cpsh">
				<div id="cpsh-generator-shell">

					<div id="cpsh-generator-header">

						<div class="cpsh-shortcodes">
							<h2 class="cpsh-title"><?php _e( "Column shortcodes", 'column-shortcodes' ); ?></h2>
							<?php $this->display_shortcode_buttons(); ?>
						</div>

						<?php if ( ! apply_filters( 'cpsh_hide_padding_settings', false ) ) : ?>

							<div class="cpsh-settings">
								<h2 class="cpsh-title"><?php _e( "Column padding ( optional )", 'column-shortcodes' ); ?></h2>
								<p class="description">
									<?php _e( "Use the input fields below to customize the padding of your column shortcode.", 'column-shortcodes' ); ?>
									<?php _e( "Enter padding first, then select your column shortcode.", 'column-shortcodes' ); ?>
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

									<a class="padding-reset" href="javascript:;"><?php _e( "reset", 'column-shortcodes' ); ?></a>
								</div>
							</div><!--.cpsh-settings-->

						<?php endif; ?>

					</div><!--cpsh-generator-header-->

					<div id="cpsh-generator-sidebar">
						<div class="sidebox" id="cpsh-sidebox-feedback">
							<div id="feedback-choice">
								<h3><?php _e( 'Are you happy with Columns Shortcodes?', 'column-shortcodes' ); ?></h3>

								<div class="inside">
									<a href="#" class="yes"><?php _e( 'Yes' ); ?></a>
									<a href="#" class="no"><?php _e( 'No' ); ?></a>
								</div>
							</div>
							<div id="feedback-support">
								<div class="inside">
									<p>
										<?php _e( "What's wrong? Need help? Let us know: please open a support topic on WordPress.org!", 'column-shortcodes' ); ?>
									</p>
									<ul class="share">
										<li>
											<a href="https://wordpress.org/support/plugin/column-shortcodes#new-post" target="_blank">
												<div class="dashicons dashicons-wordpress"></div> <?php _e( 'Support Forums', 'column-shortcodes' ); ?>
											</a>
										</li>
									</ul>
									<div class="clear"></div>
								</div>
							</div>
							<div id="feedback-rate">
								<div class="inside">
									<p>
										<?php _e( "Woohoo! We're glad to hear that!", 'column-shortcodes' ); ?>
									</p>
									<p>
										<?php _e( 'We would really love it if you could show your appreciation by giving us a rating on WordPress.org or tweet about Column Shortcodes!', 'column-shortcodes' ); ?>
									</p>
									<ul class="share">
										<li>
											<a href="http://wordpress.org/support/view/plugin-reviews/column-shortcodes#new-post" target="_blank">
												<div class="dashicons dashicons-star-empty"></div> <?php _e( 'Rate', 'column-shortcodes' ); ?>
											</a>
										</li>

										<li>
											<a href="<?php echo esc_url( add_query_arg( array( 'hashtags' => 'columnshortcodes', 'text' => urlencode( __( "I'm using Column Shortcodes for WordPress!", 'column-shortcodes' ) ), 'url' => urlencode( 'https://wordpress.org/plugins/column-shortcodes' ) ), 'https://twitter.com/intent/tweet' ) ); ?>" target="_blank">
												<div class="dashicons dashicons-twitter"></div> <?php _e( 'Tweet', 'column-shortcodes' ); ?>
											</a>
										</li>
									</ul>
									<div class="clear"></div>
								</div>
							</div>
						</div><!--cpsh-sidebox-feedback-->

						<?php if ( $this->show_banner() ) : ?>

							<div class="cs-acsidebox">
								<div class="cs-acsidebox__wrapper">
									<p class="cs-acsidebox__intro">
										<?php printf( __( 'Be sure to check out other plugins by Codepress, such as %s. It adds custom columns to your posts, users, comments and media overview in your admin. Get more insight in your content now!', 'column-shortcodes' ), '<a target="_blank" href="https://wordpress.org/plugins/codepress-admin-columns/" class="cs-acsidebox__link">Admin Columns</a>' ); ?>
									</p>
									<a href="<?php echo esc_url( add_query_arg( array( 's' => 'Admin Columns', 'tab' => 'search', 'type' => 'term' ), admin_url( 'plugin-install.php' ) ) ); ?>#install_admin_columns" target="_blank" class="cs-acsidebox__button">
										<img src="<?php echo $this->get_url() . "/assets/images/ac_vignet_grey.svg"; ?>" alt="" class="cs-acsidebox__button__logo"/>
										<?php _e( 'Download for Free', 'column-shortcodes' ); ?>
									</a>
									<div class="cs-acsidebox__stars">
										<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
											<span class="dashicons dashicons-star-filled"></span>
										<?php endfor; ?>
										<span class="cs-acsidebox__stars__count">(<?php echo $this->get_num_ratings(); ?>)</span>
									</div>
									<p class="cs-acsidebox__footer">
										<?php printf( __( "%s Active Installs", 'column-shortcodes' ), '<em>' . $this->get_active_installs() . '+</em>' ); ?>
									</p>
								</div>
							</div><!--cpsh-sidebox-admin-columns-->
						<?php endif; ?>
					</div><!--cpsh-generator-sidebar-->

				</div><!--cpsh-generator-shell-->
			</div>
		</div>

		<?php
	}

	/**
	 * @return bool True when banner is shown
	 */
	private function show_banner() {
		$show_banner = true;

		// Plugin is already installed
		if ( class_exists( 'CPAC' ) || class_exists( 'ACP_Full' ) ) {
			$show_banner = false;
		}

		return apply_filters( 'cpsh_show_banner', $show_banner );
	}

	/**
	 * @return false|stdClass Plugin info object
	 */
	private function get_plugin_info() {
		$data = get_transient( 'cpsh_plugin_admin_columns_info' );

		if ( false === $data && ! get_transient( 'cpsh_plugin_timeout' ) ) {

			include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );

			$info = plugins_api( 'plugin_information', array(
				'slug'   => 'codepress-admin-columns',
				'fields' => array(
					'rating'            => true,
					'ratings'           => true,
					'active_installs'   => true,
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
					'versions'          => false,
				),
			) );

			if ( $info && ! is_wp_error( $info ) && isset( $info->name ) ) {
				set_transient( 'cpsh_plugin_admin_columns_info', $info, DAY_IN_SECONDS * 7 ); // 7 day cache

				$data = $info;
			}

			// Limit request in case API is not responding
			set_transient( 'cpsh_plugin_timeout', true, HOUR_IN_SECONDS * 4 ); // 4 hours
		}

		return $data;
	}

	/**
	 * @return string Active install count
	 */
	private function get_active_installs() {
		$active_installs = 90000; // fallback

		if ( $data = $this->get_plugin_info() ) {
			$active_installs = $data->active_installs;
		}

		return number_format( $active_installs );
	}

	/**
	 * @return string Number of ratings
	 */
	private function get_num_ratings() {
		$active_installs = 730; // fallback

		if ( $data = $this->get_plugin_info() ) {
			$active_installs = $data->num_ratings;
		}

		return number_format( $active_installs );
	}

	/**
	 * get shortcodes
	 *
	 * @since 0.1
	 */
	private function get_shortcodes() {
		$shortcodes = array();

		$column_shortcodes = apply_filters( 'cpsh_column_shortcodes', array(
			'full_width'   => array( 'display_name' => __( 'full width', 'column-shortcodes' ) ),
			'one_half'     => array( 'display_name' => __( 'one half', 'column-shortcodes' ) ),
			'one_third'    => array( 'display_name' => __( 'one third', 'column-shortcodes' ) ),
			'one_fourth'   => array( 'display_name' => __( 'one fourth', 'column-shortcodes' ) ),
			'two_third'    => array( 'display_name' => __( 'two third', 'column-shortcodes' ) ),
			'three_fourth' => array( 'display_name' => __( 'three fourth', 'column-shortcodes' ) ),
			'one_fifth'    => array( 'display_name' => __( 'one fifth', 'column-shortcodes' ) ),
			'two_fifth'    => array( 'display_name' => __( 'two fifth', 'column-shortcodes' ) ),
			'three_fifth'  => array( 'display_name' => __( 'three fifth', 'column-shortcodes' ) ),
			'four_fifth'   => array( 'display_name' => __( 'four fifth', 'column-shortcodes' ) ),
			'one_sixth'    => array( 'display_name' => __( 'one sixth', 'column-shortcodes' ) ),
			'five_sixth'   => array( 'display_name' => __( 'five sixth', 'column-shortcodes' ) ),
		) );

		foreach ( $column_shortcodes as $short => $options ) {

			// add prefix
			$shortcode = $this->get_prefix() . $short;

			$shortcodes[] = array(
				'name'    => $shortcode,
				'class'   => $short,
				'options' => array(
					'display_name' => $options['display_name'],
					'open_tag'     => '\n' . "[{$shortcode}]",
					'close_tag'    => "[/{$shortcode}]" . '\n',
					'key'          => '',
				),
			);

			if ( 'full_width' === $short ) {
				continue;
			}

			$shortcodes[] = array(
				'name'    => "{$shortcode}_last",
				'class'   => "{$short}_last",
				'options' => array(
					'display_name' => $options['display_name'] . ' (' . __( 'last', 'column-shortcodes' ) . ')',
					'open_tag'     => '\n' . "[{$shortcode}_last]",
					'close_tag'    => "[/{$shortcode}_last]" . '\n',
					'key'          => '',
				),
			);
		}

		return $shortcodes;
	}

	/**
	 * Add buttons to TinyMCE HTML tab
	 *
	 * @since 0.1
	 */
	public function add_html_buttons() {
		wp_print_scripts( 'quicktags' );

		// output script
		$script_buttons = array();
		foreach ( $this->get_shortcodes() as $shortcode ) {
			$options = $shortcode['options'];

			$script_buttons[] = "edButtons[edButtons.length] = new edButton('ed_{$shortcode['name']}'
				,'{$shortcode['name']}'
				,'{$options['open_tag']}'
				,'{$options['close_tag']}'
				,'{$options['key']}'
			); \n";
		}

		?>
		<script type='text/javascript'>
			<?php echo implode( $script_buttons ); ?>
		</script>
		<?php
	}

	/**
	 * Content Helper
	 *
	 * @since 0.1
	 *
	 * @param string $content
	 * @param bool   $paragraph_tag Filter p-tags
	 * @param bool   $br_tag        Filter br-tags
	 *
	 * @return string Shortcode
	 */
	private function content_helper( $content, $paragraph_tag = false, $br_tag = false ) {
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