<?php
/** Plugin Name: TFP E-commerce Checkout Fix */
if (!defined('ABSPATH')) exit;
function tfp_ecom_course($o=null){
 if($o&&is_a($o,'WC_Order')){foreach($o->get_items() as $i){$p=$i->get_product();if($p&&has_term('programs','product_cat',$p->get_id()))return true;}}
 return function_exists('tfp_course_cart_cohort_id')&&(bool)tfp_course_cart_cohort_id();
}
function tfp_ecom_confirm($o){
 if(!$o||!is_a($o,'WC_Order'))return '';
 $p=get_page_by_path('order-confirmed',OBJECT,'page');
 if(!$p||'publish'!==get_post_status($p->ID))return $o->get_checkout_order_received_url();
 return add_query_arg(['order_id'=>$o->get_id(),'order_key'=>$o->get_order_key()],get_permalink($p->ID));
}
add_action('woocommerce_checkout_create_order',function($o,$data){
 if(!$o||tfp_ecom_course($o)||$o->get_customer_id())return;
 $e=sanitize_email($o->get_billing_email());if(!$e||!is_email($e))return;
 $uid=email_exists($e);$new=false;$pass='';
 if(!$uid){
  $pass=wp_generate_password(24,true,true);
  $uid=wc_create_new_customer($e,'',$pass,['first_name'=>$o->get_billing_first_name(),'last_name'=>$o->get_billing_last_name(),'source'=>'tfp_ecommerce_checkout']);
  if(is_wp_error($uid)){$uid=email_exists($e);$pass='';}else{$new=true;}
 }
 if(!$uid)return;$uid=(int)$uid;$o->set_customer_id($uid);
 // Newly-created e-commerce customers should be authenticated immediately so the Elementor order-confirmed page renders the TFP account dropdown instead of Register/Login. Existing accounts are not auto-logged in for security.
 if($new && function_exists('wc_set_customer_auth_cookie')){
  wc_set_customer_auth_cookie($uid);
 }
 $m=['first_name'=>$o->get_billing_first_name(),'last_name'=>$o->get_billing_last_name(),'billing_email'=>$e,'billing_phone'=>$o->get_billing_phone(),'billing_address_1'=>$o->get_billing_address_1(),'billing_address_2'=>$o->get_billing_address_2(),'billing_city'=>$o->get_billing_city(),'billing_state'=>$o->get_billing_state(),'billing_postcode'=>$o->get_billing_postcode(),'billing_country'=>$o->get_billing_country(),'shipping_first_name'=>$o->get_shipping_first_name(),'shipping_last_name'=>$o->get_shipping_last_name(),'shipping_address_1'=>$o->get_shipping_address_1(),'shipping_address_2'=>$o->get_shipping_address_2(),'shipping_city'=>$o->get_shipping_city(),'shipping_state'=>$o->get_shipping_state(),'shipping_postcode'=>$o->get_shipping_postcode(),'shipping_country'=>$o->get_shipping_country()];
 foreach($m as $k=>$v)update_user_meta($uid,$k,$v);
 if($new){$o->update_meta_data('_tfp_new_account','yes');$o->update_meta_data('_tfp_new_account_pass',$pass);}
},10,2);
add_action('woocommerce_checkout_order_created',function($o){
 if(!$o||'yes'!==$o->get_meta('_tfp_new_account'))return;
 $uid=(int)$o->get_customer_id();$pass=(string)$o->get_meta('_tfp_new_account_pass');
 if($uid&&$pass&&function_exists('WC')){$es=WC()->mailer()->get_emails();if(isset($es['WC_Email_Customer_New_Account']))$es['WC_Email_Customer_New_Account']->trigger($uid,$pass,true);}
 $o->delete_meta_data('_tfp_new_account');$o->delete_meta_data('_tfp_new_account_pass');$o->save();
});
add_action('template_redirect',function(){
 if(!function_exists('is_wc_endpoint_url')||!is_wc_endpoint_url('order-received'))return;
 $id=absint(get_query_var('order-received'));if(!$id&&!empty($_GET['order-received']))$id=absint($_GET['order-received']);$o=$id?wc_get_order($id):false;if(!$o)return;
 $key=!empty($_GET['key'])?sanitize_text_field(wp_unslash($_GET['key'])):'';if(!$key||!hash_equals($o->get_order_key(),$key))return;if(tfp_ecom_course($o))return;
 $u=tfp_ecom_confirm($o);if($u){wp_safe_redirect($u,303);exit;}
},1);
add_action('init',function(){
 remove_shortcode('tfp_order_number');
 add_shortcode('tfp_order_number',function($a){$a=shortcode_atts(['order_id'=>isset($_GET['order_id'])?absint($_GET['order_id']):0,'order_key'=>isset($_GET['order_key'])?sanitize_text_field(wp_unslash($_GET['order_key'])):''],$a,'tfp_order_number');$o=$a['order_id']?wc_get_order($a['order_id']):false;if(!$o||!$a['order_key']||!hash_equals($o->get_order_key(),$a['order_key']))return '';return '<span class="tfp-order-number">'.esc_html($o->get_order_number()).'</span>';});
},999);
