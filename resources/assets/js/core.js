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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Initialize an Alpine.js Tooltip (on non-mobile devices)
document.addEventListener('alpine:init', () => {
    if (window.innerWidth < 768) return;

    var currentTooltip;

    Alpine.directive('tooltip', (el, { modifiers, expression }, { cleanup }) => {
        var tooltipActive = false;
        let tooltipText = expression;
        let tooltipArrow = modifiers.includes('noarrow') ? false : true;
        let tooltipPosition = 'top';
        let tooltipId = 'tooltip-' + Date.now().toString(36) + Math.random().toString(36).substring(2, 7);
        let positions = ['top', 'bottom', 'left', 'right'];
        let elementPosition = getComputedStyle(el).position;

        let tooltipOuterStyle = modifiers.includes('white') ? 'text-gray-800 bg-white border shadow-lg' : 'px-3 py-2 text-white bg-black';
        let tooltipInnerStyle = modifiers.includes('white') ? 'text-gray-800 bg-white' : 'text-white bg-black';

        for (let position of positions) {
            if (modifiers.includes(position)) {
                tooltipPosition = position;
                break;
            }
        }

        if(!['relative', 'absolute', 'fixed'].includes(elementPosition)){
            el.style.position='relative';
        }
        
        let tooltipHTML = `
            <template x-teleport="body" id="${tooltipId}Template"><div id="${tooltipId}" x-cloak x-data="{ tooltipVisible: false, tooltipArrow: ${tooltipArrow}, tooltipPosition: '${tooltipPosition}' }" x-ref="tooltip" x-init="setTimeout(function(){ tooltipVisible = true; }, 1);" x-show="tooltipVisible" :class="{ 'top-0 left-1/2 -translate-x-1/2 -mt-1.5 -translate-y-full' : tooltipPosition == 'top', 'top-1/2 -translate-y-1/2 -ml-1.5 left-0 -translate-x-full' : tooltipPosition == 'left', 'bottom-0 left-1/2 -translate-x-1/2 -mb-0.5 ' : tooltipPosition == 'bottom', 'top-1/2 -translate-y-1/2 -mr-1.5  ' : tooltipPosition == 'right' }" class="absolute pointer-events-none max-w-sm text-sm font-normal" style="z-index: 100;" >
            
                <div x-show="tooltipVisible" class="relative ${tooltipOuterStyle} bg-opacity-80 backdrop-blur-lg backdrop-contrast-125 backdrop-saturate-150 rounded-md"
                    x-transition:enter="transition delay-75 ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-50"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-50" >
                    <div class="flex-shrink-0 block m-0 text-xs " >${tooltipText}</div>
                    <div x-ref="tooltipArrow" x-show="tooltipArrow" :class="{ 'bottom-0 -translate-x-1/2 left-1/2 w-2.5 translate-y-full' : tooltipPosition == 'top', 'right-0 -translate-y-1/2 top-1/2 h-2.5 -mt-px translate-x-full' : tooltipPosition == 'left', 'top-0 -translate-x-1/2 left-1/2 w-2.5 -translate-y-full' : tooltipPosition == 'bottom', 'left-0 -translate-y-1/2 top-1/2 h-2.5 -mt-px -translate-x-full' : tooltipPosition == 'right' }" class="absolute inline-flex items-center justify-center overflow-hidden">
                        <div :class="{ 'origin-top-left -rotate-45' : tooltipPosition == 'top', 'origin-top-left rotate-45' : tooltipPosition == 'left', 'origin-bottom-left rotate-45' : tooltipPosition == 'bottom', 'origin-top-right -rotate-45' : tooltipPosition == 'right' }" class="w-1.5 h-1.5 transform ${tooltipInnerStyle} bg-opacity-80"></div>
                    </div>
                </div>
                
            </div></template>
        `;
        
        el.dataset.tooltip = tooltipId;

        let mouseEnter = function(event){ 
            if (currentTooltip != null) {
                currentTooltip.dispatchEvent(new Event('mouseleave'));
            }
            if (!tooltipActive) {
                var elRect = el.getBoundingClientRect();

                el.insertAdjacentHTML('beforeend', tooltipHTML);
                setTimeout(function(){
                    var tooltip = document.getElementById(tooltipId);
                    if (tooltip != null) {
                        if (tooltipPosition == 'top') {
                            tooltip.style.top = (elRect.top + window.scrollY )+"px";
                            tooltip.style.left = (elRect.left + window.scrollX + (elRect.width/2.0))+"px";
                        } else if (tooltipPosition == 'bottom') {
                            tooltip.style.top = (elRect.bottom + window.scrollY + elRect.height )+"px";
                            tooltip.style.left = (elRect.left + window.scrollX + (elRect.width/2.0))+"px";
                        } else if (tooltipPosition == 'left') {
                            tooltip.style.top = (elRect.top + window.scrollY + (elRect.height/2.0))+"px";
                            tooltip.style.left = (elRect.left + window.scrollX - 12  )+"px";
                        } else if (tooltipPosition == 'right') {
                            tooltip.style.top = (elRect.top + window.scrollY + (elRect.height/2.0))+"px";
                            tooltip.style.left = (elRect.right + window.scrollX + 12  )+"px";
                        }
                    }
                }, 50);
                
                tooltipActive = true;
                currentTooltip = el;
            }
        };

        let mouseLeave = function(event){
            var tooltip = document.getElementById(tooltipId);
            if (tooltip) tooltip.remove();

            var tooltipTemplate = document.getElementById(tooltipId+"Template");
            if (tooltipTemplate) tooltipTemplate.remove();

            tooltipActive = false;
            currentTooltip = null;
        };
        
        el.addEventListener('mouseenter', mouseEnter);
        el.addEventListener('mouseleave', mouseLeave);
        document.addEventListener('htmx:beforeRequest', mouseLeave);

        cleanup(() => {
            el.removeEventListener('mouseenter', mouseEnter);
            el.removeEventListener('mouseleave', mouseLeave);
        })
    });
    
});

// Enable preventing page navigation from hx-boosted links
document.addEventListener('htmx:confirm', function(evt) {
    if (!evt.detail.elt.hasAttribute('hx-boost')) return;

    evt.preventDefault();

    if (window.onbeforeunload != null) {
        if (window.confirm(Gibbon.config.htmx.unload_confirm)) {
            window.onbeforeunload = null;
            evt.detail.issueRequest(true);
        }
    } else {
        evt.detail.issueRequest(true);
    }
}, false);


htmx.onLoad(function (content) {
    
    /**
     * Sidebar toggle switch
     */
    document.querySelector("#sidebarToggle")?.addEventListener("click", function () {
        const sidebar = document.querySelector("#sidebar");
        if (sidebar.classList.contains("lg:w-sidebar")) {
            sidebar.classList.remove("lg:w-sidebar");
            sidebar.classList.add("lg:hidden");
            this.innerHTML = "«";
        } else {
            sidebar.classList.remove("lg:hidden");
            sidebar.classList.add("lg:w-sidebar");
            this.innerHTML = "»";
        }
    });

    /**
     * Form Class: generic check All/None checkboxes
     */
    document.addEventListener("click", function(event) {
        const checkall = event.target.closest('.checkall[type="checkbox"]');
        if (!checkall) return;
        
        var checked = checkall.checked;
        var parent = checkall.parentElement.parentElement.closest(
            '.bulkActionForm, .checkboxGroup'
        );

        parent
            .querySelectorAll('input[type="checkbox"]')
            .forEach(function (element, index, elements) {
                if (element === checkall) return;

                element.checked = checked;

                let formRow = element.closest("tr");
                if (formRow != undefined) {
                    formRow.classList.toggle("selected", element.checked);
                }

                if (index == elements.length - 1) {
                    element.dispatchEvent(new Event("change"));
                }
            });
    });

    /**
     * Bulk Actions: show/hide the bulk action panel, highlight selected
     */
    document.addEventListener("click", function (event) {
        const checkbox = event.target.closest(".bulkActionForm .bulkCheckbox input[type='checkbox']");
        if (!checkbox) return;
        
        handleBulkCheckboxChange(checkbox);
    });
    
    document.addEventListener("change", function (event) {
        const checkbox = event.target.closest(".bulkActionForm .bulkCheckbox input[type='checkbox']");
        if (!checkbox) return;
        
        handleBulkCheckboxChange(checkbox);
    });
    
    function handleBulkCheckboxChange(checkbox) {
        const bulkForm = checkbox.closest(".bulkActionForm");
        const checkboxes = bulkForm.querySelectorAll(".bulkCheckbox input[type='checkbox']");
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;

        if (checkedCount > 0) {
            const countSpan = document.querySelector(".bulkActionCount span");
            if (countSpan) countSpan.innerHTML = checkedCount;

            const bulkPanel = document.querySelector(".bulkActionPanel");
            if (bulkPanel && bulkPanel.classList.contains("hidden")) {
                bulkPanel.classList.remove("hidden");

                const header = bulkForm.querySelector(".dataTable header");
                const panelHeight = bulkPanel.offsetHeight;
                
                if (header) {
                    bulkPanel.style.top = (header.offsetHeight - panelHeight + 6) + "px";
                }

                // Trigger a showhide event on any nested inputs to update their visibility & validation state
                bulkPanel.querySelectorAll(":input").forEach(input => {
                    input.dispatchEvent(new Event("showhide"));
                });
            }
        } else {
            const bulkPanel = document.querySelector(".bulkActionPanel");
            if (bulkPanel) bulkPanel.classList.add("hidden");
        }

        document.querySelectorAll(".checkall").forEach(checkall => {
            checkall.checked = checkedCount > 0;
            checkall.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        });

        const row = checkbox.closest("tr");
        if (row) {
            row.classList.toggle("selected", checkbox.checked);
        }
    }

    // Highlight any pre-checked rows
    document
        .querySelectorAll('.bulkCheckbox input[type="checkbox"]')
        .forEach(function (element) {
            element.closest("tr").classList.toggle("selected", element.checked);
        });

    /**
     * Column Highlighting
     */
    const columnHighlight = document.querySelectorAll(".columnHighlight td");
    columnHighlight.forEach(td => {
        td.addEventListener("mouseover", function () {
            const index = Array.from(this.parentElement.children).indexOf(this) + 1;
            document.querySelectorAll(`.columnHighlight td:nth-child(${index})`).forEach(cell => {
                cell.classList.add("hover");
            });
        });
        td.addEventListener("mouseout", function () {
            document.querySelectorAll(".columnHighlight td").forEach(cell => {
                cell.classList.remove("hover");
            });
        });
    });

    /**
     * Password Generator. Requires data-source, data-confirm and data-alert attributes.
     */
    document.querySelectorAll(".generatePassword").forEach(button => {
        button.addEventListener("click", function () {
            const source = this.dataset.source;
            const confirm = this.dataset.confirm;
            
            if (!source || !confirm) return;

            var chars =
                "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789![]{}()%&*$#^~@|";
            var text = "";
            for (var i = 0; i < 12; i++) {
                if (i == 0) {
                    text += chars.charAt(Math.floor(Math.random() * 26));
                } else if (i == 1) {
                    text += chars.charAt(Math.floor(Math.random() * 26) + 26);
                } else if (i == 2) {
                    text += chars.charAt(Math.floor(Math.random() * 10) + 52);
                } else if (i == 3) {
                    text += chars.charAt(Math.floor(Math.random() * 19) + 62);
                } else {
                    text += chars.charAt(Math.floor(Math.random() * chars.length));
                }
            }
            
            const sourceInput = document.querySelector(`input[name="${source}"]`);
            const confirmInput = document.querySelector(`input[name="${confirm}"]`);
            
            if (sourceInput) {
                sourceInput.value = text;
                sourceInput.blur();
                sourceInput.dispatchEvent(new Event('blur'));
            }
            if (confirmInput) {
                confirmInput.value = text;
                confirmInput.blur();
            }

            prompt(this.dataset.alert, text);
        });
    });

    /**
     * Username Generator. Requires data-alert attribute.
     */
    document.querySelectorAll(".generateUsername").forEach(button => {
        button.addEventListener("click", async function () {
            const alertText = this.dataset.alert;
            
            const formData = new URLSearchParams();
            formData.append('gibbonRoleID', document.querySelector("#gibbonRoleIDPrimary").value);
            formData.append('preferredName', document.querySelector("#preferredName").value);
            formData.append('firstName', document.querySelector("#firstName").value);
            formData.append('surname', document.querySelector("#surname").value);
            
            try {
                const response = await fetch("./modules/User Admin/user_manage_usernameAjax.php", {
                    method: "POST",
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: formData.toString()
                });
                
                const responseText = await response.text();
                
                if (responseText == 0) {
                    document.querySelector("#gibbonRoleIDPrimary").dispatchEvent(new Event('change'));
                    document.querySelector("#preferredName").dispatchEvent(new Event('blur'));
                    document.querySelector("#firstName").dispatchEvent(new Event('blur'));
                    document.querySelector("#surname").dispatchEvent(new Event('blur'));
                    alert(alertText);
                } else {
                    const usernameInput = document.querySelector("#username");
                    usernameInput.value = responseText;
                    usernameInput.dispatchEvent(new Event('input'));
                    usernameInput.dispatchEvent(new Event('blur'));
                }
            } catch (error) {
                console.error('Error generating username:', error);
            }
        });
    });

    /**
     * Data Table: Simple Drag-Drop
     */
    var sortables = content.querySelectorAll("table[data-draggable] tbody");
    for (var i = 0; i < sortables.length; i++) {
        var sortable = sortables[i];
        var sortableInstance = new Sortable(sortable, {
            animation: 150,
            ghostClass: "bg-purple-100",
            handle: ".drag-handle",
            dataIdAttr: 'data-drag-id', 
            swapThreshold: 0.75,

            // Make the `.htmx-indicator` unsortable
            filter: ".htmx-indicator",
            onMove: function (evt) {
                return evt.related.className.indexOf("htmx-indicator") === -1;
            },

            // Disable sorting on the `end` event
            onEnd: function (evt) {
                // this.option("disabled", true);
            },
        });

        // Re-enable sorting on the `htmx:afterRequest` event
        // content?.addEventListener("htmx:afterRequest", function (event) {
        //     sortableInstance.option("disabled", false);
        // });
    }
});


// Form API Functions

/**
 * Comment Editor
 */
HTMLElement.prototype.gibbonCommentEditor = function (settings) {
    var editor = this;

    updateComments(editor);

    editor.addEventListener("input", function () {
        updateComments(this);
    });

    editor.addEventListener("paste", function () {
        var element = this;
        setTimeout(function () {
            updatePlaceholders(element);
            updateComments(element);
        }, 0);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            autosize(editor);
        });
    } else {
        autosize(editor);
    }
};

function updateComments(element) {
    var commentText = element.value;

    // Update character counter for comment length
    var currentLength = commentText.length;
    var parent = element.parentElement.parentElement;
    var currentLengthEl = parent.querySelector(".characterInfo .currentLength");
    if (currentLengthEl) currentLengthEl.textContent = currentLength;

    // Look for the student's first name somewhere in the comment
    var preferredName = element.dataset.name ? element.dataset.name : "";
    if (preferredName.length > 0) {
        var nameNotFound = commentText.indexOf(preferredName) === -1;
        var statusName = parent.querySelector(".characterInfo .commentStatusName");
        if (statusName) {
            statusName.classList.toggle("hidden", !nameNotFound);
        }
    }

    // Check to ensure the pronouns match the gender of the student
    var gender = element.dataset.gender ? element.dataset.gender : "";
    if (gender.length > 0) {
        var heFound =
            commentText.search(/\bhe\b/i) !== -1 ||
            commentText.search(/\bhis\b/i) !== -1 ||
            commentText.search(/\bhim\b/i) !== -1 ||
            commentText.search(/\bhimself\b/i) !== -1;
        var sheFound =
            commentText.search(/\bshe\b/i) !== -1 ||
            commentText.search(/\bher\b/i) !== -1 ||
            commentText.search(/\bherself\b/i) !== -1;
        var pronounMismatch =
            (heFound && gender == "F") || (sheFound && gender == "M");
        var statusPronoun = element.parentElement.querySelector(".characterInfo .commentStatusPronoun");
        if (statusPronoun) {
            statusPronoun.classList.toggle("hidden", !pronounMismatch);
        }
    }
}

function updatePlaceholders(element) {
    var commentText = element.value;

    // Replace {name} with the student's preferred name
    var preferredName = element.dataset.name ? element.dataset.name : "";
    if (preferredName.length > 0) {
        commentText = commentText.replace(/{name}/gi, preferredName);
    }

    // Replace pronouns to match the student's gender
    var gender = element.dataset.gender ? element.dataset.gender : "";
    if (gender.length > 0) {
        if (gender == "F") {
            commentText = commentText
                .replace(/\bhe\b/g, "she")
                .replace(/\bHe\b/g, "She");
            commentText = commentText
                .replace(/\bhis\b/g, "her")
                .replace(/\bHis\b/g, "Her");
            commentText = commentText
                .replace(/\bhim\b/g, "her")
                .replace(/\bHim\b/g, "Her");
            commentText = commentText
                .replace(/\bhimself\b/g, "herself")
                .replace(/\bHimself\b/g, "Herself");
        } else if (gender == "M") {
            commentText = commentText
                .replace(/\bshe\b/g, "he")
                .replace(/\bShe\b/g, "He");
            commentText = commentText
                .replace(/\bher\b/g, "his")
                .replace(/\bHer\b/g, "His");
            commentText = commentText
                .replace(/\bherself\b/g, "himself")
                .replace(/\bHerself\b/g, "Himself");
        }
    }

    element.value = commentText;
}

/**
 * Gibbon Data Table: a very basic implementation of vanilla JavaScript + AJAX powered data tables in Gibbon
 * @param string basePath
 * @param Object settings
 */
var DataTable = window.DataTable || {};

DataTable = function (element, basePath, filters, identifier) {
    var _ = this;

    _.table = element;
    _.path = basePath + " #" + _.table.id + " > .dataTable";
    _.filters = filters;
    _.identifier = identifier;
    if (_.filters.sortBy.length == 0) _.filters.sortBy = {};
    if (_.filters.filterBy.length == 0) _.filters.filterBy = {};

    _.init();
};

DataTable.prototype.init = function () {
    var _ = this;

    // Pagination
    _.table?.addEventListener("click", function (event) {
        const paginate = event.target.closest(".paginate");
        if (!paginate) return;
        
        var resultCount = _.table.querySelector(".dataTable").dataset.results;
        _.filters.pageMax = Math.ceil(resultCount / _.filters.pageSize);
        _.filters.page = Math.min(paginate.dataset.page, _.filters.pageMax);
        _.refresh();
    });

    // Sortable Columns
    _.table?.addEventListener("click", function (event) {
        const sortable = event.target.closest(".column.sortable");
        if (!sortable) return;

        var columns = sortable.dataset.sort.split(",");

        // Hold shift to add columns to the sort (or toggle them), otherwise clear it each time.
        var activeColumns = columns.filter(function (item) {
            return item in _.filters.sortBy;
        });
        if (activeColumns.length == 0 && !event.shiftKey) _.filters.sortBy = {};

        columns.forEach(function (column) {
            _.filters.sortBy[column] =
                _.filters.sortBy[column] == "ASC" ? "DESC" : "ASC";
        });

        _.refresh();
    });

    // Remove Filter
    _.table?.addEventListener("click", function (event) {
        const filter = event.target.closest(".filter");
        if (!filter) return;
        
        var filterName = filter.dataset.filter;

        if (filter.classList.contains("clear")) {
            _.filters.filterBy = { "": "" };
            _.filters.searchBy.columns = [""];
        } else if (filterName in _.filters.filterBy) {
            // Remove columns from search criteria if removing an in: filter
            if (filterName == "in") _.filters.searchBy.columns = [""];
            delete _.filters.filterBy[filterName];
        }

        if (Object.keys(_.filters.filterBy).length === 0)
            _.filters.filterBy = { "": "" };

        _.filters.page = 1;
        _.refresh();
    });

    // Add Filter
    _.table?.addEventListener("change", function (event) {
        const filters = event.target.closest(".filters");
        if (!filters) return;
        
        var filterData = filters.value.split(":");
        var filter = filterData[0];
        var value = filterData[1];

        _.filters.filterBy[filter] = value;
        _.filters.page = 1;

        _.refresh();
    });

    // Page Size
    _.table?.addEventListener("change", function (event) {
        const limit = event.target.closest(".limit");
        if (!limit) return;
        
        var resultCount = _.table.querySelector(".dataTable").dataset.results;
        _.filters.pageSize = parseInt(limit.value);
        _.filters.pageMax = Math.ceil(resultCount / _.filters.pageSize);
        _.filters.page = Math.min(_.filters.page, _.filters.pageMax);
        _.refresh();
    });
};

DataTable.prototype.refresh = function () {
    var _ = this;

    var submitted = setTimeout(function () {
        var pagination = _.table.querySelector(".pagination");
        if (pagination) {
            pagination.insertAdjacentHTML('afterbegin', '<span class="submitted"></span>');
        }
    }, 500);

    var postData = {};

    if (_.identifier != "") {
        postData[_.identifier] = _.filters;
    } else {
        postData = _.filters;
    }

    var formData = new URLSearchParams();
    for (var key in postData) {
        if (typeof postData[key] === 'object') {
            for (const [index, value] of Object.entries(postData[key])) {
                formData.append(key+'['+index+']', value);
            }
        } else {
            formData.append(key, postData[key]);
        }
    }

    fetch(_.path.split(' ')[0], {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData.toString()
    })
    .then(response => response.text())
    .then(html => {
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');
        var selector = _.path.split(' ').slice(1).join(' ');
        var newContent = doc.querySelector(selector);
        
        if (newContent) {
            var targetSelector = _.path.split(' ').slice(1).join(' ');
            var target = _.table.querySelector(targetSelector);

            if (target) {
                target.innerHTML = newContent.innerHTML;
            }
        }
        
        var bulkPanel = document.querySelector(".bulkActionPanel");
        if (bulkPanel) bulkPanel.classList.add("hidden");
        
        clearTimeout(submitted);
        htmx.process(_.table);
        htmx.trigger(_.table, 'htmx:load');
    })
    .catch(error => {
        console.error('DataTable refresh error:', error);
        clearTimeout(submitted);
    });
};

HTMLElement.prototype.gibbonDataTable = function (basePath, filters, identifier) {
    this.gibbonDataTable = new DataTable(this, basePath, filters, identifier);
};

/**
 * Multi Selects
 */
// Define the MultiSelect behaviour
var MultiSelect = window.MultiSelect || {};

MultiSelect = function (element, name) {
    var _ = this;

    _.container = element;
    _.selectSource = _.container.querySelector("#" + name + "Source");
    _.selectDestination = _.container.querySelector("#" + name);
    _.name = name;
    _.sortBy = _.container.querySelector("#" + name + "Sort");

    _.init();
};

MultiSelect.prototype.init = function () {
    var _ = this;

    document.getElementById(_.name + "Add")?.addEventListener("click", function () {
        _.transferOption(true);
    });

    _.container.querySelector("#" + _.name + "Remove")?.addEventListener("click", function () {
        _.transferOption(false);
    });

    var form = _.container.closest("form");

    // Select all options on submit so we can validate this select input.
    form.querySelectorAll("input[type='Submit'],button[type='Submit'],button[value~='Save']").forEach(function(button) {
        button.addEventListener("click", function () {
            _.selectDestination.querySelectorAll("option").forEach(function (option) {
                option.selected = true;
            });
            document.getElementById(_.name).dispatchEvent(new Event('change'));
        });
    });

    _.sortBy?.addEventListener("change", function () {
        _.sortSelects();
    });

    _.container.querySelector("#" + _.name + "Search")?.addEventListener("keyup", function(event) {
        var search = this.value.toLowerCase();
        _.selectSource.querySelectorAll("option").forEach(function (option) {
            if (option.textContent.toLowerCase().includes(search)) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    });
    
    _.container.querySelector("#" + _.name + "Search")?.addEventListener("input", function(event) {
        var search = this.value.toLowerCase();
        _.selectSource.querySelectorAll("option").forEach(function (option) {
            if (option.textContent.toLowerCase().includes(search)) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    });
    
    _.container.querySelector("#" + _.name + "Search")?.addEventListener("compositionend", function(event) {
        var search = this.value.toLowerCase();
        _.selectSource.querySelectorAll("option").forEach(function (option) {
            if (option.textContent.toLowerCase().includes(search)) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    });
};

MultiSelect.prototype.transferOption = function (add) {
    var _ = this;

    var selectFrom = add ? _.selectSource : _.selectDestination;
    var selectTo = add ? _.selectDestination : _.selectSource;

    Array.from(selectFrom.querySelectorAll("option:checked")).forEach(function (option) {
        var opt = option.cloneNode(true);
        if (option.parentElement.tagName === 'OPTGROUP') {
            var optgroupnew = selectTo.querySelector(
                "optgroup[label='" + option.parentElement.getAttribute('label') + "']"
            );
            if (!optgroupnew) {
                optgroupnew = option.parentElement.cloneNode(false);
                selectTo.appendChild(optgroupnew);
            }
            opt.dataset.parent = optgroupnew;
            optgroupnew.appendChild(opt);
        } else {
            selectTo.appendChild(opt);
        }
        option.remove();
    });

    _.sortSelects();

    selectTo.dispatchEvent(new Event('change'));
    selectTo.focus();
};

MultiSelect.prototype.sortSelects = function () {
    var _ = this;

    var values = null;

    var sortBy = null;
    if (_.sortBy) {
        sortBy = _.sortBy.value;
    }

    if (sortBy != null && sortBy != "Sort by Name") {
        var sortableData = _.container.dataset.sortable;
        if (sortableData) {
            try {
                var parsedData = JSON.parse(sortableData);
                values = parsedData[sortBy];
            } catch (e) {
                console.error('Error parsing sortable data:', e);
            }
        }
    }

    _.sortSelect(_.selectSource, values);
    _.sortSelect(_.selectDestination, values);
};

MultiSelect.prototype.sortSelect = function (list, sortValues) {
    var _ = this;

    var listEl = list;
    
    listEl.querySelectorAll("optgroup").forEach(function (optgroup) {
        _.sortSelect(optgroup, sortValues);
    });

    var options = Array.from(listEl.querySelectorAll("option"));
    if (listEl.tagName === 'SELECT') {
        options = options.filter(opt => opt.parentElement.tagName !== 'OPTGROUP');
    }

    if (sortValues == null) {
        sortValues = {};
    }

    var arr = options.map(function (o) {
        return {
            tSort: (sortValues[o.value] || '') + o.textContent,
            t: o.textContent,
            v: o.value,
        };
    });
    
    arr.sort(function (o1, o2) {
        return o1.tSort > o2.tSort ? 1 : o1.tSort < o2.tSort ? -1 : 0;
    });
    
    options.forEach(function (o, i) {
        o.value = arr[i].v;
        o.textContent = arr[i].t;
    });
};

// Add the method to HTMLElement prototype
HTMLElement.prototype.gibbonMultiSelect = function (name) {
    this.gibbonMultiSelect = new MultiSelect(this, name);
};

function debounce(func, timeout) {
    timeout = timeout || 300;

    var timer;

    return function () {
        clearTimeout(timer);
        var args = arguments;
        timer = setTimeout(function () {
            func.apply(this, args);
        }, timeout);
    };
}

/**
 * Store a map of debounced functions for AJAX form submissions
 *
 * @type {Record<string, Function>}
 */
var __GIBBON_URL_DEBOUNCE_MAP = {};

/**
 * Gibbon Form Submit: a generic form submit function that can be used to submit forms via AJAX
 *
 * @param {HTMLFormElement} form
 */
function gibbonFormSubmitQuiet(form, url) {
    var submitData = new URLSearchParams(new FormData(form)).toString();

    if (!__GIBBON_URL_DEBOUNCE_MAP[url]) {
        __GIBBON_URL_DEBOUNCE_MAP[url] = debounce(function (submitData) {
            fetch(url, {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: submitData
            }).catch(error => {
                console.error('Form submit error:', error);
            });
        });
    }

    __GIBBON_URL_DEBOUNCE_MAP[url](submitData);
}
