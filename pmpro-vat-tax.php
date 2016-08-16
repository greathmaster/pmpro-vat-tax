<?php
/*
Plugin Name: Paid Memberships Pro - VAT Tax
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-vat-tax/
Description: Calculate VAT tax at checkout and allow customers with a VAT Number lookup for VAT tax exemptions in EU countries.
Version: .3
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
Text Domain: pmprovat
*/

//uses: https://github.com/herdani/vat-validation/blob/master/vatValidation.class.php
//For EU VAT number checking.

/**
 * Load plugin textdomain.
 */
function pmprovat_load_textdomain() {
	$domain = 'pmprovat';
	$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

	load_textdomain( $domain ,  WP_LANG_DIR.'/pmpro-vat-tax/'.$domain.'-'.$locale.'.mo' );
	load_plugin_textdomain( $domain , FALSE ,  dirname(plugin_basename(__FILE__)). '/languages/' );
}

add_action( 'plugins_loaded', 'pmprovat_load_textdomain' );


/**
 * Setup required classes and global variables.
 */
function pmprovat_init()
{
	global $pmpro_vat_by_country;

	$pmpro_vat_by_country = array(
		"BE" => 0.21,
		"BG" => 0.20,
		"CZ" => 0.21,
		"DK" => 0.25,
		"DE" => 0.19,
		"EE" => 0.20,
		"EL" => 0.23,
		"ES" => 0.21,
		"FR" => 0.20,
		"HR" => 0.25,
		"IE" => 0.23,
		"IT" => 0.22,
		"CY" => 0.19,
		"LV" => 0.21,
		"LT" => 0.21,
		"LU" => 0.17,
		"HU" => 0.27,
		"MT" => 0.18,
		"NL" => 0.21,
		"AT" => 0.20,
		"PL" => 0.23,
		"PT" => 0.23,
		"RO" => 0.24,
		"SI" => 0.22,
		"SK" => 0.20,
		"FI" => 0.24,
		"SE" => 0.25,
		"UK" => 0.20,
		"CA" => array("BC" => 0.05)
	);

	/**
	 * Filter to add or filter vat taxes by country
	 */
	$pmpro_vat_by_country = apply_filters('pmpro_vat_by_country', $pmpro_vat_by_country);

	//Identify EU countries
	global $pmpro_european_union;

	$pmpro_european_union = array(""	 => __( "- Choose One -" , 'pmprovat' ),
							"NOTEU" => __( "Non-EU Resident" , 'pmprovat' ),
							"BE"  => __( "Belgium" , 'pmprovat' ),
							"BG"  => __( "Bulgaria" , 'pmprovat' ),
							"CZ"  => __( "Czech Republic", 'pmprovat' ),
							"DK"  => __( "Denmark" , 'pmprovat' ),
							"DE"  => __( "Germany" , 'pmprovat' ),
							"EE"  => __( "Estonia" , 'pmprovat' ),
							"IE"  => __( "Ireland" , 'pmprovat' ),
							"EL"  => __( "Greece" , 'pmprovat' ),
							"ES"  => __( "Spain" , 'pmprovat' ),
							"FR"  => __( "France" , 'pmprovat' ),
							"IT"  => __( "Italy" , 'pmprovat' ),
							"CY"  => __( "Cyprus" , 'pmprovat' ),
							"LV"  => __( "Latvia" , 'pmprovat' ),
							"LT"  => __( "Lithuania" , 'pmprovat' ),
							"LU"  => __( "Luxembourg" , 'pmprovat' ),
							"HU"  => __( "Hungary" , 'pmprovat' ),
							"MT"  => __( "Malta" , 'pmprovat' ),
							"NL"  => __( "Netherlands" , 'pmprovat' ),
							"AT"  => __( "Austria" , 'pmprovat' ),
							"PL"  => __( "Poland" , 'pmprovat' ),
							"PT"  => __( "Portugal" , 'pmprovat' ),
							"RO"  => __( "Romania" , 'pmprovat' ),
							"SI"  => __( "Slovenia" , 'pmprovat' ),
							"SK"  => __( "Slovakia" , 'pmprovat' ),
							"FI"  => __( "Finland" , 'pmprovat' ),
							"SE"  => __( "Sweden" , 'pmprovat' ),
							"UK"  => __( "United Kingdom", 'pmprovat' )
						    );

	/**
	 * Filter to add/edit EU countries
	 */
	$pmpro_european_union = apply_filters('pmpro_european_union', $pmpro_european_union);

	add_action( 'wp_ajax_nopriv_pmprovat_vat_verification_ajax_callback', 'pmprovat_vat_verification_ajax_callback' );
	add_action( 'wp_ajax_pmprovat_vat_verification_ajax_callback', 'pmprovat_vat_verification_ajax_callback' );
}
add_action("init", "pmprovat_init");

/**
 * Enqueue VAT JS on checkout page
 */
function pmprovat_enqueue_scripts() {
	global $pmpro_pages, $pmpro_european_union;

	//PMPro not active
	if(empty($pmpro_pages))
		return;

	//only if we're on the checkout page
	if(!empty($_REQUEST['level']) || is_page($pmpro_pages['checkout'])) {
		//register
		wp_register_script('pmprovat', plugin_dir_url( __FILE__ ) . 'js/pmprovat.js');

		//get values
		wp_localize_script('pmprovat', 'pmprovat',
			array(
				'eu_array' => array_keys($pmpro_european_union),
				'ajaxurl' => admin_url('admin-ajax.php'),
				'timeout' => apply_filters("pmpro_ajax_timeout", 5000, "applydiscountcode"),
			)
		);
		//enqueue
		wp_enqueue_script('pmprovat', NULL, array('jquery'), '.1');
	}
}
add_action('wp_enqueue_scripts', 'pmprovat_enqueue_scripts');

/**
 * Get VAT Validation Class
 */
function pmprovat_get_VAT_validation() {
	global $vatValidation;
	if(empty($vatValidation))
	{
		if(!class_exists("vatValidation"))
		{
			require_once(dirname(__FILE__) . "/includes/vatValidation.class.php");
		}

		$vatValidation = new vatValidation(array('debug' => false));
	}

	return $vatValidation;
}

/**
 * Helper function to verify a VAT number.
 */
function pmprovat_verify_vat_number($country, $vat_number)
{
	$vatValidation = pmprovat_get_VAT_validation();

	if(empty($country) || empty($vat_number))
	{
		$result = false;
	}

	else
	{
		$result = $vatValidation->check($country, $vat_number);
	}

	$result = apply_filters('pmprovat_custom_vat_number_validate', $result);

	return $result;
}

/**
 * Show VAT country and number field at checkout.
 */
function pmprovat_pmpro_checkout_boxes()
{
	global $pmpro_european_union;?>

<table id="pmpro_vat_table" class="pmpro_checkout" width="100%" cellpadding="0" cellspacing="0" border="0">
<thead>
	<tr>
		<th>
			<?php _e('European Union Residents VAT', 'pmprovat');?>
		</th>
	</tr>
</thead>
<tbody>
	<tr id="vat_confirm_country">
		<td>
			<div>
				<?php
				//Add section below if billing address is from EU country
				if(!empty($_REQUEST['eucountry']))
					$eucountry = $_REQUEST['eucountry'];
				else
					$eucountry = "";
				?>
				<div id="eu_self_id_instructions"><?php _e('EU customers must confirm country of residence for VAT.', 'pmprovat');?></div>
				<label for="eucountry"><?php _e('Country of Residence', 'pmprovat');?></label>
					<select id="eucountry" name="eucountry" class=" <?php echo pmpro_getClassForField("eucountry");?>">
						<?php
							foreach($pmpro_european_union as $abbr => $country)
							{?>
								<option value="<?php echo $abbr?>" <?php selected($eucountry, $abbr);?>><?php echo $country?></option><?php
							}
						?>
					</select>

				<?php //Hidden field to enable tax?>

				<input type="hidden" id="taxregion" name="taxregion" value="1">
				<input type="hidden" id="geo_ip" name="geo_ip" value=<?php echo pmprovat_determine_country_from_ip(); ?>>
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<div><input id="show_vat" type="checkbox" name="show_vat" value="1"> <label for="show_vat" class="pmpro_normal pmpro_clickable"><?php _e('I have a VAT number', 'pmprovat');?></label></div>
		</td>
	</tr>

	<tr id="vat_number_validation_tr">
		<td>
			<div>
				<label for="vat_number"><?php _e('Vat Number', 'pmprovat');?></label>
				<input id="vat_number" name="vat_number" class="input" type="text"  size="20" value="<?php ?>" />
				<input type="button" name="vat_number_validation_button" id="vat_number_validation_button" value="<?php _e('Apply', 'pmpro');?>" />
				<p id="vat_number_message" class="pmpro_message" style="display: none;"></p>
			</div>
		</td>
	</tr>
</tbody>
</table>
<?php
}
add_action("pmpro_checkout_after_billing_fields", "pmprovat_pmpro_checkout_boxes");

/**
 * AJAX callback to check the VAT number.
 */
function pmprovat_vat_verification_ajax_callback()
{
	$vat_number = $_REQUEST['vat_number'];
	$country = $_REQUEST['country'];

	$result = pmprovat_verify_vat_number($country, $vat_number);

	if($result)
		echo "true";
	else
		echo "false";

	exit();
}

function pmprovat_pmpro_paypal_express_return_url_parameters($params)
{	
	if (!empty($_REQUEST['vat_number_verified']))
	{
		$params["vat_number_verified"] = isset($_REQUEST['vat_number_verified']) ? intval($_REQUEST['vat_number_verified']) : null;
		$params["vat_number"] = isset($_REQUEST['vat_number']) ? intval($_REQUEST['vat_number']) : null;
	}
	
	$params["bcountry"] = isset($_REQUEST['bcountry']) ? $_REQUEST['bcountry'] : null;
	$params["eucountry"] = isset($_REQUEST['eucountry']) ? $_REQUEST['eucountry'] : null;
	$params["self_identify"] = isset($_REQUEST['self_identify']) ? $_REQUEST['self_identify'] : null;

	return $params;	
}

add_filter('pmpro_paypal_express_return_url_parameters', 'pmprovat_pmpro_paypal_express_return_url_parameters');

function pmprovat_pmpro_paypal_standard_nvpstr($str)
{
	if (!empty($_REQUEST['vat_number_verified']))
	{
		$vat_number = $_REQUEST['vat_number'];
		$vat_number_verified = $_REQUEST['vat_number_verified'];
		
		$str = $str."&vat_number=".$vat_number."&vat_number_verified=".$vat_number_verified;
	}

	return $str;
}

add_filter('pmpro_paypal_standard_nvpstr', 'pmprovat_pmpro_paypal_standard_nvpstr');

/**
 * Check self identified country with billing address country and verify VAT number
 */
function pmprovat_check_vat_fields_submission($value)
{
	global $pmpro_european_union, $pmpro_msg, $pmpro_msgt;

	if(!empty($_REQUEST['bcountry']))
		$bcountry = $_REQUEST['bcountry'];
	else
		$bcountry = "";

	if(!empty($_REQUEST['eucountry']))
		$eucountry = $_REQUEST['eucountry'];
	else
		$eucountry = "";
	
	if(!empty($_REQUEST['geo_ip']))
		$country_by_ip = $_REQUEST['geo_ip'];
	else
		$country_by_ip = '';
	
	//only if billing country is an EU country
	if(!empty($bcountry) && array_key_exists($bcountry, $pmpro_european_union))
	{
		if($country_by_ip != $bcountry)
		{
			//The IP Geolocation is disagreeing with the Billing Address & the self
			//identifcation is incorrect.
			if($bcountry !== $eucountry)
			{
				$pmpro_msg = "Billing country and country self identification must match";
				$pmpro_msgt = "pmpro_error";
				$value = false;	
			}
		}
	}

	//they checked to box for VAT Number and entered the number but didn't
	//actually hit "Apply". If it verifies, go through with checkout
	//otherwise, assume they made a mistake and stop the checkout

	$vat_number = $_REQUEST['vat_number'];

	if(!empty($_REQUEST['show_vat']))
		$show_vat = 1;
	else
		$show_vat = 0;

	if($show_vat && !pmprovat_verify_vat_number($bcountry, $vat_number))
	{
		$pmpro_msg = __( "VAT number was not verifed. Please try again.",  'pmprovat' );
		$pmpro_msgt = "pmpro_error";
		$value = false;
	}

	return $value;
}

add_filter("pmpro_registration_checks", "pmprovat_check_vat_fields_submission");

/**
 * Update tax calculation if buyer is in EU or other states that charge VAT
 */
function pmprovat_region_tax_check()
{
	//check request and session
	if(isset($_REQUEST['taxregion']))
	{
		//update the session var
		$_SESSION['taxregion'] = $_REQUEST['taxregion'];

		//not empty? setup the tax function
		if(!empty($_REQUEST['taxregion']))
		{
			add_filter("pmpro_tax", "pmprovat_pmpro_tax", 10, 3);
		}
	}
	elseif(!empty($_SESSION['taxregion']))
	{
		//add the filter
		add_filter("pmpro_tax", "pmprovat_pmpro_tax", 10, 3);
	}
	else
	{
		add_filter("pmpro_tax", "pmprovat_pmpro_tax", 10, 3);
	}
	
	if(isset($_REQUEST['vat_number']) && isset($_REQUEST['vat_number_verified']))
	{
		$_SESSION['vat_number']	= $_REQUEST['vat_number'];
		$_SESSION['vat_number_verified']	= $_REQUEST['vat_number_verified'];
	}
}
add_action("init", "pmprovat_region_tax_check");

/**
 * Apply the VAT tax if an EU country is chosen at checkout.
 */
function pmprovat_pmpro_tax($tax, $values, $order)
{
	global $current_user, $pmpro_vat_by_country;

	if(!empty($_REQUEST['vat_number']))
		$vat_number = $_REQUEST['vat_number'];
	else
		$vat_number = "";

	if(!empty($_REQUEST['eucountry']))
		$eucountry = $_REQUEST['eucountry'];
	elseif(!empty($values['billing_country']))
		$eucountry = $values['billing_country'];
	else
		$eucountry = "";

	if(!empty($_REQUEST['show_vat']))
		$show_vat = 1;
	else
		$show_vat = 0;

	if(!empty($_REQUEST['vat_number_verified']) && $_REQUEST['vat_number_verified'] == "1")
		$vat_number_verified = true;
	else
		$vat_number_verified = false;

	$vat_rate = 0;

	//They didn't use the AJAX verify. Either they don't have a VAT number or
	//entered it didn't use it.
	if(!$vat_number_verified)
	{
		//they didn't use AJAX verify. Verify them now.
		if(!empty($vat_number) && !empty($eucountry) && pmprovat_verify_vat_number($eucountry, $vat_number))
		{
			$vat_rate = 0;
			
			//set this so when we return from PayPal Express (w/extra step) we will show the non-VAT price.
			$_REQUEST['vat_number_verified'] = true;
		}
		//they don't have a VAT number.
		elseif(!empty($eucountry) && array_key_exists($eucountry, $pmpro_vat_by_country))
		{
			//state VAT like British Columbia Canada
			if(is_array($pmpro_vat_by_country[$eucountry]))
			{
				if(!empty($_REQUEST['bstate']))
					$state = $_REQUEST['bstate'];
				else
					$state = "";

				if(!empty($state) && array_key_exists($state, $pmpro_vat_by_country[$values['billing_country']]))
				{
					$vat_rate = $pmpro_vat_by_country[$values['billing_country']][$state];
				}
			}
			else
				$vat_rate = $pmpro_vat_by_country[$eucountry];
		}
	}

	if(!empty($vat_rate))
		$tax = $tax + pmprovat_calculate_vat($values['price'], $vat_rate, $eucountry);

	return $tax;
}

function pmprovat_calculate_vat($price, $rate, $country)
{
	$tax = round((float)$price * $rate, 2);
	
	//Use the filter for custom rounding rules
	$tax = apply_filters('pmprovat_calculate_custom_vat', $tax, $price, $rate, $country);
	
	return $tax;
}

function pmprovat_get_vat_rate($country, $state = false)
{
	if($country == 'GB')
		$country = 'UK';
	
	if($country == 'GR')
		$country = 'EL';
	
	global $pmpro_vat_by_country;
	
	if(!$state)
		$vat_rate = $pmpro_vat_by_country[$country];
	
	return $vat_rate;
}

/**
 * Remove the taxregion session var on checkout
 */
function pmprovat_pmpro_after_checkout()
{		
	if(isset($_SESSION['vat_number']))
		unset($_SESSION['vat_number']);
		
	if(isset($_SESSION['vat_number_verified']))
		unset($_SESSION['vat_number_verified']);
	
	if(isset($_SESSION['taxregion']))
		unset($_SESSION['taxregion']);
}
add_action("pmpro_after_checkout", "pmprovat_pmpro_after_checkout");
add_action('pmpro_before_send_to_paypal_standard', 'pmprovat_pmpro_after_checkout', 10);

//Add to admin order pages
function pmprovat_pmpro_add_cols_header($order_ids)
{
	global $pmpro_currency;?>

	<th>VAT Verification</th>
	<th>VAT Number</th>
	<th>IP</th>
	<th>Database</th>
	<th>Percentage</th>
	<th>Tax</th>
	<th>Country</th>
	<th>Conversion Rate ( <?php echo $pmpro_currency. ' to EURO'; ?>) </th><?php
}
add_filter('pmpro_orders_extra_cols_header', 'pmprovat_pmpro_add_cols_header');

function pmprovat_pmpro_add_cols_body($order)
{
	global $pmpro_currency_symbol;
	
	$vat_method 			= pmpro_getMatches("/{EU_VAT_VERIFY_SOURCE:([^}]*)}/", $order->notes, true);
	$vat_percent 			= pmpro_getMatches("/{EU_VAT_PERCENT:([^}]*)}/", $order->notes, true);
	$vat_geo_ip_database 	= pmpro_getMatches("/{EU_VAT_GEO_IP_DATABASE:([^}]*)}/", $order->notes, true);
	$vat_geo_ip 			= pmpro_getMatches("/{EU_VAT_GEO_IP:([^}]*)}/", $order->notes, true);
	$vat_country 			= pmpro_getMatches("/{EU_VAT_COUNTRY:([^}]*)}/", $order->notes, true);
	$conversion_rate		= pmpro_getMatches("/{EU_VAT_EURO_RATE:([^}]*)}/", $order->notes, true);
	$vat_number			= pmpro_getMatches("/{EU_VAT_NUMBER:([^}]*)}/", $order->notes, true);
		
	if(!empty($vat_method))	
		echo '<td>'.$vat_method.'</td>';
	else
		echo '<td></td>';
	
	if(!empty($vat_number))	
		echo '<td>'.$vat_number.'</td>';
	else
		echo '<td></td>';
	
	if(!empty($vat_geo_ip))
		echo '<td>'.$vat_geo_ip.'</td>';
	else
		echo '<td></td>';
	
	if(!empty($vat_geo_ip_database))
		echo '<td>'.$vat_geo_ip_database.'</td>';
	else
		echo '<td></td>';
	
	if(!empty($vat_percent))
		echo '<td>'.$vat_percent.'</td>';
	else
		echo '<td></td>';
	
	if(!empty($order->tax))
		echo '<td>'.$pmpro_currency_symbol.$order->tax.'</td>';
	else
		echo '<td></td>';
	
	if(!empty($vat_country))
		echo '<td>'.$vat_country.'</td>';
	else
		echo '<td></td>';
	
	if(!empty($conversion_rate))
		echo '<td>'.$conversion_rate.'</td>';
	else
		echo '<td></td>';	
}
add_filter('pmpro_orders_extra_cols_body', 'pmprovat_pmpro_add_cols_body');

function pmprovat_pmpro_invoice_bullets_bottom($pmpro_invoice)
{
	global $pmpro_currency_symbol;
	
	$vat_percent	= pmpro_getMatches("/{EU_VAT_PERCENT:([^}]*)}/", $pmpro_invoice->notes, true);
	$vat_number	= pmpro_getMatches("/{EU_VAT_NUMBER:([^}]*)}/", $pmpro_invoice->notes, true);

	if(isset($vat_percent) && $vat_percent > 0)
		echo '<li><strong>VAT Rate: </strong>'.$vat_percent*100 .'%</li>';
	elseif(isset($vat_number))
		echo '<li><strong>VAT Number: </strong>'.$vat_number.'</li>';
}

add_action('pmpro_invoice_bullets_bottom', 'pmprovat_pmpro_invoice_bullets_bottom');

function pmprovat_pmpro_orders_csv_extra_columns($columns)
{
	$columns["vat_method"]		= "pmprovat_extra_column_vat_method";
	$columns["vat_number"]		= "pmprovat_extra_column_vat_number";
	$columns["percent"]			= "pmprovat_extra_column_vat_percent";
	$columns["ip"]				= "pmprovat_extra_column_vat_ip";
	$columns["source"]			= "pmprovat_extra_column_vat_source";
	$columns["conversion_rate"]	= "pmprovat_extra_column_vat_conversion_rate";

	return $columns;
}
add_filter("pmpro_orders_csv_extra_columns", "pmprovat_pmpro_orders_csv_extra_columns", 10);

function pmprovat_extra_column_vat_method($order)
{
	$vat_method = pmpro_getMatches("/{EU_VAT_VERIFY_SOURCE:([^}]*)}/", $order->notes, true);
	
	if($vat_method)
	{
		return $vat_method;
	}
	else
		return '';
}

function pmprovat_extra_column_vat_number($order)
{
	$vat_number = pmpro_getMatches("/{EU_VAT_NUMBER:([^}]*)}/", $order->notes, true);
	
	if($vat_number)
	{
		return $vat_number;
	}
	else
		return '';
}

function pmprovat_extra_column_vat_ip($order)
{
	$vat_geo_ip = pmpro_getMatches("/{EU_VAT_GEO_IP:([^}]*)}/", $order->notes, true);
	
	if($vat_geo_ip)
	{
		return $vat_geo_ip;
	}
	else
		return '';
}

function pmprovat_extra_column_vat_source($order)
{
	$vat_geo_ip_database = pmpro_getMatches("/{EU_VAT_GEO_IP_DATABASE:([^}]*)}/", $order->notes, true);
	
	if($vat_geo_ip_database)
	{
		return $vat_geo_ip_database;
	}
	else
		return '';	
}

function pmprovat_extra_column_vat_percent($order)
{
	$vat_percent = pmpro_getMatches("/{EU_VAT_PERCENT:([^}]*)}/", $order->notes, true);

	if($vat_percent)
	{
		return $vat_percent;
	}
	else
		return '';	
	
}

function pmprovat_extra_column_vat_conversion_rate($order)
{
	$conversion_rate = pmpro_getMatches("/{EU_VAT_EURO_RATE:([^}]*)}/", $order->notes, true);
	
	if($conversion_rate)
		return $conversion_rate;
	else
		return '';
}


/**
 * Function to add links to the plugin row meta
 */
function pmprovat_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-vat-tax.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plus-add-ons/vat-tax/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmprovat' ) ) . '">' . __( 'Support', 'pmprovat' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmprovat_plugin_row_meta', 10, 2);

function pmprovat_determine_country_from_ip()
{
	global $country_from_ip;
	
	//check if the GEO IP Detect plugin is active
	if(!defined('GEOIP_DETECT_VERSION'))
		return false;
	
	if(!isset($country_from_ip))
	{
		//get the country
		$record = geoip_detect2_get_info_from_current_ip();
		$country_from_ip = $record->country->isoCode;

		if(empty($country_from_ip))
			$country_from_ip = false;
	}
	
	return $country_from_ip;
}

/**
 * Show VAT tax info below level cost text.
 */
function pmprovat_pmpro_level_cost_text($r, $level, $tags, $short)
{	
	if(isset($_REQUEST['self_identify']))
		$self_identify = $_REQUEST['self_identify'];
	else
		$self_identify = 0;
	
	if(isset($_REQUEST['bcountry']))
		$bcountry = $_REQUEST['bcountry'];
	else
		$bcountry = 0;
	
	global $already_run, $country_from_ip, $pmpro_vat_by_country, $pmpro_european_union, $pmpro_pages, $post;
	if(!is_array($already_run))
		$already_run = array();
	
	if(!isset($already_run[$level->id]) && !is_admin())
	{
		$already_run = array();
	
		$country_from_ip = pmprovat_determine_country_from_ip();
	
		//we are in the EU
		if($country_from_ip && array_key_exists($country_from_ip, $pmpro_european_union))
		{	
			$vat_rate = $pmpro_vat_by_country[$country_from_ip];
			
			if($self_identify == 1)
				$vat_rate = $pmpro_vat_by_country[$bcountry];
		
			if(!empty($vat_rate))
			{
				$level = pmprovat_pmpro_apply_vat_to_level($level, $vat_rate);
			}
		}
		
		$already_run[$level->id] = true;
		
		if($pmpro_pages['checkout'] == $post->ID)
			return '<span id = "reg_price">'.$r.'</span><span id = "vat_price">'.pmpro_getLevelCost($level, $tags, $short).'</span>';
		else return pmpro_getLevelCost($level, $tags, $short);
	}
	
	else
	{
		return $r;
	}
}

add_filter('pmpro_level_cost_text', 'pmprovat_pmpro_level_cost_text', 10, 4);

function pmprovat_pmpro_apply_vat_to_level($level, $vat_rate)
{
	$level->initial_payment = $level->initial_payment * (1 + $vat_rate);
	$level->trial_amount = $level->trial_amount * (1 + $vat_rate);
	$level->billing_amount = $level->billing_amount * (1 + $vat_rate);
	
	return $level;
}

function pmprovat_init_load_session_vars($params)
{
	if(empty($_REQUEST['vat_number_verified']) && !empty($_SESSION['vat_number_verified']))
	{
		$_REQUEST['vat_number_verified']	= $_SESSION['vat_number_verified'];
		$_REQUEST['vat_number']			= $_SESSION['vat_number'];
	}
	
	return $params;
}

add_action('init', 'pmprovat_init_load_session_vars', 5);

//add note with vat data
function pmprovat_pmpro_added_order($order)
{
	global $pmpro_currency, $wpdb;
	
	if(isset($_REQUEST['geo_ip']))
		$country_by_ip = $_REQUEST['geo_ip'];
	
	if(isset($_REQUEST['bcountry']))
		$bcountry = $_REQUEST['bcountry'];
	
	if(isset($_REQUEST['eucountry']))
		$eucountry = $_REQUEST['eucountry'];
	
	if(isset($_REQUEST['vat_number']))
		$vat_number = $_REQUEST['vat_number'];
	
	if(isset($_REQUEST['vat_number_verified']))
		$vat_number_verified = $_REQUEST['vat_number_verified'];
	
	$euro_conversion_rate = pmprovat_get_euro_conversion_rate($pmpro_currency);
	
	$notes = "";
		
	//vat number	
	if(!empty($vat_number) && $vat_number_verified)
	{
		//they used VAT Validation
		$notes .= "\n---\n{EU_VAT_VERIFY_SOURCE: VAT_NUMBER" . "}\n---\n";
		$notes .= "\n---\n{EU_VAT_NUMBER:" . $vat_number . "}\n---\n";
		$notes .= "\n---\n{EU_VAT_COUNTRY:" . $bcountry . "}\n---\n";
	}
	
	//geo ip was used
	elseif($bcountry == $country_by_ip)
	{
		$notes .= "\n---\n{EU_VAT_VERIFY_SOURCE: GEO_IP" . "}\n---\n";
		$notes .= "\n---\n{EU_VAT_GEO_IP:" . geoip_detect2_get_client_ip() . "}\n---\n";
		$notes .= "\n---\n{EU_VAT_GEO_IP_DATABASE:" . geoip_detect2_get_current_source_description() . "}\n---\n";
		$notes .= "\n---\n{EU_VAT_PERCENT:" . pmprovat_get_vat_rate($bcountry) . "}\n---\n";
		$notes .= "\n---\n{EU_VAT_COUNTRY:" . $bcountry . "}\n---\n";
	}
	
	//they self identified
	elseif($bcountry == $eucountry)
	{
		$notes .= "\n---\n{EU_VAT_VERIFY_SOURCE: SELF_IDENTIFY" . "}\n---\n";
		$notes .= "\n---\n{EU_VAT_PERCENT:" . pmprovat_get_vat_rate($bcountry) . "}\n---\n";
		$notes .= "\n---\n{EU_VAT_COUNTRY:" . $bcountry . "}\n---\n";
	}

	if($euro_conversion_rate !== false)
	{
		$notes .= "\n---\n{EU_VAT_EURO_RATE:" . $euro_conversion_rate . "}\n---\n";
	}	
	
	//add conversion rate to note
	$order->notes .= $notes;
	$sqlQuery = "UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($order->notes) . "' WHERE id = '" . intval($order->id) . "' LIMIT 1";
	$wpdb->query($sqlQuery);
	
	return $order;
}
add_action('pmpro_added_order', 'pmprovat_pmpro_added_order');

//get the conversion rate to the Euro
function pmprovat_get_euro_conversion_rate($currency)
{
	//Adapted from:
	//https://www.ecb.europa.eu/stats/exchange/eurofxref/html/index.en.html and
	//https://wordpress.org/plugins/woocommerce-eu-vat-compliance/
	
	//the file is updated daily between 2.15 p.m. and 3.00 p.m. CET
	$XML = simplexml_load_file("http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml");
	
	foreach($XML->Cube->Cube->Cube as $cur)
	{
		if (isset($cur['currency']) && $currency == $cur['currency'] && isset($cur['rate']))
		{
			return (float)$cur['rate'];
		}
	}

	return false;
}
