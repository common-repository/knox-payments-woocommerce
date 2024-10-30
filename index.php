<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/*
Plugin Name: WooCommerce Knox Payment Gateway
Plugin URI: http://www.itechcareers.com
Description: Knox Payment gateway for woocommerce
Version: 0.5
Author: Knox Payments
Author URI: http://www.knoxpayments.com
*/ 

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
add_action('plugins_loaded', 'woocommerce_itechcareers_Knox_init', 0);
function woocommerce_itechcareers_Knox_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_itechcareers_Knox extends WC_Payment_Gateway{
    public function __construct(){
      $this -> id = 'Knox';
      $this -> method_title = 'Knox';
      $this -> has_fields = false;
 
      $this -> init_form_fields();
      $this -> init_settings();
 
      $this -> title = $this -> settings['title'];
      $this -> description = $this -> settings['description'];
      $this -> knox_key = $this -> settings['knox_key'];
      $this -> knox_pass = $this -> settings['knox_pass'];
      $this -> redirect_page_id = $this -> settings['redirect_page_id'];
	  
	  global $woocommerce;
	  $this -> liveurl = 'https://knoxpayments.com/pay/index.php?';
 
      $this -> msg['message'] = "";
      $this -> msg['class'] = "";

      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
      add_action('woocommerce_receipt_Knox', array(&$this, 'receipt_page'));

      add_action( 'woocommerce_thankyou_Knox', array(&$this, 'knox_response_success'));

   }
    function init_form_fields(){
 
       $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'itechcareers'),
                    'type' => 'checkbox',
                    'label' => __('Enable Knox Payment Module.', 'itechcareers'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'itechcareers'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'itechcareers'),
                    'default' => __('Knox', 'itechcareers')),
                'description' => array(
                    'title' => __('Description:', 'itechcareers'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'itechcareers'),
                    'default' => __('Knox allows you to pay using your online banking for the top 30 banks in the USA including Wells Fargo, Bank of America, Chase, Citibank, and more.', 'itechcareers')),
                'knox_key' => array(
                    'title' => __('API Key', 'itechcareers'),
                    'type' => 'text',
                    'description' => __('This is the API Key related to the module provided via Knox Servers at knoxpayments.com/docs."'),
                    'default' => __('7063_d07449f011f370', 'itechcareers')),
                'knox_pass' => array(
                    'title' => __('API Pass', 'itechcareers'),
                    'type' => 'text',
                    'description' =>  __('This is the API Password related to the module provided via Knox Servers at knoxpayments.com/docs.', 'itechcareers'),
                    'default' => __('7063_399fa0b786158d7', 'itechcareers')),
                'knox_invoice' => array(
                    'title' => __('Invoice Detail', 'itechcareers'),
                    'type' => 'text',
                    'description' =>  __('Enter the details of the invoice if any.', 'itechcareers'),
                    'default' => __('Knox WooCommerce Payment', 'itechcareers'))
            );
    }

       public function admin_options(){
        echo '<h3>'.__('Knox Payment Gateway', 'itechcareers').'</h3>';
        echo '<p>'.__('Knox is most popular online banking payment gateway in the United States').'</p>';
        echo '<table class="form-table">';
        // Generate the HTML For the settings form.
        $this -> generate_settings_html();
        echo '</table>';
 
    }
	
 
    /**
     *  There are no payment fields for Knox, but we want to show the description if set.
     **/
    function payment_fields(){
        if($this -> description) echo wpautop(wptexturize($this -> description));
    }
	
	
    /**
     * Receipt Page
     **/
    function receipt_page($order){
        echo '<p>'.__('Thank you for your order, please click the button below to pay with Knox.', 'itechcareers').'</p>';
        echo $this -> generate_Knox_form($order);
    }
    
    function knox_response_success($order) {
		$paid_status = $_GET['pst'];
		if($paid_status === "Paid") {
			$this -> finish_payment($order);
		}
		else if($paid_status === "Unpaid") {
			echo "THIS IS HAPPENING";
			
			echo $order->get_cancel_order_url();
		}
	}
	
	public function finish_payment($order_id){

		global $woocommerce;
		$order = new WC_Order($order_id);
		$order->payment_complete();
		$order -> add_order_note('Knox payment successful');
        $order -> add_order_note($this->msg['message']);
        $woocommerce -> cart -> empty_cart();
	}

	
	function knox_response_fail() {
		
	}
 
	
	
    /**
     * Generate Knox button link
     **/
    public function generate_Knox_form($order_id){
		
        global $woocommerce;
        
 
        $order = new WC_Order($order_id);
        $txnid = $order_id.'_'.date("ymds");
 
        $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
		$red_email = $order -> billing_email;
		//set the cancel and success URLs to the stock WooCommerce objects 
		$redirect_url_new = $order->get_checkout_order_received_url();
		$cancel_url = $order->get_cancel_order_url();
 
        $productinfo = "Order $order_id";
 
        $str = "$this->knox_key|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||$this->knox_pass";
        $hash = hash('sha512', $str);
		?>
		<script>
		//Knox is best used in an iFrame. I know this isn't the WooCommerce way, but it's impossible to let WooCommerce use the Knox API to trigger their own views as that would allow businesses to store username and passwords for their users banks. That's a no-no. 
		jQuery("body").append('<iframe allowtransparency="true" frameborder="0" scrolling="yes" src="about:blank" name="myFrame" id="myFrame" style="overflow:scroll !important; -webkit-overflow-scrolling:touch !important; position: fixed; top: 0px; left: 0px; z-index: 999999; width: 100%; height: 100%; display: none; "></iframe>');
		var dispframe = function() {
			jQuery("#myFrame").show()
		}
		</script>
	
		<?php
		//Knox requires the user to have set an api_key at the very least to make payments. 
        $Knox_args = array(
          'api_key' => $this -> knox_key,
          'api_password' => $this -> knox_pass,
          'invoice_detail' => '',
		  'recurring' => 'ot',
		  'information_request' => 'none',
          'txnid' => $txnid,
          'amount' => $order -> order_total,
          "cancel_url" => $cancel_url,
		  'redirect_url' => $redirect_url_new
          );
 
        $Knox_args_array = array();
        foreach($Knox_args as $key => $value){
          $Knox_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
        }
/*
Build a simple form so we can trigger a javascript event. I would love feedback on how to avoid this, but it's the only way I could see to trigger Knox in an iFrame properly. 
*/
        return '<form style="opacity:0;" action="'.$this -> liveurl.'" method="get" id="Knox_payment_form" target="myFrame" onsubmit="dispframe()">
            ' . implode('', $Knox_args_array) . '
            <input type="submit" class="button-alt" id="submit_Knox_payment_form" value="'.__('Pay via Knox', 'itechcareers').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'itechcareers').'</a>
            <script type="text/javascript">
jQuery(function(){
jQuery("body").block(
        {
            message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'itechcareers').'",
                overlayCSS:
        {
            background: "#fff",
                opacity: 0.6
    },
    css: {
        padding:        20,
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:"32px"
    }
    });
    jQuery("#submit_Knox_payment_form").click();});
    </script>
            </form>';
 
 
    }
	public function process_payment( $order_id ) {
		

		$order = new WC_Order( $order_id );

		// Reduce stock levels
		$order->reduce_order_stock();
		
		// Return thankyou redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> $order->get_checkout_payment_url( true )
		);
	}
	
    function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }
}


   /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_itechcareers_Knox_gateway($methods) {
        $methods[] = 'WC_itechcareers_Knox';
        return $methods;
    }
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_itechcareers_Knox_gateway' );
}
}