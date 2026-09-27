<?php
/**
 * Plugin Name:       Resource Library
 * Plugin URI:        https://github.com/Halleluyahsalako/wp-resource-library
 * Description:       A filterable library of downloadable documents for WordPress. One plugin instead of a custom post type plugin, a custom fields plugin, a filter plugin and a search plugin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Salako Halleluyah
 * Author URI:        https://halleluyahsalako.github.io
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       resource-library
 * Domain Path:       /languages
 *
 * @package Resource_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RESLIB_VERSION', '1.0.0' );
define( 'RESLIB_FILE', __FILE__ );
define( 'RESLIB_URL', plugin_dir_url( __FILE__ ) );

/**
 * A filterable document library.
 *
 * Type and subject are taxonomies rather than a fixed list of options. That
 * decision is the whole plugin: every site that needs a resource library needs
 * different vocabulary, and hardcoding one organisation's terms is what makes
 * this sort of thing unusable by anyone else.
 */
class Resource_Library {

	const POST_TYPE = 'resource';
	const TAX_TYPE  = 'resource_type';
	const TAX_SUBJ  = 'resource_subject';

	public function __construct() {
		add_action( 'init', array( $this, 'register_content_types' ) );
		add_action( 'init', array( $this, 'register_meta' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );

		add_shortcode( 'resource_library', array( $this, 'render_library' ) );
		add_shortcode( 'resource_buttons', array( $this, 'render_buttons' ) );

		add_action( 'wp_ajax_reslib_filter', array( $this, 'ajax_filter' ) );
		add_action( 'wp_ajax_nopriv_reslib_filter', array( $this, 'ajax_filter' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Elementor renders custom post types only when they are opted in.
		add_action( 'after_setup_theme', array( $this, 'enable_elementor_support' ) );
	}

	/* ---------------------------------------------------------------------
	 * Content types
	 * ------------------------------------------------------------------- */

	public function register_content_types() {
		register_post_type(
			self::POST_TYPE,
			apply_filters(
				'reslib_post_type_args',
				array(
					'labels'             => array(
						'name'          => __( 'Resources', 'resource-library' ),
						'singular_name' => __( 'Resource', 'resource-library' ),
						'add_new_item'  => __( 'Add New Resource', 'resource-library' ),
						'edit_item'     => __( 'Edit Resource', 'resource-library' ),
						'search_items'  => __( 'Search Resources', 'resource-library' ),
						'menu_name'     => __( 'Resources', 'resource-library' ),
					),
					'public'             => true,
					'has_archive'        => true,
					'publicly_queryable' => true,
					'show_in_rest'       => true,
					'menu_icon'          => 'dashicons-media-document',
					'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
					'rewrite'            => array( 'slug' => 'resources' ),
				)
			)
		);

		register_taxonomy(
			self::TAX_TYPE,
			self::POST_TYPE,
			apply_filters(
				'reslib_type_taxonomy_args',
				array(
					'labels'            => array(
						'name'          => __( 'Resource Types', 'resource-library' ),
						'singular_name' => __( 'Resource Type', 'resource-library' ),
						'menu_name'     => __( 'Types', 'resource-library' ),
					),
					'hierarchical'      => true,
					'public'            => true,
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'rewrite'           => array( 'slug' => 'resource-type' ),
				)
			)
		);

		register_taxonomy(
			self::TAX_SUBJ,
			self::POST_TYPE,
			apply_filters(
				'reslib_subject_taxonomy_args',
				array(
					'labels'            => array(
						'name'          => __( 'Subjects', 'resource-library' ),
						'singular_name' => __( 'Subject', 'resource-library' ),
						'menu_name'     => __( 'Subjects', 'resource-library' ),
					),
					'hierarchical'      => true,
					'public'            => true,
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'rewrite'           => array( 'slug' => 'resource-subject' ),
				)
			)
		);
	}

	public function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			'_reslib_file_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_reslib_year',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public function enable_elementor_support() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		$supported = get_option( 'elementor_cpt_support', array( 'post', 'page' ) );

		if ( ! in_array( self::POST_TYPE, $supported, true ) ) {
			$supported[] = self::POST_TYPE;
			update_option( 'elementor_cpt_support', $supported );
		}
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------- */

	public function add_meta_box() {
		add_meta_box(
			'reslib-details',
			__( 'Resource File', 'resource-library' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'reslib_save', 'reslib_nonce' );

		$file_id = (int) get_post_meta( $post->ID, '_reslib_file_id', true );
		$year    = (int) get_post_meta( $post->ID, '_reslib_year', true );
		$url     = $file_id ? wp_get_attachment_url( $file_id ) : '';
		?>
		<p>
			<label for="reslib_file_id"><strong><?php esc_html_e( 'File', 'resource-library' ); ?></strong></label><br>
			<input type="number" id="reslib_file_id" name="reslib_file_id" value="<?php echo esc_attr( $file_id ?: '' ); ?>" class="widefat" min="0">
			<span class="description">
				<?php esc_html_e( 'Media library attachment ID. Upload the file under Media, then paste its ID here.', 'resource-library' ); ?>
			</span>
			<?php if ( $url ) : ?>
				<br><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'View current file', 'resource-library' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<p>
			<label for="reslib_year"><strong><?php esc_html_e( 'Year of publication', 'resource-library' ); ?></strong></label><br>
			<input type="number" id="reslib_year" name="reslib_year" value="<?php echo esc_attr( $year ?: '' ); ?>"
				class="widefat" min="1900" max="<?php echo esc_attr( (int) gmdate( 'Y' ) + 1 ); ?>"
				placeholder="<?php echo esc_attr( gmdate( 'Y' ) ); ?>">
		</p>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['reslib_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['reslib_nonce'] ) ), 'reslib_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$file_id = isset( $_POST['reslib_file_id'] ) ? absint( $_POST['reslib_file_id'] ) : 0;
		$year    = isset( $_POST['reslib_year'] ) ? absint( $_POST['reslib_year'] ) : 0;

		$file_id ? update_post_meta( $post_id, '_reslib_file_id', $file_id ) : delete_post_meta( $post_id, '_reslib_file_id' );
		$year ? update_post_meta( $post_id, '_reslib_year', $year ) : delete_post_meta( $post_id, '_reslib_year' );
	}

	/* ---------------------------------------------------------------------
	 * Front end
	 * ------------------------------------------------------------------- */

	public function register_assets() {
		wp_register_style( 'reslib', RESLIB_URL . 'assets/css/resource-library.css', array(), RESLIB_VERSION );
		wp_register_script( 'reslib', RESLIB_URL . 'assets/js/resource-library.js', array(), RESLIB_VERSION, true );
	}

	/**
	 * Render the filter interface. Assets are enqueued here rather than
	 * globally, so a site with one resources page does not load this on
	 * every request.
	 */
	public function render_library( $atts ) {
		$atts = shortcode_atts(
			array(
				'per_page' => 12,
				'columns'  => 3,
			),
			$atts,
			'resource_library'
		);

		wp_enqueue_style( 'reslib' );
		wp_enqueue_script( 'reslib' );
		wp_localize_script(
			'reslib',
			'reslibVars',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'reslib' ),
				'i18n'    => array(
					'noResults' => __( 'No resources match those filters.', 'resource-library' ),
					'error'     => __( 'Could not load resources. Please try again.', 'resource-library' ),
					'showing'   => __( 'Showing %1$s to %2$s of %3$s resources', 'resource-library' ),
					'view'      => __( 'View', 'resource-library' ),
					'download'  => __( 'Download', 'resource-library' ),
				),
			)
		);

		ob_start();
		?>
		<div class="reslib"
			data-per-page="<?php echo esc_attr( absint( $atts['per_page'] ) ); ?>"
			data-columns="<?php echo esc_attr( absint( $atts['columns'] ) ); ?>">

			<form class="reslib__filters" role="search" onsubmit="return false;">
				<div class="reslib__search">
					<label class="screen-reader-text" for="reslib-search"><?php esc_html_e( 'Search resources', 'resource-library' ); ?></label>
					<input type="search" id="reslib-search" class="reslib__search-input"
						placeholder="<?php esc_attr_e( 'Search resources, topics, keywords', 'resource-library' ); ?>">
				</div>

				<div class="reslib__selects">
					<?php
					$this->render_term_select( self::TAX_TYPE, __( 'Type', 'resource-library' ), __( 'All types', 'resource-library' ) );
					$this->render_term_select( self::TAX_SUBJ, __( 'Subject', 'resource-library' ), __( 'All subjects', 'resource-library' ) );
					$this->render_year_select();
					?>
					<button type="button" class="reslib__clear"><?php esc_html_e( 'Clear filters', 'resource-library' ); ?></button>
				</div>
			</form>

			<p class="reslib__meta" aria-live="polite"></p>
			<div class="reslib__grid" aria-busy="false"></div>
			<nav class="reslib__pagination" aria-label="<?php esc_attr_e( 'Resource pages', 'resource-library' ); ?>"></nav>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_term_select( $taxonomy, $label, $all_label ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}
		?>
		<div class="reslib__field">
			<label for="reslib-<?php echo esc_attr( $taxonomy ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="reslib-<?php echo esc_attr( $taxonomy ); ?>" data-filter="<?php echo esc_attr( $taxonomy ); ?>">
				<option value=""><?php echo esc_html( $all_label ); ?></option>
				<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Years come from the published resources themselves, so the dropdown
	 * never offers a year with nothing behind it.
	 */
	private function render_year_select() {
		$years = get_transient( 'reslib_years' );

		if ( false === $years ) {
			global $wpdb;

			$years = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_value
					 FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					 WHERE pm.meta_key = %s
					   AND pm.meta_value != ''
					   AND p.post_type = %s
					   AND p.post_status = 'publish'
					 ORDER BY pm.meta_value DESC",
					'_reslib_year',
					self::POST_TYPE
				)
			);

			set_transient( 'reslib_years', $years, HOUR_IN_SECONDS );
		}

		if ( empty( $years ) ) {
			return;
		}
		?>
		<div class="reslib__field">
			<label for="reslib-year"><?php esc_html_e( 'Year', 'resource-library' ); ?></label>
			<select id="reslib-year" data-filter="year">
				<option value=""><?php esc_html_e( 'All years', 'resource-library' ); ?></option>
				<?php foreach ( $years as $year ) : ?>
					<option value="<?php echo esc_attr( $year ); ?>"><?php echo esc_html( $year ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * View and download buttons for a single resource. Useful inside a loop
	 * template built with a page builder.
	 */
	public function render_buttons( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'resource_buttons' );

		$post_id = absint( $atts['id'] ) ?: get_the_ID();
		$file_id = (int) get_post_meta( $post_id, '_reslib_file_id', true );
		$url     = $file_id ? wp_get_attachment_url( $file_id ) : '';

		if ( ! $url ) {
			return '';
		}

		wp_enqueue_style( 'reslib' );

		ob_start();
		?>
		<div class="reslib__actions">
			<a class="reslib__btn reslib__btn--view" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View', 'resource-library' ); ?>
			</a>
			<a class="reslib__btn reslib__btn--download" href="<?php echo esc_url( $url ); ?>" download>
				<?php esc_html_e( 'Download', 'resource-library' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	public function ajax_filter() {
		check_ajax_referer( 'reslib', 'nonce' );

		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$type     = isset( $_POST['type'] ) ? sanitize_title( wp_unslash( $_POST['type'] ) ) : '';
		$subject  = isset( $_POST['subject'] ) ? sanitize_title( wp_unslash( $_POST['subject'] ) ) : '';
		$year     = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : 0;
		$paged    = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? min( 50, max( 1, absint( $_POST['per_page'] ) ) ) : 12;

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
		);

		if ( $search ) {
			$args['s'] = $search;
		}

		$tax_query = array();

		if ( $type ) {
			$tax_query[] = array(
				'taxonomy' => self::TAX_TYPE,
				'field'    => 'slug',
				'terms'    => $type,
			);
		}

		if ( $subject ) {
			$tax_query[] = array(
				'taxonomy' => self::TAX_SUBJ,
				'field'    => 'slug',
				'terms'    => $subject,
			);
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		if ( $tax_query ) {
			$args['tax_query'] = $tax_query;
		}

		if ( $year ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_reslib_year',
					'value'   => $year,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
			);
		}

		// Newest first by publication year, unless the visitor is searching,
		// in which case relevance is more useful than chronology.
		if ( ! $search ) {
			$args['meta_key'] = '_reslib_year';
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
		}

		$query     = new WP_Query( apply_filters( 'reslib_query_args', $args ) );
		$resources = array();

		foreach ( $query->posts as $post ) {
			$file_id = (int) get_post_meta( $post->ID, '_reslib_file_id', true );

			$resources[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'permalink' => get_permalink( $post ),
				'excerpt'   => wp_trim_words( get_the_excerpt( $post ), 22 ),
				'thumbnail' => get_the_post_thumbnail_url( $post, 'medium_large' ) ?: '',
				'fileUrl'   => $file_id ? wp_get_attachment_url( $file_id ) : '',
				'type'      => $this->term_names( $post->ID, self::TAX_TYPE ),
				'subject'   => $this->term_names( $post->ID, self::TAX_SUBJ ),
				'year'      => (int) get_post_meta( $post->ID, '_reslib_year', true ),
			);
		}

		wp_send_json_success(
			array(
				'resources' => $resources,
				'found'     => (int) $query->found_posts,
				'maxPages'  => (int) $query->max_num_pages,
				'page'      => $paged,
				'perPage'   => $per_page,
			)
		);
	}

	private function term_names( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		return wp_list_pluck( $terms, 'name' );
	}
}

new Resource_Library();

/**
 * Flush rewrite rules once on activation so the archive and single URLs work
 * without a visit to Settings > Permalinks.
 */
register_activation_hook(
	RESLIB_FILE,
	function () {
		( new Resource_Library() )->register_content_types();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( RESLIB_FILE, 'flush_rewrite_rules' );

// The year dropdown is cached, so clear it whenever a resource changes.
add_action( 'save_post_' . Resource_Library::POST_TYPE, function () {
	delete_transient( 'reslib_years' );
} );

add_action( 'deleted_post', function ( $post_id, $post ) {
	if ( $post instanceof WP_Post && Resource_Library::POST_TYPE === $post->post_type ) {
		delete_transient( 'reslib_years' );
	}
}, 10, 2 );
