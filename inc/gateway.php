<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function CF7_PayPing_activate(){
	global $wpdb;
    $table_name = $wpdb->prefix . "cf7_payping_transaction";
    if( $wpdb->get_var( "show tables like '$table_name'" ) != $table_name ){
        $sql = "CREATE TABLE " . $table_name . " (
			id mediumint(11) NOT NULL AUTO_INCREMENT,
			idform bigint(11) DEFAULT '0' NOT NULL,
			transid VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
			gateway VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
			cost bigint(11) DEFAULT '0' NOT NULL,
			created_at bigint(11) DEFAULT '0' NOT NULL,
			email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci  NULL,
			description VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
			user_mobile VARCHAR(11) CHARACTER SET utf8 COLLATE utf8_general_ci  NULL,
			status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
			PRIMARY KEY id (id)
		);";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
	
    // remove ajax from contact form 7 to allow for php redirects
    function wp_config_put( $slash = '' ){
        $config = file_get_contents( ABSPATH . "wp-config.php" );
        $config = preg_replace( "/^([\r\n\t ]*)(\<\?)(php)?/i", "<?php define('WPCF7_LOAD_JS', false);", $config );
        file_put_contents( ABSPATH . $slash . "wp-config.php", $config );
    }

    if( file_exists( ABSPATH . "wp-config.php" ) && is_writable( ABSPATH . "wp-config.php" ) ){
        wp_config_put();
    }elseif( file_exists( dirname( ABSPATH ) . "/wp-config.php" ) && is_writable( dirname( ABSPATH ) . "/wp-config.php" ) ){
        wp_config_put( '/' );
    }else{ ?>
        <div class="error">
            <p><?php esc_html_e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been activated.', 'payping-cf7'); ?></p>
        </div>
        <?php
        exit;
    }

    // write initical options
    $cf7_PayPing_options = array(
        'payping_token' => '',
        'callback' => '',
        'error_color'=>'#f44336',
        'sucess_color' => '#089a13',
    );

    add_option( "cf7_PayPing_options", $cf7_PayPing_options );
}

function CF7_PayPing_deactivate() {
    function wp_config_delete( $slash = '' ) {
        $config = file_get_contents( ABSPATH . "wp-config.php" );
        $config = preg_replace(
            "/( ?)(define)( ?)(\()( ?)(['\"])WPCF7_LOAD_JS(['\"])( ?)(,)( ?)(0|1|true|false)( ?)(\))( ?);/i", 
            "", 
            $config
        );
        file_put_contents( ABSPATH . $slash . "wp-config.php", $config );
    }

    if ( file_exists( ABSPATH . "wp-config.php" ) && is_writable( ABSPATH . "wp-config.php" ) ) {
        wp_config_delete();
    } elseif ( file_exists( dirname( ABSPATH ) . "/wp-config.php" ) && is_writable( dirname( ABSPATH ) . "/wp-config.php" ) ) {
        wp_config_delete('/');
    } else {
        add_action( 'admin_notices', 'CF7_PayPing_wp_config_error_notice' );
        wp_enqueue_script( 'cf7-payping-admin-js', plugins_url( '/assets/js/script.js', __FILE__ ), array(), '1.0', true );
        exit;
    }

    delete_option( "cf7_PayPing_options" );
    delete_option( "CF7_PayPing_plugin_notice_shown" );
}

function CF7_PayPing_wp_config_error_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e( 'wp-config.php is not writable. Please make it writable (e.g., set permissions to 0777 temporarily), then revert to its original setting after deactivating this plugin.', 'payping-cf7' ); ?></p>
        <button id="go-back-button" class="button button-primary">
            <?php esc_html_e( 'Go Back and Try Again', 'payping-cf7' ); ?>
        </button>
    </div>
    <?php
}

function CF7_PayPing_CreateMessage( $title, $body, $endstr = "" ){
    if( $endstr != "") {
        return $endstr;
    }
    $tmp = '<div class="result-box-text-verify-payping"><h2 class="title-text-verify-payping">' . $title . '</h2><br>' . $body . '</div>';
    return $tmp;
}

// display activation notice
add_action( 'admin_notices', 'CF7_PayPing_plugin_admin_notices' );

function CF7_PayPing_plugin_admin_notices() {
    if (!get_option('CF7_PayPing_plugin_notice_shown')) {
        echo esc_html_e("<div class='updated'><p><a href='admin.php?page=CF7_PayPing_table'>برای تنظیم اطلاعات درگاه  کلیک کنید</a>.</p></div>", 'payping-cf7');
        update_option("CF7_PayPing_plugin_notice_shown", "true");
    }
}


    // add payping menu under contact form 7 menu
    add_action( 'admin_menu', 'CF7_PayPing_admin_menu', 20 );
    function CF7_PayPing_admin_menu(){
        $addnew = add_submenu_page(
            'wpcf7',
            esc_html__('تنظیمات پی‌پینگ', 'payping-cf7'),
            esc_html__('تنظیمات پی‌پینگ', 'payping-cf7'),
            'wpcf7_edit_contact_forms',
            'CF7_PayPing_table',
            'CF7_PayPing_table'
        );
        $addnew = add_submenu_page(
            'wpcf7',
            esc_html__('لیست تراکنش‌ها', 'payping-cf7'),
            esc_html__('لیست تراکنش‌ها', 'payping-cf7'),
            'wpcf7_edit_contact_forms',
            'CF7_PayPing_list_trans',
            'CF7_PayPing_list_trans'
        );
    }

    // hook into contact form 7 - after send
    add_action( 'wpcf7_mail_sent', 'CF7_PayPing_after_send_mail' );
    function CF7_PayPing_after_send_mail( $cf7 ){
        global $wpdb;
        global $postid;
        $postid = $cf7->id();
        
        $enable = get_post_meta( $postid, "_cf7pp_enable", true );
        $email = get_post_meta( $postid, "_cf7pp_email", true );

        if ( $enable == "1" ) {
            if ($email == "2") {
					include_once( "CreatePay.php" );
                exit;
            }
        }
    } // End Function

    // hook into contact form 7 form
    add_action( 'wpcf7_admin_after_additional_settings', 'CF7_PayPing_admin_after_additional_settings' );
    function CF7_PayPing_editor_panels( $panels ){
        $new_page = array(
            'PricePay' => array(
                'title' => esc_html__('اطلاعات پرداخت', 'payping-cf7'),
                'callback' => 'CF7_PayPing_admin_after_additional_settings'
            )
        );
        $panels = array_merge( $panels, $new_page );
        return $panels;
    }
    add_filter('wpcf7_editor_panels', 'CF7_PayPing_editor_panels');

    function CF7_PayPing_admin_after_additional_settings( $cf7 ){
        if (isset($_GET['post'])) {
            $post_id = sanitize_text_field($_GET['post']);
        } else {
            $post_id = '';
        }
        
        $enable = get_post_meta($post_id, "_cf7pp_enable", true);
        $price = get_post_meta($post_id, "_cf7pp_price", true);
        $email = get_post_meta($post_id, "_cf7pp_email", true);
        $user_mobile = get_post_meta($post_id, "_cf7pp_mobile", true);
        $description = get_post_meta($post_id, "_cf7pp_description", true);
        
        if( $enable == "1" ){
            $checked = "CHECKED";
        }else{
            $checked = "";
        }

        if( $email == "1" ){
            $before = "SELECTED";
            $after = "";
        }elseif( $email == "2" ){
            $after = "SELECTED";
            $before = "";
        }else{
            $before = "";
            $after = "";
        }

        $admin_table_output = "";
        $admin_table_output .= "<form>";
        $admin_table_output .= "<div id='additional_settings-sortables' class='meta-box-sortables ui-sortable'><div id='additionalsettingsdiv' class='postbox'>";
        $admin_table_output .= "<div class='handlediv' title='Click to toggle'><br></div><h3 class='hndle ui-sortable-handle'> <span>اطلاعات پرداخت فرم</span></h3>";
        $admin_table_output .= "<div class='inside'>";

        $admin_table_output .= "<div class='mail-field'>";
        $admin_table_output .= "<input name='enable' id='cf71' value='1' type='checkbox' $checked>";
        $admin_table_output .= "<label for='cf71'>فعال‌سازی پرداخت آنلاین</label>";
        $admin_table_output .= "</div>";

        //input -name
        $admin_table_output .= "<table>";
        $admin_table_output .= "<tr><td>مبلغ: </td><td><input type='text' name='price' style='text-align:left;direction:ltr;' value='$price'></td><td>(مبلغ به تومان)</td></tr>";

        $admin_table_output .= "</table>";

        //input -id
        $admin_table_output .= "<br> برای اتصال به درگاه پرداخت میتوانید از نام فیلدهای زیر استفاده کنید ";
        $admin_table_output .= "<br />
        <span style='color:#F00;'>
        user_email نام فیلد دریافت ایمیل کاربر بایستی user_email انتخاب شود.
        <br />
         description نام فیلد  توضیحات پرداخت بایستی description انتخاب شود.
        <br />
         user_mobile نام فیلد  موبایل بایستی user_mobile انتخاب شود.
        <br />
        user_price اگر کادر مبلغ در بالا خالی باشد می توانید به کاربر اجازه دهید مبلغ را خودش انتخاب نماید . کادر متنی با نام user_price ایجاد نمایید
		<br/>
		مانند [text* user_price]
        </span>	";
        $admin_table_output .= "<input type='hidden' name='email' value='2'>";

        $admin_table_output .= "<input type='hidden' name='post' value='$post_id'>";

        $admin_table_output .= "</td></tr></table></form>";
        $admin_table_output .= "</div>";
        $admin_table_output .= "</div>";
        $admin_table_output .= "</div>";
        
        $allowed_html = array_merge(
            wp_kses_allowed_html('post'),
            array(
                'form' => array(),
                'div' => array(
                    'id' => array(),
                    'class' => array(),
                ),
                'table' => array(),
                'tr' => array(),
                'td' => array(),
                'h3' => array(
                    'class' => array(),
                ),
                'span' => array(
                    'style' => array(),
                ),
                'input' => array(
                    'type'        => array(),
                    'name'        => array(),
                    'value'       => array(),
                    'class'       => array(),
                    'id'          => array(),
                    'placeholder' => array(),
                    'checked'     => array(),
                    'style'       => array(),
                ),
                'label' => array(
                    'for' => array(),
                ),
                'br' => array(),
            )
        );
        
        echo wp_kses($admin_table_output, $allowed_html);

    }

    // hook into contact form 7 admin form save
    add_action('wpcf7_save_contact_form', 'CF7_PayPing_save_contact_form');

    function CF7_PayPing_save_contact_form( $cf7 ){
        $post_id = sanitize_text_field($_POST['post']);
        if(!empty($_POST['enable'])){
            $enable = sanitize_text_field($_POST['enable']);
            update_post_meta($post_id, "_cf7pp_enable", $enable);
        }else{
            update_post_meta($post_id, "_cf7pp_enable", 0);
        }
        
        $price = sanitize_text_field($_POST['price']);
        update_post_meta($post_id, "_cf7pp_price", $price);

        $email = sanitize_text_field($_POST['email']);
        update_post_meta($post_id, "_cf7pp_email", $email);
    }

    function CF7_PayPing_list_trans() {
    
        if (!current_user_can("manage_options")) {
            wp_die(esc_html("You do not have sufficient permissions to access this page."));
        }
    
        global $wpdb;
        $table_name = $wpdb->prefix . "cf7_payping_transaction";
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE status NOT LIKE %s ORDER BY id DESC",
            'none'
        );
        $transactions = $wpdb->get_results($query, ARRAY_A);
    
        // Output the table
        echo wp_kses_post('<div class="wrap">
            <h2>تراکنش‌ها</h2>
            <table class="widefat post fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
                        <th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
                        <th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
                        <th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
                        <th scope="col" id="name" width="15%" class="manage-column" style="">کد تراکنش</th>
                        <th scope="col" id="name" width="13%" class="manage-column" style="">وضعیت</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
                        <th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
                        <th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
                        <th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
                        <th scope="col" id="name" width="15%" class="manage-column" style="">کد تراکنش</th>
                        <th scope="col" id="name" width="13%" class="manage-column" style="">وضعیت</th>
                    </tr>
                </tfoot>
                <tbody>');
    
        if (count($transactions) == 0) {
            echo wp_kses_post('<tr class="alternate author-self status-publish iedit" valign="top">
                    <td class="" colspan="6">هيج تراکنش وجود ندارد.</td>
                </tr>');
        } else {
            foreach ($transactions as $transaction) {
                echo wp_kses_post('<tr class="alternate author-self status-publish iedit" valign="top"><td class="">' . get_the_title($transaction['idform']) . '</td>');
                echo wp_kses_post('<td class="">' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $transaction['created_at']) . '</td>');
                echo wp_kses_post('<td class="">' . $transaction['email'] . '</td>');
                echo wp_kses_post('<td class="">' . $transaction['cost'] . ' تومان</td>');
                echo wp_kses_post('<td class="">' . $transaction['transid'] . '</td>');
                echo wp_kses_post('<td class="">');
    
                if ($transaction['status'] == "success") {
                    echo wp_kses_post('<b style="color:#0C9F55">پرداخت موفق</b>');
                } else {
                    echo wp_kses_post('<b style="color:#f00">پرداخت ناموفق</b>');
                }
                echo wp_kses_post('</td></tr>');
            }
        }
    
        echo wp_kses_post('</tbody>
            </table>
            <br>
            <hr>
        </div>');
    }
    
    function CF7_PayPing_table() {
        global $wpdb;
        if (!current_user_can("manage_options")) {
            wp_die(esc_html_e("You do not have sufficient permissions to access this page.", 'payping-cf7'));
        }
    
        echo '<form method="post" action="' . esc_url(filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL)) . '" enctype="multipart/form-data">';
        
        // Save and update options
        if (isset($_POST['update'])) {
            // Verify nonce
            if (!isset($_POST['cf7_payping_nonce']) || !wp_verify_nonce($_POST['cf7_payping_nonce'], 'cf7_payping_settings')) {
                wp_die(esc_html("Invalid nonce. Please refresh the page and try again.", 'payping-cf7'));
            }
    
            // Sanitize and update options
            $options['payping_token'] = sanitize_text_field($_POST['payping_token']);
            $options['callback'] = sanitize_text_field($_POST['callback']);
            $options['sucess_color'] = sanitize_text_field($_POST['sucess_color']);
            $options['error_color'] = sanitize_text_field($_POST['error_color']);
                        
            update_option("cf7_PayPing_options", $options);
            update_option('cf7pp_theme_message', wp_filter_post_kses($_POST['theme_message']));
            update_option('cf7pp_theme_error_message', wp_filter_post_kses($_POST['theme_error_message']));
            
            echo wp_kses_post('<br /><div class="updated"><p><strong>');
            esc_html_e("بروز رسانی انجام شد.", "payping-cf7");
            echo wp_kses_post("</strong></p></div>");
        }
    
        // Get existing options
        $options = get_option('cf7_PayPing_options');
        foreach ($options as $k => $v) {
            $value[$k] = $v;
        }
    
        $theme_message = get_option('cf7pp_theme_message', '');
        $theme_error_message = get_option('cf7pp_theme_error_message', '');
    
        ?>
        <div class='wrap'>
            <h1 class="wp-heading-inline">تنظیمات درگاه پرداخت پی‌پینگ فرم تماس7</h1>
            <table width='100%'>
                <tr>
                    <td>توکن دریافتی از پی‌پینگ:</td>
                    <td>
                        <input type="text" style="width:450px;text-align:left;direction:ltr;" name="payping_token" value="<?php echo esc_attr(sanitize_text_field($value['payping_token'])); ?>"> الزامی
                    </td>
                </tr>
                <hr>
                <tr>
                    <td>آدرس صفحه بازگشت:</td>
                    <td>
                        <input type="text" name="callback" style="width:450px;text-align:left;direction:ltr;" value="<?php echo esc_attr(sanitize_text_field($value['callback'])); ?>"> الزامی
                        <br />
                        حتما نامک برگه را قرار دهید مانند: فرم-پرداخت<br />
                        <strong> حتما باید یک برگه ایجادکنید و کد [result_payment] را در آن قرار دهید</strong>
                    </td>
                </tr>
                <tr>
                    <td>قالب تراکنش موفق :</td>
                    <td>
                        <textarea name="theme_message" style="width:450px;text-align:right;direction:rtl;"><?php echo esc_textarea($theme_message); ?></textarea>
                        <br />
                        متنی که میخواهید در هنگام موفقیت آمیز بودن تراکنش نشان دهید
                        <br />
                        <b>از شورتکد [transaction_id] برای نمایش شماره تراکنش در قالب های نمایشی استفاده کنید</b>
                    </td>
                </tr>
                <tr>
                    <td>قالب تراکنش ناموفق :</td>
                    <td>
                        <textarea name="theme_error_message" style="width:450px;text-align:right;direction:rtl;"><?php echo esc_textarea($theme_error_message); ?></textarea>
                        <br />
                        متنی که میخواهید در هنگام موفقیت آمیز نبودن تراکنش نشان دهید
                    </td>
                </tr>
                <tr>
                    <td>رنگ متن موفقیت آمیز بودن تراکنش :</td>
                    <td>
                        <input 
                            type="text" 
                            name="sucess_color" 
                            style="width:150px;text-align:left;direction:ltr;color:<?php echo esc_attr(sanitize_text_field($value['sucess_color'])); ?>" 
                            value="<?php echo esc_attr(sanitize_text_field($value['sucess_color'])); ?>">
                        مانند : #8BC34A یا نام رنگ green
                    </td>
                </tr>
                <tr>
                    <td>رنگ متن موفقیت آمیز نبودن تراکنش :</td>
                    <td>
                        <input 
                            type="text" 
                            name="error_color" 
                            style="width:150px;text-align:left;direction:ltr;color:<?php echo esc_attr(sanitize_text_field($value['error_color'])); ?>" 
                            value="<?php echo esc_attr(sanitize_text_field($value['error_color'])); ?>">
                        مانند : #f44336 یا نام رنگ red
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <input type="submit" name="btn2" class="button-primary" style="font-size: 17px;line-height: 28px;height: 32px;float: right;" value="ذخیره تنظیمات">
                    </td>
                </tr>
            </table>
        </div>
        <?php 
        // Add nonce field
        wp_nonce_field('cf7_payping_settings', 'cf7_payping_nonce');
        ?>
        <input type='hidden' name='update'>
        </form>
        <?php
    }
    


/* ShortCode Result Page */
add_shortcode('result_payment', 'CF7_PayPing_result_payment_func');
function CF7_PayPing_result_payment_func( $atts ){
	$payStatus = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';

	$paypingResponse = isset($_REQUEST['data']) ? wp_unslash($_REQUEST['data']) : '';
    $responseData = json_decode($paypingResponse, true);
	
	$refId = isset($responseData['paymentRefId']) ? sanitize_text_field($responseData['paymentRefId']) : null;
	
    $clientrefid = isset($responseData['clientRefId']) ? (int) $responseData['clientRefId'] : 0;
	
	if( is_admin() ){ 
		return; 
	}
	
	if( ! isset( $clientrefid ) ){ 
		return CF7_PayPing_CreateMessage("پرداخت ناموفق!", 'شماره فاکتور پیدا نشد!', "" ); 
	}
	
    global $wpdb;
    $Status = '';
    $Theme_Message = get_option('cf7pp_theme_message', '');
    $theme_error_message = get_option('cf7pp_theme_error_message', '');

    $options = get_option('cf7_PayPing_options');
    if (!isset($options) || empty($options)) {
        return CF7_PayPing_CreateMessage("پرداخت ناموفق!", 'مشکل تنظیمات، با مدیر سایت در ارتباط باشید.', "");
    }

    $TokenCode = $options['payping_token'];
    $sucess_color = $options['sucess_color'];
    $error_color = $options['error_color'];

    $table_name = $wpdb->prefix . 'cf7_payping_transaction';

    $query = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $clientrefid);
    $cf_Form = $wpdb->get_row($query);

    if (null !== $cf_Form) {
        $Amount = $cf_Form->cost;
    }

    if (!isset($Amount) || empty($Amount)) {
        return CF7_PayPing_CreateMessage("پرداخت ناموفق!", 'عدم ارسال مبلغ، لطفا با مدیر سایت در تماس باشید.', "");
    }
	
	
    /* Verify Pay */
	if ($payStatus == 0) {
		$Status = 'error';
		$txterror = 'تراکنش توسط کاربر لغو شد';
	} else {
		
		$varify_data = array( 'PaymentRefId' => $refId, 'Amount' => $Amount );
		$varify_args = array(
			'body' => json_encode( $varify_data ),
			'timeout' => '45',
			'redirection' => '5',
			'httpsversion' => '1.0',
			'blocking' => true,
			'headers' => array(
				'Authorization' => 'Bearer ' . $TokenCode,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json'
			),
			'cookies' => array()
		);

		$verify_url = 'https://api.payping.ir/v3/pay/verify';
		$verify_response = wp_remote_post( $verify_url, $varify_args );
		
		$body = wp_remote_retrieve_body( $verify_response );
		$rbody = json_decode( $body, true );
		
		
		if( is_wp_error( $verify_response ) ){
			$Status = 'error';
			$Message = 'خطا در ارتباط با پی‌پینگ : شرح خطا '.$verify_response->get_error_message();
			return CF7_PayPing_CreateMessage("پرداخت ناموفق!", $Message, "" );
		}elseif (isset($rbody['status']) && $rbody['status'] == 409) {
			$Status = 'success';
			
		}else{
			$code = wp_remote_retrieve_response_code( $verify_response );
			if( $code === 200 ){
				$Status = 'success';
			}else{
				$Message = json_decode( $verify_response['body'], true );
				if( array_key_exists( '15', $Message) ){
					$Status = 'success';
				}elseif( array_key_exists( 'RefId', $Message) ){
					$Status = 'error';
					$txterror = 'RefId نمی تواند خالی باشد';
				}else{
					$Status = 'error';
				}
			}
		}
	}
    
	
    
    
    

    if( $Status == 'success' ){
        $wpdb->update( $wpdb->prefix . 'cf7_payping_transaction', array( 'status' => 'success', 'transid' => $refId ), array( 'id' => $clientrefid ), array( '%s', '%s' ), array( '%d' ) );

        //Dispaly
        $body = '<b style="color:'.$sucess_color.';">'.stripslashes(str_replace('[transaction_id]', $refId, $Theme_Message ) ).'<b/>';
        return CF7_PayPing_CreateMessage( "پرداخت موفق", $body, "" );
    }elseif( $Status == 'error' ){
        $wpdb->update( $wpdb->prefix . 'cf7_payping_transaction', array( 'status' => 'error', 'transid' => $refId ), array( 'id' => $clientrefid ), array( '%s', '%s' ), array( '%d' ) );
		
        //Dispaly
		if( isset($txterror) ){
			$body = '<b style="color:'.$error_color.';">'.$theme_error_message.'<b/><br><span> دلیل: </span>'.$txterror;
		}else{
			$body = '<b style="color:'.$error_color.';">'.$theme_error_message.'<b/>';
		}
        
        return CF7_PayPing_CreateMessage("پرداخت ناموفق!", $body, "" );
    }
}