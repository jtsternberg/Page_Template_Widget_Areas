<?php
/**
 * Template Name: Widget Area
 *
 * This template assumes the Genesis Framework, but only for ease in demonstration.
 *
 * @link https://gist.github.com/jtsternberg/7dc6094f1d4416ae447f
 */

add_filter( 'the_content', array( 'Page_Template_Widget_Areas', 'do_after_content' ) );

genesis();
