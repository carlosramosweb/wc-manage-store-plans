<?php
/*---------------------------------------------------------
Plugin Name: WC Manage Store Plans for WooCommerce 
Plugin URI: https://profiles.wordpress.org/carlosramosweb/#content-plugins
Author: carlosramosweb
Author URI: https://criacaocriativa.com
Donate link: https://donate.criacaocriativa.com
Description: This plugin is a BETA version. Developed to manage exclusive virtual store plans using the WordPress CMS together with the WooCommerce plugin. WordPress: https://br.wordpress.org/ - WooCommerce: https://woocommerce.com/
Text Domain: wc-manage-store-plans
Domain Path: /languages/
Version: 3.6.2
Requires at least: 3.5.0
Tested up to: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html 
Package: WooCommerce
------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Manage_Store_Plans' ) ) {		
	class WC_Manage_Store_Plans {

		public function __construct() {	
			register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
			register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) ); 
			add_action( 'init', array( $this, 'load_plugin_textdomain' ), 10, 2 );
			if ( is_admin() ) {
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links_settings' ) );
				add_action( 'admin_menu', array( $this, 'register_wc_manage_store_plans_menu_page' ), 10, 2 );
				add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu_monthly_traffic' ), 1000 );
				add_action( 'admin_head', array( $this, 'admin_bar_menu_styles' ) );
				add_action( 'init', array( $this, 'check_product_limit' ), 10, 2 );
			} else {
				add_action( 'init', array( $this, 'check_monthly_traffic' ), 10, 2 );
				add_action( 'init', array( $this, 'check_limit_registered_products' ), 10, 2 );
				add_action( 'init', array( $this, 'set_count_monthly_traffic' ), 10, 2 );
				add_action( 'init', array( $this, 'check_date_store_plans' ), 10, 2 );
			}
		}

		public static function deactivate_plugin() {
			delete_option( 'wc_manage_store_plans_settings' );
		}

		public static function load_plugin_textdomain(){
			load_plugin_textdomain( 'wc-manage-store-plans', false, dirname( plugin_basename(__FILE__) ) . '/languages' );
		}

		public function activate_plugin() {	  
			if ( is_admin() && get_option( 'Activated_Plugin' ) == 'wc-manage-store-plans' ) {
				update_option( 'Activated_Plugin', 'wc-manage-store-plans', 'yes' );
			} else {
				add_option( 'Activated_Plugin', 'wc-manage-store-plans', '', 'yes' );	
			}
			add_action( 'init', array( $this, 'permission_folder_uploads_logo' ), 10, 2 );
			$wc_settings 	= get_option( 'wc_manage_store_plans_settings' );
			$admin_user 	= get_users( array( 'role__in' => array( 'administrator' ) ) );		
			$admin_email 	= get_option( 'admin_email' );	
			$plans_date 	= date( 'Y-m-d', strtotime( "+3 months" ) );

			$product_delete 		= isset( $wc_settings['product_delete'] ) ? $wc_settings['product_delete'] : "no";
			$product_limit 			= isset( $wc_settings['product_limit'] ) ? $wc_settings['product_limit'] : 1000;
			$monthly_traffic 		= isset( $wc_settings['monthly_traffic'] ) ? $wc_settings['monthly_traffic'] : 20000;
			$user_permissions 		= isset( $wc_settings['user_permissions'] ) ? $wc_settings['user_permissions'] : 'administrator';
			$super_admin_email 		= isset( $wc_settings['super_admin_email'] ) ? $wc_settings['super_admin_email'] : $admin_email;
			$plans_settings_date 	= isset( $wc_settings['plans_settings_date'] ) ? $wc_settings['plans_settings_date'] : $plans_date;
			$super_admin_logo 		= isset( $wc_settings['super_admin_logo'] ) ? $wc_settings['super_admin_logo'] : '';
			$logo_default 			= esc_url( plugin_dir_url( __FILE__ ) . 'images/logohost.jpg' );

			$settings = array(
				'enabled'				=> "yes",
				'product_delete'		=> $product_delete,
				'product_limit'			=> $product_limit,
				'monthly_traffic'		=> $monthly_traffic,
				'user_permissions'		=> $user_permissions,
				'super_admin_email'		=> $super_admin_email,
				'plans_settings_date'	=> $plans_settings_date,
				'super_admin_logo'		=> $super_admin_logo,
				'logo_default'			=> $logo_default,
			);
			update_option( 'wc_manage_store_plans_settings', $settings, 'yes' );
		}

		public function admin_bar_menu_styles() { 
			$wc_settings = get_option( 'wc_manage_store_plans_settings' ); 
			if ( $wc_settings['enabled'] == 'yes' ) { ?>
			<style type="text/css">
			#wpadminbar #wp-admin-bar-monthly-traffic .mt-icon {
				position: relative;
				float: left;
				font: normal 20px/1 dashicons !important;
				speak: never;
				padding: 2px 0px;
				-webkit-font-smoothing: antialiased;
				-moz-osx-font-smoothing: grayscale;
				background-image: none !important;
				margin-right: 6px;
				top: 4px;
			}
			#wpadminbar #wp-admin-bar-monthly-traffic .mt-icon::before {
				content: "\f238";
			}
			</style>
			<?php
			}
		}

		public function admin_bar_menu_monthly_traffic( $wp_admin_bar ) {
			global $wp_admin_bar, $wpdb;
			$wc_settings = get_option( 'wc_manage_store_plans_settings' );
			if ( $wc_settings['enabled'] == 'yes' ) { 
				if ( ! current_user_can( 'manage_options' ) OR ! current_user_can( 'manage_woocommerce' ) ) {
					return;
				}
				$data_option = date( "Y_m" ); // 2020_12 ano_mês
				$monthly_traffic = get_option( 'count_monthly_traffic_' . $data_option );
				$wp_admin_bar->add_menu( array(
					'id'    	=> 'monthly-traffic',
					'parent' 	=> null,
					'group'  	=> null,			    
					'title' 	=> '<span class="mt-icon"></span><span class="ab-label">' . __( 'Total for this month:', 'wc-manage-store-plans' ) . '<strong>' . $monthly_traffic["count_monthly"] . __( 'views', 'wc-manage-store-plans' ) . '</strong></span>',
					'href'  	=> '#',
					'meta' 		=> [
						'class' 	=> 'menupop',
					]
				) );
			}
		}

		public function check_limit_registered_products() {
			$wc_settings = get_option( 'wc_manage_store_plans_settings' ); 
			if ( $wc_settings['enabled'] == 'yes' ) { 
				global $post, $pagenow;
				if( ! is_admin() || is_admin() && $pagenow == "edit.php" && $pagenow == "post-new.php" ) {
					$product_limit 		= $wc_settings['product_limit'];
					$product_delete 	= $wc_settings['product_delete'];
					$product_publish 	= wp_count_posts( $post_type = 'product' )->publish;
					$product_private 	= wp_count_posts( $post_type = 'product' )->private;
					$product_future 	= wp_count_posts( $post_type = 'product' )->future;
					$product_notice 	= false;
					$args = array(
						'numberposts' => -1,
						'post_type'   => 'product',
						'post_status' => array( 'publish', 'future', 'private' ),
						'orderby'     => 'date',
						'order'       => 'DESC',
					);				 
					$products = get_posts( $args );
					if ( empty( $product_limit ) ) {
						$product_limit = 25;
					}
					if ( $products && $product_limit > 0 ) {	
						$count_products = ( $product_publish + $product_private + $product_future );
						$counts = count( $products );		
						foreach ( $products as $product ) { 
							if ( $count_products > $product_limit && $product_delete == 'yes' ) {
								wp_delete_post( $product->ID, true );
								$count_products--;
								$product_notice = true;
							}
						}
						if ( $product_notice ) {
							add_action( 'admin_notices', array( $this, 'limit_registered_products_admin_notice__error' ) );
							$this->send_limit_registered_products_admin_notice();
						}
					}
				}
			}
		}

		public function send_limit_registered_products_admin_notice() {
			$admin_email 	= get_option( 'admin_email' );
			$blogname 		= get_option( 'blogname' );
			$shop_users 	= get_users( array( 'role__in' => array( 'shop_manager' ) ) );
			$shop_manager 	= '';
			foreach ( $shop_users as $shop_user ) {
				$shop_manager .= esc_html( $shop_user->user_email ) . ',';
			}
			$site_url 	= get_site_url();
			$btn_admin 	= admin_url( 'edit.php?post_type=product', 'https' );
			$to 		= $shop_manager;
			$subject 	= __( 'Alert: Product registration limits reached!', 'wc-manage-store-plans' );
			$body 		= __( '<p>Hello, Store Manager.<br/> The limit of registered products has been reached and with that our system has erased the excess products!<br/><br/>', 'wc-manage-store-plans' );
			$body 		.= '<a href="' . esc_url( $btn_admin ) . '" target="_blank" rel="noopener noreferrer" style="display: inline-block; background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; background-color: rgb(0, 105, 153) !important; color: white; font-family: Arial; font-size: 13px; font-weight: normal; line-height: 120%; margin: 0px; text-transform: none; padding: 10px 25px; border-radius: 3px; text-decoration: none;" data-linkindex="0" data-ogsb="rgb(67, 171, 224)"><b>' . __( 'Product Panel', 'wc-manage-store-plans' ) . '</b></a>';
			$body 		.= '<br/><br/>' . esc_html( $blogname ) . '<br/> <a href="' . esc_url( $site_url ) . '" target="_blank">' . esc_html( $site_url ) . '</a></p>';
			$headers = array(
			    "From: $blogname <$admin_email>", 
			    "Cc: $admin_email", 
			    'Content-Type: text/html; charset=UTF-8',
			);
			wp_mail( $to, $subject, $body, $headers );
		}

		public function send_notice_shop_manager_date_store_plans() {

			$admin_email 	= get_option( 'admin_email' );
			$blogname 		= get_option( 'blogname' );
			$site_url 		= get_site_url();
			$btn_admin 	= admin_url( 'edit.php?post_type=product', 'https' );
			$shop_users 	= get_users( array( 'role__in' => array( 'shop_manager' ) ) );

			$shop_manager 	= '';
			foreach ( $shop_users as $shop_user ) {
				$shop_manager .= esc_html( $shop_user->user_email ) . ',';
			}

			$to 		= $shop_manager;
			$subject 	= __( 'Alert: The deadline for payment for your online store plan has expired.', 'wc-manage-store-plans' );
			$body 		= __( '<p>Hello, Shop Manager.<br/>', 'wc-manage-store-plans' );
			$body 		.= $blogname;
			$body 		.= __( ' your store has expired the payment date set in the WooCommerce plan management plugin.<br/>', 'wc-manage-store-plans' );
			$body 		.= __( 'Virtual store plan ', 'wc-manage-store-plans' );
			$body 		.= $site_url . '.<br/><br/>';
			$body 		.= '<a href="' . esc_url( $btn_admin ) . '" target="_blank" rel="noopener noreferrer" style="display: inline-block; background-image: initial; background-position: initial; background-size: initial; background-repeat: initial; background-attachment: initial; background-origin: initial; background-clip: initial; background-color: rgb(0, 105, 153) !important; color: white; font-family: Arial; font-size: 13px; font-weight: normal; line-height: 120%; margin: 0px; text-transform: none; padding: 10px 25px; border-radius: 3px; text-decoration: none;" data-linkindex="0" data-ogsb="rgb(67, 171, 224)"><b>' . __( 'Product Panel', 'wc-manage-store-plans' ) . '</b></a>';
			$body 		.= '<br/><br/>' . esc_html( $blogname ) . '<br/> <a href="' . esc_url( $site_url ) . '" target="_blank">' . esc_html( $site_url ) . '</a></p>';
			$headers = array(
			    "From: $blogname <$admin_email>", 
			    "Cc: $admin_email", 
			    'Content-Type: text/html; charset=UTF-8',
			);
			wp_mail( $to, $subject, $body, $headers );
		}

		public function limit_registered_products_admin_notice__error() {
			$class = 'notice notice-error';
			$message = __( '<strong>Important Alert:</strong> Limits of registered products have been reached and we have deleted the excess!', 'wc-manage-store-plans' );		 
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
		}

		public function check_product_limit() {
			$wc_settings = get_option( 'wc_manage_store_plans_settings' ); 
			if ( $wc_settings['enabled'] == 'yes' ) { 
				$product_limit = $wc_settings['product_limit'];
				if( empty( $product_limit ) ) {
					$product_limit = '25';
				}
				if( wp_count_posts( $post_type = 'product' )->publish >= intval( $product_limit ) ) {
					global $pagenow;
					if ( $pagenow == "post-new.php" && $_GET['post_type'] == "product" ) { 
						add_action( 'edit_form_top', array( $this, 'blocking_page_new_product' ) );
					}
				}
			}
		}

		public function get_days_monthly() {
			$day_end = date( "t", mktime( 0, 0, 0, date( "m" ), '01', date( "Y" ) ) );
			$days = array(
				'day_start' => "01",
				'day_end' 	=> $day_end,
			);
			return $days;
		}

		public function check_visite_ip_day() {
			//unset( $_COOKIE['set_visite_ip_day'] );
			if( isset( $_COOKIE['set_visite_ip_day'] ) && ! empty( $_COOKIE['set_visite_ip_day'] ) ) {
				return true;
			} else {
				$visite_ip_day = str_replace( ".", "", $_SERVER["REMOTE_ADDR"] );
				setcookie( "set_visite_ip_day", intval( $visite_ip_day ), time() + ( 1 * 24 ) );
				return false;
			}
		}

		public function set_count_monthly_traffic() {
			$wc_settings = get_option( 'wc_manage_store_plans_settings' ); 
			if ( $wc_settings['enabled'] == 'yes' ) { 
				if ( ! is_admin() && $wc_settings['enabled'] == 'yes' ) {
					$data_option = date( "Y_m" ); // 2020_12 ano_mês
					$monthly_traffic = get_option( 'count_monthly_traffic_' . $data_option );
					$days = $this->get_days_monthly();
					if ( $monthly_traffic == "" ) {
						$monthly_traffic['date_start'] = date( "Y-m-" . $days['day_start'] . "" );
						$monthly_traffic['date_end'] = date( "Y-m-" . $days['day_end'] . "" );
						$monthly_traffic['count_monthly'] = 1;
						update_option( 'count_monthly_traffic_' . $data_option, $monthly_traffic, 'yes' );
					} else {
						if ( ! $this->check_visite_ip_day() ) {
							$monthly_traffic['count_monthly'] = intval( $monthly_traffic['count_monthly'] ) + 1;
							update_option( 'count_monthly_traffic_' . $data_option, $monthly_traffic, 'yes' );
						}
					}
				}
			}
		}	

		public static function notice_date_store_plans_admin() { ?>
			<section style="max-width: 100%; margin: 10px 20px; padding: 10px 20px; text-align: center;">
				<div class="woocommerce">
					<ul class="woocommerce-error" style="margin: 0;">
						<li style="margin: 0; padding: 0;">
							<?php echo __( 'Dear administrator, <strong>This store has late payment for the plan.</strong>', 'wc-manage-store-plans' ); ?>
						</li>
					</ul>
				</div>
			</section>
			<?php
		}

		public static function notice_date_store_plans() { ?>
			<section style="max-width: 100%; margin: 10px 20px; padding: 10px 20px; text-align: center;">
				<div class="woocommerce">
					<ul class="woocommerce-error" style="margin: 0;">
						<li style="margin: 0; padding: 0;">
							<?php echo __( 'Dear customer, we appreciate our partnership, thats why you are receiving this notice of the expiration date of your LojaWeb App subscription plan. Regularize your subscription so that your store is not blocked.</strong>', 'wc-manage-store-plans' ); ?>
						</li>
					</ul>
				</div>
			</section>
			<?php
		}

		public static function notice_monthly_traffic() { 
			$wc_settings = get_option( 'wc_manage_store_plans_settings' ); 
			if ( $wc_settings['enabled'] == 'yes' ) { 
					$file_url_upload 	= plugin_dir_url( __FILE__ ) . 'images/uploads/';
					$super_admin_logo 	= $wc_settings['super_admin_logo'];
					if ( ! wp_http_validate_url( $super_admin_logo ) && empty( $super_admin_logo ) ) {	
						$super_admin_logo 	= esc_url( $wc_settings['logo_default'] );
					} else {
						$super_admin_logo 	= esc_url( $file_url_upload . $super_admin_logo );
					}
				?>
				<!DOCTYPE html>
				<html class="wp-toolbar" lang="pt-BR">
				<head>
					<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
					<meta name="viewport" content="width=device-width, initial-scale=1">
					<title>
						<?php echo __( 'Plans Shop for WooCommerce', 'wc-manage-store-plans' ); ?>
					</title>
					<style type="text/css">
					body {
						background: #f1f1f1;
						color: #444;
						font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
						font-size: 13px;
						line-height: 1.4em;
						min-width: 600px;
						display: block;
						margin: 8px;
					}
					body, html {
						height: 100%;
						margin: 0;
						padding: 0;
					}
					h1 {
						color: #23282d;
						font-size: 2em;
						margin: .67em 0;
						display: block;
						font-weight: 600;
					}
					.notice-error, div.error {
						border-left-color: red;
					}
					.notice, div.error, div.updated {
						background: #fff;
						border: 1px solid #ccd0d4;
						border-left-width: 4px;
						box-shadow: 0 1px 1px rgba(0,0,0,.04);
						margin: 5px 15px 2px;
						padding: 1px 12px;
					}
					.notice p {
						font-size: 13px;
						line-height: 1.5;
						margin: 1em 0;
						display: block;
						margin-block-start: 1em;
						margin-block-end: 1em;
						margin-inline-start: 0px;
						margin-inline-end: 0px;
					}
					.notice p a {
						outline: 0;
						color: #0073aa;
						transition-property: border,background,color;
						transition-duration: .05s;
						transition-timing-function: ease-in-out;
						color: -webkit-link;
						cursor: pointer;
						text-decoration: underline;
					}
					@media screen and (min-width: 800px) {
						section {
							max-width: 100% !important;
						}
					}
				</style>
				</head>
				<body>
					<section style="max-width: 60%; margin: 0 auto; padding: 40px 20px; text-align: center;">
						<img src="<?php echo $super_admin_logo; ?>" class="" style="width: 100%; max-width:180px; display: block; margin: 0 auto; padding:0;">
						<h1>
							<?php echo __( 'Alert to the Manager of this Store', 'wc-manage-store-plans' ); ?>
						</h1>
						<div class="notice notice-error">
							<p>
								<?php 
								$super_admin_email = $wc_settings['super_admin_email'];
								echo __( "Dear customer, <strong>you have reached the maximum number of views per month</strong> in your online store. Contact our support: ", 'wc-manage-store-plans' );
								echo " <a href='mailto:" . $super_admin_email . "'>" . $super_admin_email . "</a>";
								?>						
							</p>
						</div>
					</section>
				</body>
				</html>
				<?php
			}
		}

		public static function notice_monthly_traffic_admin() { 
			$wc_settings = get_option( 'wc_manage_store_plans_settings' );
			if ( $wc_settings['enabled'] == 'yes' ) { ?>
			<section style="max-width: 100%; margin: 10px 20px; padding: 10px 20px; text-align: center;">
				<div class="woocommerce">
					<ul class="woocommerce-error" style="margin: 0;">
						<li style="margin: 0; padding: 0;">
							<?php echo __( 'Dear administrator, <strong>this store has reached its maximum views per month.</strong>', 'wc-manage-store-plans' ); ?>
						</li>
					</ul>
				</div>
			</section>
			<?php
			}
		}

		public function notice_product_limit() { 
			$wc_settings = get_option( 'wc_manage_store_plans_settings' ); 
			if ( $wc_settings['enabled'] == 'yes' ) { ?>
				<div class="notice notice-error is-dismissible">
					<p>
						<?php 
						$super_admin_email = $wc_settings['super_admin_email'];
						echo __( '<strong>Dear customer, you have reached the maximum limit for registering products. Contact our support: </strong>', 'wc-manage-store-plans' );
						echo "<a href='mailto:" . $super_admin_email . "'>" . $super_admin_email . "</a>";
						?>	
					</p>
					<button type="button" class="notice-dismiss">
						<span class="screen-reader-text">
							<?php echo __( 'Close', 'wc-manage-store-plans' ); ?>
						</span>
					</button>
				</div>
				<hr/>
				<br/>
				<a href="<?php echo admin_url( 'edit.php?post_type=product', 'https' ); ?>" class="button page-title-action button-large">
					<?php echo __( 'Return to Product List', 'wc-manage-store-plans' ); ?>
				</a>
				<?php
			}
		}

		public function blocking_page_new_product() {
			$wc_settings = get_option( 'wc_manage_store_plans_settings' ); 
			if ( ! $this->get_current_user_administrator() && $wc_settings['enabled'] == 'yes' ) {
				$this->notice_product_limit();
				//die();
			} else {
				$this->notice_product_limit();
			}
		}

		public function blocking_date_store_plans() {
			if ( ! in_array( $GLOBALS[ 'pagenow' ], array( 'wp-login.php' ) ) && 
				! $this->get_current_user_administrator() ) {
				$this->notice_date_store_plans();
				//die();
			} else {
				$this->notice_date_store_plans_admin();
			}
		}

		public function blocking_monthly_traffic() {
			if ( ! in_array( $GLOBALS[ 'pagenow' ], array( 'wp-login.php' ) ) && 
				! $this->get_current_user_administrator() ) {
				$this->notice_monthly_traffic();
				//die();
			} else {
				$this->notice_monthly_traffic_admin();
			}
		}

		public function check_date_store_plans() {
			$wc_settings = get_option( 'wc_manage_store_plans_settings' );
			if ( $wc_settings['enabled'] == 'yes' ) { 				
				$plans_date 	= date( 'Y-m-d', strtotime( $wc_settings['plans_settings_date'] ) );
				$date_block 	= date( 'Y-m-d', strtotime( $wc_settings['plans_settings_date'] . ' +7 days' ) );
				$blocking_date 	= get_option( 'blocking_date_store_plans_' . $plans_date );
				if( isset( $plans_date ) && date( "Y-m-d" ) > $plans_date ) {
					$this->blocking_date_store_plans();
					if(  date( "Y-m-d" ) > $date_block && ! empty( $blocking_date ) ) {
						$this->send_notice_shop_manager_date_store_plans();
						update_option( 'blocking_date_store_plans_' . $plans_date, 'send_notice_shop_manager', 'yes' );
					}
				} 
			}
		}

		public function check_monthly_traffic() {
			$wc_settings = get_option( 'wc_manage_store_plans_settings' ); 
			if ( $wc_settings['enabled'] == 'yes' ) { 
				$data_option = date( "Y_m" );
				$monthly_traffic = get_option( 'count_monthly_traffic_' . $data_option );
				$wc_settings = get_option( 'wc_manage_store_plans_settings' );
				if( $monthly_traffic['count_monthly'] > $wc_settings['monthly_traffic'] ) {
					$this->blocking_monthly_traffic();
				}
			}
		}

		public function get_current_user_administrator() {
			$wc_settings = get_option( 'wc_manage_store_plans_settings' );	
			if ( isset( $wc_settings['user_permissions'] ) ) {
				$user = wp_get_current_user();
				if ( in_array( 'administrator', $user->roles, true ) ) {
					return true;				
				} else {
					return false;
				}
			}
		}

		public function register_wc_manage_store_plans_menu_page() {
			if( $this->get_current_user_administrator() ) {
				add_menu_page(
					__( 'Plans Shop for WooCommerce', 'wc-manage-store-plans' ),
					__( 'WooCommerce Plans', 'wc-manage-store-plans' ),
					'manage_options',
					'wc-manage-store-plans',
					array( $this, 'wc_manage_store_plans_page_admin_callback' ),
					'dashicons-privacy',
					20
				);
			}
		}

		public function plugin_action_links_settings( $links ) {
			$action_links = [];
			if( $this->get_current_user_administrator() ) {
				$action_links = array(
					'settings' => '<a href="' . admin_url( 'admin.php?page=wc-manage-store-plans' ) . '" title="Configuracões" class="edit">' . __( 'Settings', 'wc-manage-store-plans' ) . '</a>',
					'donate'   => '<a href="' . esc_url( 'https://donate.criacaocriativa.com') . '" title="'. __( 'Plugin Donation', 'wc-manage-store-plans' ) .'" class="error" target="_blank">'. __( 'Donation', 'wc-manage-store-plans' ) .'</a>',
				);


			}
			return array_merge( $action_links, $links );
		}

		public function select_option_user_admin() {
			$wc_settings = get_option( 'wc_manage_store_plans_settings' );	
			$wp_roles = wp_roles();
			foreach ( $wp_roles->roles as $role ) {
				$selected = "";
				if ( strtolower( $role['name'] ) == strtolower( $wc_settings['user_permissions'] ) ) {
					$selected = 'selected="" ';
				}
				echo '<option value="' . esc_html( $role['name'] ) . '" ' . $selected . '>' . esc_html( $role['name'] ) . '</option>';
			}
		}

		public function permission_folder_uploads_logo() {
			$uploads_path 	= plugin_dir_path( __FILE__ ) . 'images/uploads/';
			$uploads_url 	= plugin_dir_url( __FILE__ ) . 'images/uploads/';
			if ( ! chmod( $uploads_path, 0777 ) ) {
				$old = umask( 0 );
				chmod( $uploads_path, 0777 );
				umask( $old );
			}
		}

		public function delete_file_upload_logo() {
			$uploads_path 	= plugin_dir_path( __FILE__ ) . 'images/uploads';
	        $files = array_diff( scandir( $uploads_path ), array( '.', '..' ) ); 
	        foreach ( $files as $file ) { 
	            ( is_dir( "$uploads_path/$file" ) ) ? delTree( "$uploads_path/$file" ) : unlink( "$uploads_path/$file" ); 
	        }     
	        return true; 
		}

		public function handle_file_upload_logo( $file_upload ) {		
			if ( empty( $file_upload ) ) {
		        return false;
		    } else {
				$home_path 			= get_home_path();
				$home_url 			= get_home_url();
				$file_path_upload 	= plugin_dir_path( __FILE__ ) . 'images/uploads/';
				$file_url_upload 	= plugin_dir_url( __FILE__ ) . 'images/uploads/';

				$file_tmp 			= file_get_contents( $file_upload['tmp_name'] );
				$file_name 			= $file_upload['name'];
				$file_path_item 	= $file_path_upload . $file_name;
				$file_url_item 		= $file_url_upload . $file_name;

				$file_put_upload 	= false;
				if ( $this->delete_file_upload_logo() ) {
					$file_put_upload = file_put_contents( $file_path_item, $file_tmp );
				}
			    if ( $file_put_upload ) {
			    	$wc_store_settings 						= get_option( 'wc_manage_store_plans_settings' );
					$wc_store_settings['super_admin_logo'] 	= $file_name;
					update_option( 'wc_manage_store_plans_settings', $wc_store_settings );	
			        return true;
			    } else {
			        return false;
			    }	
		    }
		}

		//Page Admin
		public function wc_manage_store_plans_page_admin_callback() {
			global $wpdb;
			$message 			= "";
			$wc_store_settings 	= get_option( 'wc_manage_store_plans_settings' );

			if( isset( $_GET['_update_delete'] ) && isset( $_GET['_wpnonce_delete'] ) ) {
				if ( wp_verify_nonce( $_GET['_wpnonce_delete'], "wc-manage-store-plans-delete" ) ) {
					if( $this->delete_file_upload_logo() ) { 
						$wc_store_settings['super_admin_logo'] 	= '';
						update_option( 'wc_manage_store_plans_settings', $wc_store_settings );	
						$message = "updated";
					} else {
						$message = "error";	
					}
				}
			}

			if( isset( $_POST['_update_upload'] ) && isset( $_POST['_wpnonce_upload'] ) ) {
				$_wpnonce_upload = sanitize_text_field( $_POST['_wpnonce_upload'] );
				if ( wp_verify_nonce( $_wpnonce_upload, "wc-manage-store-plans-upload" ) ) {
					$file_upload = $this->handle_file_upload_logo( $_FILES['file_upload_logo'] );
					if( $file_upload ) { 
						$message = "updated";
					} else {
						$message = "error";	
					}
				}
			}

			if( isset( $_POST['_update'] ) && isset( $_POST['_wpnonce'] ) ) {
				$_update 	= sanitize_text_field( $_POST['_update'] );
				$_wpnonce 	= sanitize_text_field( $_POST['_wpnonce'] );

				if( isset( $_wpnonce ) && isset( $_update ) ) {
					if ( ! wp_verify_nonce( $_wpnonce, "wc-manage-store-plans" ) ) {
						$message = "error";		
					} else {
						if( ! isset( $_GET['tab'] ) ) {
							$wc_store_settings['enabled'] = isset( $_POST['enabled'] ) ? $_POST['enabled'] : '';
							if ( isset( $_POST['plans_settings_date'] ) ) {
								if ( ! empty( $_POST['plans_settings_date'] ) ) {
									$wc_store_settings['plans_settings_date'] = $_POST['plans_settings_date'];
								} else {
									$plans_date = date( 'Y-m-d', strtotime( "+6 months" ) );
									$wc_store_settings['plans_settings_date'] = $plans_date;
								}
							}
						}
						if( isset( $_GET['tab'] ) && $_GET['tab'] == 'product-limit' ) {
							$wc_store_settings['product_delete'] = isset( $_POST['product_delete'] ) ? $_POST['product_delete'] : 'no';
							if ( isset( $_POST['product_limit'] ) ) {
								$wc_store_settings['product_limit'] = isset( $_POST['product_limit'] ) ? $_POST['product_limit'] : '1000';
							}
						}
						if( isset( $_GET['tab'] ) && $_GET['tab'] == 'monthly-traffic' ) {
							if ( isset( $_POST['monthly_traffic'] ) ) {
								$wc_store_settings['monthly_traffic'] = isset( $_POST['monthly_traffic'] ) ? $_POST['monthly_traffic'] : '20000';
							}
						}
						if( isset( $_GET['tab'] ) && $_GET['tab'] == 'user-permissions' ) {
							if ( isset( $_POST['user_permissions'] ) ) {
								$wc_store_settings['user_permissions'] = isset( $_POST['user_permissions'] ) ? $_POST['user_permissions'] : 'administrator';
							}
							if ( isset( $_POST['super_admin_email'] ) ) {
								$wc_store_settings['super_admin_email'] = isset( $_POST['super_admin_email'] ) ? $_POST['super_admin_email'] : 'contato@sitedoadministrador.com.br';
							}
						}

						update_option( 'wc_manage_store_plans_settings', $wc_store_settings );					
						$message = "updated";
					}
				}
			}

			$file_url_upload 		= plugin_dir_url( __FILE__ ) . 'images/uploads/';
			$wc_settings 			= get_option( 'wc_manage_store_plans_settings' );

			$enabled 				= esc_attr( $wc_settings['enabled'] );
			$product_delete 		= esc_attr( $wc_settings['product_delete'] );
			$product_limit 			= esc_attr( $wc_settings['product_limit'] );
			$monthly_traffic 		= esc_attr( $wc_settings['monthly_traffic'] );		
			$user_permissions 		= esc_attr( $wc_settings['user_permissions'] );
			$super_admin_email 		= esc_attr( $wc_settings['super_admin_email'] );
			$plans_settings_date 	= esc_attr( $wc_settings['plans_settings_date'] );
			$super_admin_logo 		= $wc_settings['super_admin_logo'];
			$logo_default 			= esc_url( $wc_settings['logo_default'] );

			$data_option 			= date( "Y_m" );
			$count_monthly_traffic 	= get_option( 'count_monthly_traffic_' . $data_option );

			if ( ! wp_http_validate_url( $super_admin_logo ) && empty( $super_admin_logo ) ) {			
				$super_admin_logo = $logo_default;
			} else {
				$super_admin_logo 	= esc_url( $file_url_upload . $super_admin_logo );
			}
			?>

			<div id="wpwrap">
				<h1>
					<?php echo __( 'WooCommerce Plans', 'wc-manage-store-plans' ); ?>
				</h1>
				<p>
					<?php echo __( 'Below you can configure the plugin by filling in the plan data for that store.', 'wc-manage-store-plans' ); ?>
				<p/>	

					<?php if( isset( $message ) ) { ?>
						<div class="wrap">    
							<?php if( $message == "updated" ) { ?>
								<div id="message" class="updated notice is-dismissible" style="margin-left: 0px;">
									<p><?php echo __( 'Success! Data were updates successfully!', 'wc-manage-store-plans' ); ?></p>
									<button type="button" class="notice-dismiss">
										<span class="screen-reader-text">
											<?php echo __( 'Dismiss this notice.', 'wc-manage-store-plans' ); ?>
										</span>
									</button>
								</div>
							<?php } ?>    
							<?php if( $message == "error" ) { ?>
								<div id="message" class="updated error is-dismissible" style="margin-left: 0px;">
									<p><?php echo __( 'Error! We are unable to make the updates!', 'wc-manage-store-plans' ); ?></p>
									<button type="button" class="notice-dismiss">
										<span class="screen-reader-text">
											<?php echo __( 'Dismiss this notice.', 'wc-manage-store-plans' ); ?>
										</span>
									</button>
								</div>
							<?php } ?>
						</div>
					<?php } ?>

					<div class="wrap woocommerce">
						<?php
						if( isset( $_GET['tab'] ) ) {
							$tab = esc_attr( $_GET['tab'] );
						}
						?>
						<nav class="nav-tab-wrapper wc-nav-tab-wrapper">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-manage-store-plans' ) ); ?>" class="nav-tab <?php if( $tab == "" ) { echo "nav-tab-active"; }; ?>">
								<?php echo __( 'Plans Settings', 'wc-manage-store-plans' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-manage-store-plans&tab=super-admin-logo' ) ); ?>" class="nav-tab <?php if( $tab == "super-admin-logo" ) { echo "nav-tab-active"; }; ?>">
								<?php echo __( 'Super Admin Logo', 'wc-manage-store-plans' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-manage-store-plans&tab=product-limit' ) ); ?>" class="nav-tab <?php if( $tab == "product-limit" ) { echo "nav-tab-active"; }; ?>">
								<?php echo __( 'Product Limit', 'wc-manage-store-plans' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-manage-store-plans&tab=monthly-traffic' ) ); ?>" class="nav-tab <?php if( $tab == "monthly-traffic" ) { echo "nav-tab-active"; }; ?>">
								<?php echo __( 'Monthly Traffic', 'wc-manage-store-plans' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-manage-store-plans&tab=user-permissions' ) ); ?>" class="nav-tab <?php if( $tab == "user-permissions" ) { echo "nav-tab-active"; }; ?>">
								<?php echo __( 'User Permissions', 'wc-manage-store-plans' ); ?>
							</a>
						</nav>

						<?php if( ! isset( $tab ) ) { ?>
							<!--form-->
							<form method="POST" id="mainform" name="mainform">
								<!---->
								<table class="form-table">
									<tbody>
				                        <!---->
				                        <tr valign="top">
				                            <th scope="row">
				                                <label>
				                                    <?php echo __( 'Enable', 'wc-manage-store-plans' ); ?>:
				                                </label>
				                            </th>
				                            <td>
				                                <label>
				                                    <input type="checkbox" name="enabled" value="yes" <?php if( $enabled == "yes" ) { echo 'checked="checked"'; } ?> class="form-control">
				                                    <?php echo __( 'Activate plugin', 'wc-manage-store-plans' ) ; ?>
				                                </label>
				                           </td>
				                        </tr>
				                        <!---->
										<tr valign="top">
											<th scope="row">
												<label>
													<?php echo __( 'Expiration of the plan', 'wc-manage-store-plans' ); ?>:
												</label>
											</th>
											<td>
												<label>
													<input type="date" required min="10" max="10" name="plans_settings_date" value="<?php echo $plans_settings_date; ?>"  style=" min-width:100px; width:auto;">
													<i>
														<?php echo __( '<strong>Note:</strong> The system will notify the administrator when the date expires.', 'wc-manage-store-plans' ); ?>
													</i>
												</label>
											</td>
										</tr>
										<!---->
									</tbody>
								</table>
								<!---->
								<hr/>
								<div class="submit">
									<button class="button-primary" type="submit">
										<?php echo __( 'Save Editions', 'wc-manage-store-plans' ); ?>
									</button>
									<input type="hidden" name="_update" value="yes">
									<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'wc-manage-store-plans' ) ); ?>">
								</div>
								<!---->  
							</form>
							<!---->
							<?php } else if ( $tab == "super-admin-logo" ) { ?>
								<style type="text/css">
									.button-delete-logo {
										position: absolute; 
										border-radius: 50% !important; 
										background-color: #d63638 !important; 
										background: #d63638 !important; 
										border-color: #b92123 !important; 
										line-height:normal !important; 
										padding: 5px !important; 
										margin: 10px !important;
									}
									.button-delete-logo:hover {
										background-color: #651011 !important; 
										background: #651011 !important; 
										border-color: #270505 !important; 
									}
								</style>
							<!--form-->
							<form action="<?php echo esc_url( admin_url( 'admin.php?page=wc-manage-store-plans&tab=super-admin-logo' ) ); ?>" enctype="multipart/form-data" method="POST" id="mainform" name="mainform">
								<!---->
								<table class="form-table">
									<tbody>
										<!---->
				                        <tr valign="top">
				                            <th scope="row">
				                                <label>
				                                    <?php echo __( 'Super Administrator Logo', 'wc-manage-store-plans' ); ?>:
				                                </label>
				                            </th>
				                            <td>
				                           		<div class="box-admin-logo"> 
				                           			<?php 
				                           			$wpnonce_delete = esc_attr( wp_create_nonce( 'wc-manage-store-plans-delete' ) ); 
				                           			?>
													<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-manage-store-plans&tab=super-admin-logo&_update_delete=yes&_wpnonce_delete=' . $wpnonce_delete ) ); ?>" class="button-primary button-delete-logo" type="submit" title="<?php echo __( 'Delete Logo', 'wc-manage-store-plans' ); ?>">
														<span class="dashicons dashicons-no"></span>
													</a>
				                           			<img alt="<?php echo __( 'Super Administrator Logo', 'wc-manage-store-plans' ); ?>" src="<?php echo $super_admin_logo; ?>" title="<?php echo __( 'Super Administrator Logo', 'wc-manage-store-plans' ); ?>" border="0" style="width: 100%; max-width:350px;">
				                           		</div>
												<i>
													<?php echo __( 'Current Logo.', 'wc-manage-store-plans' ); ?>
												</i>
				                                <hr/>
				                                <label>
													  <input type="file" name="file_upload_logo" id="file_upload" />
													<br/>
													<i>
														<?php echo __( '<strong>Note:</strong> Upload an image with a maximum width of 400px.', 'wc-manage-store-plans' ); ?>
													</i>
				                                </label>
				                           </td>
				                        </tr>
				                        <!---->
									</tbody>
								</table>
								<!---->
								<hr/>
								<div class="submit">
									<button class="button-primary" type="submit">
										<?php echo __( 'Save Logo', 'wc-manage-store-plans' ); ?>
									</button>
									<input type="hidden" name="_update_upload" value="yes">
									<input type="hidden" name="_wpnonce_upload" value="<?php echo esc_attr( wp_create_nonce( 'wc-manage-store-plans-upload' ) ); ?>">
								</div>
								<!---->  
							</form>
							<!---->
							<?php } else if ( $tab == "product-limit" ) { ?>
							<!--form-->
							<form method="POST" id="mainform" name="mainform">
								<!---->
								<table class="form-table">
									<tbody>
				                        <!---->
				                        <tr valign="top">
				                            <th scope="row">
				                                <label>
				                                    <?php echo __( 'Delete Products Automatic', 'wc-manage-store-plans' ); ?>:
				                                </label>
				                            </th>
				                            <td>
				                                <label>
				                                    <input type="checkbox" name="product_delete" value="yes" <?php if( $product_delete == "yes" ) { echo 'checked="checked"'; } ?> class="form-control">
				                                    <?php echo __( 'The system will delete the products that exceed the limit defined in the plugin.', 'wc-manage-store-plans' ) ; ?>
				                                </label>
				                           </td>
				                        </tr>
										<!---->
										<tr valign="top">
											<th scope="row">
												<label>
													<?php echo __( 'Product Limit', 'wc-manage-store-plans' ); ?>:
												</label>
											</th>
											<td>
												<label>
													<input type="number" required min="25" step="5" name="product_limit" value="<?php echo $product_limit; ?>"  style=" min-width:100px; width:auto;">
													<i>
														<?php echo __( '<strong>Minimum:</strong> 25 products per store.', 'wc-manage-store-plans' ); ?>
													</i>
												</label>
											</td>
										</tr>
										<!---->
										<tr valign="top">
											<th scope="row">
												<label>
													<?php echo __( 'Total Products', 'wc-manage-store-plans' ); ?>:
												</label>
											</th>
											<td>
												<label>
													<strong>
														<?php echo wp_count_posts( $post_type = 'product' )->publish; ?>
													</strong> 
													<?php echo __( 'Published Products', 'wc-manage-store-plans' ); ?>                    	
												</label>
											</td>
										</tr>
										<!---->	
									</tbody>
								</table>
								<!---->
								<hr/>
								<div class="submit">
									<button class="button-primary" type="submit">
										<?php echo __( 'Save Editions', 'wc-manage-store-plans' ); ?>
									</button>
									<input type="hidden" name="_update" value="yes">
									<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'wc-manage-store-plans' ) ); ?>">
								</div>
								<!---->  
							</form>
							<!---->
						<?php } else if ( $tab == "monthly-traffic" ) { ?>
							<!--form-->
							<form method="POST" id="mainform" name="mainform">
								<!---->
								<table class="form-table">
									<tbody>
										<!---->
										<tr valign="top">
											<th scope="row">
												<label>
													<?php echo __( 'Monthly Traffic', 'wc-manage-store-plans' ); ?>:
												</label>
											</th>
											<td>
												<label>
													<input type="number" required min="1000" step="500" name="monthly_traffic" value="<?php echo $monthly_traffic; ?>"  style=" min-width:100px; width:auto;">
													<i>
														<?php echo __( '<strong>Minimum:</strong> 1000 views per month.', 'wc-manage-store-plans' ); ?>
													</i>
												</label>
											</td>
										</tr>
										<!---->
										<tr valign="top">
											<th scope="row">
												<label>
													<?php echo __( 'Total for this month', 'wc-manage-store-plans' ); ?>:
												</label>
											</th>
											<td>
												<label>
													<strong>
														<?php echo $count_monthly_traffic['count_monthly']; ?>
													</strong> 
													<?php echo __( 'Views', 'wc-manage-store-plans' ); ?>                              	
												</label>
											</td>
										</tr>
										<!---->	
									</tbody>
								</table>
								<!---->
								<hr/>
								<div class="submit">
									<button class="button-primary" type="submit">
										<?php echo __( 'Save Editions', 'wc-manage-store-plans' ); ?>
									</button>
									<input type="hidden" name="_update" value="yes">
									<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'wc-manage-store-plans' ) ); ?>">
								</div>
								<!---->  
							</form>
							<!---->
						<?php } else if ( $tab == "user-permissions" ) { ?>
							<!--form-->
							<form method="POST" id="mainform" name="mainform">
								<!---->
								<table class="form-table">
									<tbody>
										<!---->
										<tr valign="top">
											<th scope="row">
												<label>
													<?php echo __( 'User Permissions', 'wc-manage-store-plans' ); ?>:
												</label>
												<hr/>
												<i style="font-style:italic; font-size: 11px; font-weight:normal; ">
													<?php echo __( 'If you only exist as an administrator on this WordPress you will automatically have all permissions.', 'wc-manage-store-plans' ); ?>
												</i>
											</th>
											<td>
												<label>
													<select required name="user_permissions" style="width:100%; width:auto;">
														<option value="">
															<?php echo __( 'Select an Administrator', 'wc-manage-store-plans' ); ?>
														</option>
														<?php $this->select_option_user_admin(); ?>
													</select>
												</label>
												<hr/>
												<i>
													<?php echo __( '<strong>Note:</strong> Select an administrator who will have the permissions.', 'wc-manage-store-plans' ); ?>
												</i>
											</td>
										</tr>
										<!---->
										<tr valign="top">
											<th scope="row">
												<label>
													<?php echo __( 'Super Administrator Email', 'wc-manage-store-plans' ); ?>:
												</label>
												<hr/>
												<i style="font-style:italic; font-size: 11px; font-weight:normal; ">
													<?php echo __( 'If the system blocks the store managers access to pre-configured limits, your customer will have a direct channel with you.', 'wc-manage-store-plans' ); ?>
												</i>
											</th>
											<td>
												<label>
													<input type="email" required="" min="25" step="5" name="super_admin_email" value="<?php echo $super_admin_email; ?>" style=" min-width:350px; width:auto;" class="">
												</label>
												<hr/>
												<i>
													<?php echo __( '<strong>Note:</strong> Provide an email to the store manager to get their support when you need it.', 'wc-manage-store-plans' ); ?>
												</i>
											</td>
										</tr>
										<!---->
									</tbody>
								</table>
								<!---->
								<hr/>
								<div class="submit">
									<button class="button-primary" type="submit">
										<?php echo __( 'Save Editions', 'wc-manage-store-plans' ); ?>
									</button>
									<input type="hidden" name="_update" value="yes">
									<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'wc-manage-store-plans' ) ); ?>">
								</div>
								<!---->  
							</form>
							<!---->
						<?php } ?>
						<!---->      
					</div>    
				</div>
				<?php
			}
		}
	new WC_Manage_Store_Plans();
	//=>
}

