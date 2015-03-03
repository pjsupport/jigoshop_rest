<?php
/*
Plugin Name: Jigoshop - PayJunction Gateway
Plugin URI: http://jigoshop.com
Description: Extends JigoShop with the ability to process credit cards via the <a href="http://www.payjunction.com/trinity/support/view.action?knowledgeBase.knbKnowledgeBaseId=589" target="_blank">PayJunction</a> payment gateway. A PayJunction gateway account, and a server with SSL support and an SSL certificate is required for security reasons. <a href="http://www.payjunction.com/trinity/support/view.action?knowledgeBase.knbKnowledgeBaseId=323" target="_blank">Click Here</a> for to find the Test QuickLink Login/Password and Test Gateway URL to run transactions in test mode. <a href="https://www.payjunction.com" target="_blank">Click Here</a> to find the Live QuickLink Login/Password for your PayJunction account.
Version: 1.5.0
Author: PayJunction
Modified: Matthew Cooper, PayJunction
*/

add_action('plugins_loaded', 'init_jigoshop_payjunction_gateway');

function init_jigoshop_payjunction_gateway() {
	
	if (!class_exists('jigoshop')) return;
	
	if (!defined('PAYJUNCTION_DIR')) {
		define('PAYJUNCTION_DIR', plugins_url('', __FILE__));
	}
	
	add_filter('jigoshop_payment_gateways', function($methods) {
		$methods[] = 'payjunction';
		return $methods;
	}, 0);
	
	class payjunction extends jigoshop_payment_gateway {
		
		public function __construct() {
			parent::__construct();
			
			$options = Jigoshop_Base::get_options();
			
			$this->id = 'payjunction';
			$this->icon = PAYJUNCTION_DIR.'/assets/pjLogoBlack160x40.png';
			$this->has_fields = true;
			$this->enabled = $options->get('jigoshop_payjunction_enabled');
			$this->title = $options->get('jigoshop_payjunction_title');
			$this->description = $options->get('jigoshop_payjunction_description');
			$this->show_description = $options->get('jigoshop_payjunction_show_description') == 'yes' ? true : false;
			$this->cvvmode = $options->get('jigoshop_payjunction_disable_cvv') == 'no' ? true : false;
			$this->avsmode = $options->get('jigoshop_payjunction_avs_mode');
			$this->dynavsmode = $options->get('jigoshop_payjunction_dynamic_avs') == 'no' ? false : true;
			// See if we're in test mode and set the URL and login/password appropriately
			$this->testmode = $options->get('jigoshop_payjunction_test_mode') == 'yes' ? true : false;
			
			if ($this->testmode) {
				$this->apilogin = 'pj-ql-01';
				$this->apipassword = 'pj-ql-01p';
				$this->gatewayurl = 'https://api.payjunctionlabs.com/transactions';
				$this->appkey = 'e4a8fe17-25e0-450d-bae2-dc6d173ad7bc';
			} else {
				$this->apilogin = $options->get('jigoshop_payjunction_api_login');
				$this->apipassword = $options->get('jigoshop_payjunction_api_password');
				$this->gatewayurl = 'https://api.payjunction.com/transactions';
				$this->appkey = '3b2d0c91-ab7e-4f6f-a243-33df8b6b5dc1';
			}
			
			// See if we're doing Authorization Only
			if ($options->get('jigoshop_payjunction_auth_only') == 'yes') {
				$this->salemethod = 'HOLD';
			} else {
				$this->salemethod = 'CAPTURE';
			}
			 $this->ssl_enforced = $options->get('jigoshop_force_ssl_checkout');
			add_action('receipt_payjunction', array(&$this, 'receipt_page'));
			add_action('jigoshop_settings_scripts', array($this, 'admin_scripts'));
		}
		
		protected function get_default_options() {
			$defaults = array();
			
			// Define the section name for the Jigoshop_Options
			$defaults[] = array(
				'name' => __('PayJunction<img src="'.PAYJUNCTION_DIR.'/assets/pjLogoBlack160x40.png" style="vertical-align:middle;margin-top:-4px;margin-left:10px;">', 'jigoshop'),
				'type' => 'title',
				'desc' => __('PayJunction Credit Card Processing Gateway', 'jigoshop')
			);
			
			$defaults[] = array(
				'name' => __('Enable PayJunction Gateway', 'jigoshop'),
				'desc' => '',
				'tip' => '',
				'id' => 'jigoshop_payjunction_enabled',
				'std' => 'yes',
				'type' => 'checkbox',
				'choices' => array(
					'yes' => __('Yes', 'jigoshop'),
					'no' => __('No', 'jigoshop')
				)
			);
			
			$defaults[] = array(
				'name' => __('PayJunction QuickLink API Login', 'jigoshop'),
				'desc' => '',
				'tip' => __('<a href="https://company.payjunction.com/pages/viewpage.action?pageId=328435">Click here</a> for instructions on how to verify your API login and reset your API password.', 'jigoshop'),
				'id' => 'jigoshop_payjunction_api_login',
				'std' => __('pj-ql-01', 'jigoshop'),
				'type' => 'text'
			);
			
			$defaults[] = array(
				'name' => __('PayJunction QuickLink API Password', 'jigoshop'),
				'desc' => '',
				'tip' =>  __('<a href="https://company.payjunction.com/pages/viewpage.action?pageId=328435">Click here</a> for instructions on how to verify your API login and reset your API password.', 'jigoshop'),
				'id' => 'jigoshop_payjunction_api_password',
				'std' => __('pj-ql-01p', 'jigoshop'),
				'type' => 'text'
			);
			
			$defaults[] = array(
				'name' => __('Test/Sandbox Mode', 'jigoshop'),
				'desc' => '',
				'tip' => __('Enable testing mode to send transactions to PayJunctionLabs.com.', 'jigoshop'),
				'id' => 'jigoshop_payjunction_test_mode',
				'std' => 'no',
				'type' => 'checkbox',
				'choices' => array(
					'yes' => __('Yes', 'jigoshop'),
					'no' => __('No', 'jigoshop')
				)
			);
			
			$defaults[] = array(
				'name' => __('Authorize Only', 'jigoshop'),
				'desc' => __('Authorize but do not capture transaction, i.e. process it as a Hold in PayJunction. 
				Transactions left on Hold status in PayJunction will not be funded and will automatically void after 21 days.
				<strong>You must manually set transactions to "Capture" from your PayJunction website account in order to be paid for the
				previously authorized funds.</strong>', 'jigoshop'),
				'id' => 'jigoshop_payjunction_auth_only',
				'std' => 'no',
				'type' => 'checkbox',
				'choices' => array(
					'yes' => __('Yes', 'jigoshop'),
					'no' => __('No', 'jigoshop')
				)
			);
			
			$defaults[] = array(
				'name' => __('Disable CVV Check', 'jigoshop'),
				'desc' => __("Check this option to remove the card security code requirement from the checkout page.", 'jigoshop'),
				'tip' => __('Disables the security code check for transactions, it is highly recommended to leave this unchecked', 'jigoshop'),
				'id' => 'jigoshop_payjunction_disable_cvv',
				'std' => 'no',
				'type' => 'checkbox',
				'choices' => array(
					'yes' => __('Yes', 'jigoshop'),
					'no' => __('No', 'jigoshop')
				)
			);
			
			$defaults[] = array(
				'name' => __('Address Verification Security', 'jigoshop'),
				'desc' => __("This tells PayJunction what conditions to automatically void a transaction under depending on if the street address and/or zip code match
							(if Dynamic Bypass Mode is not selected below) or put on Hold status (if Dynamic Bypass Mode is enabled).
							Please note, voids take approximately 1-2 business days to be processed by the customer's bank.
							<ul>
							<li>Address AND Zip: Require BOTH match</li>
							<li>Address OR Zip: Require AT LEAST ONE matches</li>
							<li>Bypass AVS: NO Requirement, AVS info still requested</li>
							<li>Address ONLY: Require the Address matches</li>
							<li>Zip ONLY: Require the Zip matches</li>
							<li>Disable AVS: Do not request AVS info</li>
							</ul>", 'jigoshop'),
				'tip' => 'Sets the AVS Match Type to use on transactions',
				'id' => 'jigoshop_payjunction_avs_mode',
				'std' => 'ADDRESS_AND_ZIP',
				'type' => 'select',
				'choices' => array(
					'ADDRESS_AND_ZIP' => __('Address AND Zip', 'jigoshop'),
					'ADDRESS_OR_ZIP' => __('Address OR Zip', 'jigoshop'),
					'BYPASS' => __('Bypass AVS', 'jigoshop'),
					'ZIP' => __('Zip Only', 'jigoshop'),
					'ADDRESS' => __('Address Only', 'jigoshop'),
					'OFF' => __('Disable AVS (Not Recommended)', 'jigoshop')
				)
			);
			
			$defaults[] = array(
				'name' => __('Dynamic Bypass Mode', 'jigoshop'),
				'desc' => __('When in dynamic mode, all transactions will be run through PayJunction in Bypass mode, however if the AVS result does not
				pass the requirement set in the Address Verification Security setting above, the transaction will automatically be set to Hold in PayJunction
				and JigoShop. <strong>Please note, you will need to manually set the transaction to
				Capture in the PayJunction website as well as in JigoShop if you choose to move forward with the order.</strong>'),
				'id' => 'jigoshop_payjunction_dynamic_avs',
				'std' => 'no',
				'type' => 'checkbox',
				'choices' => array(
					'yes' => __('Yes', 'jigoshop'),
					'no' => __('No', 'jigoshop')
				)
			);
			
			$defaults[] = array(
				'name' => __('Method Title', 'jigoshop'),
				'desc' => '',
				'tip' => __('This controls the title which the user sees during checkout.', 'jigoshop'),
				'id' => 'jigoshop_payjunction_title',
				'std' => __('Credit/Debit Card', 'jigoshop'),
				'type' => 'text'
			);
			
			$defaults[] = array(
				'name' => __('Show Customer Message at Checkout', 'jigoshop'),
				'desc' => __('Displays the Customer Message set below on the checkout page when the customer selects PayJunction.', 'jigoshop'),
				'id' => 'jigoshop_payjunction_show_description',
				'std' => 'no',
				'type' => 'checkbox',
				'choices' => array(
					'yes' => __('Yes', 'jigoshop'),
					'no' => __('No', 'jigoshop')
				)
			);
			
			$defaults[] = array (
				'name' => __('Customer Message', 'jigoshop'),
				'desc' => '',
				'tip' => __('This controls the description which the user sees during checkout.', 'jigoshop'),
				'id' => 'jigoshop_payjunction_description',
				'std' => __('Pay with your credit or debit card using PayJunction', 'jigoshop'),
				'type' => 'longtext'
			);
		
			return $defaults;
		}
		
		public function admin_scripts() {
			$no_ssl = '<div class="error"><p>PayJunction is enabled and the <a href="/wp-admin/admin.php?page=jigoshop_settings&tab=general">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.';
			?>
			<script type="text/javascript">
			/*<! [CDATA[*/
				jQuery(function($) {
					$('#jigoshop_payjunction_api_password').attr('type', 'password');
					<?php 
					if ($this->ssl_enforced == 'no') {
						?>
						jQuery('body').append('<?php echo $no_ssl; ?>');
						<?php
					}
					if (!function_exists('curl_version')) {
						$no_curl = '<div class="error"><p>The cURL extension for PHP is not installed and transactions will not run!</p></div>';
						?>
						jQuery('body').append('<?php echo $no_curl?>');
						<?php
					} ?>
					
					// Add test button for API credentials
					var $apiTest = $('<button>Test Credentials</button>');
					$apiTest.click(function(event) {
						event.preventDefault();
						
						var login = $('#jigoshop_payjunction_api_login').val();
						var pass = $('#jigoshop_payjunction_api_password').val();
						
						var credentials = 'login=' + encodeURIComponent(login) + '&pass=' + encodeURIComponent(pass);
						
						$.post('<?php echo plugins_url('pjApiCheck.php', __FILE__) ?>', credentials, function(data) {
							var response;
							try {
								response = JSON.parse(data);
							} catch (err) {
								// Do nothing for security reasons but let's give a response
								response = {'status': 'error', 'type': 'Invalid response', 'message': 'Could not parse the response from pjApiCheck.php'};
							} finally {
								if (response['status'] === 'success') {
									alert('Success! Your API login and password are valid.');
								} else if (response['status'] === 'failure') {
									alert('Failure: The API login and password are not valid.');
								} else if (response['status'] === 'error') {
									alert("There was an error: \n" + response['type'] + ":\n" + response['message']);
								} else {
									alert("Could not check credentials due to an unknown error");
								}
							}
						});
					});
					$('#jigoshop_payjunction_api_password').after($apiTest);
				});
				
			/*]]>*/
			</script>
			<?php
		}
		
		function payment_fields() {
			if ($this->description && $this->show_description) {
				echo wpautop(wptexturize($this->description));
			}
			if ($this->testmode) echo '<span style="color:red;">The PayJunction module is currently in testing mode, the credit card will not actually be charged.</span>';
			?>
			<fieldset>
				<p>
					<label for="ccnum">
						<?php echo __('Credit Card Number', 'jigoshop') ?>
						<span class='required'>
							*
						</span>
					</label>
					<input type='text' class='input-text' id='ccnum' name='ccnum' />
				</p>
				<p>
					<label for='cc-expire-month'>
						<?php echo __('Expiration Date', 'jigoshop'); ?>
						<span class='required'>
							*
						</span>
					</label>
					<select name='expmonth' id='expmonth'>
						<option value=''>
							<?php echo __('Month', 'jigoshop'); ?>
						</option>
						<?php
							$months = array();
							for ($x = 1; $x <= 12; $x++) {
								$timestamp = mktime(0, 0, 0, $x, 1);
								$months[date('m', $timestamp)] = date('F', $timestamp);
							}
							foreach ($months as $num => $name) {
								printf('<option value="%s">%s</option>', $num, $name);
							}
						?>
					</select>
					<select name='expyear' id='expyear'>
						<option value=''>
							<?php echo __('Year', 'jigoshop'); ?>
						</option>
						<?php
							for ($x = date('Y'); $x <= date('Y') + 15; $x++) {
								printf('<option value="%u">%u</option>', $x, $x);
							}
						?>
					</select>
				</p>
				<?php
					if ($this->cvvmode) {
						?>
						<p>
							<label for='cvv'>
								<?php echo __('Card Security Code (CVV)', 'jigoshop'); ?>
								<span class='required'>
									*
								</span>
							</label>
							<input type='text' class='input-text' id='cvv' name='cvv' maxlength='4' style='width:75px' />
						</p>
			<?php	} ?>
			</fieldset>
			<?php
		}
	
		function set_payjunction_hold($txnid) {
			$post = 'status=HOLD';
			$this->process_rest_request("PUT", $post, $txnid);
		}
		
		function process_rest_request($type, $post=null, $txnid=null) {
			
			$url = !is_null($txnid) ? $this->gatewayurl."/".$txnid : $this->gatewayurl;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'X-PJ-Application-Key: ' . $this->appkey));
			curl_setopt($ch, CURLOPT_USERPWD, $this->apilogin . ':' . $this->apipassword);
			switch($type) {
				case "POST":
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
					break;
				case "GET":
					curl_setopt($ch, CURLOPT_HTTPGET, true);
					break;
				case "PUT":
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
					curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
					break;
			}
			
			$content = curl_exec($ch);
			$curl_errno = curl_errno($ch);
			$curl_error = curl_error($ch);
			curl_close($ch);
			
			if ($curl_errno) {
				echo "<center><font color=red>Your payment did not process successfully</font></center><br><br>";
				echo "Transaction Details<br><br>";
				echo "cURL error code: $curl_errno <br>";
				echo "cURL message: $curl_error <br>";
				$response=array("cURL"=>"Error");
				return $response;
			} else {
				return json_decode($content, true);
			}
		}
		/**
		 * Process the payment and return the result
		 **/
		function process_payment($order_id) {
			if (!$this->validate_fields()) return;
			$order = new jigoshop_order( $order_id );
			$checkout_redirect = apply_filters( 'jigoshop_get_checkout_redirect_page_id', jigoshop_get_page_id('thanks') );
			$payjunction_request = array(
				'amountBase' => $order->order_subtotal,
				'cardNumber' => $_POST['ccnum'],
				'cardExpMonth' => $_POST['expmonth'],
				'cardExpYear' => $_POST['expyear'],
				'status' => $this->salemethod,
				'billingFirstName' => $order->billing_first_name,
				'billingLastName' => $order->billing_last_name,
				'billingAddress' => $order->billing_address_1,
				'billingCity' => $order->billing_city,
				'billingState' => $order->billing_state,
				'billingZip' => $order->billing_postcode,
				'billingCountry' => $order->billing_country,
				'shippingFirstName' => $order->shipping_first_name,
				'shippingLastName' => $order->shipping_last_name,
				'shippingAddress' => $order->shipping_address_1,
				'shippingCity' => $order->shipping_city,
				'shippingState' => $order->shipping_state,
				'shippingZip' => $order->shipping_postcode,
				'shippingCountry' => $order->shipping_country,
				'note' => "Customer ID: ".$order->user_id,
				'invoiceNumber' => $order->id,
				'avs' => $this->avsmode
			);
			$total_amount += (float)$order->order_subtotal;
			if (isset($order->order_shipping)) { // Add shipping amount
				$payjunction_request['amountShipping'] = $order->order_shipping;
				$total_amount += (float)$order->order_shipping;
			}
			
			if (isset($order->order_tax['*']['amount'])) { // Add tax amount
				$payjunction_request['amountTax'] = $order->order_tax['*']['amount'];
				$total_amount += $order->order_tax['*']['amount'];
			}
			
			if (isset($order->order_shipping_tax)) { // Add shipping tax
				$tax = $payjunction_request['amountTax'];
				$s_tax = (float)$order->order_shipping_tax;
				$total_tax = $tax + $s_tax;
				$payjunction_request['amountTax'] = sprintf("%.2f", $total_tax);
				$total_amount += (float)$order->order_shipping_tax;
			}
			
			// Make sure that we've added everything together by comparing with the total amount we've collected so far
			if (sprintf("%.2f", $order->order_total) != sprintf("%.2f", $total_amount)) {
				// For some reason, we haven't gotten all the costs. Run the base amount as the order total and remove the shipping and tax
				// to make sure we don't undercharge or overcharge the customer.
				$payjunction_request['amountTax'] = '';
				$payjunction_request['amountShipping'] = '';
				$payjunction_request['amountBase'] = $order->order_total;
				$payjunction_request['note'] .= "\nJigoShop module was unable to determine the tax and shipping, processed as a total amount instead.";
				$payjunction_request['note'] .= sprintf("\nOrder Total: %.2f\nComputed Total: %.2f", $order->order_total, $total_amount);
			}
			
			if ($this->dynavsmode) {
				$payjunction_request['avs'] = 'BYPASS';
			}
			
			if ($this->cvvmode) {
				$payjunction_request['cvv'] = 'ON';
				$payjunction_request['cardCvv'] = $_POST['cvv'];
			} else {
				$payjunction_request['cvv'] = 'OFF';
			}
			
			// Build the query string...
			foreach($payjunction_request as $key => $value) {
				$post .= urlencode($key) . '=' . urlencode($value) . '&';
			}
			
			$post = substr($post, 0, -1);
			$content = $this->process_rest_request('POST', $post);
			
			if (isset($content['transactionId'])) { // Valid response
				$transactionId = $content['transactionId'];
				$order->add_order_note(__('PJ TransactionId: ' . $transactionId));
				$resp_code = $content['response']['code'];
				if (strcmp($resp_code, '00') == 0 || strcmp($resp_code, '85') == 0) {
					// Successful Payment
					if ($this->salemethod == "HOLD") $order->add_order_note(__("Don't forget to Capture or Void the transaction in PayJunction!", 'jigoshop'));
					if ($this->dynavsmode) {
						// See what the results were for AVS check
						$address = $content['response']['processor']['avs']['match']['ADDRESS'];
						$zip = $content['response']['processor']['avs']['match']['ZIP'];
						$note = __(sprintf('Placed on Hold Status due to Address Match: %s and Zip Match: %s (Dynamic AVS)', $address == true ? 'true' : 'false', $zip == true ? 'true' : 'false'), 'jigoshop');
						// See what AVS mode we're in and compare accordingly
						if ($this->avsmode == 'ADDRESS_AND_ZIP') {
							if ($address && $zip) {
								$order->add_order_note(__('Credit Card/Debit Card payment completed', 'jigoshop'));
								$order->payment_complete();
							} else {
								$order->update_status('on-hold', $note);
								if ($this->salemethod != 'HOLD') { 
									$this->set_payjunction_hold($transactionId);
									$order->add_order_note(__("Don't forget to Capture or Void the transaction in PayJunction!", 'jigoshop'));
								}
								jigoshop_cart::empty_cart();
							}
						} elseif ($this->avsmode == 'ADDRESS_OR_ZIP') {
							if ($address || $zip) {
								$order->add_order_note(__('Credit Card/Debit Card payment completed', 'jigoshop'));
								$order->payment_complete();
							} else {
								$order->update_status('on-hold', $note);
								if ($this->salemethod != 'HOLD') { 
									$this->set_payjunction_hold($transactionId);
									$order->add_order_note(__("Don't forget to Capture or Void the transaction in PayJunction!", 'jigoshop'));
								}
								jigoshop_cart::empty_cart();
							}
						} elseif ($this->avsmode == 'ADDRESS') {
							if ($address) {
								$order->add_order_note(__('Credit Card/Debit Card payment completed', 'jigoshop'));
								$order->payment_complete();
							} else {
								$order->update_status('on-hold', __(sprintf('Placed on Hold Status due to Address Match: %s (Dynamic AVS)', $address == true ? 'true' : 'false'), 'jigoshop'));
								if ($this->salemethod != 'HOLD') { 
									$this->set_payjunction_hold($transactionId);
									$order->add_order_note(__("Don't forget to Capture or Void the transaction in PayJunction!", 'jigoshop'));
								}
								jigoshop_cart::empty_cart();
							}
						} elseif ($this->avsmode == 'ZIP') {
							if ($zip) {
								$order->add_order_note(__('Credit Card/Debit Card payment completed', 'jigoshop'));
								$order->payment_complete();
							} else {
								$order->update_status('on-hold', __(sprintf('Placed on Hold Status due to Zip Match: %s (Dynamic AVS)', $zip == true ? 'true' : 'false'), 'jigoshop'));
								if ($this->salemethod != 'HOLD') { 
									$this->set_payjunction_hold($transactionId);
									$order->add_order_note(__("Don't forget to Capture or Void the transaction in PayJunction!", 'jigoshop'));
								}
								jigoshop_cart::empty_cart();
							}
						} else {
							$order->add_order_note(__('Credit Card/Debit Card payment completed', 'jigoshop'));
							$order->payment_complete();
						}
						return array(
							'result' 	=> 'success',
							'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink( $checkout_redirect )))
						);
					} else {
						$order->add_order_note(__('Credit Card/Debit Card payment completed', 'jigoshop'));
						$order->payment_complete();
						
						// Return thankyou redirect
						return array(
							'result' 	=> 'success',
							'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink( $checkout_redirect )))
						);
					}
					
				} else {
					// Non-successful Payment (boo...)
					$cancelNote = __(sprintf('PayJunction payment failed (Code: %s, Message: %s).', $resp_code, $content['response']['message']), 'jigoshop');
			
					$order->add_order_note( $cancelNote );
					// To (again) try and prevent multiple attempts when the decline is for AVS/CVV mismatch, use different error messages
					
					if ($this->is_fraud_decline($resp_code)) {
						jigoshop::add_error(__('Payment error, before attempting to process again please contact us directly for assistance.', 'jigoshop'));
					} else {
						jigoshop::add_error(__('Transaction Declined.', 'jigoshop'));
					}
				}
			} else {
				$error = 'There was at least one unrecoverable error:';
				foreach ($content['errors'] as $err) {
					$error .= sprintf('<br>%s',  $err['message']);
				}
				jigoshop::add_error(__($error, 'jigoshop'));
				$order->add_order_note(__($error, 'jigoshop'));
			}
		}
		
		function is_fraud_decline($resp) {
			$fraud_declines = array("AA", "AI", "AN", "AU", "AW", "AX", "AY", "AZ", "CN", "CV");
			return in_array($resp, $fraud_declines);
		}
		
		/**
		Validate payment form fields
		**/
		
		public function validate_fields() {
			
			$cardNumber = $_POST['ccnum'];
			$cardCSC = isset($_POST['cvv']) ? $_POST['cvv'] : null;
			$cardExpirationMonth = $_POST['expmonth'];
			$cardExpirationYear = $_POST['expyear'];
	
			if ($this->cvvmode) {
				//check security code
				if(!ctype_digit($cardCSC)) {
					jigoshop::add_error(__('Card security code is invalid (only digits are allowed)', 'jigoshop'));
					return false;
				}
			}
	
			//check expiration data
			$currentYear = date('Y');
			
			if(!ctype_digit($cardExpirationMonth) || !ctype_digit($cardExpirationYear) ||
				 $cardExpirationMonth > 12 ||
				 $cardExpirationMonth < 1 ||
				 $cardExpirationYear < $currentYear ||
				 $cardExpirationYear > $currentYear + 20
			) {
				jigoshop::add_error(__('Card expiration date is invalid', 'jigoshop'));
				return false;
			}
	
			//check card number
			$cardNumber = str_replace(array(' ', '-'), '', $cardNumber);
	
			if(empty($cardNumber) || !ctype_digit($cardNumber)) {
				jigoshop::add_error(__('Card number is invalid', 'jigoshop'));
				return false;
			}
			return true;
		}
	}
}
?>
