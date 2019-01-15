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

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\Staff\SubstituteGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/subs_manage_edit.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo '</div>';
} else {
    //Proceed!
    $search = $_GET['search'] ?? '';

    $page->breadcrumbs
        ->add(__('Manage Subs'), 'subs_manage.php', ['search' => $search])
        ->add(__('Edit Sub'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    if ($search != '') {
        echo "<div class='linkTop'>";
        echo "<a href='".$_SESSION[$guid]['absoluteURL']."/index.php?q=/modules/Staff/subs_manage.php&search=$search'>".__('Back to Search Results').'</a>';
        echo '</div>';
    }

    $gibbonSubstituteID = $_GET['gibbonSubstituteID'] ?? '';

    if (empty($gibbonSubstituteID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $subGateway = $container->get(SubstituteGateway::class);
    $values = $subGateway->getByID($gibbonSubstituteID);

    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = Form::create('subsManage', $_SESSION[$guid]['absoluteURL'].'/modules/'.$_SESSION[$guid]['module']."/subs_manage_editProcess.php?search=$search");

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('smallIntBorder fullWidth');

    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonSubstituteID', $gibbonSubstituteID);

    $form->addRow()->addHeading(__('Basic Information'));

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Person'));
        $row->addSelectUsers('gibbonPersonID')->placeholder()->isRequired()->selected($values['gibbonPersonID'])->readonly();

    $row = $form->addRow();
        $row->addLabel('active', __('Active'));
        $row->addYesNo('active')->isRequired();

    $row = $form->addRow();
        $row->addLabel('type', __('Type'));
        $row->addTextField('type')->maxlength(30);

    $row = $form->addRow();
        $row->addLabel('details', __('Details'))->description(__('Additional information such as year group preference, language preference, etc.'));
        $row->addTextField('details')->maxlength(255);

    $row = $form->addRow();
        $row->addLabel('priority', __('Priority'))->description(__('Higher priority substitutes appear first when booking coverage.'));
        $row->addSelect('priority')->fromArray(range(-9, 9))->isRequired()->selected(0);

    $form->addRow()->addHeading(__('Contact Information'));

    $row = $form->addRow()->addClass('contact');
        $row->addLabel('contactCall', __('Call?'));
        $row->addYesNo('contactCall')->isRequired();

    $row = $form->addRow()->addClass('contact');
        $row->addLabel('contactSMS', __('SMS?'));
        $row->addYesNo('contactSMS')->isRequired();

    $row = $form->addRow()->addClass('contact');
        $row->addLabel('contactEmail', __('Email?'));
        $row->addYesNo('contactEmail')->isRequired();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

     $form->loadAllValuesFrom($values);

    echo $form->getOutput();
}
