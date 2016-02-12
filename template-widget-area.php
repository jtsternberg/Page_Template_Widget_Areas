// Template Name: Widget Area

add_filter( 'the_content', array( 'Page_Template_Widget_Areas', 'after_content' ) );

genesis();
