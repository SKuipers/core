<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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

namespace Gibbon\Forms\Input;

/**
 * FileDragDrop
 *
 * @version v19
 * @since   v19
 */
class FileDragDrop extends Input
{
    protected $page;

    public function __construct($name, &$page)
    {
        $this->page = $page;
        $page->scripts->add('dropzone');
        $page->stylesheets->add('dropzone');

        parent::__construct($name);
    }
   
    /**
     * Gets the HTML output for this form element.
     * @return  string
     */
    protected function getElement()
    {
        $output = '';
        $output .= '<div class="dropzone dropzone-previews rounded border-4 border-gray-400 border-dashed p-4 text-gray-400 text-center flex" id="'.$this->getID().'"></div>';
    
        $output .= '<script>
        Dropzone.options.'.$this->getID().' = { 
            paramName: "'.$this->getName().'",
            url: "/file/upload",
            autoProcessQueue: false,
            uploadMultiple: true,
            parallelUploads: 5,
            maxFiles: 5,
            maxFilesize: 2,
            acceptedFiles: "image/*",
            addRemoveLinks: true,
            previewTemplate: "'.str_replace(['"', "\n"], ["'", ''], $this->page->fetchFromTemplate('ui/fileDragDrop.twig.html')).'",
            init: function() {
                var _this = this;
                var submitButton = document.querySelector(".standardForm input[type=submit]");

                submitButton.addEventListener("click", function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    _this.getQueuedFiles().forEach(function(item) {
                        $("<input>").attr({
                            type: "hidden",
                            name: "test[]",
                            value: item.dataURL,
                        }).appendTo(submitButton.form);
                    });

                    submitButton.form.submit();
                });
            },
        };
        </script>';
       
        return $output;
    }
}
