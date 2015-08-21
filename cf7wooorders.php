<?php
/*
Plugin Name: Contact Form 7 WooCommerce Order
Plugin URI: http://wpboxr.com/product/contact-form-7-woocommerce-orders
Description: Woocommerce Customer Orders Dropdown Selector
Author: WPBoxr
Author URI: http://wpboxr.com
Version: 1.0.2
*/

register_activation_hook(__FILE__, 'wpcf7_wooorders_activation');

function wpcf7_wooorders_activation(){
    /**
     * Check if WooCommerce & Cubepoints are active
     **/
    if ( !in_array( 'contact-form-7/wp-contact-form-7.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )) {

        // Deactivate the plugin
        deactivate_plugins(__FILE__);

        // Throw an error in the wordpress admin console
        $error_message = __('This plugin requires <a target="_blank" href="https://wordpress.org/plugins/contact-form-7/">Contact Form 7</a> plugin to be active!', 'contactform7wooorders');
        die($error_message);

    }
}

/*
function wpcf7_wooorders_detect_plugin_deactivation( $plugin, $network_activation ) {
    //var_dump($plugin); exit();

    if ($plugin == 'contact-form-7/wp-contact-form-7.php'){
        //$dependent =  array('contact-form-7-wooorders/cf7wooorders.php');

        //deactivate_plugins($dependent, false);
        deactivate_plugins(__FILE__);
    }
}

add_action( 'deactivated_plugin', 'wpcf7_wooorders_detect_plugin_deactivation', 10, 2 );
*/

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wpboxr_cf7wooorders' );

function wpboxr_cf7wooorders( $links ) {
	$links[] = '<a href="http://wpboxr.com/product/contact-form-7-woocommerce-orders" target="_blank">WPBoxr</a>';
	return $links;
}

/* Shortcode handler */

add_action( 'wpcf7_init', 'wpcf7_add_shortcode_wooorders' );

function wpcf7_add_shortcode_wooorders() {

	wpcf7_add_shortcode( array( 'wooorders' ),	'wpcf7_wooorders_shortcode_handler', true );
}

function wpcf7_wooorders_shortcode_handler( $tag ) {
	$tag = new WPCF7_Shortcode( $tag );

	if ( empty( $tag->name ) )
		return '';

	$validation_error = wpcf7_get_validation_error( $tag->name );

	$class = wpcf7_form_controls_class( $tag->type );

	if ( $validation_error )
		$class .= ' wpcf7-not-valid';

	$atts = array();

	$atts['class'] = $tag->get_class_option( $class );
	$atts['id'] = $tag->get_id_option();
	$atts['tabindex'] = $tag->get_option( 'tabindex', 'int', true );

	if ( $tag->is_required() )
		$atts['aria-required'] = 'true';

	$atts['aria-invalid'] = $validation_error ? 'true' : 'false';

	$multiple = $tag->has_option( 'multiple' );
	//$include_blank = $tag->has_option( 'include_blank' );
	//$first_as_label = $tag->has_option( 'first_as_label' );

	$values     = array();
	$labels     = array();

	$customer_orders = get_posts( apply_filters( 'woocommerce_my_account_my_orders_query', array(
		'numberposts' => -1,
		'meta_key'    => '_customer_user',
		'meta_value'  => get_current_user_id(),
		'post_type'   => wc_get_order_types( 'view-orders' ),
		'post_status' => array_keys( wc_get_order_statuses() )
	) ) );
/*
	echo '<pre>';
	print_r($customer_orders);
	echo '</pre>';*/

	foreach ( $customer_orders as $customer_order ) {
		$order = wc_get_order( $customer_order );
		$order->populate( $customer_order );
		$item_count = $order->get_item_count();

		$labels[] = '#'.$order->get_order_number().'( Status: '.wc_get_order_status_name( $order->get_status()) .')';
		$values[] = ''.$order->get_order_number();
	}


	//$values = $tag->values;

	//$labels = $tag->labels;

	//var_dump($tag->get_data_option());

	/*if ( $data = (array) $tag->get_data_option() ) {
		$values = array_merge( $values, array_values( $data ) );
		$labels = array_merge( $labels, array_values( $data ) );
	}*/

	$defaults = array();

	$default_choice = $tag->get_default_option( null, 'multiple=1' );

	foreach ( $default_choice as $value ) {
		$key = array_search( $value, $values, true );

		if ( false !== $key ) {
			$defaults[] = (int) $key + 1;
		}
	}

	if ( $matches = $tag->get_first_match_option( '/^default:([0-9_]+)$/' ) ) {
		$defaults = array_merge( $defaults, explode( '_', $matches[1] ) );
	}

	$defaults = array_unique( $defaults );

	$shifted = false;

	//if ( $include_blank || empty( $values ) ) {
		array_unshift( $labels, '---' );
		array_unshift( $values, '' );
		$shifted = true;
	//} elseif ( $first_as_label ) {
		//$values[0] = '';
		//$labels[]
	//}

	$html = '';
	$hangover = wpcf7_get_hangover( $tag->name );

	foreach ( $values as $key => $value ) {
		$selected = false;

		if ( $hangover ) {
			if ( $multiple ) {
				$selected = in_array( esc_sql( $value ), (array) $hangover );
			} else {
				$selected = ( $hangover == esc_sql( $value ) );
			}
		} else {
			if ( ! $shifted && in_array( (int) $key + 1, (array) $defaults ) ) {
				$selected = true;
			} elseif ( $shifted && in_array( (int) $key, (array) $defaults ) ) {
				$selected = true;
			}
		}

		$item_atts = array(
			'value' => $value,
			'selected' => $selected ? 'selected' : '' );

		$item_atts = wpcf7_format_atts( $item_atts );

		$label = isset( $labels[$key] ) ? $labels[$key] : $value;

		$html .= sprintf( '<option %1$s>%2$s</option>',
			$item_atts, esc_html( $label ) );
	}

	if ( $multiple )
		$atts['multiple'] = 'multiple';

	$atts['name'] = $tag->name . ( $multiple ? '[]' : '' );

	$atts = wpcf7_format_atts( $atts );

	$html = sprintf(
		'<span class="wpcf7-form-control-wrap %1$s"><select %2$s>%3$s</select>%4$s</span>',
		sanitize_html_class( $tag->name ), $atts, $html, $validation_error );

	return $html;
}


/* Validation filter */

add_filter( 'wpcf7_validate_wooorders', 'wpcf7_wooorders_validation_filter', 10, 2 );
//add_filter( 'wpcf7_validate_wooorders*', 'wpcf7_wooorders_validation_filter', 10, 2 );
//add_filter( 'wpcf7_validate_select*', 'wpcf7_select_validation_filter', 10, 2 );

function wpcf7_wooorders_validation_filter( $result, $tag ) {
	$tag = new WPCF7_Shortcode( $tag );

	$name = $tag->name;

	if ( isset( $_POST[$name] ) && is_array( $_POST[$name] ) ) {
		foreach ( $_POST[$name] as $key => $value ) {
			if ( '' === $value )
				unset( $_POST[$name][$key] );
		}
	}

	$empty = ! isset( $_POST[$name] ) || empty( $_POST[$name] ) && '0' !== $_POST[$name];

	if ( $tag->is_required() && $empty ) {
		$result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
	}

	return $result;
}


/* Tag generator */

add_action( 'admin_init', 'wpcf7_add_tag_generator_wooorders', 25 );

function wpcf7_add_tag_generator_wooorders() {
    if(!class_exists('WPCF7_TagGenerator')) return;

	$tag_generator = WPCF7_TagGenerator::get_instance();
	$tag_generator->add( 'wooorders', __( 'WooCommerce Order Dropdown', 'contact-form-7' ), 'wpcf7_tag_generator_wooorders' );
}

function wpcf7_tag_generator_wooorders( $contact_form, $args = '' ) {
	$args = wp_parse_args( $args, array() );

	$description = __( "Generate a form-tag for a drop-down menu. For more details, see %s.", 'contact-form-7' );

	$desc_link = wpcf7_link( __( 'http://contactform7.com/checkboxes-radio-buttons-and-menus/', 'contact-form-7' ), __( 'Checkboxes, Radio Buttons and Menus', 'contact-form-7' ) );

	?>
	<div class="control-box">
		<fieldset>
			<legend><?php echo sprintf( esc_html( $description ), $desc_link ); ?></legend>

			<table class="form-table">
				<tbody>
				<!--tr>
					<th scope="row"><?php echo esc_html( __( 'Field type', 'contact-form-7' ) ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php echo esc_html( __( 'Field type', 'contact-form-7' ) ); ?></legend>
							<label><input type="checkbox" name="required" /> <?php echo esc_html( __( 'Required field', 'contact-form-7' ) ); ?></label>
						</fieldset>
					</td>
				</tr-->

				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php echo esc_html( __( 'Name', 'contact-form-7' ) ); ?></label></th>
					<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" /></td>
				</tr>

				<tr>
					<th scope="row"><?php echo esc_html( __( 'Options', 'contact-form-7' ) ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php echo esc_html( __( 'Options', 'contact-form-7' ) ); ?></legend>
							<!--textarea name="values" class="values" id="<?php echo esc_attr( $args['content'] . '-values' ); ?>"></textarea>
							<label for="<?php echo esc_attr( $args['content'] . '-values' ); ?>"><span class="description"><?php echo esc_html( __( "One option per line.", 'contact-form-7' ) ); ?></span></label><br /-->
							<label><input type="checkbox" name="multiple" class="option" /> <?php echo esc_html( __( 'Allow multiple selections', 'contact-form-7' ) ); ?></label><br />
							<!--label><input type="checkbox" name="include_blank" class="option" /> <?php echo esc_html( __( 'Insert a blank item as the first option', 'contact-form-7' ) ); ?></label-->
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-id' ); ?>"><?php echo esc_html( __( 'Id attribute', 'contact-form-7' ) ); ?></label></th>
					<td><input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-id' ); ?>" /></td>
				</tr>

				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-class' ); ?>"><?php echo esc_html( __( 'Class attribute', 'contact-form-7' ) ); ?></label></th>
					<td><input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-class' ); ?>" /></td>
				</tr>

				</tbody>
			</table>
		</fieldset>
	</div>

	<div class="insert-box">
		<input type="text" name="wooorders" class="tag code" readonly="readonly" onfocus="this.select()" />

		<div class="submitbox">
			<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ); ?>" />
		</div>

		<br class="clear" />

		<p class="description mail-tag"><label for="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>"><?php echo sprintf( esc_html( __( "To use the value input through this field in a mail field, you need to insert the corresponding mail-tag (%s) into the field on the Mail tab.", 'contact-form-7' ) ), '<strong><span class="mail-tag"></span></strong>' ); ?><input type="text" class="mail-tag code hidden" readonly="readonly" id="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>" /></label></p>
	</div>
<?php
}

