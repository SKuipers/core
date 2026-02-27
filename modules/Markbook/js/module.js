/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

jQuery(function($){

    // In markbook_edit_data.php, update the attainment value to match raw score
    // But not the other way around, in case teachers need to adjust the value
	$('input[id$="attainmentValueRaw"]').change( function() {

        var attainmentRawMax = $('[name="attainmentRawMax"]');
		// This value wont exist if not using a percent scale
		if (attainmentRawMax.length == false) return;

		$(this).removeClass('highlight');
        $(this).prop('title', '' );

		var index = $(this).attr('name').substr(0, $(this).attr('name').indexOf('-'));
		var thisValue = parseFloat( $(this).val() );
		var maxValue = parseFloat( attainmentRawMax.val() );
        var attainment = $( '#' + index + '-attainmentValue');
        var scaleType = $('[name="attainmentScaleType"]').val();

		if ( $(this).val() == '' || maxValue == 0 || maxValue == '') {
			return;
		}
		else if ( isNaN(thisValue) ) {
			attainment.find('option[value=""]').prop('selected', true);
			$(this).val('');
			return;
        }
        
		if (scaleType == '%') {
			if ( thisValue > maxValue ) {
				thisValue = maxValue;
				$(this).val(thisValue);
			}

			var calculatedValue = Math.round( ( thisValue / maxValue ) * 100  );

			if (calculatedValue >= 0 && calculatedValue <= 100) {
				if (attainment.val( calculatedValue + '%' ).length) {
					attainment.val( calculatedValue + '%' ).prop('selected', true);
				} else {
					attainment.find('option[value=""]').prop('selected', true);
					$(this).addClass('highlight');
				}
			}
		}
	});

	// Highlight a raw value if it doesnt match the percent, but don't change it
	$('select[id$="attainmentValue"]').change( function() {

        var attainmentRawMax = $('[name="attainmentRawMax"]');
        var scaleType = $('[name="attainmentScaleType"]').val();

		// This value wont exist if not using a percent scale
		if (attainmentRawMax.length == false || scaleType != '%') return;

		var index = $(this).attr('name').substr(0, $(this).attr('name').indexOf('-'));
		var attainmentRaw = $( '#' + index + '-attainmentValueRaw');

		if (attainmentRaw.length) {
			attainmentRaw.removeClass('highlight');

			if (attainmentRaw.val() != '' ) {
				var rawValue = parseFloat( attainmentRaw.val() );
				var maxValue = parseInt( attainmentRawMax.val() );

				var calculatedValue = Math.round( ( rawValue / maxValue ) * 100  ) ;
				if (calculatedValue >= 0 && calculatedValue <= 100 && calculatedValue+'%' != $(this).val() ) {
					attainmentRaw.addClass('highlight');
                    attainmentRaw.prop('title', calculatedValue+'%' );
				}
			}
		}
	});

});
