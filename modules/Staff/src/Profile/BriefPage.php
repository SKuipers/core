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
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

/**
 * BriefPage
 * 
 * Provides a limited profile view for users with only 'Staff Directory_brief' permission.
 * Displays only non-confidential information suitable for a staff directory:
 * - Basic information: name, staff type, job title, email, website
 * - Biography: country of origin, qualifications, biography
 * - Staff photo in sidebar
 * 
 * Does NOT display personal, family, emergency contact, or confidential information.
 * 
 * @package Gibbon\Module\Staff\Profile
 */
class BriefPage extends ProfilePage
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
        return 'Overview';
    }

    public function checkAccess(): bool
    {
        return Access::allows('Staff', 'staff_view_details', 'Staff Directory_brief');
    }

    public function getOutput(): string
    {
        // Guard: validate staff ID
        if (empty($this->gibbonPersonID)) {
            return Format::alert(__('Invalid staff ID.'), 'error');
        }

        // Fetch staff data
        $staff = $this->fetchStaffData();

        // Guard: check data exists
        if (empty($staff)) {
            return Format::alert(__('The selected record does not exist, or you do not have access to it.'), 'error');
        }

        // Display overview table with basic information only
        return $this->renderBriefTable($staff);
    }

    /**
     * Fetch basic staff data for brief profile view
     * Only retrieves non-confidential information
     * 
     * @return array Staff data or empty array if not found
     */
    protected function fetchStaffData(): array
    {
        $data = ['gibbonPersonID' => $this->gibbonPersonID];
        $sql = "SELECT title, surname, preferredName, type, gibbonStaff.jobTitle, email, website, 
                       countryOfOrigin, qualifications, biography, image_240 
                FROM gibbonPerson 
                JOIN gibbonStaff ON (gibbonStaff.gibbonPersonID=gibbonPerson.gibbonPersonID) 
                WHERE status='Full' 
                  AND (dateStart IS NULL OR dateStart<=:currentDate) 
                  AND (dateEnd IS NULL OR dateEnd>=:currentDate) 
                  AND gibbonPerson.gibbonPersonID=:gibbonPersonID";
        
        $data['currentDate'] = date('Y-m-d');
        $result = $this->pdo->select($sql, $data);
        
        return $result->rowCount() > 0 ? $result->fetch() : [];
    }

    /**
     * Render brief profile table with basic information only
     * 
     * @param array $staff Staff data
     * @return string HTML output
     */
    protected function renderBriefTable(array $staff): string
    {
        $table = DataTable::createDetails('overview');

        // Basic Information column
        $col = $table->addColumn('Basic Information');

        $col->addColumn('preferredName', __('Name'))
            ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Parent']));
        $col->addColumn('type', __('Staff Type'));
        $col->addColumn('jobTitle', __('Job Title'));
        $col->addColumn('email', __('Email'))->format(Format::using('link', 'email'));
        $col->addColumn('website', __('Website'))->format(Format::using('link', 'website'));

        // Biography column
        $col = $table->addColumn('Biography', __('Biography'));

        $col->addColumn('countryOfOrigin', __('Country Of Origin'));
        $col->addColumn('qualifications', __('Qualifications'))->addClass('col-span-2');
        $col->addColumn('biography', __('Biography'))->addClass('col-span-3');

        return $table->render([$staff]);
    }
}
