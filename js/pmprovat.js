jQuery(document).ready(function(){
	jQuery("#reg_price").hide();
	
	vat_number_verified = get_query_var('vat_number_verified');
	
	if(vat_number_verified == 1)
	{
		jQuery("#reg_price").show();
		jQuery("#vat_price").hide();
	}
	
	jQuery("select[name='bcountry']").change(function() {
			
		var country = jQuery('select[name="bcountry"] option:selected').val();
		var country_by_ip = jQuery('#geo_ip').val();
		var showHideVATTable;
		showHideVATTable = jQuery.inArray(country, pmprovat.eu_array);
				
		if(showHideVATTable > -1 && vat_number_verified != 1)
		{
			jQuery('#pmpro_vat_table').show();
			
			jQuery('#eu_self_id_instructions_tr').hide();
			if(jQuery('#eucountry').val() == '' && jQuery('select[name=bcountry]').val() != '')
				jQuery('#eucountry').val(jQuery('select[name=bcountry]').val());
			
			//Only show the self identify box if IP Geolocation & Billing address don't match
			if(country_by_ip == country)
				jQuery('#vat_confirm_country').hide();
			else
				jQuery('#vat_confirm_country').show();
		}
		else
		{
			jQuery('#pmpro_vat_table').hide();
			jQuery('#pmpro_vat_table').focus();
		}
		
	}).change();
	
	jQuery('#vat_number_validation_button').click(function() {
		var vat_number = jQuery('#vat_number').val();
		var country = jQuery('select[name="bcountry"] option:selected').val();
		
		if(vat_number)
		{
			jQuery.ajax({
				url: pmprovat.ajaxurl,
				type:'GET',
				timeout: pmprovat.timeout,
				dataType: 'text',
				data: "action=pmprovat_vat_verification_ajax_callback&country=" + country + "&vat_number=" + vat_number,
				error: function(xml){					
					alert('Error verifying VAT [2]');
					jQuery("#reg_price").hide();
					jQuery("#vat_price").show();
					},
				success: function(responseHTML)
				{
					if(responseHTML.trim() == 'true')
					{
						jQuery('#pmpro_message, #vat_number_message').show();							
						jQuery('#pmpro_message, #vat_number_message').addClass('pmpro_success');
						jQuery('#pmpro_message, #vat_number_message').html('VAT number was verifed. You will not be charged a VAT');
						jQuery("#reg_price").show();
						jQuery("#vat_price").hide();

						jQuery('<input>').attr({
							type: 'hidden',
							id: 'vat_number_verified',
							name: 'vat_number_verified',
							value: '1'
						}).appendTo('#pmpro_form');
						
						jQuery('<input>').attr({
							type: 'hidden',
							id: 'vat_number',
							name: 'vat_number',
							value: vat_number
						}).appendTo('#pmpro_form');
					}
					else
					{
						jQuery('#pmpro_message, #vat_number_message').show();
						jQuery('#pmpro_message, #vat_number_message').removeClass('pmpro_success');
						jQuery('#pmpro_message, #vat_number_message').addClass('pmpro_error');
						jQuery('#pmpro_message, #vat_number_message').html('VAT number was not verifed. Please try again.');
						jQuery("#reg_price").hide();
						jQuery("#vat_price").show();
					}
				}
			});
		}	
	});
	
	function pmprovt_toggleVAT() {
		if(jQuery('#show_vat').is(":checked"))
		{
			jQuery('#vat_number_validation_tr').show();
			jQuery('#pmpro_vat_table').focus();
		}
		
		else
		{
			jQuery('#vat_number_validation_tr').hide();
			jQuery('#pmpro_vat_table').focus();
		}
	}
	
	//Taken from: http://snipplr.com/view/26662/get-url-parameters-with-jquery--improved/
	function get_query_var(name) {
		var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(window.location.href);
		if (!results) { return 0; }
		return results[1] || 0;
	}
	
	//toggle when checking
	jQuery('#show_vat').change(function(){
		pmprovt_toggleVAT();
	});
	
	//toggle on load
	pmprovt_toggleVAT();
});