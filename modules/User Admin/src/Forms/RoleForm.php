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

namespace Gibbon\UserAdmin\Forms;

use Gibbon\Forms\Form;

/**
 * @version v17
 * @since   v17
 */
class RoleForm extends Form
{
    protected function getActionURL($role = null)
    {
        return !empty($role)
            ? '/modules/User Admin/role_manage_editProcess.php?gibbonRoleID='.$role['gibbonRoleID']
            : '/modules/User Admin/role_manage_addProcess.php';
    }

    public function make($role = null)
    {
        $this->setAttribute('action', $this->getActionURL($role));
        $this->setClass('smallIntBorder fullWidth standardForm');

        $isReadOnly = ($role && $role['type'] == 'Core');

        $categories = array(
            'Staff'   => __('Staff'),
            'Student' => __('Student'),
            'Parent'  => __('Parent'),
            'Other'   => __('Other'),
        );

        $restrictions = array(
            'None'       => __('None'),
            'Same Role'  => __('Users with the same role'),
            'Admin Only' => __('Administrators only'),
        );

        $row = $this->addRow();
            $row->addLabel('category', __('Category'));
            $row->addSelect('category')->fromArray($categories)->isRequired()->placeholder()->readonly($isReadOnly);

        $row = $this->addRow();
            $row->addLabel('name', __('Name'));
            $row->addTextField('name')->isRequired()->maxLength(20)->readonly($isReadOnly);

        $row = $this->addRow();
            $row->addLabel('nameShort', __('Short Name'));
            $row->addTextField('nameShort')->isRequired()->maxLength(4)->readonly($isReadOnly);

        $row = $this->addRow();
            $row->addLabel('description', __('Description'));
            $row->addTextField('description')->isRequired()->maxLength(60)->readonly($isReadOnly);

        $row = $this->addRow();
            $row->addLabel('type', __('Type'));
            $row->addTextField('type')->isRequired()->readonly()->setValue(__('Additional'));

        $row = $this->addRow();
            $row->addLabel('canLoginRole', __('Can Login?'))->description(__('Are users with this primary role able to login?'));
            if ($role['name'] == 'Administrator') {
                $row->addTextField('canLoginRole')->isRequired()->readonly()->setValue(__('Yes'));
            } else {
                $row->addYesNo('canLoginRole')->isRequired()->selected($role['canLoginRole']);
                $this->toggleVisibilityByClass('loginOptions')->onSelect('canLoginRole')->when('Y');
            }

        $row = $this->addRow()->addClass('loginOptions');
            $row->addLabel('pastYearsLogin', __('Login To Past Years'));
            $row->addYesNo('pastYearsLogin')->isRequired()->selected($role['pastYearsLogin'] ?? 'N');

        $row = $this->addRow()->addClass('loginOptions');
            $row->addLabel('futureYearsLogin', __('Login To Future Years'));
            $row->addYesNo('futureYearsLogin')->isRequired()->selected($role['futureYearsLogin'] ?? 'N');

        $row = $this->addRow();
            $row->addLabel('restriction', __('Restriction'))->description('Determines who can grant or remove this role in Manage Users.');
        if ($role['name'] == 'Administrator') {
            $row->addTextField('restriction')->isRequired()->readonly()->setValue('Admin Only');
        } else {
            $row->addSelect('restriction')->fromArray($restrictions)->isRequired()->selected($role['restriction']);
        }

        $row = $this->addRow();
            $row->addFooter();
            $row->addSubmit();

        if ($role) {
            $this->loadAllValuesFrom($role);
        }
    }
}
