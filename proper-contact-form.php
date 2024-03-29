<?php
/*
Plugin Name: PROPER Contact Form
Plugin URI: http://theproperweb.com/shipped/wp/proper-contact-form
Description: A better contact form processor
Version: 0.9.7
Author: PROPER Development
Author URI: http://theproperweb.com
License: GPL2
*/


// Make sure we don't expose any info if called directly
// Thanks Akismet!
if ( ! function_exists( 'add_action' ) ) {
	die( 'Nothing to do...' );
}

// Important constants
define( 'PROPER_CONTACT_VERSION', '0.9.6' );
define( 'PROPER_CONTACT_URL', plugin_dir_url( __FILE__ ) );

// Required helper functions
include_once( dirname( __FILE__ ) . '/inc/helpers.php' );
include_once( dirname( __FILE__ ) . '/inc/settings.php' );
include_once( dirname( __FILE__ ) . '/inc/widget.php' );

/**
 * Outputs the contact form or a confirmation message is submitted
 *
 * @param      $atts
 * @param null $content
 *
 * @return string
 */

add_shortcode( 'proper_contact_form', 'proper_contact_form' );
function proper_contact_form( $atts, $content = NULL ) {

	// Looking for a submitted form if not redirect
	if ( isset( $_SESSION['propercfp_sent'] ) && $_SESSION['propercfp_sent'] === 'yes' ) {
		unset( $_SESSION['propercfp_sent'] );
		return '
		<div class="proper_contact_form_wrap">
			<h2>' . sanitize_text_field( proper_contact_get_key( 'propercfp_label_submit' ) ) . '</h2>
		</div>';
	}

	// FormBuilder
	// https://github.com/joshcanhelp/php-form-builder
	require_once( dirname( __FILE__ ) . '/inc/FormBuilder.php' );
	$form = new ThatFormBuilder;

	// TODO: make a better ID system
	$form->set_att( 'id', 'proper_contact_form_' . ( get_the_id() ? get_the_id() : 1 ) );
	$form->set_att( 'class', array( 'proper_contact_form' ) );
	$form->set_att( 'add_nonce', get_bloginfo( 'admin_email' ) );
	if ( ! proper_contact_get_key( 'propercfp_html5_no_validate' ) ) {
		$form->set_att( 'novalidate', TRUE );
	}

	// Add name field if selected on the settings page
	$propercfp_name_field = proper_contact_get_key( 'propercfp_name_field' );
	if ( $propercfp_name_field ) {
		$required     = $propercfp_name_field === 'req' ? TRUE : FALSE;
		$wrap_classes = array( 'form_field_wrap', 'contact_name_wrap' );

		// If this field was submitted with invalid data
		if ( isset( $_SESSION['cfp_contact_errors']['contact-name'] ) ) {
			$wrap_classes[] = 'error';
		}

		// Build the input with the correct label, options, and name
		$form->add_input(
			stripslashes( sanitize_text_field( proper_contact_get_key( 'propercfp_label_name' ) ) ),
			array(
				'required'   => $required,
				'wrap_class' => $wrap_classes
			),
			'contact-name'
		);

	}

	// Add email field if selected on the settings page
	$propercfp_email_field = proper_contact_get_key( 'propercfp_email_field' );
	if ( $propercfp_email_field ) {
		$required     = $propercfp_email_field === 'req' ? TRUE : FALSE;
		$wrap_classes = array( 'form_field_wrap', 'contact_email_wrap' );

		// If this field was submitted with invalid data
		if ( isset( $_SESSION['cfp_contact_errors']['contact-email'] ) ) {
			$wrap_classes[] = 'error';
		}

		// Build the input with the correct label, options, and name
		$form->add_input(
			stripslashes( sanitize_text_field( proper_contact_get_key( 'propercfp_label_email' ) ) ),
			array(
				'required'   => $required,
				'type'       => 'email',
				'wrap_class' => $wrap_classes
			),
			'contact-email'
		);

	}

	// Add phone field if selected on the settings page
	$propercfp_phone_field = proper_contact_get_key( 'propercfp_phone_field' );
	if ( $propercfp_phone_field ) {
		$required     = $propercfp_phone_field === 'req' ? TRUE : FALSE;
		$wrap_classes = array( 'form_field_wrap', 'contact_phone_wrap' );

		// If this field was submitted with invalid data
		if ( isset( $_SESSION['cfp_contact_errors']['contact-phone'] ) ) {
			$wrap_classes[] = 'error';
		}

		// Build the input with the correct label, options, and name
		$form->add_input(
			stripslashes( sanitize_text_field( proper_contact_get_key( 'propercfp_label_phone' ) ) ),
			array(
				'required'   => $required,
				'wrap_class' => $wrap_classes
			),
			'contact-phone'
		);
	}

	// Add reasons drop-down if any have been entered
	$options = proper_get_textarea_opts( trim( proper_contact_get_key( 'propercfp_reason' ) ) );
	if ( ! empty( $options ) ) {

		// Prepare the options array
		$options = array_map( 'trim', $options );
		$options = array_map( 'sanitize_text_field', $options );
		array_unshift( $options, 'Select one...' );

		// Build the select with the correct label, options, and name
		$form->add_input(
			stripslashes( sanitize_text_field( proper_contact_get_key( 'propercfp_label_reason' ) ) ),
			array(
				'type'       => 'select',
				'wrap_class' => array(
					'form_field_wrap',
					'contact_reasons_wrap'
				),
				'options'    => $options
			),
			'contact-reasons'
		);
	}

	// Comment field, required to be displayed
	$wrap_classes = array( 'form_field_wrap', 'question_or_comment_wrap' );
	if ( isset( $_SESSION['cfp_contact_errors']['question-or-comment'] ) ) {
		$wrap_classes[] = 'error';
	}
	$form->add_input(
		stripslashes( proper_contact_get_key( 'propercfp_label_comment' ) ),
		array(
			'required'   => TRUE,
			'type'       => 'textarea',
			'wrap_class' => $wrap_classes
		),
		'question-or-comment'
	);

	// Add a math CAPTCHA, if desired
	if ( proper_contact_get_key( 'propercfp_captcha_field' ) ) {

		$wrap_classes = array( 'form_field_wrap', 'math_captcha_wrap' );

		// If this field was submitted with invalid data
		if ( isset( $_SESSION['cfp_contact_errors']['math-captcha'] ) ) {
			$wrap_classes[] = 'error';
		}

		$num_1 = mt_rand( 1, 10 );
		$num_2 = mt_rand( 1, 10 );
		$sum   = $num_1 + $num_2;

		// Build the input with the correct label, options, and name
		$form->add_input(
			stripslashes( sanitize_text_field( proper_contact_get_key( 'propercfp_label_math' ) ) ) . " $num_1 + $num_2",
			array(
				'required'    => TRUE,
				'wrap_class'  => $wrap_classes,
				'use_request' => false
			),
			'math-captcha'
		);

		$form->add_input(
			'Math CAPTCHA sum',
			array(
				'type'        => 'hidden',
				'value'       => $sum,
				'use_request' => FALSE
			),
			'math-captcha-sum'
		);

	}

	// Submit button
	$form->add_input(
		stripslashes( sanitize_text_field( proper_contact_get_key( 'propercfp_label_submit_btn' ) ) ),
		array(
			'type'       => 'submit',
			'wrap_class' => array(
				'form_field_wrap', 'submit_wrap'
			)
		),
		'submit'
	);

	// Referring site or page, if any
	if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$form->add_input(
			'Contact Referrer',
			array(
				'type'  => 'hidden',
				'value' => $_SERVER['HTTP_REFERER']
			)
		);
	}

	// Referring page, if sent via URL query
	if ( ! empty( $_REQUEST['src'] ) || ! empty( $_REQUEST['ref'] ) ) {
		$form->add_input(
			'Referring page', array(
				'type'  => 'hidden',
				'value' => ! empty( $_REQUEST['src'] ) ? $_REQUEST['src'] : $_REQUEST['ref']
			)
		);
	}

	// Are there any submission errors?
	$errors = '';
	if ( ! empty( $_SESSION['cfp_contact_errors'] ) ) {
		$errors = proper_display_errors( $_SESSION['cfp_contact_errors'] );
		unset( $_SESSION['cfp_contact_errors'] );
	}

	// Display that beautiful form!
	return '
	<div class="proper_contact_form_wrap">
	' . $errors . '
	' . $form->build_form( FALSE ) . '
	</div>';

}

/**
 * Process the incoming contact form data, if any
 */
add_action( 'template_redirect', 'cfp_process_contact' );
function cfp_process_contact() {

	// If POST, nonce and honeypot are not set, beat it
	if (
			empty( $_POST ) ||
			empty( $_POST['wordpress-nonce'] ) ||
			! isset( $_POST['honeypot'] )
	) {
		return false;
	}

	// Session variable for form errors
	$_SESSION['cfp_contact_errors'] = array();

	// If nonce is not valid, beat it
	if ( ! wp_verify_nonce( $_POST['wordpress-nonce'], get_bloginfo( 'admin_email' ) ) ) {
		$_SESSION['cfp_contact_errors']['nonce'] = __( 'Nonce failed!', 'proper-contact' );
		return false;
	}

	// If the honeypot caught a bear, beat it
	if ( ! empty( $_POST['honeypot'] ) ) {
		$_SESSION['cfp_contact_errors']['honeypot'] = __( 'Form submission failed!', 'proper-contact' );
		return false;
	}

	// Start the body of the contact email
	$body = "
*** " . __( 'Contact form submission on', 'proper-contact' ) . " " . get_bloginfo( 'name' ) . " (" . site_url() . ") *** \n\n";

	// Sanitize and validate name
	$contact_name = isset( $_POST['contact-name'] ) ?
			sanitize_text_field( trim( $_POST['contact-name'] ) ) :
			'';

	// Do we require an email address?
	if ( proper_contact_get_key( 'propercfp_name_field' ) === 'req' && empty( $contact_name ) ) {
		$_SESSION['cfp_contact_errors']['contact-name'] = proper_contact_get_key( 'propercfp_label_err_name' );
		// If not required and empty, leave it out
	}
	elseif ( ! empty( $contact_name ) ) {
		$body .= stripslashes( proper_contact_get_key( 'propercfp_label_name' ) ) . ": $contact_name \r";
	}

	// Sanitize and validate email
	$contact_email = isset( $_POST['contact-email'] ) ?
			sanitize_email( $_POST['contact-email'] ) :
			'';

	// If required, is it valid?
	if (
			proper_contact_get_key( 'propercfp_email_field' ) === 'req' &&
			! filter_var( $contact_email, FILTER_VALIDATE_EMAIL )
	) {
		$_SESSION['cfp_contact_errors']['contact-email'] = proper_contact_get_key( 'propercfp_label_err_email' );
		// If not required and empty, leave it out
	}
	elseif ( ! empty( $contact_email ) ) {
		$body .= stripslashes( proper_contact_get_key( 'propercfp_label_email' ) )
				. ": $contact_email \r"
				. __( 'Google it', 'proper-contact' )
				. ": https://www.google.com/#q=$contact_email \r";
	}

	// Sanitize phone number
	$contact_phone = isset( $_POST['contact-phone'] ) ?
			sanitize_text_field( $_POST['contact-phone'] ) :
			'';

	// Do we require a phone number?
	if ( proper_contact_get_key( 'propercfp_phone_field' ) === 'req' && empty( $contact_phone ) ) {
		$_SESSION['cfp_contact_errors']['contact-phone'] = proper_contact_get_key( 'propercfp_label_err_phone' );
		// If not required and empty, leave it out
	}
	elseif ( ! empty( $contact_phone ) ) {
		$body .= stripslashes( proper_contact_get_key( 'propercfp_label_phone' ) )
				. ": $contact_phone \r"
				. __( 'Google it', 'proper-contact' )
				. ": https://www.google.com/#q=$contact_phone\r";
	}

	// Sanitize contact reason
	$contact_reason = isset( $_POST['contact-reasons'] ) ?
			sanitize_text_field( $_POST['contact-reasons'] ) :
			'';

	// If empty, leave it out
	if ( ! empty( $contact_reason ) ) {
		$contact_reason = stripslashes( $contact_reason );
		$body .= stripslashes( proper_contact_get_key( 'propercfp_label_reason' ) ) . ": $contact_reason \r";
	}

	// Sanitize and validate comments
	$contact_comment = sanitize_text_field( trim( $_POST['question-or-comment'] ) );
	if ( empty( $contact_comment ) ) {
		$_SESSION['cfp_contact_errors']['question-or-comment'] =
				sanitize_text_field( proper_contact_get_key( 'propercfp_label_err_no_content' ) );
	}
	else {
		$body .= "\n\n" . stripslashes( proper_contact_get_key( 'propercfp_label_comment' ) )
				. ": " . stripslashes( $contact_comment ) . " \n\n";
	}

	// Check the math CAPTCHA, if present
	if ( proper_contact_get_key( 'propercfp_captcha_field' ) ) {
		$captcha_sum = isset( $_POST['math-captcha'] ) ? intval( $_POST['math-captcha'] ) : 0;
		if ( $captcha_sum != (int) $_POST['math-captcha-sum'] ) {
			$_SESSION['cfp_contact_errors']['math-captcha'] =
					sanitize_text_field( proper_contact_get_key( 'propercfp_label_err_captcha' ) );
		}
	}

	// Sanitize and validate IP
	$contact_ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP );

	// If valid and present, create a link to an IP search
	if ( ! empty( $contact_ip ) ) {
		$body .= "IP address: $contact_ip \r
IP search: http://whatismyipaddress.com/ip/$contact_ip \n\n";
	}

	// Sanitize and prepare referrer;
	if ( ! empty( $_POST['contact-referrer'] ) ) {
		$body .= "Came from: " . sanitize_text_field( $_POST['contact-referrer'] ) . " \r";
	}

	// Show the page this contact form was submitted on
	$body .= 'Sent from page: ' . get_permalink( get_the_id() );

	// Check the blacklist
	$blocked = proper_get_blacklist();
	if ( ! empty( $blocked ) ) {
		if (
				in_array( $contact_email, $blocked ) ||
				in_array( $contact_ip, $blocked )
		) {
			$_SESSION['cfp_contact_errors']['blacklist-blocked'] = 'Form submission blocked!';
			return false;
		}
	}

	// No errors? Go ahead and process the contact
	if ( empty( $_SESSION['cfp_contact_errors'] ) ) {

		$site_email = proper_contact_get_key( 'propercfp_email' );
		$site_name  = get_bloginfo( 'name' );

		// No name? Use the email address, if one is present
		if ( empty( $contact_name ) ) {
			$contact_name = ! empty( $contact_email ) ? $contact_email : '[None given]';
		}

		// Need an email address for the email notification
		if ( empty( $contact_email ) ) {
			$contact_email = $site_email;
		}

		// Sent an email notification to the correct address
		$headers   = array();
		$headers[] = "From: $contact_name <$contact_email>";
		$headers[] = "Reply-To: $contact_email";
		wp_mail(
			$site_email,
				'Contact on ' . $site_name,
			$body,
			$headers
		);

		// Should a confirm email be sent?
		$confirm_body = stripslashes( trim( proper_contact_get_key( 'propercfp_confirm_email' ) ) );
		if ( ! empty( $confirm_body ) && ! empty( $contact_email ) ) {
			$headers   = array();
			$headers[] = "From: $site_name <$site_email>";
			$headers[] = "Reply-To: $site_email";
			wp_mail(
				$contact_email,
					proper_contact_get_key( 'propercfp_label_submit' ) . ' - ' . get_bloginfo( 'name' ),
				$confirm_body,
				$headers
			);
		}

		// Should the entry be stored in the DB?
		if ( proper_contact_get_key( 'propercfp_store' ) === 'yes' ) {
			$new_post_id = wp_insert_post(
				array(
					'post_type'    => 'proper_contact',
					'post_title'   => date( 'l, M j, Y', time() ) . ' by "' . $contact_name . '"',
					'post_content' => $body,
					'post_author'  => 1,
					'post_status'  => 'private'
				)
			);

			if ( isset( $contact_email ) && ! empty( $contact_email ) ) {
				add_post_meta( $new_post_id, 'Contact email', $contact_email );
			}
		}

		// Should the user get redirected?
		if ( proper_contact_get_key( 'propercfp_result_url' ) ) {
			$redirect_id = proper_contact_get_key( 'propercfp_result_url' );
			$redirect    = get_permalink( $redirect_id );
			wp_redirect( $redirect );
		}
		else {
			$_SESSION['propercfp_sent'] = 'yes';
		}

	}

}

// Get a settings value
function proper_contact_get_key( $id ) {
	$propercfp_options = get_option( 'propercfp_settings_array' );
	return isset( $propercfp_options[$id] ) ? $propercfp_options[$id] : '';
}

// If styles should be added, do that
if ( proper_contact_get_key( 'propercfp_css' ) === 'yes' ) {

	add_action( 'wp_enqueue_scripts', 'proper_contact_styles' );
	function proper_contact_styles() {
		wp_register_style( 'proper_contact_styles', plugins_url( 'css/front.css', __FILE__ ) );
		wp_enqueue_style( 'proper_contact_styles' );
	}

}

// If submissions should be stored in the DB, create the CPT
if ( proper_contact_get_key( 'propercfp_store' ) === 'yes' ) {

	add_action( 'init', 'proper_contact_content_type' );
	function proper_contact_content_type() {
		$labels = array(
			'name'               => __( 'Contacts', 'proper-contact' ), 'post type general name',
			'singular_name'      => __( 'Contact', 'proper-contact' ), 'post type singular name',
			'add_new'            => __( 'Add Contact', 'proper-contact' ), 'proper_contact',
			'add_new_item'       => __( 'Add New Contact', 'proper-contact' ),
			'edit_item'          => __( 'Edit Contact', 'proper-contact' ),
			'new_item'           => __( 'New Contact', 'proper-contact' ),
			'all_items'          => __( 'All Contacts', 'proper-contact' ),
			'view_item'          => __( 'View Contact', 'proper-contact' ),
			'not_found'          => __( 'No Contacts found', 'proper-contact' ),
			'not_found_in_trash' => __( 'No Contacts found in Trash', 'proper-contact' ),
			'menu_name'          => __( 'Contacts', 'proper-contact' )
		);
		$args   = array(
			'labels'             => $labels,
			'public'             => FALSE,
			'show_ui'            => TRUE,
			'show_in_menu'       => TRUE,
			'hierarchical'       => FALSE,
			'menu_position'      => 27,
			'menu_icon'          => PROPER_CONTACT_URL . '/images/person.png',
			'supports'           => array( 'title', 'editor', 'custom-fields' )
		);
		register_post_type( 'proper_contact', $args );
	}

}