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

namespace Gibbon\Module\Staff\Profile;

use Gibbon\Support\Facades\Access;
use Gibbon\Contracts\Services\Session;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

/**
 * EmergencyContactsPage
 * 
 * Displays emergency contact information for staff members including:
 * - Adult family members with contact information
 * - Emergency contact 1 (name, relationship, phone numbers)
 * - Emergency contact 2 (name, relationship, phone numbers)
 * 
 * Requires 'Manage Staff_confidential' permission to access.
 * 
 * @package Gibbon\Module\Staff\Profile
 */
class EmergencyContactsPage extends ProfilePage
{
    private Connection $pdo;

    public function __construct(
        Session $session,
        Connection $pdo
    ) {
        parent::__construct($session);
        $this->pdo = $pdo;
    }

    public function getPageName(): string
    {
        return __('Emergency Contacts');
    }

    /**
     * Check if the current user has permission to view emergency contacts
     * 
     * Requires 'Manage Staff_confidential' permission as this is confidential data.
     * 
     * @return bool True if user has access, false otherwise
     */
    public function checkAccess(): bool
    {
        return Access::allows('Staff', 'staff_manage', 'Manage Staff_confidential');
    }

    /**
     * Generate HTML output for the emergency contacts page
     * 
     * @return string HTML content for display
     */
    public function getOutput(): string
    {
        // Guard clause: validate staff ID
        if (empty($this->gibbonPersonID)) {
            return Format::alert(__('Invalid staff ID.'), 'error');
        }

        // Fetch staff data
        $staff = $this->fetchStaffData();

        // Guard clause: check if staff exists
        if (empty($staff)) {
            return Format::alert(__('The selected record does not exist.'), 'error');
        }

        $output = '';

        // Add edit button if user has permission
        if (Access::allows('User Admin', 'user_manage_edit')) {
            $form = Form::createBlank('buttons');
            $form->addHeaderAction('edit', __('Edit User'))
                ->setURL('/modules/User Admin/user_manage_edit.php')
                ->addParam('gibbonPersonID', $this->gibbonPersonID)
                ->displayLabel();
            $output .= $form->getOutput();
        }

        $output .= '<p>';
        $output .= __('In an emergency, please try and contact the adult family members listed below first. If these cannot be reached, then try the emergency contacts below.');
        $output .= '</p>';

        // Render adult family members
        $output .= $this->renderAdultFamilyMembers();

        // Render emergency contacts
        $output .= $this->renderEmergencyContacts($staff);

        return $output;
    }

    /**
     * Fetch staff data including emergency contact information
     * 
     * @return array Staff data or empty array if not found
     */
    protected function fetchStaffData(): array
    {
        $data = ['gibbonPersonID' => $this->gibbonPersonID];
        $sql = "SELECT * FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID";

        $result = $this->pdo->select($sql, $data);
        
        return $result->rowCount() > 0 ? $result->fetch() : [];
    }

    /**
     * Render adult family members section
     * 
     * @return string HTML for adult family members
     */
    protected function renderAdultFamilyMembers(): string
    {
        $output = '<h4>';
        $output .= __('Adult Family Members');
        $output .= '</h4>';

        // Query families the staff member belongs to
        $dataFamily = ['gibbonPersonID' => $this->gibbonPersonID];
        $sqlFamily = 'SELECT * FROM gibbonFamily 
                      JOIN gibbonFamilyAdult ON (gibbonFamily.gibbonFamilyID=gibbonFamilyAdult.gibbonFamilyID) 
                      WHERE gibbonFamilyAdult.gibbonPersonID=:gibbonPersonID';
        $resultFamily = $this->pdo->select($sqlFamily, $dataFamily);

        if ($resultFamily->rowCount() == 0) {
            $output .= "<div class='error'>";
            $output .= __('There is no family information available for the current staff member.');
            $output .= '</div>';
            return $output;
        }

        $rowFamily = $resultFamily->fetch();
        $count = 1;

        // Get all adults in the family
        $dataMember = ['gibbonFamilyID' => $rowFamily['gibbonFamilyID']];
        $sqlMember = "SELECT * FROM gibbonFamilyAdult 
                      JOIN gibbonPerson ON (gibbonFamilyAdult.gibbonPersonID=gibbonPerson.gibbonPersonID) 
                      WHERE gibbonFamilyID=:gibbonFamilyID 
                      AND gibbonFamilyAdult.contactCall='Y'
                      ORDER BY contactPriority, surname, preferredName";
        $resultMember = $this->pdo->select($sqlMember, $dataMember);

        while ($rowMember = $resultMember->fetch()) {
            if ($rowMember['gibbonPersonID'] == $this->gibbonPersonID) continue;

            $table = DataTable::createDetails('family' . $count);

            $table->addColumn('preferredName', __('Name'))
                ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Parent']));

            $table->addColumn('phone', __('Contact By Phone'))
                ->format(function($rowMember) {
                    $phones = '';

                    for ($i = 1; $i < 5; ++$i) {
                        if ($rowMember['phone'.$i] != '') {
                            $phones .= Format::phone($rowMember['phone' . $i], $rowMember['phone'.$i.'CountryCode'], $rowMember['phone'.$i.'Type']) . '<br/>';
                        }
                    }

                    return $phones;
                });

            $output .= $table->render([$rowMember]);

            ++$count;
        }

        return $output;
    }

    /**
     * Render emergency contacts section
     * 
     * @param array $staff Staff data
     * @return string HTML for emergency contacts
     */
    protected function renderEmergencyContacts(array $staff): string
    {
        $table = DataTable::createDetails('emergency');
        $table->setTitle(__('Emergency Contacts'));

        for ($i = 1; $i <= 2; $i++) {
            $emergency = 'emergency' . $i;
            $table->addColumn($emergency . 'Name', __('Contact ' . $i))
                ->format(function($row) use ($emergency) {
                    if ($row[$emergency . 'Relationship'] != '') {
                        return $row[$emergency . 'Name'] . ' (' . __($row[$emergency . 'Relationship']) . ')';
                    }
                    return $row[$emergency . 'Name'];
                });

            $table->addColumn($emergency . 'Number1', __('Number 1'));
            $table->addColumn($emergency . 'Number2', __('Number 2'));
        }

        return $table->render([$staff]);
    }
}
