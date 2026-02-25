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

document.addEventListener('DOMContentLoaded', function() {

	// Show the reason/comment when the attendance dropdown is changed
	document.querySelectorAll('select[name$="-type"]').forEach(function(select) {
		select.addEventListener('change', function() {
			// Get the data-id from the preceding hidden input
			var prevInput = this.previousElementSibling;
			if (prevInput && prevInput.tagName === 'INPUT') {
				var id = prevInput.dataset.id;
				// Show/hide the div container
				var hideReasons = document.getElementById(id + '-hideReasons');
				if (hideReasons) {
					hideReasons.style.display = 'block';
				}
			}
		});
	});

});

// Handle checkbox toggles for student homework
document.addEventListener('click', function(event) {
	if (event.target.classList.contains('mark-complete')) {
		var checkbox = event.target;
		var complete = checkbox.checked;
		var parentRow = checkbox.closest('tr') || checkbox.parentElement.parentElement;
		
		if (complete) {
			parentRow.classList.add('success');
		} else {
			parentRow.classList.remove('success');
		}

		fetch('./modules/Planner/planner_deadlinesAjax.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams({
				complete: complete ? 'Y' : 'N',
				type: checkbox.dataset.type,
				gibbonPlannerEntryID: checkbox.dataset.id
			})
		})
		.then(response => response.text())
		.then(data => {
			if (data == 'error0') {
				window.location = location.href + "&return=error0";
			} else if (data == 'error1') {
				window.location = location.href + "&return=error1";
			}
		})
		.catch(error => {
			console.error('Error updating homework completion:', error);
		});
	}
});
