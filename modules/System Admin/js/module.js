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

/**
 * Compares two version number strings.
 * @param    string  a
 * @param    string  b
 * @return   Return values:
            - a number < 0 if a < b
            - a number > 0 if a > b
            - 0 if a = b
 */
function versionCompare(a, b) {
    var i, diff;
    var regExStrip0 = /(\.0+)+$/;
    var segmentsA = a.replace(regExStrip0, '').split('.');
    var segmentsB = b.replace(regExStrip0, '').split('.');
    var l = Math.min(segmentsA.length, segmentsB.length);

    for (i = 0; i < l; i++) {
        diff = parseInt(segmentsA[i], 10) - parseInt(segmentsB[i], 10);
        if (diff) {
            return diff;
        }
    }
    return segmentsA.length - segmentsB.length;
}


document.addEventListener('DOMContentLoaded', function(){

    document.querySelectorAll('select.columnOrder').forEach(function(select) {
        select.addEventListener('change', function(){

            var currentSelection = this.value;
            var row = this.closest('tr') || this.parentElement.parentElement;
            var textBox = row ? row.querySelector('input.columnText') : null;

            if (textBox) {
                textBox.readOnly = (currentSelection != columnDataCustom);
                textBox.disabled = (currentSelection != columnDataCustom);

                if ( currentSelection == columnDataFunction ) {
                    textBox.value = "*generated*";
                } else if ( currentSelection == columnDataCustom ) {
                    textBox.value = "";
                } else if ( currentSelection == columnDataSkip ) {
                    textBox.value = "*skipped*";
                } else if ( currentSelection >= 0 ) {
                    if ( currentSelection in csvFirstLine ) {
                        textBox.value = csvFirstLine[ currentSelection ];
                    } else {
                        textBox.value = "";
                    }
                }
            }
        });
    });
    document.querySelectorAll('select.columnOrder').forEach(function(select) {
        select.dispatchEvent(new Event('change'));
    });

    var ignoreErrors = document.getElementById('ignoreErrors');
    if (ignoreErrors) {
        ignoreErrors.addEventListener('click', function() {
            var submitStep3 = document.getElementById('submitStep3');
            if (this.checked) {
                this.value = 1;
                if (submitStep3) {
                    submitStep3.disabled = false;
                    submitStep3.type = 'submit';
                    submitStep3.value = 'Submit';
                }
            } else {
                this.value = 0;
                if (submitStep3) {
                    submitStep3.disabled = true;
                    submitStep3.value = 'Cannot Continue';
                }
            }
        });
    }
}); 
