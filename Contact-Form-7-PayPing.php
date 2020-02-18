<?php
/*
Plugin Name: درگاه پرداخت پی‌پینگ فرم تماس7
Plugin URI: https://payping.ir
Description:  انجام پرداخت فرم‌های تماس7 با درگاه پی‌پینگ. با تشکر از <a href="http://MasoudAmini.ir" target="blank">Masoud Amini</a>.
Version: 1.0.0
Author: Mahdi Sarani
Author URI: https://mahdisarani.ir
Text Domain: CF7PayPing
Domain Path: /lang/
*/

/* Contact Form7 PayPing Plugin Dir Path */
define( 'CF7PPPDP',  plugin_dir_path( __FILE__ ) );
/* Contact Form7 PayPing Plugin Dir Url */
define( 'CF7PPPDU',  plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'CF7_PayPing_Load_TextDomain' );
function CF7_PayPing_Load_TextDomain(){
  load_plugin_textdomain( 'CF7PayPing', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' ); 
}

//  plugin functions
register_activation_hook( __FILE__, "CF7_PayPing_activate" );
register_deactivation_hook( __FILE__, "CF7_PayPing_deactivate" );
register_uninstall_hook( __FILE__, "CF7_PayPing_uninstall" );

include_once( "inc/gateway.php" );