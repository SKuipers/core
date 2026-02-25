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

function getDate() {
	// GET CURRENT DATE
	var date = new Date();
 
	// GET YYYY, MM AND DD FROM THE DATE OBJECT
	var yyyy = date.getFullYear().toString();
	var mm = (date.getMonth()+1).toString();
	var dd  = date.getDate().toString();
 
	// CONVERT mm AND dd INTO chars
	var mmChars = mm.split('');
	var ddChars = dd.split('');
 
	// CONCAT THE STRINGS IN YYYY-MM-DD FORMAT
	var datestring = yyyy + '-' + (mmChars[1]?mm:"0"+mmChars[0]) + '-' + (ddChars[1]?dd:"0"+ddChars[0]);
	
	return datestring ;
}

document.addEventListener('DOMContentLoaded', function() {

	// Select all tool for Attendance by Class/Form Group
	const setAllButton = document.querySelector('#set-all');
	if (setAllButton) {
		setAllButton.addEventListener('click', function() {
			const setAllType = document.querySelector('select[name="set-all-type"]');
			const setAllReason = document.querySelector('select[name="set-all-reason"]');
			const setAllComment = document.querySelector('input[name="set-all-comment"]');
			
			// Set all type selects
			document.querySelectorAll('select[name$="-type"]').forEach(function(select) {
				select.value = setAllType ? setAllType.value : '';
			});
			
			// Set all reason selects
			document.querySelectorAll('select[name$="-reason"]').forEach(function(select) {
				select.value = setAllReason ? setAllReason.value : '';
			});
			
			// Set all comment inputs
			document.querySelectorAll('input[name$="-comment"]').forEach(function(input) {
				input.value = setAllComment ? setAllComment.value : '';
			});
			
			// Show note
			const setAllNote = document.querySelector('#set-all-note');
			if (setAllNote) {
				setAllNote.style.display = 'block';
			}
		});
	}
});
