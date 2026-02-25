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

	// Matches the width of the top placeholder to the final table width
	window.addEventListener('load', function(e) {
		var topWidth = document.querySelector('.doublescroll-top-tablewidth');
		var containerTable = document.querySelector('.doublescroll-container table');
		if (topWidth && containerTable) {
			topWidth.style.width = containerTable.offsetWidth + 'px';
		}
	});
	
	// Pairs the position of the top scrollbar with the bottom scrollbar
	var topScroll = document.querySelector(".doublescroll-top");
	var containerScroll = document.querySelector(".doublescroll-container");
	
	if (topScroll && containerScroll) {
		topScroll.addEventListener('scroll', function() {
			containerScroll.scrollLeft = topScroll.scrollLeft;
		});
		
		containerScroll.addEventListener('scroll', function() {
			topScroll.scrollLeft = containerScroll.scrollLeft;
		});
	}

});
