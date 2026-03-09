<?php
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

namespace Gibbon\Forms\Layout;

use Gibbon\Forms\OutputableInterface;
use Gibbon\Forms\FormFactoryInterface;
use Gibbon\Forms\Traits\FormFieldsTrait;

/**
 * ButtonGroup
 *
 * @version v31
 * @since   v31
 */
class ButtonGroup implements OutputableInterface
{
    use FormFieldsTrait;

    protected FormFactoryInterface $factory;
    protected array $elements = [];
    
    /**
     * Construct a button group with access to a specific factory.
     * @param  FormFactoryInterface  $factory
     * @param  string                $id
     */
    public function __construct(FormFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Adds an outputtable element to the row's internal collection.
     * @param  OutputableInterface  $element
     */
    public function addElement(OutputableInterface $element)
    {
        $id = $this->getUniqueIdentifier($element);
        $this->elements[$id] = $element;

        return $element;
    }

    /**
     * Iterate over each element in the collection and concatenate the output.
     * @return  string
     */
    public function getOutput()
    {
        $output = '';
        foreach ($this->elements as $element) {
            $output .= $element->getOutput();
        }

        return '<div class="button-group">'.$output.'</div>';
    }
}
