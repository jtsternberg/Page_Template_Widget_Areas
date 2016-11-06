<?php
/**
 * Template Name: Widget Area
 *
 * This template assumes the Genesis Framework, but only for ease in demonstration.
 *
 * @link https://github.com/jtsternberg/Page_Template_Widget_Areas
 */

add_filter( 'the_content', array( 'Page_Template_Widget_Areas', 'do_after_content' ) );

genesis();
