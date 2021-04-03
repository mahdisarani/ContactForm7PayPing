<?php
        global $wpdb;
        global $postid;
        
        $wpcf7 = WPCF7_ContactForm::get_current();
        $submission = WPCF7_Submission::get_instance();
        $user_email = '';
        $user_mobile = '';
        $description = '';
        $user_price = '';

        if($submission){
            $data = $submission->get_posted_data();
            $user_email = isset($data['user_email']) ? $data['user_email'] : "";
            $user_mobile = isset($data['user_mobile']) ? $data['user_mobile'] : "";
            $description = isset($data['description']) ? $data['description'] : "";
            $user_price = isset($data['user_price']) ? $data['user_price'] : "";
        }
        
        $price = get_post_meta( $postid, "_cf7pp_price", true );
                if( $price == "" ){
                    $price = $user_price;
                }
                $options = get_option( 'cf7_PayPing_options' );
                foreach( $options as $k => $v ){
                    $value[$k] = $v;
                }
                $active_gateway = 'PayPing';
                $TokenCode = $value['payping_token'];
                $url_return = $value['callback'];

                // Set Data -> Table Trans_ContantForm7
                $table_name = $wpdb->prefix . "cf7_payping_transaction";
                $_x = array();
                $_x['idform'] = $postid;
                $_x['transid'] = ''; // create dynamic or id_get
                $_x['gateway'] = $active_gateway; // name gateway
                $_x['cost'] = $price;
                $_x['created_at'] = time();
                $_x['email'] = $user_email;
                $_x['user_mobile'] = $user_mobile;
                $_x['description'] = $description;
                $_x['status'] = 'none';
                $_y = array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' );

                if( $active_gateway === 'PayPing' ){
                    $TokenCode = $TokenCode; //Required
                    $Amount = $price; //Amount will be based on Toman - Required
                    $Description = $description; // Required
                    $Email = $user_email; // Optional
                    $Mobile = $user_mobile; // Optional
                    if( !empty( $Mobile ) ){
                        $Paymenter = $Mobile;
                        $payerIdentity = $Mobile;
                    }elseif( !empty( $Email ) ){
                        $Paymenter = $Email;
                        $payerIdentity = $Email;
                    }else{
                        $Paymenter = 'NONE';
                        $payerIdentity = 'NONE';
                    }
                    $CallbackURL = get_page_by_title( $url_return )->guid;
                    $wpdb->insert( $table_name, $_x, $_y );
                    $clientrefid = $wpdb->insert_id;
                    /* Create Pay */
                    $pay_data = array(
                        'payerName' => $Paymenter,
                        'Amount' => $Amount,
                        'payerIdentity'=> $payerIdentity ,
                        'returnUrl' => $CallbackURL,
                        'Description' => $Description ,
                        'clientRefId' => $clientrefid
                    );
                    $pay_args = array(
                        'body' => json_encode( $pay_data ),
                        'timeout' => '45',
                        'redirection' => '5',
                        'httpsversion' => '1.0',
                        'blocking' => true,  
                        'headers' => array(   
                            'Authorization' => 'Bearer ' . $TokenCode,  
                            'Content-Type'  => 'application/json',    
                            'Accept' => 'application/json'  ),
                        'cookies' => array()
                    );
                    $pay_url = 'https://api.payping.ir/v2/pay';
                    $pay_response = wp_remote_post( $pay_url, $pay_args );
                    $PAY_XPP_ID = $pay_response["headers"]["x-paypingrequest-id"];
                    if( is_wp_error( $pay_response ) ){
                        $Status = 'failed';
                        $Fault = $pay_response->get_error_message();
                        $Message = 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$pay_response->get_error_message();
                    }else{
                        $code = wp_remote_retrieve_response_code( $pay_response );
                        if( $code === 200 ){
                            if( isset( $pay_response["body"] ) and $pay_response["body"] != '' ){
                                $code_pay = wp_remote_retrieve_body( $pay_response );
                                $code_pay =  json_decode( $code_pay, true );
                                $_x['transid'] = $code_pay["code"];
                                $wpdb->update( $table_name, $_x, array( 'id' => $clientrefid ), $_y, array( '%d' ) );
                                wp_redirect( sprintf( 'https://api.payping.ir/v2/pay/gotoipg/%s', $code_pay["code"] ) );
                                exit;
                            }else{
                                $Message = ' تراکنش ناموفق بود- کد خطا : '.$PAY_XPP_ID;
                                echo '<pre>';
                                print_r($Message);
                                echo '</pre>';
                            }
                        }else{
                            $Message = wp_remote_retrieve_body( $pay_response ).'<br /> کد خطا: '.$PAY_XPP_ID;
                            echo '<pre>';
                            print_r($Message);
                            echo '</pre>';
                        }
                    }

                }
?>