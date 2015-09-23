<?php
/**
 * Plugin Name: Role Map - EDD Members
 * Plugin URI:  http://themehybrid.com
 * Description: Maps user roles to EDD Members options. Note: Currently only supports variable pricing.
 * Version:     1.0.0
 * Author:      Justin Tadlock
 * Author URI:  http://justintadlock.com
 */

// Validate the membership.
add_action( 'init', 'th_edd_validate_membership', 95 );

// Add EDD extension settings.
add_filter( 'edd_settings_extensions', 'th_edd_settings_extensions' );

// Set the user role on purchase completion.
add_action( 'edd_complete_purchase', 'th_edd_complete_purchase' );

// Custom role price options.
add_action( 'edd_download_price_table_head', 'th_edd_members_prices_header',     801    );
add_action( 'edd_download_price_table_row',  'th_edd_members_price_option_role', 801, 3 );

/**
 * Gets an array of allowed membership roles.
 *
 * @todo - Make plugin option.
 * @since  1.0.0
 * @access public
 * @return array
 */
function th_edd_get_membership_roles() {
	$roles = edd_get_option( 'th_edd_membership_roles', false );

	return $roles ? array_keys( $roles ) : array( get_option( 'default_role' ) );
}

/**
 * Gets an array of allowed membership role names.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function th_edd_get_membership_role_names() {
	global $wp_roles;

	$names = array();

	foreach ( th_edd_get_membership_roles() as $role ) {

		if ( isset( $wp_roles->role_names[ $role ] ) )
			$names[ $role ] = $wp_roles->role_names[ $role ];
	}

	return $names;
}

/**
 * Validates the membership and changes the user's role to the default if their
 * membership has expired.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function th_edd_validate_membership() {

	// Bail if user is not logged in.
	if ( ! is_user_logged_in() )
		return;

	// Get the current user's ID.
	$user_id = get_current_user_id();

	// Bail if the user doesn't have a membership role or if the membership is valid.
	if ( ! th_edd_user_has_membership_role( $user_id ) || edd_members_is_membership_valid( $user_id ) )
		return;

	// Get the user object.
	$user = new WP_User( $user_id );

	// Loop through the roles to remove.
	foreach ( th_edd_get_membership_roles() as $r ) {

		// If the user has the role, remove it.
		if ( in_array( $r, (array) $user->roles ) )
			$user->remove_role( $r );
	}

	// Add the default role to the user.
	$user->add_role( get_option( 'default_role' ) );
}

/**
 * Checks if a user has one of the membership roles.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $user_id
 * @return bool
 */
function th_edd_user_has_membership_role( $user_id ) {

	// Get the user object.
	$user = new WP_User( $user_id );

	// Loop through the roles to remove.
	foreach ( th_edd_get_membership_roles() as $r ) {

		if ( in_array( $r, (array) $user->roles ) )
			return true;
	}

	return false;
}

/**
 * Adds variable prices header.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $download_id
 * @return void
 */
function th_edd_members_prices_header( $download_id ) {

	if ( 'bundle' == edd_get_download_type( $download_id ) )
		return;

	// Get membership length enabled for deciding when to show membership length
	$edd_members_length_enabled = get_post_meta( $download_id, '_edd_members_length_enabled', true ) ? true : false;
	$edd_members_display   	    = $edd_members_length_enabled ? '' : ' style="display:none;"'; ?>

	<th <?php echo $edd_members_display; ?> class="edd-members-toggled-hide"><?php esc_html_e( 'Role', 'edd-members' ); ?></th>

<?php }

/**
 * Adds variable price role option.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $download_id
 * @param  int     $price_id
 * @param  array   $args
 * @return bool
 */
function th_edd_members_price_option_role( $download_id, $price_id, $args ) {

	if ( 'bundle' == edd_get_download_type( $download_id ) )
		return;

	// Get price role value.
	$th_edd_role = th_edd_get_price_option_role( $download_id, $price_id );

	// Get membership length enabled for deciding when to show membership length option
	$edd_members_length_enabled = get_post_meta( $download_id, '_edd_members_length_enabled', true ) ? true : false;
	$edd_members_display   	    = $edd_members_length_enabled ? '' : ' style="display:none;"'; ?>

	<td <?php echo $edd_members_display; ?> class="edd-members-toggled-hide">

		<select name="edd_variable_prices[<?php echo $price_id; ?>][th_edd_role]" id="edd_variable_prices[<?php echo $price_id; ?>][th_edd_role]">

		<?php foreach ( th_edd_get_membership_role_names() as $role => $name ) : ?>

			<option value="<?php echo esc_attr( $role ); ?>" <?php selected( $role, $th_edd_role ); ?>><?php echo esc_html( $name ); ?></option>

		<?php endforeach; ?>

		</select>
	</td>
<?php }

/**
 * Gets the role for the price option.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $download_id
 * @param  int     $price_id
 * @return string|bool
 */
function th_edd_get_price_option_role( $download_id = 0, $price_id = null ) {

	$prices = edd_get_variable_prices( $download_id );

	if ( isset( $prices[ $price_id ][ 'th_edd_role' ] ) )
		return esc_attr( $prices[ $price_id ][ 'th_edd_role' ] );

	return false;
}

/**
 * Callback on the `edd_complete_purchase` hook that updates the user's role based on purchase
 *
 * @since  1.0.0
 * @access public
 * @param  int     $payment_id
 * @return bool
 */
function th_edd_complete_purchase( $payment_id = 0 ) {

	// User info
	$user_info = edd_get_payment_meta_user_info( $payment_id );

	// User ID
	$user_id = $user_info['id'];

	// Cart details
	$downloads = edd_get_payment_meta_cart_details( $payment_id );

	// Bail if there are no downloads.
	if ( ! is_array( $downloads ) )
		return;

	// Get user roles purchased.
	$roles = array();

	foreach ( $downloads as $download ) {

		// Bypass downloads that are not memberships.
		if ( ! get_post_meta( $download['id'], '_edd_members_length_enabled', true ) )
			continue;

		// Get price id
		$price_id = edd_get_cart_item_price_id( $download );

		$roles[] = th_edd_get_price_option_role( $download['id'], $price_id );
	}

	// If we have roles.
	if ( $roles ) {

		// Note: We're currently grabbing the first role.  We need to add a check to
		// get the highest priced role in case someone purchases multiple.
		th_edd_set_user_role( $user_id, array_shift( $roles ) );
	}
}

/**
 * Sets the user membership role and removes other membership roles.
 *
 * @since  1.0.0
 * @access public
 * @param  int     $user_id
 * @param  string  $role
 * @return void
 */
function th_edd_set_user_role( $user_id, $role ) {

	$allowed = th_edd_get_membership_role_names();

	// If not an allowed role, bail.
	if ( ! isset( $allowed[ $role ] ) )
		return;

	// Get the user object.
	$user = new WP_User( $user_id );

	// If the user doesn't have the new role, add it.
	if ( ! in_array( $role, (array) $user->roles ) )
		$user->add_role( $role );

	// Loop through the membership roles.
	foreach ( $allowed as $allowed_role => $name ) {

		// If the user has another membership role other than the purchased role, remove it.
		if ( $role !== $allowed_role && in_array( $allowed_role, (array) $user->roles ) )
			$user->remove_role( $allowed_role );
	}
}

/**
 * Adds custom extension setting for EDD. The setting allows users to select the
 * roles that can be mapped to EDD Members pricing options.
 *
 * @since  1.0.0
 * @access public
 * @param  array   $settings
 * @global object  $wp_roles
 * @return array
 */
function th_edd_settings_extensions( $settings ) {
	global $wp_roles;

	$roles = $wp_roles->role_names;
	asort( $roles );

	$settings[] = array(
		'id'      => 'th_edd_membership_roles',
		'name'    => esc_html__( 'Membership Roles', 'th-edd' ),
		'desc'    => esc_html__( 'Select which roles can be mapped to EDD Members pricing options.', 'th-edd' ),
		'type'    => 'multicheck',
		'options' => $roles
	);

	return $settings;
}
