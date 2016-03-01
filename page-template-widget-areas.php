<?php
/**
 * Utility class added to themes to allow a "Widget Area" page template.
 * The widget area can be output before, after, or replace the content.
 * Or really, can be output anywhere in the template.
 *
 * Examples for before, after and replacing content.
 * add_filter( 'the_content', array( 'Page_Template_Widget_Areas', 'do_before_content' ) );
 * add_filter( 'the_content', array( 'Page_Template_Widget_Areas', 'do_after_content' ) );
 * add_filter( 'the_content', array( 'Page_Template_Widget_Areas', 'do_replace_content' ) );
 *
 *
 * For convenience, a link to edit the widgets in the customizer will be added to the
 * page edit screen, if the page has this template specified.
 *
 * This utility class removes these page-specific registered sidebars/widget areas
 * from the WordPress admin widget management interface to keep from cluttering it up,
 * and in favor of using the customizer for managing them.
 *
 * If you WANT these to still show in the admin widget management interface:
 * remove_action(
 *     'sidebar_admin_setup',
 *     array(
 *         Page_Template_Widget_Areas::get_instance(),
 *         'deregister_sidebars_on_widget_admin'
 *     )
 * );
 *
 * @version 1.0.0
 * @link    https://gist.github.com/jtsternberg/7dc6094f1d4416ae447f
 *
 */
class Page_Template_Widget_Areas {

	protected static $single_instance = null;
	public static $default_number_of_widget_area_rows = 2;
	public static $sidebar_default_args = array(
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-title widgettitle">',
		'after_title'   => '</h4>',
	);
	protected $widget_areas = array();

	/**
	 * Creates or returns an instance of this class.
	 * @since  0.1.0
	 * @return Page_Template_Widget_Areas A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	protected function __construct() {
		add_action( 'widgets_init', array( $this, 'register_template_widget_areas' ) );
		// Only want our custom widget areas to show up in the customizer.
		add_action( 'sidebar_admin_setup', array( $this, 'deregister_sidebars_on_widget_admin' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_widget_customize_link' ) );
		add_action( 'save_post', array( $this, 'check_to_save_widget_template_pages' ) );

		// Register cmb2 field for widget area rows, and output in publish metabox? maybe? Maybe add_widget_customize_link goes with that field or metabox.
	}

	public function check_to_save_widget_template_pages( $post_id ) {
		// If saving a page w/ the widget page template set,
		// reset our cached widgetized template page
		if ( self::page_is_widget_template( $post_id ) ) {
			self::cache_widgetized_template_pages();
		}
	}

	public function add_widget_customize_link() {
		if ( ! self::page_is_widget_template() ) {
			return;
		}

		$url = admin_url( 'customize.php?autofocus[panel]=widgets&url='. urlencode( get_permalink() ) );
		echo '<div class="misc-pub-section manage-page-widgets">
			<span class="dashicons dashicons-admin-appearance"></span> <a href="'. $url .'">Manage Widgets</a>
		</div>';
	}

	public function register_template_widget_areas() {
		$pages = self::widgetized_template_pages();

		if ( empty( $pages ) ) {
			return;
		}

		foreach ( $pages as $page ) {
			$number_of_widget_area_rows = self::get_page_widget_area_rows( $page->ID );

			if ( $number_of_widget_area_rows > 1 ) {
				for ( $i = 1; $i < ( $number_of_widget_area_rows + 1 ); $i++ ) {
					$this->register_widget_area( array(
						'name' => '| Page Widget Area, Row '. $i,
						'id'   => 'sb-'. $page->post_name . '-' . $i,
					) );
				}
			} else {
				$this->register_widget_area( array(
					'name' => '| Page Widget Area',
					'id'   => 'sb-'. $page->post_name . '-1',
				) );
			}
		}
	}

	protected function register_widget_area( $args ) {
		$this->widget_areas[] = $args['id'];
		register_sidebar( array_merge( $this->sidebar_default_args(), $args ) );
	}

	public static function get( $post_id = 0 ) {
		$post_id = $post_id ? $post_id : get_the_ID();

		ob_start();

		do_action( 'page_template_widget_areas_before_output', $post_id );

		$page = get_post( $post_id );
		if ( isset( $page->ID ) ) {
			$number_of_widget_area_rows = self::get_page_widget_area_rows( $page->ID );

			if ( $number_of_widget_area_rows > 1 ) {
				for ( $i = 1; $i < ( $number_of_widget_area_rows + 1 ); $i++ ) {
					self::do_widget_area( array(
						'post_id' => $post_id,
						'row'     => $i,
						'id'      => 'sb-'. $page->post_name . '-' . $i,
					) );
				}
			} else {
				self::do_widget_area( array(
					'post_id' => $post_id,
					'row'     => $i,
					'id'      => 'sb-'. $page->post_name . '-1',
				) );
			}
		}

		do_action( 'page_template_widget_areas_after_output', $post_id );

		// grab sidebar output from the output buffer
		$output = ob_get_clean();

		return apply_filters( 'page_template_widget_areas_output', $output, $post_id );
	}

	protected static function do_widget_area( $args ) {
		do_action( 'page_template_widget_areas_before_area', $args );
		dynamic_sidebar( $args['id'] );
		do_action( 'page_template_widget_areas_after_area', $args );
	}

	public static function output( $post_id = 0 ) {
		echo self::get( $post_id );
	}

	public static function widgetized_template_pages() {
		return get_option( 'widgetized_template_pages' );
	}

	protected static function cache_widgetized_template_pages() {
		$pages = array();
		$_pages = get_pages( array(
			'meta_key'   => '_wp_page_template',
			'meta_value' => 'template-widget-area.php',
		) );

		if ( ! empty( $_pages ) ) {
			foreach ( $_pages as $page ) {
				$pages[] = (object) array(
					'ID'         => $page->ID,
					'post_title' => $page->post_title,
					'post_name'  => $page->post_name,
				);
			}
		}

		return update_option( 'widgetized_template_pages', $pages );
	}

	public static function get_page_widget_area_rows( $post_id = 0 ) {
		$post_id = $post_id ? $post_id : get_the_ID();

		$number_of_widget_area_rows = absint( get_post_meta( $post_id, 'widget_area_rows', 1 ) );

		$default_number_of_widget_area_rows = apply_filters( 'page_template_widget_areas_default_rows', self::$default_number_of_widget_area_rows );

		return $number_of_widget_area_rows
			? $number_of_widget_area_rows
			: $default_number_of_widget_area_rows;
	}

	public static function page_is_widget_template( $post_id = 0 ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		// This could easily just check for the existence of some meta value (to enable with a checkbox)
		$page_template = get_page_template_slug( $post_id );
		return 'template-widget-area.php' === $page_template;
	}

	public function deregister_sidebars_on_widget_admin() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;
		if ( ! isset( $screen->id ) || 'widgets' !== $screen->id ) {
			return;
		}
		// We only want these widget areas to show in the customizer (and not clutter the widgets screen)
		foreach ( $this->registered_widget_areas() as $widget_area ) {
			unregister_sidebar( $widget_area );
		}
	}

	public function registered_widget_areas() {
		return $this->widget_areas;
	}

	public static function sidebar_default_args() {
		return apply_filters( 'page_template_widget_areas_sidebar_defaults', self::$sidebar_default_args );
	}

	public static function do_before_content( $content ) {
		$sidebars = self::get();
		return $sidebars . $content;
	}

	public static function do_after_content( $content ) {
		$sidebars = self::get();
		return $content . $sidebars;
	}

	public static function do_replace_content() {
		return self::get();
	}

}

add_action( 'after_setup_theme', array( 'Page_Template_Widget_Areas', 'get_instance' ) );
// add_filter( 'the_content', array( 'Page_Template_Widget_Areas', 'do_before_content' ) );
// add_filter( 'the_content', array( 'Page_Template_Widget_Areas', 'do_after_content' ) );
// add_filter( 'the_content', array( 'Page_Template_Widget_Areas', 'do_replace_content' ) );
