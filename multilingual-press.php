<?php
/**
 * Plugin Name:	Multilingual Press
 * Plugin URI:	https://github.com/inpsyde/multilingual-press
 * Description:	By using the WordPress plugin Multilingual-Press it's much easier to build multilingual sites and run them with WordPress Multisite feature. 
 * Author:		Inpsyde GmbH
 * Author URI:	http://inpsyde.com
 * Version:		0.8
 * Text Domain:	inpsyde_multilingualpress
 * Domain Path:	/languages
 * License:		GPLv3
 * 
 * Available hooks
 * 
 * - inpsyde_mlp_init - This hook is called upon instantiation of the main class
 * - mlp_blogs_add_fields - Allows modules to add form fields to the Multilingual Press blog settings screen
 * - mlp_blogs_add_fields_secondary - Same as above, with lower priority. 
 * - mlp_blogs_save_fields - Modules can hook in here to handle user data returned by their form fields
 * - mlp_options_page_add_metabox - This hook registers a metabox on the Multilingual Press options page. Use Multilingual_Press_Settingspage::$class_object->options_page for 'screen' parameter.
 * - mlp_settings_save_fields - Handles the data of the options page form. Function parameter contains the form data
 * - mlp_modules_add_fields - Add data to the module manager. Probably obsolete in the future.
 * - mlp_modules_save_fields - Hooks into the module manager's saving routine.
 * 
 * Available filters
 * 
 * - mlp_pre_save_postdata - This filter is passed the postdata prior to creating blog interlinks.
 * - mlp-context-help - Is passed the content of the contextual help screen.
 * - mlp_language_codes - Is passed all the language codes used by Multilingual Press.
 * 
 * Changelog
 * 
 * 0.8
 * - Codexified
 * - Renamed some Files
 * 
 * 0.7.5a
 * - Display an admin notice if the plugin was not activated on multisite
 * - Set the parent page if this page was also handled by the plugin [Issue 2](https://github.com/inpsyde/multilingual-press/issues/2)
 * - Fix a problem that a new multisite cannot set related blogs
 * - Change filter [Issue 12](https://github.com/inpsyde/multilingual-press/issues/12)
 * - Widget bugfix [Issue 12](https://github.com/inpsyde/multilingual-press/issues/12)
 * - Smaller source via use function selected() [Issue 12](https://github.com/inpsyde/multilingual-press/issues/12)
 * - Static value for register widget [Issue 12](https://github.com/inpsyde/multilingual-press/issues/12)
 * - Update Wiki for wrapper functions [Wiki on Repo](https://github.com/inpsyde/multilingual-press/wiki)
 * - Add new pages on [Wiki on Repo](https://github.com/inpsyde/multilingual-press/wiki) for Filter- and Action Hooks inside the plugin
 * - Fix bug, if you kill data on an blog for dont interlinked with other blogs
 * 
 * 0.7.4a
 * - Exported the basic UI and userinput handling functionality into "default-module" class
 * - By default post types other than post and page are excluded
 * - Incorrect flags for some languages [Issue 7](https://github.com/inpsyde/multilingual-press/issues/7)
 *
 * 0.7.3a
 * - Exported helper functions into own class
 * - Code documentation
 *
 * 0.7.2a
 * - Updated language codes
 */

if ( ! class_exists( 'Multilingual_Press' ) ) {
	
	// Enqueue plugin
	if ( function_exists( 'add_filter' ) ) {
	
		// Kick-Off
		add_filter( 'plugins_loaded', array( 'Multilingual_Press', 'get_object' ) );
	
		// Upon activation
		register_activation_hook( __FILE__, array( 'Multilingual_Press', 'install_plugin' ) );
	
		// Upon deactivation
		register_deactivation_hook( __FILE__, array( 'Multilingual_Press', 'remove_plugin' ) );
	}

	class Multilingual_Press {
	
		/**
		 * The class object
		 *
		 * @static
		 * @since  0.1
		 * @var    string
		 */
		static protected $class_object = NULL;

		/**
		 * The linked elements table
		 *
		 * @since  0.1
		 * @var    string
		 */
		protected $link_table = '';

		/**
		 * source id of the current element 
		 *
		 * @static
		 * @since  0.1
		 * @var    string
		 */
		private $source_id = 0;

		/**
		 * source id of the current blog
		 *
		 * @since  0.1
		 * @var    string
		 */
		private $source_blog_id = 0;

		/**
		 * source type of the current content
		 *
		 * @since  0.2
		 * @var    string
		 */
		private $source_type = '';

		/**
		 * Array containing loaded modules
		 *
		 * @since  0.5
		 * @var    array
		 */
		protected $loaded_modules = array( );

		/**
		 * array containing language codes and names
		 *
		 * @protected
		 * @since  0.5
		 * @var    array
		 */
		protected $lang_codes; // 

		/**
		 * to load the object and get the current state 
		 *
		 * @access  public
		 * @since   0.1
		 * @return  $class_object
		 */
		public function get_object() {
			if ( NULL == self::$class_object )
				self::$class_object = new self;
			return self::$class_object;
		}

		/**
		 * init function to register all used hooks,
		 * load class files and set parameters
		 * such as the database table 
		 *
		 * @access  public
		 * @since   0.1
		 * @uses	add_filter, get_site_option, is_multisite, is_super_admin,
		 * 			do_action, apply_filters
		 * @global	$wpdb | WordPress Database Wrapper
		 * @return  void
		 */
		public function __construct() {
			
			if ( function_exists( 'is_multisite' ) && ! is_multisite() && is_super_admin() ) {
				add_filter( 'admin_notices',  array( $this, 'error_msg_no_multisite' ) );
				return;
			}
			
			global $wpdb;

			// Show database errors
			// (only for development)
			$wpdb->show_errors();

			// Set linked elements table
			$this->link_table = $wpdb->base_prefix . 'multilingual_linked';

			// Load classes
			$this->include_files();

			// Kick-Off Init
			do_action( 'inpsyde_mlp_init' );

			// Hooks and filters
			add_filter( 'init', array( $this, 'localize_plugin' ) );

			// Load modules
			add_filter( 'plugins_loaded', array( $this, 'load_modules' ), 9 );

			// Add the meta box
			add_filter( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

			// Does another plugin offer its own save method?
			$external_save_method = apply_filters( 'inpsyde_multilingualpress_external_save_method', FALSE );
			if ( ! $external_save_method )
				add_filter( 'save_post', array( $this, 'save_post' ) );

			// Enqueue scripts
			add_filter( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			add_filter( 'wp_enqueue_scripts', array( $this, 'wp_styles' ) );

			// AJAX hooks for the tab form and the meta boxes
			add_filter( 'wp_ajax_tab_form', array( $this, 'draw_blog_settings_form' ) );
			add_filter( 'wp_ajax_save_multilang_settings', array( $this, 'update_blog_settings' ) );
			add_filter( 'wp_ajax_get_metabox_content', array( $this, 'ajax_get_metabox_content' ) );

			// Context help of the plugin
			add_filter( 'contextual_help', array( $this, 'context_help' ), 10, 3 );

			// Cleanup upon blog delete
			add_filter( 'delete_blog', array( $this, 'delete_blog' ), 10, 2 );
		}

		/**
		 * Include class files when needed
		 * 
		 * @access  public
		 * @since   0.1
		 * @uses	is_admin, add_filter
		 * @global	$pagenow | current page identifier
		 * @return  void
		 */
		private function include_files() {

			global $pagenow;

			// Include helper functions
			require_once( 'inc/class-mlp-helpers.php' );

			// Include widget
			require_once( 'inc/class-mlp-widget.php' );
			
			// Include default module
			require_once( 'inc/class-mlp-default-module.php' );

			// Page specific admin files
			$hook = array( 'sites.php' );
			if ( is_admin() && in_array( $pagenow, $hook ) ) {

				require_once( 'inc/class-mlp-custom-columns.php' );
				add_filter( 'init', array( 'Mlp_Custom_Columns', 'init' ) );
			}

			// Global admin files
			if ( is_admin() ) {

				// Include settings page _after_ modules are loaded
				require_once( 'inc/class-mlp-settings-page.php' );
				add_filter( 'plugins_loaded', array( 'Multilingual_Press_Settingspage', 'get_object' ), 8 );
			}
		}

		/**
		 * Load frontend CSS
		 * 
		 * @access  public
		 * @since	0.5.3b
		 * @uses	wp_enqueue_style, plugins_url
		 * @return  void
		 */
		public function wp_styles() {
			wp_enqueue_style( 'mlp-frontend-css', plugins_url( 'css/frontend.css', __FILE__ ) );
		}

		/**
		 * Load admin javascript and CSS
		 *
		 * @access  public
		 * @since	0.5.3b
		 * @uses	wp_enqueue_script, plugins_url, wp_localize_script, wp_enqueue_style
		 * @global	$pagenow | current page identifier
		 * @return  void
		 */
		public function admin_scripts() {

			global $pagenow;

			// We only need our Scripts on our pages
			$pages = array(
				'site-info.php',
				'site-users.php',
				'site-themes.php',
				'site-settings.php'
			);

			if ( in_array( $pagenow, $pages ) ) {
				wp_enqueue_script( 'mlp-js', plugins_url( 'js/', __FILE__ ) . 'multilingual_press.js' );
				wp_localize_script( 'mlp-js', 'mlp_loc', $this->localize_script() );
				wp_enqueue_style( 'mlp-admin-css', plugins_url( 'css/admin.css', __FILE__ ) );
			}
		}

		/**
		 * Make localized strings available in javascript
		 *
		 * @access  public
		 * @since	0.1
		 * @uses	wp_create_nonce
		 * @global	$pagenow | current page identifier
		 * @return	type $loc | Array containing localized strings
		 */
		public function localize_script() {

			$loc = array(
				'tab_label'						=> __( 'Multilingual Press', $this->get_textdomain() ),
				'blog_id'						=> intval( $_GET[ 'id' ] ),
				'ajax_tab_nonce'				=> wp_create_nonce( 'mlp_tab_nonce' ),
				'ajax_form_nonce'				=> wp_create_nonce( 'mlp_form_nonce' ),
				'ajax_select_nonce'				=> wp_create_nonce( 'mlp_select_nonce' ),
				'ajax_switch_language_nonce'	=> wp_create_nonce( 'mlp_switch_language_nonce' ),
				'ajax_check_single_nonce'		=> wp_create_nonce( 'mlp_check_single_nonce' )
			);

			return $loc;
		}

		/**
		 * Return Textdomain string
		 *
		 * @access	public
		 * @since	0.1
		 * @return	string | Plugins' textdomain
		 */
		public function get_textdomain() {

			return 'inpsyde_multilingualpress';
		}

		/**
		 * register the textdomain
		 *
		 * @access  public
		 * @since   0.1
		 * @uses	load_plugin_textdomain, plugin_basename
		 * @return  void
		 */
		public function localize_plugin() {

			load_plugin_textdomain(
				$this->get_textdomain(), FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages'
			);
		}

		/**
		 * set the source id for starting
		 *
		 * @access  public
		 * @since   0.1
		 * @uses	get_current_blog_id
		 * @param   int $sourceid ID of the selected element
		 * @param   int $source_blog ID of the selected blog
		 * @param   string $source_type type of the selected element
		 * @return  void
		 */
		public function set_source_id( $sourceid, $source_blog = 0, $source_type = '' ) {

			$this->source_id = $sourceid;
			if ( 0 == $source_blog )
				$source_blog = get_current_blog_id();
			
			$this->source_blog_id = $source_blog;
			$this->source_type = $source_type;

			// set the current element as the first linked element
			$this->set_linked_element( $sourceid, $sourceid, $source_blog, $source_type );
		}

		/**
		 * set the source id of the element
		 *
		 * @access  public
		 * @since   0.1
		 * @uses	get_current_blog_id
		 * @param   int $element_id ID of the selected element
		 * @param   int $sourceid ID of the selected element
		 * @param   int $source_blog ID of the selected blog
		 * @param   string $type type of the selected element
		 * @param   int $blog_id ID of the selected blog
		 * @global	$wpdb | WordPress Database Wrapper
		 * @return  void
		 */
		public function set_linked_element( $element_id, $source_id = 0, $source_blog_id = 0, $type = '', $blog_id = 0 ) {

			global $wpdb;

			if ( 0 == $blog_id )
				$blog_id = get_current_blog_id();
			
			if ( 0 == $source_id )
				$source_id = $this->source_id;
			
			if ( 0 == $source_blog_id )
				$source_blog_id = $this->source_blog_id;
			
			$wpdb->insert( $this->link_table, array( 'ml_source_blogid' => $source_blog_id, 'ml_source_elementid' => $source_id, 'ml_blogid' => $blog_id, 'ml_elementid' => $element_id, 'ml_type' => $type ) );
		}

		/**
		 * create the element on other blogs and link them
		 *
		 * @access  public
		 * @since   0.1
		 * @uses	get_post_status, get_post, get_post_thumbnail_id, wp_upload_dir, get_post_meta, 
		 *			pathinfo, get_blog_list, get_current_blog_id, switch_to_blog, wp_insert_post, 
		 *			wp_unique_filename, wp_check_filetype, is_wp_error, wp_update_attachment_metadata, 
		 *			wp_generate_attachment_metadata, update_post_meta, restore_current_blog
		 * @param   $post_id ID of the post
		 * @return  void
		 */
		public function save_post( $post_id ) {

			// We're only interested in published posts at this time
			$post_status = get_post_status( $post_id );
			if ( 'publish' !== $post_status )
				return;
			
			// Get the post
			$postdata = get_post( $post_id, ARRAY_A );
			
			// Apply a filter here so modules can play around
			// with the postdata before it is processed.
			$postdata = apply_filters( 'mlp_pre_save_postdata', $postdata );
			
			// If there is no filter hooked into this saving method, then we
			// will exclude all post types other that "post" and "page".
			// @TODO: improve this logic :/
			// @TODO: create a whitelist for allowed post types, incl. apply_filters() ?		   
			if ( ! has_filter( 'mlp_pre_save_postdata' ) ) 
				if ( 'post' != $postdata[ 'post_type'] && 'page' != $postdata[ 'post_type'] ) 
					return;
			
			// When the filter returns FALSE, we'll stop here
			if ( FALSE == $postdata || ! is_array( $postdata ) )
				return;
								
			$linked = mlp_get_linked_elements( $post_id );

			// We already linked this element?
			if ( 0 !== count( $linked ) )
				return;

			$this->set_source_id( $post_id );
			$file = '';
			
			// Check for thumbnail
			if ( current_theme_supports( 'post-thumbnails' ) ) {
				$thumb_id = get_post_thumbnail_id( $post_id );
				if ( 0 < $thumb_id ) {
					$path = wp_upload_dir();
					$file = get_post_meta( $thumb_id, '_wp_attached_file', true );
					$fileinfo = pathinfo( $file );
				}
			}
			
			// Create the post array
			$newpost = array(
				'post_title'	=> $postdata[ 'post_title' ],
				'post_content'	=> $postdata[ 'post_content' ],
				'post_status'	=> 'draft',
				'post_author'	=> $postdata[ 'post_author' ],
				'post_excerpt'	=> $postdata[ 'post_excerpt' ],
				'post_date'		=> $postdata[ 'post_date' ],
				'post_type'		=> $postdata[ 'post_type' ]
			);
			
			$blogs = mlp_get_available_languages();
			$current_blog = get_current_blog_id();

			if ( ! ( 0 < count( $blogs ) ) )
				return;

			// Load Page Parents			
			$parent_elements = array( );
			if ( 'page' == $postdata[ 'post_type' ] && 0 < $postdata[ 'post_parent' ] )
				$parent_elements = mlp_get_linked_elements( $postdata[ 'post_parent' ] );

			// Create a copy of the item for every related blog
			foreach ( $blogs as $blogid => $blogname ) {

				if ( $blogid != $current_blog ) {
					
					switch_to_blog( $blogid );

					// Set the linked parent page 
					if ( 0  < count( $parent_elements ) && 0 < $parent_elements[ $blogid ] )
						$newpost[ 'post_parent'] = $parent_elements[ $blogid ];
					
					// Insert remote blog post
					$remote_post_id = wp_insert_post( $newpost );
					
					if ( '' != $file ) { // thumbfile exists
						include_once ( ABSPATH . 'wp-admin/includes/image.php' ); //including the attachment function
						if ( 0 < count( $fileinfo ) ) {
							
							$filedir = wp_upload_dir();
							$filename = wp_unique_filename( $filedir[ 'path' ], $fileinfo[ 'basename' ] );
							$copy = copy( $path[ 'basedir' ] . '/' . $file, $filedir[ 'path' ] . '/' . $filename );
							
							if ( $copy ) {
								unset( $postdata[ 'ID' ] );
								$wp_filetype = wp_check_filetype( $filedir[ 'url' ] . '/' . $filename ); //get the file type
								$attachment = array(
									'post_mime_type'	=> $wp_filetype[ 'type' ],
									'guid'				=> $filedir[ 'url' ] . '/' . $filename,
									'post_parent'		=> $remote_post_id,
									'post_title'		=> '',
									'post_excerpt'		=> '',
									'post_author'		=> $postdata[ 'post_author' ],
									'post_content'		=> '',
								);
								
								//insert the image
								$attach_id = wp_insert_attachment( $attachment, $filedir[ 'path' ] . '/' . $filename );
								if ( ! is_wp_error( $attach_id ) ) {
									wp_update_attachment_metadata(
										$attach_id, wp_generate_attachment_metadata( $attach_id, $filedir[ 'path' ] . '/' . $filename )
									);
									set_post_thumbnail( $remote_post_id, $attach_id );
								} // update the image data
							}
						}
					}
					$this->set_linked_element( $remote_post_id );
					restore_current_blog();
				}
			}
		}

		/**
		 * add the metaboxes on posts and pages
		 *
		 * @access  public
		 * @since   0.1
		 * @uses	add_meta_box
		 * @global	$post | the current post
		 * @return  void
		 */
		public function add_meta_boxes() {
			global $post;
			
			// Do we have linked elements?
			$linked = mlp_get_linked_elements( $post->ID );
			if ( ! $linked )
				return;

			// Register metaboxes
			add_meta_box( 'multilingual_press_link', __( 'Linked posts', $this->get_textdomain() ), array( $this, 'display_meta_box' ), 'post', 'normal', 'high' );
			add_meta_box( 'multilingual_press_link', __( 'Linked pages', $this->get_textdomain() ), array( $this, 'display_meta_box' ), 'page', 'normal', 'high' );
		}

		/**
		 * show the metabox
		 *
		 * @access  public
		 * @since   0.1
		 * @uses	get_site_option, get_current_blog_id
		 * @param   $post post object  
		 * @return  void
		 */
		public function display_meta_box( $post ) {

			$linked = mlp_get_linked_elements( $post->ID );
			if ( 0 < count( $linked ) ) { // post is a linked post
				$languages = mlp_get_available_languages();
				if ( 0 < count( $languages ) ) {
					
					?>
					<select name="multilingual_press" id="multilingual_press">
						<option><?php _e( 'choose preview language', $this->get_textdomain() ); ?></option>
					<?php
					foreach ( $languages as $language_blogid => $language_name ) {
						if ( $language_blogid != get_current_blog_id() ) {
							?>
							<option value="<?php echo $language_blogid; ?>"><?php $language_name;?></option>
							<?php 
						}
					}
					?>
					</select>
					<div id="multilingual_press_content"></div>
					
					<script type="text/javascript">
						//<![CDATA[
						jQuery( document ).ready( function( $ ) {
							$( '#multilingual_press' ).change( function() {

								blogid = '';
								$( '#multilingual_press option:selected' ).each( function () {
									blogid += $( this ).attr( 'value' );
								} );
								
								$.post( ajaxurl,
									{
										action: 'get_metabox_content',
										blogid: blogid,
										post: <?php echo $post->ID; ?>
									},
									function( returned_data ) {
										if ( '' != returned_data ) {
											$( '#multilingual_press_content' ).html( returned_data );
										}
									}
								);
							} );
						} );
						//]]>
					</script>
					<?php
				}
			}
		}

		/**
		 * Load the Content for the Metabox 
		 *
		 * @access  public
		 * @since   0.1
		 * @uses	switch_to_blog, get_post, get_the_title, get_post_status, admin_url, apply_filters, restore_current_blog 
		 * @return  void
		 */
		public function ajax_get_metabox_content() {

			$has_linked = FALSE;

			// Get elements linked to this item
			$linked = mlp_get_linked_elements( esc_attr( $_POST[ 'post' ] ) );

			// No elements available? Au revoir.
			if ( ! $linked )
				die( __( 'No post available', $this->get_textdomain() ) );

			// Walk through elements
			foreach ( $linked as $linked_blog => $linked_post ) {

				// Not concerning this blog?
				if ( intval( $_POST[ 'blogid' ] ) !== $linked_blog )
					continue;

				// Switch to appropriate blog to
				// have the helper functions at hand
				switch_to_blog( $linked_blog );
				
				// Get post
				$remote_post = get_post( $linked_post );

				// Create output
				if ( NULL != $remote_post ) {
					$has_linked = TRUE;
					echo '<p>' . __( 'Status:', $this->get_textdomain() ) . '&nbsp;<b>' . ucfirst( get_post_status( $linked_post ) ) . '</b>&nbsp;|&nbsp;' . __( 'Published on:', $this->get_textdomain() ) . '<b>&nbsp;' . get_post_time( get_option( 'date_format' ), FALSE, $linked_post ) . '</b></p>';
					echo '<h2>' . get_the_title( $linked_post ) . '</h2>';
					echo '<textarea class="large-text cols="80" rows="10" disabled="disabled">' . apply_filters( 'the_content', $remote_post->post_content ) . '</textarea><br />';
					echo '<p><a href="' . admin_url( 'post.php?post=' . $linked_post . '&action=edit' ) . '">' . __( 'Edit', $this->get_textdomain() ) . '</a></p>';
				}
				restore_current_blog();
			}

			// No posts available?
			if ( FALSE === $has_linked )
				die( '<p>' . __( 'No post available', $this->get_textdomain() ) . '</p>' );

			die();
		}

		/**
		 * This is the basic blog settings page.
		 * Modules can hook in their form fields here. 
		 *
		 * @access  public
		 * @since   0.1
		 * @uses	check_ajax_referer, wp_die, get_blogaddress_by_id, _e, screen_icon
		 * 			do_action, submit_button
		 * @param   $id ID of the blog
		 * @return  void
		 */
		public function draw_blog_settings_form() {

			// check nonce
			check_ajax_referer( 'mlp_tab_nonce', 'tab_nonce' );

			// Get blog id
			$current_blog_id = isset( $_REQUEST[ 'id' ] ) ? intval( $_REQUEST[ 'id' ] ) : 0;

			if ( ! $current_blog_id )
				wp_die( __( 'Invalid site ID.' ) );

			// Use a linked page title
			$site_url_no_http = preg_replace( '#^http(s)?://#', '', get_blogaddress_by_id( $current_blog_id ) );
			$title_site_url_linked = sprintf( __( 'Edit Site: <a href="%1$s">%2$s</a>' ), get_blogaddress_by_id( $current_blog_id ), $site_url_no_http );
			
			screen_icon( 'ms-admin' );
			?>
			<h2 id="edit-site"><?php echo $title_site_url_linked; ?></h2>
			
			<h3 class="nav-tab-wrapper">
				<a class="nav-tab" href="site-info.php?id=<?php echo $current_blog_id; ?>"><?php _e( 'Info' ); ?></a>
				<a class="nav-tab" href="site-users.php?id=<?php echo $current_blog_id; ?>"><?php _e( 'Users' ); ?></a>
				<a class="nav-tab" href="site-themes.php?id=<?php echo $current_blog_id; ?>"><?php _e( 'Themes' ); ?></a>
				<a class="nav-tab" href="site-settings.php?id=<?php echo $current_blog_id; ?>"><?php _e( 'Settings' ); ?></a>
				<a href="#" class="nav-tab nav-tab-active" id="mlp_settings_tab"><?php _e( 'Multilingual Press', $this->get_textdomain() ); ?></a>
			</h3>

			<form action="" method="post" id="multilingualpress_settings">
				<!-- Modules can hook their form fields here -->
				<?php do_action( 'mlp_blogs_add_fields', $current_blog_id ); ?>

				<!-- Secondary fields hook -->
				<?php do_action( 'mlp_blogs_add_fields_secondary', $current_blog_id ); ?>

				<?php submit_button(); ?>
			</form>
			<?php
			die;
		}

		/**
		 * @todo	PHP Doc
		 */
		private function parse_serialized_postdata( $data ) {

			parse_str( $data, $parsed_data );

			return $parsed_data;
		}

		/**
		 * Make available a hook 
		 * to save blog settings
		 *
		 * @access	public
		 * @since	0.1
		 * @uses	check_ajax_referer, do_action, _e
		 * @return	void
		 */
		public function update_blog_settings() {

			// check nonce
			check_ajax_referer( 'mlp_form_nonce', 'form_nonce' );

			// Modules can hook their saving routine here.
			// They will be passed the user input a
			// a function parameter
			$data = $this->parse_serialized_postdata( urldecode( $_POST[ 'serialized_data' ] ) );

			do_action( 'mlp_blogs_save_fields', $data );
			?>

			<div class="updated"><p><?php _e( 'Blog settings saved', $this->get_textdomain() ); ?></p></div>

			<?php
			die;
		}


		/**
		 * create the element links database table  
		 *
		 * @access	public
		 * @since	0.1
		 * @uses	dbDelta
		 * @global	$wpdb | WordPress Database Wrapper
		 * @return	void
		 */
		public function install_plugin() {

			global $wpdb;

			// The sql executed to create the elements table
			$sql = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->base_prefix . 'multilingual_linked (
						`ml_id` INT NOT NULL AUTO_INCREMENT ,
						`ml_source_blogid` int(11) NOT NULL,
						`ml_source_elementid` int(11) NOT NULL,
						`ml_blogid` INT NOT NULL,
						`ml_elementid` INT NOT NULL,
						`ml_type` varchar(20) CHARACTER SET utf8 NOT NULL,
						PRIMARY KEY ( `ml_id` ) , INDEX ( `ml_blogid` , `ml_elementid` )
					);';

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}

		/**
		 * Execute this function
		 * upon plugin deactivation
		 * 
		 * @since	0.1
		 * @return	type 
		 */
		public function remove_plugin() {
			
			return;
		}

		/**
		 * Returns array of modules, also
		 * saves them in the class var "loaded_modules".
		 * Scans the plugins' subfolder "/features"
		 *
		 * @access	public
		 * @since	0.1
		 * @return	array Files to include
		 */
		public function load_modules() {

			// Get dir
			$handle = opendir( dirname( __FILE__ ) . '/features' );
			if ( ! $handle )
				return;

			// Loop through directory files
			while ( FALSE !== ( $plugin = readdir( $handle ) ) ) {
				
				// Is this file for us?
				if ( '.php' == substr( $plugin, -4 ) ) {

					// Save in class var
					$this->loaded_modules[ substr( $plugin, 0, -4 ) ] = TRUE;

					// Include module file
					require_once dirname( __FILE__ ) . '/features/' . $plugin;
				}
			}
			closedir( $handle );
		}

		/**
		 * Check state of module to determine
		 * aktivation/deactivation
		 * 
		 * @access	protected
		 * @since	0.3b
		 * @param	string $module | module handler
		 * @uses	get_site_option, update_site_option, 
		 * @return	array $state | module state  
		 */
		protected function get_module_state( $module ) {

			// Get the current states
			$state = get_site_option( 'state_modules', FALSE );

			// New module?
			if ( ! ( isset( $state[ $module[ 'slug' ] ] ) ) ) {
				$module[ 'state' ] = 'on';
				$state[ $module[ 'slug' ] ] = $module;

				update_site_option( 'state_modules', $state );
				return $state[ $module[ 'slug' ] ][ 'state' ];
			}
			else if ( 'off' == $state[ $module[ 'slug' ] ][ 'state' ] ) {
				// Deaktivated module?
				return $state[ $module[ 'slug' ] ][ 'state' ];
			}
		}

		/**
		 * Delete removed blogs from site_option 'inpsyde_multilingual'
		 * and cleanup linked elements table
		 * 
		 * @param	int $blog_id 
		 * @since	0.3
		 * @uses	get_site_option, update_site_option
		 * @global	$wpdb | WordPress Database Wrapper
		 * @return	void
		 */
		public function delete_blog( $blog_id ) {

			global $wpdb;

			// Update site_option
			$blogs = get_site_option( 'inpsyde_multilingual' );
			if ( array_key_exists( $blog_id, $blogs ) )
				unset( $blogs[ $blog_id ] );
			update_site_option( 'inpsyde_multilingual', $blogs );

			// Cleanup linked elements table
			$error = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->link_table} WHERE ml_source_blogid = %d OR ml_blogid = %d", $blog_id, $blog_id ) );
		}

		/**
		 * Contextual help
		 *
		 * @since	0.5.6b
		 * @param	string $contextual_help
		 * @param	type $screen_id
		 * @param	type $screen
		 * @uses	get_current_screen, apply_filters
		 * @return	void
		 */
		public function context_help( $contextual_help, $screen_id, $screen ) {

			$screen = get_current_screen();

			$sites = array(
				'site-info-network',
				'site-users-network',
				'site-themes-network',
				'site-settings-network'
			);

			if ( in_array( $screen_id, $sites ) ) {

				$content  = '<p><strong>' . __( 'Choose blog language', $this->get_textdomain() ) . '</strong>';
				$content .= __( ' - Set the language and flag. You can use this to distinguish blogs and display them in the frontend using the Multilingual Press widget.', $this->get_textdomain() ) . '</p>';
				$content .= '<p><strong>' . __( 'Alternative language title', $this->get_textdomain() ) . '</strong>';
				$content .= __( ' - Alternative language title will be used mainly in the Multilingual Press frontend widget. You can use it to determine a site title other than the default one (i.e. "My Swedish Site")', $this->get_textdomain() ) . '</p>';
				$content .= '<p><strong>' . __( 'Blog flag image URL', $this->get_textdomain() ) . '</strong>';
				$content .= __( ' - Multilingual Press uses a default flag image, you can define a custom one here.', $this->get_textdomain() ) . '</p>';
				$content .= '<p><strong>' . __( 'Multilingual Blog Relationship', $this->get_textdomain() ) . '</strong>';
				$content .= __( ' - Determine which blogs will be interlinked. If you create a post or page, they will be automaticaly duplicated into each interlinked blog.', $this->get_textdomain() ) . '</p>';

				$content = apply_filters( 'mlp-context-help', $content );

				$screen->add_help_tab( array(
					'id'		=> 'multilingualpress-help',
					'title'		=> __( 'Multilingual Press', $this->get_textdomain() ),
					'content'	=> $content
				) );
			}
		}

		/**
		 * Display an Admin Notice if multisite is not active 
		 * 
		 * @since	0.7.5a
		 * @uses	_e
		 * @return	void
		*/
		public function error_msg_no_multisite() {
			?>
			<div class="error">
				<p>
					<?php _e( 'Multilingual Press only works in a multisite installation. See how to install a multisite network:', $this->get_textdomain() ); ?>
					<a href="http://codex.wordpress.org/Create_A_Network" title="<?php _e( 'WordPress Codex: Create a network', $this->get_textdomain() ); ?>"><?php _e( 'WordPress Codex: Create a network', $this->get_textdomain() ); ?></a>
				</p>
			</div><?php
		}
	}
}
?>