<?php
/*
Plugin Name: PayPing CF7
Plugin URI: https://payping.ir
Description:  انجام پرداخت فرم‌های تماس7 با درگاه پی‌پینگ.
Version: 1.1.2
Text Domain: payping-cf7
Author: Hadi Hosseini
Author URI: https://hosseini-dev.ir/
Requires Plugins: contact-form-7
License: GPLv3 or later
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/* Contact Form7 PayPing Plugin Dir Path */
define( 'CF7PPPDP',  plugin_dir_path( __FILE__ ) );
/* Contact Form7 PayPing Plugin Dir Url */
define( 'CF7PPPDU',  plugin_dir_url( __FILE__ ) );


//  plugin functions
register_activation_hook( __FILE__, "CF7_PayPing_activate" );
register_deactivation_hook( __FILE__, "CF7_PayPing_deactivate" );
register_uninstall_hook( __FILE__, "CF7_PayPing_uninstall" );

include_once( "inc/gateway.php" );