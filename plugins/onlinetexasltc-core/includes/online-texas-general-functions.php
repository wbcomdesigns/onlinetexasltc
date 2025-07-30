<?php

/**
 * Online Texas Core - Complete General Functions
 * 
 * This file contains all the core functions for the Online Texas Core plugin
 * including vendor product creation, LearnDash integration, and utility functions.
 * 
 * @package OnlineTexasCore
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if required dependencies are available.
 *
 * @since 1.1.0
 * @return bool True if dependencies are met, false otherwise.
 */
function check_dependencies() {
	$missing = array();

	if ( ! class_exists( 'WooCommerce' ) ) {
		$missing[] = 'WooCommerce';
	}

	if ( ! function_exists( 'dokan' ) ) {
		$missing[] = 'Dokan';
	}

	if ( ! defined( 'LEARNDASH_VERSION' ) && ! class_exists( 'SFWD_LMS' ) ) {
		$missing[] = 'LearnDash';
	}

	if ( ! empty( $missing ) ) {
		log_debug( 'Missing dependencies: ' . implode( ', ', $missing ), 'error' );
		return false;
	}

	return true;
}

/**
 * Create a single vendor product with COMPLETE LearnDash integration.
 * Task 1.3: Use vendor_product_status option for new products.
 *
 * @since 1.0.0
 * @param int   $admin_product_id The admin product ID.
 * @param int   $vendor_id        The vendor user ID.
 * @param array $course_ids       Array of course IDs.
 * @return int|false The created product ID on success, false on failure.
 */
function create_single_vendor_product( $admin_product_id, $vendor_id, $course_ids ) {
	try {
		if ( ! check_dependencies() ) {
			throw new Exception( 'Required dependencies not available' );
		}

		$admin_product = wc_get_product( $admin_product_id );
		if ( ! $admin_product ) {
			throw new Exception( "Invalid admin product ID: {$admin_product_id}" );
		}

		// Validate vendor
		$vendor = get_user_by( 'ID', $vendor_id );
		if ( ! $vendor ) {
			throw new Exception( "Invalid vendor ID: {$vendor_id}" );
		}

		// Check if vendor is active
		if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $vendor_id ) ) {
			throw new Exception( "User {$vendor_id} is not an active vendor" );
		}

		// Get vendor store info
		$store_info = function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $vendor_id ) : array();
		$store_name = ! empty( $store_info['store_name'] ) ? $store_info['store_name'] : $vendor->display_name;

		// Get admin product price for initial setup
		$admin_price = $admin_product->get_regular_price();
		if ( empty( $admin_price ) ) {
			$admin_price = $admin_product->get_price();
		}

		// STEP 1: Duplicate the product
		$duplicated_product = duplicate_product( $admin_product );
		if ( is_wp_error( $duplicated_product ) ) {
			throw new Exception( 'Failed to duplicate product: ' . $duplicated_product->get_error_message() );
		}

		// Get vendor product status from options
		$options = get_option( 'otc_options', array() );
		$vendor_product_status = isset( $options['vendor_product_status'] ) ? $options['vendor_product_status'] : 'draft';
		$allowed_statuses = array( 'draft', 'pending', 'publish' );
		if ( ! in_array( $vendor_product_status, $allowed_statuses, true ) ) {
			$vendor_product_status = 'draft';
		}

		// FORCE automator_codes products to be published by default
		if ( $admin_product->get_type() === 'automator_codes' ) {
			$vendor_product_status = 'publish';
		}

		// STEP 2: Update the duplicated product
		$vendor_product_title = sanitize_text_field( $store_name . ' - ' . $admin_product->get_name() );

		$update_result = wp_update_post( array(
			'ID'          => $duplicated_product->get_id(),
			'post_title'  => $vendor_product_title,
			'post_name'   => sanitize_title( $vendor_product_title ),
			'post_status' => $vendor_product_status,
			'post_author' => $vendor_id
		) );

		if ( is_wp_error( $update_result ) ) {
			// Clean up on failure
			wp_delete_post( $duplicated_product->get_id(), true );
			throw new Exception( 'Failed to update vendor product: ' . $update_result->get_error_message() );
		}

		// STEP 3: Set initial price from admin product (vendor can change later)
		if ( ! empty( $admin_price ) ) {
			$duplicated_product->set_regular_price( $admin_price );
			$duplicated_product->set_price( $admin_price );
			$duplicated_product->save();
		}

		// STEP 4: Remove any copied course/group associations from admin product
		delete_post_meta( $duplicated_product->get_id(), '_related_course' );
		delete_post_meta( $duplicated_product->get_id(), '_related_group' );
		delete_post_meta( $duplicated_product->get_id(), 'learndash_course_enrolled_courses' );
		delete_post_meta( $duplicated_product->get_id(), 'learndash_group_enrolled_groups' );

		// STEP 5: Create LearnDash group and set meta ONLY for non-automator_codes
		if ( $admin_product->get_type() !== 'automator_codes' ) {
			$group_id = create_learndash_group( $vendor_id, $course_ids, $vendor_product_title, $duplicated_product->get_id(), $admin_price );
			if ( ! $group_id ) {
				// Clean up on failure
				wp_delete_post( $duplicated_product->get_id(), true );
				throw new Exception( 'Failed to create LearnDash group for vendor product' );
			}

			// STEP 6: Save metadata - including the group association
			$meta_data = array(
				'_parent_product_id'              => $admin_product_id,
				'_linked_ld_group_id'             => $group_id,
				'_vendor_product_original_title'  => $admin_product->get_name(),
				'_created_on'                     => current_time( 'mysql' ),
				'_related_group'                  => array( $group_id ),
				'learndash_group_enrolled_groups' => array( $group_id )
			);

			foreach ( $meta_data as $key => $value ) {
				update_post_meta( $duplicated_product->get_id(), $key, $value );
			}
		} else {
			// automator_codes: just set parent and original title meta
			$meta_data = array(
				'_parent_product_id'             => $admin_product_id,
				'_vendor_product_original_title' => $admin_product->get_name(),
				'_created_on'                    => current_time( 'mysql' ),
			);
			foreach ( $meta_data as $key => $value ) {
				update_post_meta( $duplicated_product->get_id(), $key, $value );
			}
		}

		log_debug( "Created vendor product ID: {$duplicated_product->get_id()} for vendor: {$vendor_id} with status: {$vendor_product_status}" . ($admin_product->get_type() !== 'automator_codes' ? ", LearnDash group: {$group_id}" : '') . " and initial price: {$admin_price}" );

		return $duplicated_product->get_id();

	} catch ( Exception $e ) {
		log_debug( "Error creating vendor product: " . $e->getMessage(), 'error' );
		return false;
	}
}

/**
 * Create a LearnDash group for the vendor.
 * This is the CRITICAL function that creates the course mapping!
 *
 * @since 1.0.0
 * @param int    $vendor_id           The vendor user ID.
 * @param array  $course_ids          Array of course IDs.
 * @param string $vendor_product_title The vendor product title.
 * @param int    $vendor_product_id   The vendor product ID.
 * @param string $initial_price       The initial price from admin product.
 * @return int|false The created group ID or false on failure.
 */
function create_learndash_group( $vendor_id, $course_ids, $vendor_product_title, $vendor_product_id = null, $initial_price = '' ) {
	if ( ! function_exists( 'learndash_get_post_type_slug' ) ) {
		log_debug( 'LearnDash functions not available', 'error' );
		return false;
	}

	// Validate course IDs
	$valid_courses = array();
	foreach ( $course_ids as $course_id ) {
		$course = get_post( $course_id );
		if ( $course && get_post_type( $course_id ) === learndash_get_post_type_slug( 'course' ) ) {
			$valid_courses[] = intval( $course_id );
		}
	}

	if ( empty( $valid_courses ) ) {
		log_debug( 'No valid courses found for group creation' );
		return false;
	}

	$group_title = sanitize_text_field( $vendor_product_title );

	// STEP 1: Create LearnDash group
	$group_data = array(
		'post_title'  => $group_title,
		'post_type'   => learndash_get_post_type_slug( 'group' ),
		'post_status' => 'publish',
		'post_author' => $vendor_id
	);

	$group_id = wp_insert_post( $group_data );

	if ( is_wp_error( $group_id ) || ! $group_id ) {
		log_debug( 'Failed to create LearnDash group: ' . ( is_wp_error( $group_id ) ? $group_id->get_error_message() : 'Unknown error' ), 'error' );
		return false;
	}

	// Get the product URL for the button
	$product_url = '';
	if ( $vendor_product_id ) {
		$product_url = get_permalink( $vendor_product_id );
	}

	// STEP 2: Set up group as closed with product link and initial price
	$groups_settings = array(
		0 => '', // First element is empty
		'groups_course_short_description' => '',
		'groups_group_price_type' => 'closed',
		'groups_custom_button_url' => $product_url,
		'groups_group_price' => $initial_price,
		'groups_group_start_date' => '0',
		'groups_group_end_date' => '0',
		'groups_group_seats_limit' => 0,
		'groups_group_price_billing_p3' => '',
		'groups_group_price_type_subscribe_billing_recurring_times' => '',
		'groups_group_price_billing_t3' => '',
		'groups_group_trial_price' => '',
		'groups_group_trial_duration_t1' => '',
		'groups_group_trial_duration_p1' => '',
		'groups_group_materials_enabled' => '',
		'groups_group_materials' => '',
		'groups_certificate' => '',
		'groups_group_disable_content_table' => '',
		'groups_group_courses_order_enabled' => '',
		'groups_group_courses_orderby' => '',
		'groups_group_courses_order' => ''
	);

	// STEP 3: Save group settings
	update_post_meta( $group_id, '_groups', $groups_settings );
	
	// Set LearnDash price type to closed
	update_post_meta( $group_id, '_ld_price_type', 'closed' );
	
	// STEP 4: Set related courses
	update_post_meta( $group_id, '_related_course', $valid_courses );

	// Additional LearnDash Course Grid meta (if plugin is active)
	update_post_meta( $group_id, '_learndash_course_grid_short_description', '' );
	update_post_meta( $group_id, '_learndash_course_grid_duration', '' );
	update_post_meta( $group_id, '_learndash_course_grid_enable_video_preview', '0' );
	update_post_meta( $group_id, '_learndash_course_grid_video_embed_code', '' );
	update_post_meta( $group_id, '_learndash_course_grid_custom_button_text', '' );
	update_post_meta( $group_id, '_learndash_course_grid_custom_ribbon_text', '' );
	update_post_meta( $group_id, '_ld_certificate', '' );

	// STEP 5: Link group to courses using LearnDash function
	if ( function_exists( 'learndash_set_group_enrolled_courses' ) ) {
		learndash_set_group_enrolled_courses( $group_id, $valid_courses );
	}

	// STEP 6: Set vendor as group leader
	set_group_leader( $group_id, $vendor_id );

	log_debug( "Successfully created closed LearnDash group ID: {$group_id} for vendor: {$vendor_id} with courses: " . implode( ',', $valid_courses ) . ", product URL: {$product_url}, and initial price: {$initial_price}" );

	return intval( $group_id );
}

/**
 * Set a user as a group leader for a LearnDash group.
 *
 * @since 1.0.0
 * @param int $group_id The group ID.
 * @param int $user_id  The user ID.
 */
function set_group_leader( $group_id, $user_id ) {
	// Method 1: LearnDash's built-in function (preferred)
	if ( function_exists( 'learndash_set_administrators_group_id' ) ) {
		learndash_set_administrators_group_id( $user_id, $group_id );
	}

	// Method 2: Update group post meta to include leader
	$group_leaders = get_post_meta( $group_id, 'learndash_group_leaders', true );
	if ( ! is_array( $group_leaders ) ) {
		$group_leaders = array();
	}
	
	if ( ! in_array( $user_id, $group_leaders ) ) {
		$group_leaders[] = $user_id;
		update_post_meta( $group_id, 'learndash_group_leaders', $group_leaders );
	}
}

/**
 * Duplicate a WooCommerce product.
 *
 * @since 1.0.0
 * @param WC_Product $original_product The product to duplicate.
 * @return WC_Product|WP_Error The duplicated product or error.
 */
function duplicate_product( $original_product ) {
	if ( ! $original_product instanceof WC_Product ) {
		return new WP_Error( 'invalid_product', 'Invalid product object' );
	}

	// Try WooCommerce native duplication first
	if ( class_exists( 'WC_Admin_Duplicate_Product' ) ) {
		$duplicate_handler = new WC_Admin_Duplicate_Product();
		if ( method_exists( $duplicate_handler, 'product_duplicate' ) ) {
			return $duplicate_handler->product_duplicate( $original_product );
		}
	}

	// Fallback: manual duplication
	return manual_product_duplicate( $original_product );
}

/**
 * Manual product duplication fallback.
 *
 * @since 1.0.0
 * @param WC_Product $original_product The product to duplicate.
 * @return WC_Product|WP_Error The duplicated product or error.
 */
function manual_product_duplicate( $original_product ) {
	$duplicate_data = array(
		'post_title'     => $original_product->get_name(),
		'post_content'   => $original_product->get_description(),
		'post_excerpt'   => $original_product->get_short_description(),
		'post_status'    => 'draft',
		'post_type'      => 'product',
		'ping_status'    => 'closed',
		'comment_status' => 'closed'
	);

	$duplicate_id = wp_insert_post( $duplicate_data );

	if ( is_wp_error( $duplicate_id ) ) {
		return $duplicate_id;
	}

	// Copy product meta (excluding specific keys)
	$exclude_meta = array( 
		'_edit_lock', 
		'_edit_last', 
		'_related_course',
		'_related_group',
		'learndash_course_enrolled_courses',
		'learndash_group_enrolled_groups'
	);
	
	$meta_keys = get_post_meta( $original_product->get_id() );
	
	foreach ( $meta_keys as $key => $values ) {
		if ( in_array( $key, $exclude_meta, true ) ) {
			continue;
		}

		foreach ( $values as $value ) {
			add_post_meta( $duplicate_id, $key, maybe_unserialize( $value ) );
		}
	}

	return wc_get_product( $duplicate_id );
}

/**
 * Fetch courses linked to a product (direct courses or via groups).
 *
 * @since 1.0.0
 * @param int $post_id The product ID.
 * @return array Array of course IDs.
 */
function fetch_course_from_product( $post_id ) {
	if ( ! check_dependencies() ) {
		return array();
	}

	$linked_course = get_post_meta( $post_id, '_related_course', true );
	if ( ! is_array( $linked_course ) ) {
		$linked_course = ! empty( $linked_course ) ? array( $linked_course ) : array();
	}

	$linked_groups = get_post_meta( $post_id, '_related_group', true );
	
	if ( ! empty( $linked_groups ) && is_array( $linked_groups ) ) {
		foreach ( $linked_groups as $group_id ) {
			if ( function_exists( 'learndash_group_enrolled_courses' ) ) {
				$group_courses = learndash_group_enrolled_courses( $group_id );
				
				if ( ! empty( $group_courses ) ) {
					if ( is_array( $group_courses ) ) {
						$linked_course = array_merge( $linked_course, $group_courses );
					} else {
						$linked_course[] = $group_courses;
					}
				}
			}
		}
		
		$linked_course = array_unique( array_filter( $linked_course ) );
	}

	return array_map( 'intval', array_filter( $linked_course ) );
}

/**
 * Get admin products for display to vendors.
 * Task 2.2: Filter products based on vendor availability settings.
 *
 * @since 1.0.0
 * @param int $paged    Page number for pagination.
 * @param int $per_page Number of products per page.
 * @return WP_Query Query object with filtered products.
 */
function get_admin_products_for_vendor( $paged = 1, $per_page = 20 ) {
    // Get current vendor ID
    $vendor_id = get_current_user_id();
    // Get all admin users
    $admin_users = get_users( array( 'role' => 'administrator' ) );
    $admin_user_ids = wp_list_pluck( $admin_users, 'ID' );
    $admin_user_ids[] = 0; // Include products with no author
    $get_restricted_products = get_user_meta($vendor_id, 'admin_restricted_products', true) ?? array();
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'author__in' => $admin_user_ids,
        'posts_per_page' => $per_page,
        'post__not_in' => $get_restricted_products,
        'paged' => $paged,
        'meta_query' => array(
            'relation' => 'AND',
            // Existing course requirement
            array(
                'relation' => 'OR',
                array(
                    'key' => '_related_course',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_related_group',
                    'compare' => 'EXISTS'
                )
            )
        )
    );
    $products = new WP_Query( $args );
    
    // Filter out products that this vendor has already duplicated
    $filtered_products = array();
    if ( $products && $products->have_posts() ) {
        while ( $products->have_posts() ) {
            $products->the_post();
            $product_id = get_the_ID();
            
            // Only include products that this vendor hasn't duplicated yet
            if ( ! is_duplicated( $product_id, $vendor_id ) ) {
                $filtered_products[] = $product_id;
            }
        }
        wp_reset_postdata();
    }
    
    // Create a new WP_Query with the filtered product IDs
    if ( ! empty( $filtered_products ) ) {
        $filtered_args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'post__in' => $filtered_products,
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        return new WP_Query( $filtered_args );
    } else {
        // Return empty query if no products found
        return new WP_Query( array( 'post_type' => 'product', 'post__in' => array( 0 ) ) );
    }
}

/**
 * Get automator_codes products for vendor.
 * 
 * @param int $paged Page number
 * @param int $per_page Products per page
 * @param bool $admin If true, returns admin products not duplicated by vendor. If false, returns vendor's duplicated products.
 * @return array Array of product IDs
 */
function get_admin_automator_code_products_for_vendor( $paged = 1, $per_page = 20, $admin = true ) {
    $vendor_id = get_current_user_id();
    
    if ( $admin ) {
        // Get admin products that vendor can duplicate
        $admin_user_ids = get_admin_user_ids();
        
        $args = array(
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'author__in' => $admin_user_ids,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => 'automator_codes'
                )
            )
        );
        
        // Use Dokan's product manager which properly handles vendor queries
        $products = dokan()->product->all( $args );
        $filtered = array();
        
        if ( $products && $products->have_posts() ) {
            while ( $products->have_posts() ) {
                $products->the_post();
                $pid = get_the_ID();
                
                $available = get_post_meta( $pid, '_available_for_vendors', true );
                $restricted = get_post_meta( $pid, '_restricted_vendors', true );
                
                // Check if product is available for this vendor
                if ( $available === 'no' ) {
                    continue;
                }
                if ( $available === 'selective' ) {
                    if ( ! is_array( $restricted ) ) {
                        $restricted = array();
                    }
                    if ( ! in_array( $vendor_id, $restricted ) ) {
                        continue;
                    }
                }
                
                // Only include admin products that this vendor hasn't duplicated yet
                if ( ! is_duplicated( $pid, $vendor_id ) ) {
                    $filtered[] = $pid;
                }
            }
            wp_reset_postdata();
        }
        
        return $filtered;
        
    } else {
        // Get vendor's own duplicated automator_codes products
        $args = array(
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'author' => $vendor_id,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => 'automator_codes'
                )
            ),
            'meta_query' => array(
                array(
                    'key' => '_parent_product_id',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        // Use Dokan's product manager for vendor's own products
        $products = dokan()->product->all( $args );
        $filtered = array();
        
        if ( $products && $products->have_posts() ) {
            while ( $products->have_posts() ) {
                $products->the_post();
                $pid = get_the_ID();
                
                // Only include vendor's duplicated products (have parent_product_id)
                if ( get_post_meta( $pid, '_parent_product_id', true ) ) {
                    $filtered[] = $pid;
                }
            }
            wp_reset_postdata();
        }
        
        return $filtered;
    }
}

/**
 * Check if a product has been duplicated by the current vendor.
 * Updated to check per-vendor instead of globally.
 *
 * @since 1.0.0
 * @param int $product_id The product ID to check.
 * @param int $vendor_id Optional. Vendor ID to check. Defaults to current user.
 * @return bool True if THIS VENDOR has duplicated the product, false otherwise.
 */
function is_duplicated($product_id, $vendor_id = null) {
    if (!$vendor_id) {
        $vendor_id = get_current_user_id();
    }
    
    // Check if THIS SPECIFIC VENDOR has duplicated this product
    $duplicate_check = get_posts(array(
        'post_type' => 'product',
        'author' => $vendor_id, // Check by specific vendor
        'meta_key' => '_parent_product_id',
        'meta_value' => $product_id,
        'posts_per_page' => 1,
        'post_status' => 'any',
        'fields' => 'ids'
    ));
    
    return !empty($duplicate_check);
}

/**
 * Log debug messages.
 *
 * @since 1.0.0
 * @param string $message The message to log.
 * @param string $type    The log type (debug, error, info).
 */
function log_debug( $message, $type = 'debug' ) {
	// Always log to WordPress error log for errors
	if ( $type === 'error' ) {
	
	}

	// Check if debug mode is enabled
	$options = get_option( 'otc_options', array() );
	if ( empty( $options['debug_mode'] ) ) {
		return;
	}

	$log = get_option( 'otc_debug_log', array() );
	$log[] = array(
		'timestamp' => current_time( 'mysql' ),
		'message'   => sanitize_text_field( $message ),
		'type'      => sanitize_text_field( $type )
	);

	// Keep only last 100 entries to prevent database bloat
	if ( count( $log ) > 100 ) {
		$log = array_slice( $log, -100 );
	}

	update_option( 'otc_debug_log', $log );
}


// Helper functions (add these to your class or include from the previous artifact)
/**
 * Get the specific group where user is enrolled and contains the course
 */
function get_user_course_group($user_id, $course_id) {
    if (empty($user_id) || empty($course_id)) {
        return null;
    }
    
    // Get all groups where user is enrolled
    $user_groups = learndash_get_users_group_ids($user_id);
    
    if (empty($user_groups)) {
        return null;
    }
    
    // Check each user group to see if it contains the course
    $matching_group = null;
    foreach ($user_groups as $group_id) {
        $group_courses = learndash_group_enrolled_courses($group_id);
        
        if (in_array($course_id, $group_courses)) {
            $matching_group = $group_id;
            break; // Found the matching group
        }
    }
    
    if (!empty($matching_group)) {
        return $matching_group;
    }
    
    // Alternative method: Check if user has direct course access through a group
    // This handles cases where the user might have course access but group relationship is different
    if (empty($matching_group)) {
        $all_groups_with_course = get_groups_containing_course($course_id);
        
        foreach ($all_groups_with_course as $group_id) {
            // Check if user is actually a member of this group
            if (function_exists('learndash_is_user_in_group')) {
                if (learndash_is_user_in_group($user_id, $group_id)) {
                    return $group_id;
                }
            } else {
                // Fallback check
                if (in_array($group_id, $user_groups)) {
                    return $group_id;
                }
            }
        }
    }
    
    // Last resort: Check activity logs
    $activity_group = get_user_course_group_from_activity($user_id, $course_id);
    if (!empty($activity_group)) {
        return $activity_group;
    }
    
    return null;
}

function get_groups_containing_course($course_id) {
    if (empty($course_id)) {
        return array();
    }
    
    // Use LearnDash function if available
    if (function_exists('learndash_get_course_groups')) {
        return learndash_get_course_groups($course_id);
    }
    
    // Manual lookup
    $groups = get_posts(array(
        'post_type' => 'groups',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'fields' => 'ids'
    ));
    
    $course_groups = array();
    foreach ($groups as $group_id) {
        $group_courses = learndash_group_enrolled_courses($group_id);
        if (in_array($course_id, $group_courses)) {
            $course_groups[] = $group_id;
        }
    }
    
    return $course_groups;
}

/**
 * Get groups containing a specific course
 */
function user_has_group_access($user_id, $group_id) {
    if (empty($user_id) || empty($group_id)) {
        return false;
    }
    
    // Check if user is a group member
    if (function_exists('learndash_is_user_in_group')) {
        if (learndash_is_user_in_group($user_id, $group_id)) {
            return true;
        }
    } else {
        $user_groups = learndash_get_users_group_ids($user_id);
        if (in_array($group_id, $user_groups)) {
            return true;
        }
    }
    
    // Check if user is a group leader
    $group_leaders = learndash_get_groups_administrators($group_id);
    if (in_array($user_id, $group_leaders)) {
        return true;
    }
    
    return false;
}

/**
 * Get all admin user IDs.
 * @return array
 */
function get_admin_user_ids() {
    $admins = get_users( array(
        'role'   => 'administrator',
        'fields' => 'ID'
    ) );
    return $admins;
}

/**
 * Get all admin products (both regular and automator_codes) for vendor cloning.
 * Merges both product types into a single list with product type information.
 */
function get_all_admin_products_for_vendor( $paged = 1, $per_page = 20 ) {
    $vendor_id = get_current_user_id();
    $all_products = array();
    
    // Get regular products (with courses/groups)
    $regular_products = get_admin_products_for_vendor( $paged, $per_page );
    if ( $regular_products && $regular_products->have_posts() ) {
        while ( $regular_products->have_posts() ) {
            $regular_products->the_post();
            $product = wc_get_product( get_the_ID() );
            if ( $product ) {
                // Get course information for this product
                $course_ids = fetch_course_from_product( get_the_ID() );
                $courses = array();
                
                foreach ( $course_ids as $course_id ) {
                    $course = get_post( $course_id );
                    if ( $course && get_post_type( $course_id ) === 'sfwd-courses' ) {
                        $courses[] = array(
                            'id' => $course_id,
                            'name' => $course->post_title
                        );
                    }
                }
                
                $all_products[] = array(
                    'id' => get_the_ID(),
                    'name' => get_the_title(),
                    'type' => $product->get_type(),
                    'price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price(),
                    'status' => $product->get_status(),
                    'short_description' => $product->get_short_description(),
                    'thumbnail' => get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' ),
                    'is_automator_codes' => false,
                    'already_duplicated' => is_duplicated( get_the_ID(), $vendor_id ),
                    'courses' => $courses
                );
            }
        }
        wp_reset_postdata();
    }
    
    // Get automator_codes products
    $codes_products = get_admin_automator_code_products_for_vendor( $paged, $per_page );
    foreach ( $codes_products as $product_id ) {
        $product = wc_get_product( $product_id );
        if ( $product ) {
            // For automator_codes products, we don't have linked courses
            $all_products[] = array(
                'id' => $product_id,
                'name' => $product->get_name(),
                'type' => $product->get_type(),
                'price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'status' => $product->get_status(),
                'short_description' => $product->get_short_description(),
                'thumbnail' => get_the_post_thumbnail_url( $product_id, 'thumbnail' ),
                'is_automator_codes' => true,
                'already_duplicated' => is_duplicated( $product_id, $vendor_id ),
                'courses' => array() // automator_codes products don't have linked courses
            );
        }
    }
    
    // Sort by name for consistent ordering
    usort( $all_products, function( $a, $b ) {
        return strcasecmp( $a['name'], $b['name'] );
    } );
    
    return $all_products;
}