<?php
/* Notify in customizer */
require get_template_directory() . '/inc/ansar/customizer-notice/businessup-customizer-notify.php';

$config_customizer = array(
	'recommended_plugins'       => array(
		'icyclub' => array(
			'recommended' => true,
			'description' => sprintf('Activate by installing <strong>ICYCLUB</strong> plugin to use front page and all theme features %s.', 'businessup'),
		),
	),
	'recommended_actions'       => array(),
	'recommended_actions_title' => esc_html__( 'Recommended Actions', 'businessup' ),
	'recommended_plugins_title' => esc_html__( 'Recommended Plugin', 'businessup' ),
	'install_button_label'      => esc_html__( 'Install and Activate', 'businessup' ),
	'activate_button_label'     => esc_html__( 'Activate', 'businessup' ),
	'deactivate_button_label'   => esc_html__( 'Deactivate', 'businessup' ),
);
Businessup_Customizer_Notify::init( apply_filters( 'businessup_customizer_notify_array', $config_customizer ) );