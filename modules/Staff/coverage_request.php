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
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\Staff\StaffAbsenceTypeGateway;
use Gibbon\Domain\Staff\StaffAbsenceGateway;
use Gibbon\Domain\Staff\StaffAbsenceDateGateway;
use Gibbon\Domain\Staff\StaffCoverageGateway;

if (isActionAccessible($guid, $connection2, '/modules/Staff/coverage_request.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('New Coverage Request'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, [
            'success1' => __('Your request was completed successfully.').' '.__('You may now continue by submitting a coverage request for this absence.'),
            'error8' => __('Your request failed because no dates have been selected. Please check your input and submit your request again.'),
        ]);
    }

    $gibbonStaffAbsenceID = $_GET['gibbonStaffAbsenceID'] ?? '';
    $gibbonPersonIDCoverage = $_GET['gibbonPersonIDCoverage'] ?? '';

    $staffAbsenceGateway = $container->get(StaffAbsenceGateway::class);
    $staffAbsenceTypeGateway = $container->get(StaffAbsenceTypeGateway::class);

    if (empty($gibbonStaffAbsenceID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $values = $staffAbsenceGateway->getByID($gibbonStaffAbsenceID);

    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = Form::create('staffAbsenceEdit', $_SESSION[$guid]['absoluteURL'].'/modules/Staff/coverage_requestProcess.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addHiddenValue('gibbonStaffAbsenceID', $gibbonStaffAbsenceID);

    $form->addRow()->addHeading(__('Coverage Request'));

    $requestTypes = [
        'Broadcast'  => __('Any available substitute'),
        'Individual' => __('Specific substitute'),
    ];
    $row = $form->addRow();
        $row->addLabel('requestType', __('Substitute Required?'));
        $row->addSelect('requestType')->isRequired()->fromArray($requestTypes)->selected('Broadcast');

    $form->toggleVisibilityByClass('coverageOptions')->onSelect('requestType')->when('Individual');
    $form->toggleVisibilityByClass('broadcastOptions')->onSelect('requestType')->when('Broadcast');
        
    $notification = __("SMS and email");
    $row = $form->addRow()->addClass('coverageOptions');
        $row->addAlert(__("This option sends your request by {notification} to the selected substitute.", ['notification' => $notification]).' '.__("You'll receive a notification when they accept or decline your request. If your request is declined you'll have to option to send a new request."), 'message');

    $row = $form->addRow()->addClass('broadcastOptions');
        $row->addAlert(__("This option sends a request by {notification} out to <b>ALL</b> available subs.", ['notification' => $notification]).' '.__("You'll receive a notification once your request is accepted."), 'message');

    $row = $form->addRow()->addClass('coverageOptions');
        $row->addLabel('gibbonPersonIDCoverage', __('Substitute'));
        $row->addSelectSubstitute('gibbonPersonIDCoverage')->placeholder()->selected($gibbonPersonIDCoverage)->isRequired();

    $row = $form->addRow()->addClass('coverageOptions');
        $row->addContent('<div class="datesTable"></div>');

    $row = $form->addRow();
        $row->addLabel('notesRequested', __('Comment'))->description(__('This message is shared with substitutes, and is also visible to users who manage staff coverage.'));
        $row->addTextArea('notesRequested')->setRows(3);

    // if ($datesAvailableToRequest > 0) {
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();
    // }

    echo $form->getOutput();
}
?>

<script>
$(document).ready(function() {
    $('#gibbonPersonIDCoverage').on('input', function() {
        $('.datesTable').load('./modules/Staff/coverage_requestAjax.php', {
            'gibbonStaffAbsenceID': '<?php echo $gibbonStaffAbsenceID; ?>',
            'gibbonPersonIDCoverage': $(this).val(),
        }, function() {
            // Pre-highlight selected rows
            $('.bulkActionForm').find('.bulkCheckbox :checkbox').each(function () {
                $(this).closest('tr').toggleClass('selected', $(this).prop('checked'));
            });
        });
    });
}) ;
</script>
