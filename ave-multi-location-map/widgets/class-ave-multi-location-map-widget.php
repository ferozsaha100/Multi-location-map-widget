<?php
/**
 * AVE Multi-Location Map Widget
 *
 * Elementor widget: Google Maps with unlimited office/address pins, driven by
 * a repeater control. Enter an address and it auto-geocodes to a pin — on the
 * server since 1.3.0 (cached), with the browser geocoder as a fallback — and
 * lat/lng fields are optional overrides for nudging or correcting a pin.
 *
 * @package AVE_Multi_Location_Map
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

class AVE_Multi_Location_Map_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'ave-multi-location-map';
	}

	public function get_title() {
		return __( 'Multi-Location Map', 'ave-mlm' );
	}

	public function get_icon() {
		return 'eicon-google-maps';
	}

	public function get_categories() {
		return [ 'ave-widgets' ];
	}

	public function get_keywords() {
		return [ 'map', 'office', 'location', 'address', 'google maps', 'pin' ];
	}

	public function get_script_depends() {
		return [ 'ave-multi-location-map' ];
	}

	public function get_style_depends() {
		return [ 'ave-multi-location-map' ];
	}

	protected function register_controls() {

		/* ================= CONTENT: API KEY ================= */
		$this->start_controls_section(
			'section_api',
			[
				'label' => __( 'Google Maps API', 'ave-mlm' ),
			]
		);

		$this->add_control(
			'api_key',
			[
				'label'       => __( 'Google Maps API Key', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => __( 'Enable both "Maps JavaScript API" and "Geocoding API" in Google Cloud Console for this key. If you already load the Maps script globally, leave this blank.', 'ave-mlm' ),
			]
		);

		$this->add_control(
			'geocode_api_key',
			[
				'label'       => __( 'Server Geocoding Key (recommended for 20+ locations)', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => __( 'Addresses are converted to pins on your server, so there is no limit on the number of pins. Use a key with "Geocoding API" enabled and NO website (HTTP referrer) restriction — restrict it by API, and optionally by your server IP. Leave blank to try the key above. Never shown to visitors. Can also be set in wp-config.php as AVE_MLM_GEOCODE_API_KEY.', 'ave-mlm' ),
			]
		);

		$this->end_controls_section();

		/* ================= CONTENT: LOCATIONS REPEATER ================= */
		$this->start_controls_section(
			'section_locations',
			[
				'label' => __( 'Locations', 'ave-mlm' ),
			]
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'office_name',
			[
				'label'       => __( 'Office Name', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Main Office', 'ave-mlm' ),
				'label_block' => true,
			]
		);

		$repeater->add_control(
			'address',
			[
				'label'       => __( 'Address', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'placeholder' => __( '123 Main St, St. Petersburg, FL 33701', 'ave-mlm' ),
				'description' => __( 'Enter a full address — the pin location is auto-detected from this, same as Elementor\'s built-in Google Maps widget. Also shown in the info window.', 'ave-mlm' ),
			]
		);

		$repeater->add_control(
			'lat',
			[
				'label'       => __( 'Latitude (optional override)', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Leave blank to auto-detect from the address above. Fill in only if you need to correct a pin\'s exact position.', 'ave-mlm' ),
			]
		);

		$repeater->add_control(
			'lng',
			[
				'label'   => __( 'Longitude (optional override)', 'ave-mlm' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
			]
		);

		$repeater->add_control(
			'phone',
			[
				'label'       => __( 'Phone (optional)', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
			]
		);

		$repeater->add_control(
			'website_url',
			[
				'label'       => __( 'Website / Link (optional)', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'label_block' => true,
				'placeholder' => __( 'https://your-site.com or #section-id', 'ave-mlm' ),
				'description' => __( 'Accepts a full URL or an in-page anchor like #contact. The button below only appears when this is filled in.', 'ave-mlm' ),
			]
		);

		$repeater->add_control(
			'link_text',
			[
				'label'       => __( 'Button Text', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'View Details', 'ave-mlm' ),
				'label_block' => false,
				'condition'   => [
					'website_url[url]!' => '',
				],
			]
		);

		$repeater->add_control(
			'custom_marker',
			[
				'label'   => __( 'Custom Marker Icon (optional)', 'ave-mlm' ),
				'type'    => \Elementor\Controls_Manager::MEDIA,
				'default' => [
					'url' => '',
				],
			]
		);

		$this->add_control(
			'locations',
			[
				'label'       => __( 'Office Locations', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ office_name }}}',
				'default'     => [
					[
						'office_name' => __( 'Main Office', 'ave-mlm' ),
						'address'     => __( 'St. Petersburg, FL', 'ave-mlm' ),
					],
				],
			]
		);

		$this->end_controls_section();

		/* ================= CONTENT: MAP BEHAVIOR ================= */
		$this->start_controls_section(
			'section_behavior',
			[
				'label' => __( 'Map Settings', 'ave-mlm' ),
			]
		);

		$this->add_control(
			'auto_fit_bounds',
			[
				'label'       => __( 'Auto-Fit All Pins', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'Automatically zooms/pans so every marker is visible.', 'ave-mlm' ),
			]
		);

		$this->add_control(
			'zoom',
			[
				'label'   => __( 'Default Zoom (used when auto-fit is off, or as single-pin zoom)', 'ave-mlm' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 12,
				'min'     => 1,
				'max'     => 20,
			]
		);

		$this->add_control(
			'open_first_infowindow',
			[
				'label'   => __( 'Open First Info Window on Load', 'ave-mlm' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => '',
			]
		);

		$this->add_control(
			'map_style_json',
			[
				'label'       => __( 'Custom Map Style (JSON)', 'ave-mlm' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'rows'        => 6,
				'description' => __( 'Paste a Snazzy Maps / Google Maps style JSON array here. Leave blank for default styling.', 'ave-mlm' ),
			]
		);

		$this->end_controls_section();

		/* ================= STYLE: MAP ================= */
		$this->start_controls_section(
			'section_style_map',
			[
				'label' => __( 'Map', 'ave-mlm' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'map_height',
			[
				'label'      => __( 'Height', 'ave-mlm' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'vh' ],
				'range'      => [
					'px' => [ 'min' => 200, 'max' => 1000 ],
					'vh' => [ 'min' => 20, 'max' => 100 ],
				],
				'default'    => [
					'unit' => 'px',
					'size' => 480,
				],
				'selectors'  => [
					'{{WRAPPER}} .ave-mlm-canvas' => 'height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'map_border',
				'selector' => '{{WRAPPER}} .ave-mlm-canvas',
			]
		);

		$this->add_responsive_control(
			'map_border_radius',
			[
				'label'      => __( 'Border Radius', 'ave-mlm' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .ave-mlm-canvas' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				],
			]
		);

		$this->end_controls_section();

		/* ================= STYLE: INFO WINDOW ================= */
		$this->start_controls_section(
			'section_style_infowindow',
			[
				'label' => __( 'Info Window', 'ave-mlm' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'iw_title_color',
			[
				'label'     => __( 'Title Color', 'ave-mlm' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1a1a1a',
				'selectors' => [
					'{{WRAPPER}} .ave-mlm-iw-title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'iw_text_color',
			[
				'label'     => __( 'Text Color', 'ave-mlm' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#4a4a4a',
				'selectors' => [
					'{{WRAPPER}} .ave-mlm-iw-body' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'iw_link_color',
			[
				'label'     => __( 'Link / Accent Color', 'ave-mlm' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#c9a15e',
				'selectors' => [
					'{{WRAPPER}} .ave-mlm-iw-body a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'iw_title_typography',
				'label'    => __( 'Title Typography', 'ave-mlm' ),
				'selector' => '{{WRAPPER}} .ave-mlm-iw-title',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'iw_body_typography',
				'label'    => __( 'Body Typography', 'ave-mlm' ),
				'selector' => '{{WRAPPER}} .ave-mlm-iw-body',
			]
		);

		$this->end_controls_section();

		/* ================= STYLE: VIEW DETAILS BUTTON ================= */
		$this->start_controls_section(
			'section_style_button',
			[
				'label' => __( 'View Details Button', 'ave-mlm' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->start_controls_tabs( 'button_style_tabs' );

		$this->start_controls_tab(
			'button_style_normal',
			[
				'label' => __( 'Normal', 'ave-mlm' ),
			]
		);

		$this->add_control(
			'btn_text_color',
			[
				'label'     => __( 'Text Color', 'ave-mlm' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .ave-mlm-iw-btn' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'btn_bg_color',
			[
				'label'     => __( 'Background Color', 'ave-mlm' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#0B22C7',
				'selectors' => [
					'{{WRAPPER}} .ave-mlm-iw-btn' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'button_style_hover',
			[
				'label' => __( 'Hover', 'ave-mlm' ),
			]
		);

		$this->add_control(
			'btn_text_color_hover',
			[
				'label'     => __( 'Text Color', 'ave-mlm' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .ave-mlm-iw-btn:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'btn_bg_color_hover',
			[
				'label'     => __( 'Background Color', 'ave-mlm' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#08189c',
				'selectors' => [
					'{{WRAPPER}} .ave-mlm-iw-btn:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'btn_typography',
				'label'    => __( 'Typography', 'ave-mlm' ),
				'selector' => '{{WRAPPER}} .ave-mlm-iw-btn',
			]
		);

		$this->add_responsive_control(
			'btn_padding',
			[
				'label'      => __( 'Padding', 'ave-mlm' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'default'    => [
					'top'      => '8',
					'right'    => '16',
					'bottom'   => '8',
					'left'     => '16',
					'unit'     => 'px',
					'isLinked' => false,
				],
				'selectors'  => [
					'{{WRAPPER}} .ave-mlm-iw-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'btn_border_radius',
			[
				'label'      => __( 'Border Radius', 'ave-mlm' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [
					'top'      => '4',
					'right'    => '4',
					'bottom'   => '4',
					'left'     => '4',
					'unit'     => 'px',
					'isLinked' => true,
				],
				'selectors'  => [
					'{{WRAPPER}} .ave-mlm-iw-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$widget_id = 'ave-mlm-' . $this->get_id();

		if ( empty( $settings['locations'] ) ) {
			echo '<p>' . esc_html__( 'Add at least one location to display the map.', 'ave-mlm' ) . '</p>';
			return;
		}

		// Build a clean JS-ready payload — never trust repeater input directly into JS.
		// A location is valid if it has an address (to geocode) OR explicit lat/lng.
		$locations = [];
		$skipped   = 0;
		foreach ( $settings['locations'] as $loc ) {
			$address = ! empty( $loc['address'] ) ? wp_strip_all_tags( $loc['address'] ) : '';
			$lat     = ( isset( $loc['lat'] ) && '' !== $loc['lat'] ) ? floatval( $loc['lat'] ) : null;
			$lng     = ( isset( $loc['lng'] ) && '' !== $loc['lng'] ) ? floatval( $loc['lng'] ) : null;

			if ( '' === $address && ( null === $lat || null === $lng ) ) {
				$skipped++;
				continue; // Nothing usable — no address to geocode and no coordinates.
			}

			$locations[] = [
				'name'    => wp_strip_all_tags( $loc['office_name'] ),
				'address' => $address,
				'lat'     => $lat,
				'lng'     => $lng,
				'phone'       => ! empty( $loc['phone'] ) ? wp_strip_all_tags( $loc['phone'] ) : '',
				'url'         => ! empty( $loc['website_url']['url'] ) ? esc_url_raw( $loc['website_url']['url'] ) : '',
				'urlExternal' => ! empty( $loc['website_url']['is_external'] ),
				'urlNofollow' => ! empty( $loc['website_url']['nofollow'] ),
				'linkText'    => ! empty( $loc['link_text'] ) ? wp_strip_all_tags( $loc['link_text'] ) : __( 'View Details', 'ave-mlm' ),
				'icon'        => ! empty( $loc['custom_marker']['url'] ) ? esc_url_raw( $loc['custom_marker']['url'] ) : '',
			];
		}

		if ( empty( $locations ) ) {
			echo '<p>' . esc_html__( 'Add an address or lat/lng to at least one location.', 'ave-mlm' ) . '</p>';
			return;
		}

		// Geocode address-only locations on the server (cached). The browser's
		// geocoder is rate limited per visit (~20 lookups), so it is now only a
		// fallback for whatever the server could not resolve.
		$is_editor = $this->is_editor_context();
		$geo       = null;
		$to_lookup = [];
		foreach ( $locations as $loc ) {
			if ( ( null === $loc['lat'] || null === $loc['lng'] ) && '' !== $loc['address'] ) {
				$to_lookup[] = $loc['address'];
			}
		}
		if ( $to_lookup && class_exists( 'AVE_MLM_Geocoder' ) ) {
			$geo = AVE_MLM_Geocoder::lookup( $to_lookup, $this->get_geocode_api_key( $settings ), [ 'fresh' => $is_editor ] );
			foreach ( $locations as &$loc ) {
				if ( ( null === $loc['lat'] || null === $loc['lng'] ) && isset( $geo['coords'][ $loc['address'] ] ) ) {
					$loc['lat'] = $geo['coords'][ $loc['address'] ]['lat'];
					$loc['lng'] = $geo['coords'][ $loc['address'] ]['lng'];
				}
			}
			unset( $loc );
		}

		$map_style = [];
		if ( ! empty( $settings['map_style_json'] ) ) {
			$decoded = json_decode( $settings['map_style_json'], true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				$map_style = $decoded;
			}
		}

		$config = [
			'locations' => $locations,
			'skipped'   => $skipped,
			'autoFit'   => 'yes' === $settings['auto_fit_bounds'],
			'zoom'      => ! empty( $settings['zoom'] ) ? intval( $settings['zoom'] ) : 12,
			'openFirst' => 'yes' === $settings['open_first_infowindow'],
			'styles'    => $map_style,
		];

		// Server geocoding summary for the ?avemlm_debug=1 overlay — editors only.
		if ( $geo && current_user_can( 'edit_posts' ) ) {
			$config['serverGeo'] = $this->summarize_geo( $geo, $locations );
		}

		// Style handle is declared via get_style_depends(), Elementor enqueues it automatically.
		?>
		<div class="ave-mlm-wrapper">
			<?php
			if ( $geo && $is_editor ) {
				$this->render_editor_notice( $geo, $locations );
			}
			?>
			<div
				id="<?php echo esc_attr( $widget_id ); ?>"
				class="ave-mlm-canvas"
				data-config='<?php echo esc_attr( wp_json_encode( $config ) ); ?>'
			></div>
		</div>
		<?php

		// Load Google Maps JS API (+ Geocoding, used for address lookups) once per
		// page, only if an API key was supplied and the SDK isn't already enqueued.
		if ( ! empty( $settings['api_key'] ) && ! wp_script_is( 'google-maps-api', 'enqueued' ) ) {
			wp_enqueue_script(
				'google-maps-api',
				'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( $settings['api_key'] ),
				[],
				null,
				true
			);
		}
	}

	/**
	 * Key used for server-side geocoding. Priority: wp-config constant →
	 * widget "Server Geocoding Key" → widget "Google Maps API Key".
	 *
	 * @param array $settings Widget settings.
	 * @return string
	 */
	private function get_geocode_api_key( array $settings ) {
		if ( defined( 'AVE_MLM_GEOCODE_API_KEY' ) && AVE_MLM_GEOCODE_API_KEY ) {
			$key = (string) AVE_MLM_GEOCODE_API_KEY;
		} elseif ( ! empty( $settings['geocode_api_key'] ) ) {
			$key = (string) $settings['geocode_api_key'];
		} else {
			$key = ! empty( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
		}

		return trim( (string) apply_filters( 'ave_mlm_geocode_api_key', $key, $settings ) );
	}

	/**
	 * True while rendering inside the Elementor editor / preview iframe.
	 *
	 * @return bool
	 */
	private function is_editor_context() {
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$elementor = \Elementor\Plugin::$instance;

		return ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() )
			|| ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() );
	}

	/**
	 * Compact summary of a geocoding run, failures keyed by office name.
	 *
	 * @param array $geo       Result of AVE_MLM_Geocoder::lookup().
	 * @param array $locations Location payload.
	 * @return array
	 */
	private function summarize_geo( array $geo, array $locations ) {
		$names = [];
		foreach ( $locations as $loc ) {
			$names[ $loc['address'] ][] = '' !== $loc['name'] ? $loc['name'] : $loc['address'];
		}

		$failed = [];
		foreach ( $geo['failed'] as $address => $status ) {
			foreach ( isset( $names[ $address ] ) ? $names[ $address ] : [ $address ] as $name ) {
				$failed[ $name ] = $status;
			}
		}

		return [
			'resolved' => count( $geo['coords'] ),
			'cached'   => $geo['cached'],
			'pending'  => $geo['pending'],
			'failed'   => $failed,
			'error'    => $geo['key_error'],
		];
	}

	/**
	 * Status box above the map, shown only in the Elementor editor.
	 *
	 * @param array $geo       Result of AVE_MLM_Geocoder::lookup().
	 * @param array $locations Location payload.
	 */
	private function render_editor_notice( array $geo, array $locations ) {
		$summary = $this->summarize_geo( $geo, $locations );
		$total   = $summary['resolved'] + count( $geo['failed'] ) + $summary['pending'];
		$level   = 'ok';
		$lines   = [];

		if ( 'NO_KEY' === $summary['error'] ) {
			$level   = 'warn';
			$lines[] = sprintf(
				/* translators: %d: number of addresses */
				__( 'Server geocoding is off because no API key is set. %d address(es) will be looked up in the visitor\'s browser instead, which Google limits to about 20 per visit. Add a Server Geocoding Key under Content > Google Maps API.', 'ave-mlm' ),
				$summary['pending']
			);
		} elseif ( '' !== $summary['error'] ) {
			$level   = 'warn';
			$lines[] = sprintf(
				/* translators: %s: Google error status and message */
				__( 'Google rejected the key for server geocoding: %s', 'ave-mlm' ),
				$summary['error']
			);
			$lines[] = false !== stripos( $summary['error'], 'referer' )
				? __( 'This key is restricted to websites (HTTP referrers), which only works in the browser. Create a second key with only "Geocoding API" enabled (optionally restricted to your server IP) and paste it into Server Geocoding Key.', 'ave-mlm' )
				: __( 'Check that "Geocoding API" is enabled for this key and that billing is active on the Google Cloud project.', 'ave-mlm' );
			$lines[] = sprintf(
				/* translators: %d: number of addresses */
				__( 'Until this is fixed, %d address(es) fall back to browser geocoding (about 20 pins per visit).', 'ave-mlm' ),
				$summary['pending']
			);
		} else {
			$lines[] = sprintf(
				/* translators: 1: resolved addresses, 2: total addresses */
				__( 'Server geocoding: %1$d of %2$d address(es) resolved.', 'ave-mlm' ),
				$summary['resolved'],
				$total
			);
			if ( $summary['pending'] ) {
				$level   = 'warn';
				$lines[] = sprintf(
					/* translators: %d: number of addresses */
					__( '%d address(es) are still being looked up and will appear after the next reload.', 'ave-mlm' ),
					$summary['pending']
				);
			}
		}

		foreach ( $summary['failed'] as $name => $status ) {
			$level   = 'warn';
			$lines[] = sprintf(
				/* translators: 1: office name, 2: Google status code */
				__( 'Could not geocode "%1$s" (%2$s). Check the address, or enter latitude/longitude for this location.', 'ave-mlm' ),
				$name,
				$status
			);
		}

		echo '<div class="ave-mlm-notice ave-mlm-notice--' . esc_attr( $level ) . '">';
		echo '<strong>' . esc_html__( 'Multi-Location Map (editor only):', 'ave-mlm' ) . '</strong> ';
		echo implode( '<br>', array_map( 'esc_html', $lines ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each line escaped above.
		echo '</div>';
	}
}
