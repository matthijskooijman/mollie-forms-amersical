jQuery(document).ready(function($) {

/*const dropdown = $('#dropdown');
const container = $('#container-div');
const requiredInputs = $('input[data-required="1"]');

dropdown.change(function(){
  if($(this).val() == 'manual') {
    container.addClass('visible');
    if (requiredInputs) {
      requiredInputs.prop('required', 'true');
    }
  } else {
    container.removeClass('visible');
    if (requiredInputs) {
      requiredInputs.prop('required', 'false');
    }
  }
});*/

	/**
	 * On each element that has a data-show attribute attach a change listener
	 * On change fire the toggle_connected function
	 * When the changed element was a radiobutton,
	 * then we also find all it's mates (i.e. with same name) and trigger toggle_connected on them
	 */
	$("[data-show]").live('change', function() {
		// run calculations (*after* the hide/show animation is
		// complete) because visibility of fields influences the
		// calculations
		callback = run_calculations;
		if($(this).is(':radio')) {
			$('input[name="'+ $(this).attr('name')+'"]').each(function() { // vv-word-vriend
					toggle_connected($(this), callback)
			})
		} else {
			toggle_connected($(this), callback)
		}
	});

	/**
	 * Function to toggle visibility of connected element (connected to the one that
	 * was changed), also depending on the state (checked or not) of the changed element.
	 * @param    element    The element that was changed. $(this) jquery equivalent
	 */
	function toggle_connected(element, duration, callback) {
			id_connected = element.attr('data-show')
			if(element.is(':checked')) {
				$('#'+id_connected).slideDown(duration, callback);
				$('#'+id_connected).find('[data-required-if-shown="1"]').not(':hidden').each(function() {
					$(this).prop('required', true);
				})
			} else {
				$('#'+id_connected).slideUp(duration, callback);
				$('#'+id_connected).find('[data-required-if-shown="1"]').each(function() {
					$(this).prop('required', false);
				})
			}
	}

	// On load of page we first trigger a change everywhere to hide/show everything that needs to be shown/hidden innitially
	$("[data-show]").each(function() {
		toggle_connected($(this), 0);
	})
	run_calculations();

	// All items with data-price attribute (checkboxes)
	// All items with data-price-multiplier attribute (fields with a value)
	$("[data-price], [data-price-multiplier]").live('change', function() {
		run_calculations();
	})

	$("[name=vv-voorstelling]").live('change', function() {
		run_calculations();
	})

	/**
	 * Function to check all fields that have a price to see if they're needed in the calculation of the total price
	 */
	function run_calculations() {
		// when any value changes, throw away the whole total shebang and generate it again
		$('#vv-totaal').html('');

		var total = 0
		var plainsumstring = ''
		var prettysumstring = ''
		// get all data-price fields (checkboxes) and data-price-multiplier fields (selects or textfields)
		$("[data-price], [data-price-multiplier]").each(function() {
			var showlabel = ''
			// data-price fields (i.e. checkboxes)
			if( $(this).is('[data-price]') ) {
				//if it is checked, get the value
				if( $(this).is(':checked') && $(this).is(':visible') ){
					var price = $(this).attr('data-price')
					showlabel = $(this).attr('data-summary-label')
					total += parseFloat(price)
				}
			} else {// it is a data-price-multiplier field (selects or textfields)
				var multiplier = parseFloat($(this).val().replace(',','.'))
				if( $(this).is(':visible') && multiplier > 0) {
					var price = multiplier * parseFloat($(this).attr('data-price-multiplier'))
					total += price

					// if we have a data-summary-label-more then it depends on the value which string we need to display
					if( $(this).is('[data-summary-label-more]')) {
						if( $(this).val() > 1) {
							showlabel = $(this).val() + ' ' + $(this).attr('data-summary-label-more') + ' à  &euro;' + $(this).attr('data-price-multiplier')
						} else {
							showlabel = $(this).val() + ' ' + $(this).attr('data-summary-label-one') + ' à &euro;' + $(this).attr('data-price-multiplier')
						}
					} else {
						showlabel = $(this).attr('data-summary-label');
					}
				}
			}
			if (showlabel) {
				if ($(this).attr('name') == 'vv-aantal-kaarten') {
					var voorstelling = $('[name=vv-voorstelling]').val()
					if (voorstelling)
						showlabel += " (" + voorstelling + ')';
				}

				plainsumstring +=  showlabel + ':  € ' + price + '\n'
				prettysumstring += showlabel + '<span class="amountright">&euro; ' + price + '</span><br/>'
			}
		})
		if (plainsumstring) {

			plainsumstring += '---\nTotaal: € ' + total.toString();
			prettysumstring += '<hr>Nu te betalen:<span class="amountright">&euro; ' + total.toString()+'</span>';

			var yearlyfield = $('input[name="vv-jaarlijkse-donatie-bedrag"]')
			if ( yearlyfield.is(':visible') && yearlyfield.val() ){
				mandatetring = 'Daarnaast machtig je Amersical om hierna jaarlijks &euro; ' + yearlyfield.val().toString() + ' te incasseren.';
				plainsumstring += '\n\n' + mandatetring;
				prettysumstring += '<br/><br/>' + mandatetring;
			}
		} else {
			prettysumstring = "Nog niets geselecteerd";
		}
		$('#vv-totaal').html(prettysumstring);
		// set hidden fields so php can check it and put it in the database
		$('input[name="vv-totaal-bedrag"]').attr('value', total)
		$('input[name="vv-totaal"]').attr('value', plainsumstring)
	}
})
