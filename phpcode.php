<?php
add_action( 'admin_enqueue_scripts', 'wpr_admin_enqueue_scripts' );
/**
 * Admin enqueue scripts
 */
public
function wpr_admin_enqueue_scripts() {
	$scheme = 'http';
	if ( is_ssl() ) {
		$scheme = 'https';
	}

	wp_enqueue_script( 'wpr-custom-script', plugin_dir_url( __FILE__ ) . 'assets/js/wpr-custom.js', array( 'jquery' ), '1.0.0', true );
	wp_localize_script( 'wpr-custom-script', 'ajax_object', array(
		'nonce'     => wp_create_nonce( 'wpr-nonce' ),
		'ajax_url'  => admin_url( 'admin-ajax.php', $scheme ),
		'load_from' => plugin_dir_url( __FILE__ ),
	) );
}

add_action( 'wp_ajax_wpr_search_product', 'wpr_search_product' );
/**
 * Ajax request products
 */
function wpr_search_product() {
	check_ajax_referer( 'wpr-nonce', 'nonce' );
	$products    = array();
	$search_term = esc_attr( $_GET['q'] );
	$args        = array(
		's'              => $search_term,
		'posts_per_page' => - 1,
		'post_type'      => array( 'product', 'product_variation' ),
		'post_status'    => 'publish',

	);
	add_filter( 'posts_where', 'wpr_where_product_name', 10, 2 );
	$query = new WP_Query( $args );
	$posts = $query->posts;

	if ( ! empty( $posts ) ) {
		foreach ( $posts as $post ) {
			$product['value'] = $post->post_title;
			$product['id']    = $post->ID;

			array_push( $products, $product );
		}
	}
	remove_filter( 'posts_where', 'wpr_where_product_name', 10, 2 );
	echo wp_json_encode( $products );

	wp_die();
}

/**
 * Where title like
 *
 * @param $where
 * @param $wp_query
 *
 * @return string
 */
function wpr_where_product_name( $where, &$wp_query ) {
	global $wpdb;

	if ( $search_term = $wp_query->get( 'search_prod_title' ) ) {
		$search_term = $wpdb->esc_like( $search_term );
		$search_term = ' \'%' . $search_term . '%\'';
		$where       .= ' AND ' . $wpdb->posts . '.post_title LIKE ' . $search_term;
	}

	return $where;
}

add_action( 'add_meta_boxes', 'wpr_add_metabox' );
/**
 * Add metabox
 */
public
function wpr_add_metabox() {
	add_meta_box( 'wpr_add_product', esc_html__( 'Add product' ), 'wpr_add_product', 'job_listing', 'side', 'high' );
}

/**
 * Metabox callback
 *
 * @param $post
 */
public
function wpr_add_product( $post ) {
	wp_nonce_field( basename( __FILE__ ), 'wpr_add_product' );
	?>
    <p>
        <label for="smashing-post-class"><?php _e( 'Add a product' ); ?></label>
        <br/>
        <input type="text" name="wpr-product" value="" class="wpr-autocomplete"/>
        <input type="hidden" id="wpr-product-ids" name="wpr-product-ids" value=""/>
    <ul id="wpr-active-product">
		<?php
		$get_products = get_post_meta( $post->ID, 'wpr_products', true );
		if ( ! empty( $get_products ) ) {
			foreach ( $get_products as $product ) {
				echo sprintf(
					'<li><span class="wpr-product-name">%s</span><input type="hidden" value="%d" name="wpr-product-id[]" class="wpr-product-id"/> <a href="#" class="wpr-remove-product">X</a></li>',
					get_the_title( $product ),
					absint( $product )
				);
			}
		}
		?>
    </ul>
    </p>
	<?php
}

add_action( 'save_post', 'wpr_save_products', 10, 2 );
/**
 * Save product
 *
 * @param $post_id
 * @param $post
 *
 * @return mixed
 */
function wpr_save_products( $post_id, $post ) {
	if ( 'job_listing' !== $post->post_type ) {
		return $post_id;
	}
	if ( ! isset( $_POST['wpr_add_product'] ) || ! wp_verify_nonce( $_POST['wpr_add_product'], basename( __FILE__ ) ) ) {
		return $post_id;
	}

	$post_type = get_post_type_object( $post->post_type );
	if ( ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
		return $post_id;
	}

	if ( isset( $_POST['wpr-product-id'] ) && ! empty( $_POST['wpr-product-id'] ) ) {
		$products = array();
		foreach ( $_POST['wpr-product-id'] as $product_id ) {
			array_push( $products, absint( $product_id ) );
		}
		update_post_meta( $post_id, 'wpr_products', $products );
	} else {
		update_post_meta( $post_id, 'wpr_products', '' );
	}
}