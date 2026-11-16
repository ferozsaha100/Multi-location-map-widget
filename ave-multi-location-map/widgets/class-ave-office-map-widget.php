<?php
/**
 * Office Map widget (AVE Multi-Location Map 2.0).
 *
 * Unlimited office pins. Coordinates are found ONCE in the Elementor editor
 * ("Find on map" → OpenStreetMap Nominatim, server-side) and saved in the
 * widget as lat/lng, so a visitor's page view never geocodes anything and
 * there is no pin limit.
 *
 * Layouts:
 *  - popup: full-width map, popup (name, address, button) on pin hover/tap.
 *  - list:  office list on the left, pins (numbers optional on each side), list ↔ pin sync.
 *
 * Engines: Leaflet + CARTO tiles (bundled Leaflet) or Google Maps.
 *
 * @package AVE_Multi_Location_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;

class AVE_Office_Map_Widget extends \Elementor\Widget_Base {

	const CARTO_VARIANTS = [
		'light'   => 'light_all',
		'voyager' => 'rastertiles/voyager',
		'dark'    => 'dark_all',
	];

	const GOOGLE_STYLES = [ 'standard', 'silver', 'dark' ];

	/** Handset icon (Tabler Icons "phone", MIT). Kept in sync with ave-office-map.js. */
	const PHONE_ICON = '<svg class="ave-om-phone__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/></svg>';

	public function get_name() {
		return 'ave-office-map';
	}

	public function get_title() {
		return __( 'Office Map', 'ave-mlm' );
	}

	public function get_icon() {
		return 'eicon-map-pin';
	}

	public function get_categories() {
		return [ 'ave-widgets' ];
	}

	public function get_keywords() {
		return [ 'map', 'office', 'location', 'address', 'pin', 'leaflet', 'google maps', 'list' ];
	}

	public function get_script_depends() {
		return [ 'ave-office-map' ];
	}

	public function get_style_depends() {
		return [ 'ave-office-map' ];
	}

	/* =========================================================
	   CONTROLS
	   ========================================================= */

	protected function register_controls() {
		$this->controls_layout();
		$this->controls_offices();
		$this->controls_phones();
		$this->controls_map();
		$this->style_map();
		$this->style_pins();
		$this->style_popup();
		$this->style_phones();
		$this->style_button();
		$this->style_list();
	}

	private function controls_layout() {
		$this->start_controls_section( 'section_layout', [ 'label' => __( 'Layout', 'ave-mlm' ) ] );

		$this->add_control(
			'layout',
			[
				'label'   => __( 'Layout', 'ave-mlm' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'popup',
				'options' => [
					'popup' => __( 'Layout 1: pins with hover popup', 'ave-mlm' ),
					'list'  => __( 'Layout 2: office list + pins', 'ave-mlm' ),
				],
			]
		);

		$this->add_control(
			'number_pins',
			[
				'label'       => __( 'Numbers on pins', 'ave-mlm' ),
				'type'        => Controls_Manager::SWITCHER,
				'label_on'    => __( 'Show', 'ave-mlm' ),
				'label_off'   => __( 'Hide', 'ave-mlm' ),
				'default'     => 'yes',
				'description' => __( 'Hidden: pins show a dot instead.', 'ave-mlm' ),
				'condition'   => [ 'layout' => 'list' ],
			]
		);

		$this->add_control(
			'list_numbers',
			[
				'label'     => __( 'Numbers in office list', 'ave-mlm' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => __( 'Show', 'ave-mlm' ),
				'label_off' => __( 'Hide', 'ave-mlm' ),
				'default'   => 'yes',
				'condition' => [ 'layout' => 'list' ],
			]
		);

		$this->end_controls_section();
	}

	private function controls_offices() {
		$this->start_controls_section( 'section_offices', [ 'label' => __( 'Offices', 'ave-mlm' ) ] );

		$repeater = new Repeater();

		$repeater->add_control(
			'office_name',
			[
				'label'       => __( 'Office name', 'ave-mlm' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Main Office', 'ave-mlm' ),
				'label_block' => true,
			]
		);

		$repeater->add_control(
			'address',
			[
				'label'       => __( 'Address', 'ave-mlm' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => __( '100 2nd Ave N, St. Petersburg, FL 33701', 'ave-mlm' ),
			]
		);

		$repeater->add_control(
			'phones',
			[
				'label'       => __( 'Phone numbers', 'ave-mlm' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 4,
				'placeholder' => "Jonathon Kyes | Office Manager | (727) 555-0101\nFront Desk | (727) 555-0100",
				'description' => __( 'One per line: Name | Role | Number. Name and role are optional: "Front Desk | (727) 555-0100" or just the number also work.', 'ave-mlm' ),
			]
		);

		$repeater->add_control(
			'find_pin',
			[
				'type'        => Controls_Manager::BUTTON,
				'label'       => __( 'Pin position', 'ave-mlm' ),
				'text'        => __( 'Find on map', 'ave-mlm' ),
				'button_type' => 'default',
				'event'       => 'aveOfficeMap:findPin',
				'separator'   => 'before',
			]
		);

		$repeater->add_control(
			'lat',
			[
				'label'       => __( 'Latitude', 'ave-mlm' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '27.773056',
			]
		);

		$repeater->add_control(
			'lng',
			[
				'label'       => __( 'Longitude', 'ave-mlm' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '-82.639999',
				'description' => __( 'Filled in by "Find on map". Edit to fine-tune a pin.', 'ave-mlm' ),
			]
		);

		$repeater->add_control(
			'pin_heading',
			[
				'label'     => __( 'Pin for this office', 'ave-mlm' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$repeater->add_control(
			'pin_color',
			[
				'label'       => __( 'Pin colour', 'ave-mlm' ),
				'type'        => Controls_Manager::COLOR,
				'description' => __( 'Leave empty to use the colour from Style > Pins.', 'ave-mlm' ),
				// Elementor writes this per office (global kit colours work too).
				'selectors'   => [ '{{WRAPPER}} {{CURRENT_ITEM}}' => '--ave-om-pin: {{VALUE}}; --ave-om-pin-active: {{VALUE}};' ],
			]
		);

		$repeater->add_control(
			'pin_icon',
			[
				'label'       => __( 'Custom pin icon / logo', 'ave-mlm' ),
				'type'        => Controls_Manager::MEDIA,
				'media_types' => [ 'image', 'svg' ],
				'description' => __( 'Replaces the pin for this office. A PNG or SVG with a transparent background works best; its bottom centre sits on the location. Height: Style > Pins > Custom icon height.', 'ave-mlm' ),
			]
		);

		$repeater->add_control(
			'link',
			[
				'label'       => __( 'Button link', 'ave-mlm' ),
				'type'        => Controls_Manager::URL,
				'label_block' => true,
				'placeholder' => __( 'https://your-site.com/office or #contact', 'ave-mlm' ),
				'separator'   => 'before',
				'description' => __( 'The button only shows when a link is set.', 'ave-mlm' ),
			]
		);

		$repeater->add_control(
			'link_text',
			[
				'label'   => __( 'Button text', 'ave-mlm' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'View Details', 'ave-mlm' ),
			]
		);

		$this->add_control(
			'offices',
			[
				'label'       => __( 'Offices', 'ave-mlm' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ office_name }}}',
				'default'     => [
					[
						'office_name' => __( 'Downtown St. Pete', 'ave-mlm' ),
						'address'     => '100 2nd Ave N, St. Petersburg, FL 33701',
						'phones'      => "Jonathon Kyes | Office Manager | (727) 555-0101\nFront Desk | (727) 555-0100",
						'lat'         => '27.772760',
						'lng'         => '-82.635960',
					],
					[
						'office_name' => __( 'Tampa Westshore', 'ave-mlm' ),
						'address'     => '4200 W Cypress St, Tampa, FL 33607',
						'lat'         => '27.952530',
						'lng'         => '-82.517290',
					],
				],
			]
		);

		$this->add_control(
			'find_all',
			[
				'type'        => Controls_Manager::BUTTON,
				'label'       => __( 'Missing pins', 'ave-mlm' ),
				'text'        => __( 'Find all missing pins', 'ave-mlm' ),
				'button_type' => 'success',
				'event'       => 'aveOfficeMap:findAll',
				'separator'   => 'before',
			]
		);

		$this->add_control(
			'offices_note',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Each address is looked up once with OpenStreetMap and saved as latitude/longitude. Visitors never trigger a lookup, so there is no limit on pins. "Find all" looks up about one office per second.', 'ave-mlm' ),
				'content_classes' => 'elementor-descriptor',
			]
		);

		$this->end_controls_section();
	}

	private function controls_map() {
		$this->start_controls_section( 'section_map', [ 'label' => __( 'Map settings', 'ave-mlm' ) ] );

		$this->add_control(
			'engine',
			[
				'label'   => __( 'Map engine', 'ave-mlm' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'leaflet',
				'options' => [
					'leaflet' => __( 'Leaflet + CARTO (free)', 'ave-mlm' ),
					'google'  => __( 'Google Maps', 'ave-mlm' ),
				],
			]
		);

		$this->add_control(
			'leaflet_style',
			[
				'label'     => __( 'Map style', 'ave-mlm' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'light',
				'options'   => [
					'light'   => __( 'Light', 'ave-mlm' ),
					'voyager' => __( 'Voyager (colour)', 'ave-mlm' ),
					'dark'    => __( 'Dark', 'ave-mlm' ),
				],
				'condition' => [ 'engine' => 'leaflet' ],
			]
		);

		$this->add_control(
			'google_style',
			[
				'label'     => __( 'Map style', 'ave-mlm' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'standard',
				'options'   => [
					'standard' => __( 'Standard', 'ave-mlm' ),
					'silver'   => __( 'Silver', 'ave-mlm' ),
					'dark'     => __( 'Dark', 'ave-mlm' ),
				],
				'condition' => [ 'engine' => 'google' ],
			]
		);

		$this->add_control(
			'keys_note',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: settings page link */
					esc_html__( 'Map keys are set once per site in %s.', 'ave-mlm' ),
					'<a href="' . esc_url( AVE_MLM_Settings::settings_url() ) . '" target="_blank">' . esc_html__( 'Settings > AVE Maps', 'ave-mlm' ) . '</a>'
				),
				'content_classes' => 'elementor-descriptor',
			]
		);

		$this->add_control(
			'max_zoom',
			[
				'label'       => __( 'Closest zoom when fitting pins', 'ave-mlm' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 15,
				'min'         => 3,
				'max'         => 19,
				'description' => __( 'The map zooms to show every pin, but never closer than this (a single office uses this zoom).', 'ave-mlm' ),
				'separator'   => 'before',
			]
		);

		$this->add_control(
			'zoom_controls',
			[
				'label'   => __( 'Zoom buttons', 'ave-mlm' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'scroll_zoom',
			[
				'label'       => __( 'Mouse-wheel zoom', 'ave-mlm' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => __( 'Off keeps the page scrolling past the map; visitors zoom with the buttons or pinch.', 'ave-mlm' ),
			]
		);

		$this->end_controls_section();
	}

	private function style_map() {
		$this->start_controls_section(
			'section_style_map',
			[
				'label' => __( 'Map', 'ave-mlm' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'map_height',
			[
				'label'          => __( 'Height', 'ave-mlm' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => [ 'px', 'vh' ],
				'range'          => [
					'px' => [ 'min' => 240, 'max' => 1000 ],
					'vh' => [ 'min' => 20, 'max' => 100 ],
				],
				'default'        => [ 'unit' => 'px', 'size' => 520 ],
				'mobile_default' => [ 'unit' => 'px', 'size' => 380 ],
				'selectors'      => [ '{{WRAPPER}} .ave-om' => '--ave-om-height: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'     => 'frame_border',
				'selector' => '{{WRAPPER}} .ave-om__body',
			]
		);

		$this->add_responsive_control(
			'frame_radius',
			[
				'label'      => __( 'Border radius', 'ave-mlm' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [ '{{WRAPPER}} .ave-om__body' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_pins() {
		$this->start_controls_section(
			'section_style_pins',
			[
				'label' => __( 'Pins', 'ave-mlm' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'pin_color',
			[
				'label'     => __( 'Pin colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1F2937',
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-pin: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'pin_active_color',
			[
				'label'     => __( 'Highlighted pin colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0B22C7',
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-pin-active: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'pin_text_color',
			[
				'label'     => __( 'Number / dot colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-pin-text: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'pin_size',
			[
				'label'     => __( 'Pin size', 'ave-mlm' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 20, 'max' => 56 ] ],
				'default'   => [ 'unit' => 'px', 'size' => 32 ],
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-pin-size: {{SIZE}}px;' ],
			]
		);

		$this->add_control(
			'icon_size',
			[
				'label'       => __( 'Custom icon height', 'ave-mlm' ),
				'type'        => Controls_Manager::SLIDER,
				'range'       => [ 'px' => [ 'min' => 20, 'max' => 120 ] ],
				'default'     => [ 'unit' => 'px', 'size' => 40 ],
				'description' => __( 'For offices with a custom pin icon / logo. Width follows the image shape.', 'ave-mlm' ),
				'selectors'   => [ '{{WRAPPER}} .ave-om' => '--ave-om-icon-size: {{SIZE}}px;' ],
			]
		);

		$this->add_control(
			'pin_colors_note',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'These colours apply to every pin. To give one office its own colour or logo, open it under Content > Offices > Pin for this office.', 'ave-mlm' ),
				'content_classes' => 'elementor-descriptor',
			]
		);

		$this->end_controls_section();
	}

	private function style_popup() {
		$this->start_controls_section(
			'section_style_popup',
			[
				'label' => __( 'Popup', 'ave-mlm' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'popup_bg',
			[
				'label'     => __( 'Background', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-popup-bg: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'popup_width',
			[
				'label'     => __( 'Width', 'ave-mlm' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 180, 'max' => 400 ] ],
				'default'   => [ 'unit' => 'px', 'size' => 300 ],
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-popup-width: {{SIZE}}px;' ],
			]
		);

		$this->add_control(
			'popup_name_color',
			[
				'label'     => __( 'Name colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [ '{{WRAPPER}} .ave-om-pop__name' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'popup_name_typography',
				'label'    => __( 'Name typography', 'ave-mlm' ),
				'selector' => '{{WRAPPER}} .ave-om-pop__name',
			]
		);

		$this->add_control(
			'popup_address_color',
			[
				'label'     => __( 'Address colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#4B5563',
				'selectors' => [ '{{WRAPPER}} .ave-om-pop__address' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'popup_address_typography',
				'label'    => __( 'Address typography', 'ave-mlm' ),
				'selector' => '{{WRAPPER}} .ave-om-pop__address',
			]
		);

		$this->add_responsive_control(
			'popup_padding',
			[
				'label'      => __( 'Padding', 'ave-mlm' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors'  => [ '{{WRAPPER}} .ave-om-pop' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'popup_radius',
			[
				'label'     => __( 'Border radius', 'ave-mlm' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 24 ] ],
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-popup-radius: {{SIZE}}px;' ],
			]
		);

		$this->end_controls_section();
	}

	private function controls_phones() {
		$this->start_controls_section( 'section_phones', [ 'label' => __( 'Phone numbers', 'ave-mlm' ) ] );

		$this->add_control(
			'phone_label_position',
			[
				'label'   => __( 'Role label position', 'ave-mlm' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'below',
				'options' => [
					'right-top'    => __( 'Right of number, top', 'ave-mlm' ),
					'right-bottom' => __( 'Right of number, bottom', 'ave-mlm' ),
					'above'        => __( 'Above number', 'ave-mlm' ),
					'below'        => __( 'Below number', 'ave-mlm' ),
				],
			]
		);

		$this->add_control(
			'phone_label_style',
			[
				'label'   => __( 'Role label style', 'ave-mlm' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'text',
				'options' => [
					'pill' => __( 'Pill', 'ave-mlm' ),
					'text' => __( 'Small caps text', 'ave-mlm' ),
				],
			]
		);

		$this->add_control(
			'phone_icon',
			[
				'label'   => __( 'Phone icon', 'ave-mlm' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'phones_note',
			[
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Add numbers per office under Offices > Phone numbers. A name shows above its number; the role label uses the position and style above. They show in the popup and in the office list, and tap-to-call on phones.', 'ave-mlm' ),
				'content_classes' => 'elementor-descriptor',
			]
		);

		$this->end_controls_section();
	}

	private function style_phones() {
		$this->start_controls_section(
			'section_style_phones',
			[
				'label' => __( 'Phone numbers', 'ave-mlm' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'phone_name_color',
			[
				'label'     => __( 'Name colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [ '{{WRAPPER}} .ave-om-phone__name' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'phone_name_typography',
				'label'    => __( 'Name typography', 'ave-mlm' ),
				'selector' => '{{WRAPPER}} .ave-om-phone__name',
			]
		);

		$this->add_control(
			'phone_number_color',
			[
				'label'     => __( 'Number colour', 'ave-mlm' ),
				'separator' => 'before',
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => [ '{{WRAPPER}} a.ave-om-phone__number' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'phone_number_hover_color',
			[
				'label'     => __( 'Number hover colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0B22C7',
				'selectors' => [ '{{WRAPPER}} a.ave-om-phone__number:hover, {{WRAPPER}} a.ave-om-phone__number:focus-visible' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'phone_number_typography',
				'label'    => __( 'Number typography', 'ave-mlm' ),
				'selector' => '{{WRAPPER}} .ave-om-phone__number',
			]
		);

		$this->add_control(
			'phone_label_color',
			[
				'label'     => __( 'Label colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-label-text: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'phone_pill_bg',
			[
				'label'     => __( 'Pill background', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'rgba(11, 34, 199, 0.08)',
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-pill-bg: {{VALUE}};' ],
				'condition' => [ 'phone_label_style' => 'pill' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'phone_label_typography',
				'label'    => __( 'Label typography', 'ave-mlm' ),
				'selector' => '{{WRAPPER}} .ave-om-phone__label',
			]
		);

		$this->add_control(
			'phone_icon_color',
			[
				'label'     => __( 'Icon colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6B7280',
				'separator' => 'before',
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-phone-icon: {{VALUE}};' ],
				'condition' => [ 'phone_icon' => 'yes' ],
			]
		);

		$this->add_control(
			'phone_gap',
			[
				'label'       => __( 'Space between numbers', 'ave-mlm' ),
				'type'        => Controls_Manager::SLIDER,
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 32 ] ],
				'description' => __( 'Default: 6px, or 12px when numbers have names.', 'ave-mlm' ),
				'selectors'   => [ '{{WRAPPER}} .ave-om' => '--ave-om-phone-gap: {{SIZE}}px;' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_button() {
		$this->start_controls_section(
			'section_style_button',
			[
				'label' => __( 'Button', 'ave-mlm' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'btn_typography',
				'selector' => '{{WRAPPER}} .ave-om__btn',
			]
		);

		$this->start_controls_tabs( 'btn_tabs' );

		$this->start_controls_tab( 'btn_normal', [ 'label' => __( 'Normal', 'ave-mlm' ) ] );
		$this->add_control(
			'btn_color',
			[
				'label'     => __( 'Text colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .ave-om__btn' => 'color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'btn_bg',
			[
				'label'     => __( 'Background', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0B22C7',
				'selectors' => [ '{{WRAPPER}} .ave-om__btn' => 'background-color: {{VALUE}};' ],
			]
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'btn_hover', [ 'label' => __( 'Hover', 'ave-mlm' ) ] );
		$this->add_control(
			'btn_color_hover',
			[
				'label'     => __( 'Text colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .ave-om__btn:hover, {{WRAPPER}} .ave-om__btn:focus-visible' => 'color: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'btn_bg_hover',
			[
				'label'     => __( 'Background', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#08189C',
				'selectors' => [ '{{WRAPPER}} .ave-om__btn:hover, {{WRAPPER}} .ave-om__btn:focus-visible' => 'background-color: {{VALUE}};' ],
			]
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'btn_padding',
			[
				'label'      => __( 'Padding', 'ave-mlm' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'separator'  => 'before',
				'selectors'  => [ '{{WRAPPER}} .ave-om__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'btn_radius',
			[
				'label'      => __( 'Border radius', 'ave-mlm' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [ '{{WRAPPER}} .ave-om__btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();
	}

	private function style_list() {
		$this->start_controls_section(
			'section_style_list',
			[
				'label'     => __( 'Office list', 'ave-mlm' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [ 'layout' => 'list' ],
			]
		);

		$this->add_responsive_control(
			'list_width',
			[
				'label'      => __( 'List width', 'ave-mlm' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'range'      => [
					'px' => [ 'min' => 220, 'max' => 600 ],
					'%'  => [ 'min' => 20, 'max' => 60 ],
				],
				'default'    => [ 'unit' => 'px', 'size' => 340 ],
				'selectors'  => [ '{{WRAPPER}} .ave-om' => '--ave-om-list-width: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'list_bg',
			[
				'label'     => __( 'Background', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .ave-om__list' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'list_active_bg',
			[
				'label'     => __( 'Highlighted office background', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#EEF1FD',
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-item-active-bg: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'list_divider',
			[
				'label'     => __( 'Divider colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#E5E7EB',
				'selectors' => [ '{{WRAPPER}} .ave-om' => '--ave-om-divider: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'item_padding',
			[
				'label'      => __( 'Office padding', 'ave-mlm' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors'  => [ '{{WRAPPER}} .ave-om__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_control(
			'list_name_color',
			[
				'label'     => __( 'Name colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'separator' => 'before',
				'selectors' => [ '{{WRAPPER}} .ave-om__name' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'list_name_typography',
				'label'    => __( 'Name typography', 'ave-mlm' ),
				'selector' => '{{WRAPPER}} .ave-om__name',
			]
		);

		$this->add_control(
			'list_address_color',
			[
				'label'     => __( 'Address colour', 'ave-mlm' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#4B5563',
				'selectors' => [ '{{WRAPPER}} .ave-om__address' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'list_address_typography',
				'label'    => __( 'Address typography', 'ave-mlm' ),
				'selector' => '{{WRAPPER}} .ave-om__address',
			]
		);

		$this->end_controls_section();
	}

	/* =========================================================
	   RENDER — no network access here, ever.
	   ========================================================= */

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$is_editor = $this->is_editor_context();
		$layout    = ( isset( $settings['layout'] ) && 'list' === $settings['layout'] ) ? 'list' : 'popup';
		$engine    = ( isset( $settings['engine'] ) && 'google' === $settings['engine'] ) ? 'google' : 'leaflet';
		$numbers   = 'list' === $layout && ( ! isset( $settings['number_pins'] ) || 'yes' === $settings['number_pins'] ); // On the pins.
		$list_nums = 'list' === $layout && ( ! isset( $settings['list_numbers'] ) || 'yes' === $settings['list_numbers'] ); // In the list.

		$offices = [];
		$missing = [];
		foreach ( (array) ( isset( $settings['offices'] ) ? $settings['offices'] : [] ) as $row ) {
			$name    = isset( $row['office_name'] ) ? trim( wp_strip_all_tags( (string) $row['office_name'] ) ) : '';
			$address = isset( $row['address'] ) ? trim( wp_strip_all_tags( (string) $row['address'] ) ) : '';
			$lat     = self::coord( isset( $row['lat'] ) ? $row['lat'] : '', 90 );
			$lng     = self::coord( isset( $row['lng'] ) ? $row['lng'] : '', 180 );

			if ( '' === $name && '' === $address ) {
				continue;
			}
			if ( null === $lat || null === $lng ) {
				$missing[] = '' !== $name ? $name : $address;
				continue;
			}

			$url       = ! empty( $row['link']['url'] ) ? esc_url_raw( $row['link']['url'] ) : '';
			$offices[] = [
				'name'     => $name,
				'address'  => $address,
				'lat'      => $lat,
				'lng'      => $lng,
				'url'      => $url,
				'external' => '' !== $url && ! empty( $row['link']['is_external'] ),
				'nofollow' => '' !== $url && ! empty( $row['link']['nofollow'] ),
				'linkText' => ! empty( $row['link_text'] ) ? wp_strip_all_tags( (string) $row['link_text'] ) : __( 'View Details', 'ave-mlm' ),
				'phones'   => self::parse_phones( isset( $row['phones'] ) ? $row['phones'] : '' ),
				// Per-office pin: Elementor's {{CURRENT_ITEM}} CSS targets this class.
				'item'     => ! empty( $row['_id'] ) ? 'elementor-repeater-item-' . sanitize_html_class( $row['_id'] ) : '',
				'icon'     => ! empty( $row['pin_icon']['url'] ) ? esc_url_raw( $row['pin_icon']['url'] ) : '',
				'ownColor' => ! empty( $row['pin_color'] ) || ! empty( $row['__globals__']['pin_color'] ),
			];
		}

		$key_problem = $this->key_problem( $engine );
		$config      = [
			'engine'      => $engine,
			'layout'      => $layout,
			'numbers'     => $numbers,
			'zoomControl' => ! isset( $settings['zoom_controls'] ) || 'yes' === $settings['zoom_controls'],
			'scrollZoom'  => isset( $settings['scroll_zoom'] ) && 'yes' === $settings['scroll_zoom'],
			'maxZoom'     => isset( $settings['max_zoom'] ) && '' !== $settings['max_zoom'] ? max( 3, min( 19, (int) $settings['max_zoom'] ) ) : 15,
			'offices'     => $offices,
			'label'       => __( 'Office locations map', 'ave-mlm' ),
		];

		if ( 'leaflet' === $engine ) {
			$style             = ( isset( $settings['leaflet_style'] ) && array_key_exists( $settings['leaflet_style'], self::CARTO_VARIANTS ) ) ? $settings['leaflet_style'] : 'light';
			$config['style']   = $style;
			$config['tiles']   = [
				'url'         => 'https://{s}.basemaps.cartocdn.com/' . self::CARTO_VARIANTS[ $style ] . '/{z}/{x}/{y}{r}.png?key=' . rawurlencode( AVE_MLM_Settings::get( 'carto_key' ) ),
				'subdomains'  => 'abcd',
				'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions" target="_blank" rel="noopener">CARTO</a>',
			];
			$config['leaflet'] = [
				'js'  => AVE_MLM_URL . 'assets/vendor/leaflet/' . AVE_MLM_LEAFLET_VERSION . '/leaflet.js',
				'css' => AVE_MLM_URL . 'assets/vendor/leaflet/' . AVE_MLM_LEAFLET_VERSION . '/leaflet.css',
			];
		} else {
			$config['style']     = isset( $settings['google_style'] ) && in_array( $settings['google_style'], self::GOOGLE_STYLES, true ) ? $settings['google_style'] : 'standard';
			$config['googleKey'] = AVE_MLM_Settings::get( 'google_key' );
		}
		$config['disabled'] = '' !== $key_problem && 'leaflet' === $engine; // Google may already be on the page via another plugin.

		$label_pos   = isset( $settings['phone_label_position'] ) && in_array( $settings['phone_label_position'], [ 'right-top', 'right-bottom', 'above', 'below' ], true ) ? $settings['phone_label_position'] : 'below';
		$label_style = isset( $settings['phone_label_style'] ) && 'pill' === $settings['phone_label_style'] ? 'pill' : 'text';
		$phone_icon  = ! isset( $settings['phone_icon'] ) || 'yes' === $settings['phone_icon'];

		$classes = 'ave-om ave-om--' . $layout . ( $numbers ? ' ave-om--numbered' : '' )
			. ' ave-om--label-' . $label_pos . ' ave-om--label-' . $label_style
			. ( $phone_icon ? ' ave-om--phone-icon' : '' );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-ave-om="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<?php
			if ( $is_editor ) {
				$this->render_editor_notice( $missing, $key_problem, count( $offices ) );
			}
			?>
			<div class="ave-om__body">
				<?php if ( 'list' === $layout && $offices ) : ?>
					<div class="ave-om__list">
						<ul class="ave-om__items">
							<?php foreach ( $offices as $i => $office ) : ?>
								<li class="<?php echo esc_attr( trim( 'ave-om__item ' . $office['item'] ) ); ?>" data-index="<?php echo (int) $i; ?>">
									<?php if ( $list_nums ) : ?>
										<span class="ave-om__num" aria-hidden="true"><?php echo (int) ( $i + 1 ); ?></span>
									<?php endif; ?>
									<div class="ave-om__text">
										<button type="button" class="ave-om__select">
											<span class="ave-om__name"><?php echo esc_html( $office['name'] ); ?></span>
											<?php if ( '' !== $office['address'] ) : ?>
												<span class="ave-om__address"><?php echo esc_html( $office['address'] ); ?></span>
											<?php endif; ?>
										</button>
										<?php $this->render_phones( $office['phones'] ); ?>
										<?php if ( '' !== $office['url'] ) : ?>
											<a class="ave-om__btn" href="<?php echo esc_url( $office['url'] ); ?>"<?php echo $this->link_attrs( $office ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from fixed strings. ?>><?php echo esc_html( $office['linkText'] ); ?></a>
										<?php endif; ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<div class="ave-om__map" role="region" aria-label="<?php echo esc_attr( $config['label'] ); ?>"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Parse the "Phone numbers" textarea. One per line:
	 *   "Name | Role | Number"  →  name above the number, role as the label
	 *   "Role | Number"         →  label only (2.1.0 format)
	 *   "Role: Number", or just "Number".
	 * Whichever part holds the most digits is the number, so the order is
	 * forgiving; of the other parts, the first is the name, the second the role.
	 * Max 10 per office.
	 *
	 * @param string $raw Textarea value.
	 * @return array[] { name, label, number, tel }
	 */
	public static function parse_phones( $raw ) {
		$phones = [];
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( wp_strip_all_tags( $line ) );
			if ( '' === $line ) {
				continue;
			}

			if ( false !== strpos( $line, '|' ) ) {
				// Empty slots keep their position: "Ana Ruiz |  | 555-0100" = name, no role.
				$parts = array_map( 'trim', explode( '|', $line, 3 ) );
			} elseif ( preg_match( '/^([^:\d][^:]*):\s*(.+)$/u', $line, $m ) ) {
				$parts = [ trim( $m[1] ), trim( $m[2] ) ];
			} else {
				$parts = [ $line ];
			}
			if ( ! $parts ) {
				continue;
			}

			// The part with the most digits is the number.
			$num_index = 0;
			foreach ( $parts as $i => $part ) {
				if ( self::digits( $part ) > self::digits( $parts[ $num_index ] ) ) {
					$num_index = $i;
				}
			}
			$number = $parts[ $num_index ];
			$others = array_values( array_diff_key( $parts, [ $num_index => true ] ) );

			$name  = '';
			$label = '';
			if ( 2 === count( $others ) ) {
				list( $name, $label ) = $others;
			} elseif ( 1 === count( $others ) ) {
				$label = $others[0];
			}

			$tel = self::tel( $number );
			if ( '' === $tel ) {
				continue;
			}
			$phones[] = [
				'name'   => $name,
				'label'  => $label,
				'number' => $number,
				'tel'    => $tel,
			];
			if ( count( $phones ) >= 10 ) {
				break;
			}
		}
		return $phones;
	}

	/**
	 * tel: target — leading "+" and digits; an extension ("ext 12", "x12")
	 * becomes a dial pause (",12"), which phone dialers understand.
	 *
	 * @param string $number Number as typed.
	 * @return string '' when there are fewer than 3 digits.
	 */
	public static function tel( $number ) {
		$number = (string) $number;
		$ext    = '';
		if ( preg_match( '/\s*(?:ext\.?|extension|x)\s*(\d{1,6})\s*$/i', $number, $m ) ) {
			$ext    = ',' . $m[1];
			$number = substr( $number, 0, -strlen( $m[0] ) );
		}
		$digits = preg_replace( '/\D/', '', $number );
		if ( strlen( $digits ) < 3 ) {
			return '';
		}
		return ( 0 === strpos( ltrim( $number ), '+' ) ? '+' : '' ) . $digits . $ext;
	}

	private static function digits( $s ) {
		return strlen( preg_replace( '/\D/', '', (string) $s ) );
	}

	/**
	 * Phone list markup — the frontend JS builds identical markup in popups.
	 *
	 * @param array[] $phones Parsed phones.
	 */
	private function render_phones( array $phones ) {
		if ( ! $phones ) {
			return;
		}
		$people = (bool) array_filter( wp_list_pluck( $phones, 'name' ), 'strlen' );
		echo '<div class="ave-om-phones' . ( $people ? ' ave-om-phones--people' : '' ) . '">';
		foreach ( $phones as $phone ) {
			echo '<div class="ave-om-phone">';
			if ( '' !== $phone['name'] ) {
				echo '<span class="ave-om-phone__name">' . esc_html( $phone['name'] ) . '</span>';
			}
			echo self::PHONE_ICON; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			echo '<span class="ave-om-phone__body">';
			echo '<a class="ave-om-phone__number" href="' . esc_url( 'tel:' . $phone['tel'], [ 'tel' ] ) . '">' . esc_html( $phone['number'] ) . '</a>';
			if ( '' !== $phone['label'] ) {
				echo '<span class="ave-om-phone__label">' . esc_html( $phone['label'] ) . '</span>';
			}
			echo '</span></div>';
		}
		echo '</div>';
	}

	/**
	 * Parse a lat/lng control value. Accepts "27.77" and "27,77".
	 *
	 * @param mixed $value Raw control value.
	 * @param int   $limit 90 for latitude, 180 for longitude.
	 * @return float|null
	 */
	public static function coord( $value, $limit ) {
		$value = str_replace( ',', '.', trim( (string) $value ) );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return null;
		}
		$value = (float) $value;
		return ( $value >= -$limit && $value <= $limit ) ? round( $value, 7 ) : null;
	}

	/**
	 * @param array $office Office payload.
	 * @return string
	 */
	private function link_attrs( array $office ) {
		$rel = [];
		$out = '';
		if ( $office['external'] ) {
			$out  .= ' target="_blank"';
			$rel[] = 'noopener';
		}
		if ( $office['nofollow'] ) {
			$rel[] = 'nofollow';
		}
		if ( $rel ) {
			$out .= ' rel="' . implode( ' ', $rel ) . '"';
		}
		return $out;
	}

	/**
	 * @param string $engine leaflet|google.
	 * @return string '' when the engine has what it needs.
	 */
	private function key_problem( $engine ) {
		if ( 'leaflet' === $engine && '' === AVE_MLM_Settings::get( 'carto_key' ) ) {
			return 'carto';
		}
		if ( 'google' === $engine && '' === AVE_MLM_Settings::get( 'google_key' ) ) {
			return 'google';
		}
		return '';
	}

	private function is_editor_context() {
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$elementor = \Elementor\Plugin::$instance;
		return ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() )
			|| ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() );
	}

	/**
	 * Editor-only status box: missing pins and missing keys.
	 *
	 * @param string[] $missing     Offices without coordinates.
	 * @param string   $key_problem '' | carto | google.
	 * @param int      $placed      Offices with pins.
	 */
	private function render_editor_notice( array $missing, $key_problem, $placed ) {
		$lines = [];

		if ( 'carto' === $key_problem ) {
			$lines[] = __( 'No CARTO key yet, so the map is hidden on the live page. Add a free key in Settings > AVE Maps, or switch the engine to Google Maps.', 'ave-mlm' );
		} elseif ( 'google' === $key_problem ) {
			$lines[] = __( 'No Google Maps key in Settings > AVE Maps. The map only works if another plugin already loads Google Maps on this page.', 'ave-mlm' );
		}

		if ( $missing ) {
			$shown   = array_slice( $missing, 0, 8 );
			$lines[] = sprintf(
				/* translators: 1: number of offices, 2: list of office names */
				_n( '%1$d office has no pin yet and is hidden: %2$s. Click "Find all missing pins" under Offices.', '%1$d offices have no pin yet and are hidden: %2$s. Click "Find all missing pins" under Offices.', count( $missing ), 'ave-mlm' ),
				count( $missing ),
				implode( ', ', $shown ) . ( count( $missing ) > count( $shown ) ? ', …' : '' )
			);
		}

		if ( ! $lines ) {
			return;
		}

		echo '<div class="ave-om-notice" role="status"><strong>' . esc_html__( 'Office Map (editor only):', 'ave-mlm' ) . '</strong> ';
		echo implode( '<br>', array_map( 'esc_html', $lines ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each line escaped.
		/* translators: %d: number of pins on the map */
		echo '<br><span class="ave-om-notice__meta">' . esc_html( sprintf( _n( '%d pin on the map.', '%d pins on the map.', $placed, 'ave-mlm' ), $placed ) ) . '</span>';
		echo '</div>';
	}
}
