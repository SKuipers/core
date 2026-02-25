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

	window.addEventListener('load', function (e) {
        // Matches the width of the top placeholder to the final table width
        const topTablewidth = document.querySelector('.doublescroll-top-tablewidth');
        const containerTable = document.querySelector('.doublescroll-container table');
        const container = document.querySelector('.doublescroll-container');
        
        if (topTablewidth && containerTable) {
            topTablewidth.style.width = containerTable.offsetWidth + 'px';
        }

        // Start scrolled to the left, where all the recent stuff is
        if (container && containerTable) {
            container.scrollLeft = containerTable.offsetWidth;
        }
	});

	// Pairs the position of the top scrollbar with the bottom scrollbar
    const topScroll = document.querySelector('.doublescroll-top');
    const containerScroll = document.querySelector('.doublescroll-container');
    
    if (topScroll && containerScroll) {
        topScroll.addEventListener('scroll', function() {
            containerScroll.scrollLeft = topScroll.scrollLeft;
        });
        
        containerScroll.addEventListener('scroll', function() {
            topScroll.scrollLeft = containerScroll.scrollLeft;
        });
    }

    // Add SortableJS functionality to the markbook table
    const markbookTable = document.querySelector('#myTable.markbook');
    if (markbookTable && typeof Sortable !== 'undefined') {
        const thead = markbookTable.querySelector('thead tr');
        if (thead) {
            Sortable.create(thead, {
                handle: '.dragtable-drag-handle',
                animation: 150,
                onEnd: function(evt) {
                    // Reorder the body cells to match the header order
                    const tbody = markbookTable.querySelector('tbody');
                    if (tbody) {
                        const rows = tbody.querySelectorAll('tr');
                        rows.forEach(function(row) {
                            const cells = Array.from(row.children);
                            const movedCell = cells[evt.oldIndex];
                            if (movedCell) {
                                row.removeChild(movedCell);
                                if (evt.newIndex >= cells.length) {
                                    row.appendChild(movedCell);
                                } else {
                                    row.insertBefore(movedCell, cells[evt.newIndex]);
                                }
                            }
                        });
                    }
                }
            });
        }
    }

    // In markbook_edit_data.php, update the attainment value to match raw score
    // But not the other way around, in case teachers need to adjust the value
    document.addEventListener('change', function(e) {
        const target = e.target;
        
        // Handle attainmentValueRaw changes
        if (target.matches('input[id$="attainmentValueRaw"]')) {
            const attainmentRawMax = document.querySelector('[name="attainmentRawMax"]');
            // This value wont exist if not using a percent scale
            if (!attainmentRawMax) return;

            target.classList.remove('highlight');
            target.title = '';

            const index = target.name.substr(0, target.name.indexOf('-'));
            const thisValue = parseFloat(target.value);
            const maxValue = parseFloat(attainmentRawMax.value);
            const attainment = document.querySelector('#' + index + '-attainmentValue');
            const scaleType = document.querySelector('[name="attainmentScaleType"]');

            if (target.value === '' || maxValue === 0 || maxValue === '') {
                return;
            }
            else if (isNaN(thisValue)) {
                if (attainment) {
                    const emptyOption = attainment.querySelector('option[value=""]');
                    if (emptyOption) {
                        emptyOption.selected = true;
                    }
                }
                target.value = '';
                return;
            }
            
            if (scaleType && scaleType.value === '%') {
                let adjustedValue = thisValue;
                if (thisValue > maxValue) {
                    adjustedValue = maxValue;
                    target.value = adjustedValue;
                }

                const calculatedValue = Math.round((adjustedValue / maxValue) * 100);

                if (calculatedValue >= 0 && calculatedValue <= 100 && attainment) {
                    const percentOption = attainment.querySelector('option[value="' + calculatedValue + '%"]');
                    if (percentOption) {
                        attainment.value = calculatedValue + '%';
                    } else {
                        const emptyOption = attainment.querySelector('option[value=""]');
                        if (emptyOption) {
                            emptyOption.selected = true;
                        }
                        target.classList.add('highlight');
                    }
                }
            }
        }
        
        // Handle attainmentValue changes
        if (target.matches('select[id$="attainmentValue"]')) {
            const attainmentRawMax = document.querySelector('[name="attainmentRawMax"]');
            const scaleType = document.querySelector('[name="attainmentScaleType"]');

            // This value wont exist if not using a percent scale
            if (!attainmentRawMax || !scaleType || scaleType.value !== '%') return;

            const index = target.name.substr(0, target.name.indexOf('-'));
            const attainmentRaw = document.querySelector('#' + index + '-attainmentValueRaw');

            if (attainmentRaw) {
                attainmentRaw.classList.remove('highlight');

                if (attainmentRaw.value !== '') {
                    const rawValue = parseFloat(attainmentRaw.value);
                    const maxValue = parseInt(attainmentRawMax.value);

                    const calculatedValue = Math.round((rawValue / maxValue) * 100);
                    if (calculatedValue >= 0 && calculatedValue <= 100 && calculatedValue + '%' !== target.value) {
                        attainmentRaw.classList.add('highlight');
                        attainmentRaw.title = calculatedValue + '%';
                    }
                }
            }
        }
    });

});


