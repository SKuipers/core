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

use Gibbon\Http\Url;
use Gibbon\Services\Format;
use Gibbon\Support\Facades\Access;
use Gibbon\Module\Staff\Profile\Sidebar;
use Gibbon\Module\Staff\Profile\HookPage;

if (!Access::allows('Staff', 'staff_view_details')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

// Get action with highest precedence
$highestAction = Access::get('Staff', 'staff_view_details');
if (empty($highestAction)) {
    $page->addError(__('The highest grouped action cannot be determined.'));
    return;
} else {

    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $search = $_GET['search'] ?? '';
    $allStaff = $_GET['allStaff'] ?? '';
    $subpage = $_GET['subpage'] ?? '';
    $hook = $_GET['hook'] ?? '';

    if (empty($gibbonPersonID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    if (!empty($search)) {
        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('Staff', 'staff_view.php')->withQueryParam('search', $search));
    }

    // Handle brief profile view
    if ($highestAction->allows('Staff Directory_brief') && !$highestAction->allows('Staff Directory_full')) {
        $briefPage = $container->get(\Gibbon\Module\Staff\Profile\BriefPage::class);
        $briefPage->setStaff($session->get('gibbonSchoolYearID'), $gibbonPersonID);
        
        if (!$briefPage->checkAccess()) {
            $page->addError(__('You do not have access to this action.'));
            return;
        }
        
        echo $briefPage->getOutput();
        return;
    }

    // Handle full profile view
    $staffGateway = $container->get(\Gibbon\Domain\Staff\StaffGateway::class);
    $userGateway = $container->get(\Gibbon\Domain\User\UserGateway::class);

    if ($allStaff != 'on') {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'today' => date('Y-m-d')];
        $sql = "SELECT gibbonPerson.*, gibbonStaff.initials, gibbonStaff.type, gibbonStaff.jobTitle, countryOfOrigin, qualifications, biography, gibbonStaff.gibbonStaffID, firstAidQualified, firstAidQualification, firstAidExpiry, gibbonStaff.fields as fieldsStaff 
                FROM gibbonPerson 
                JOIN gibbonStaff ON (gibbonStaff.gibbonPersonID=gibbonPerson.gibbonPersonID) 
                WHERE status='Full' 
                AND (dateStart IS NULL OR dateStart<=:today) 
                AND (dateEnd IS NULL OR dateEnd>=:today) 
                AND gibbonPerson.gibbonPersonID=:gibbonPersonID";
        $result = $pdo->select($sql, $data);
        $row = $result->rowCount() == 1 ? $result->fetch() : null;
    } else {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = 'SELECT gibbonPerson.*, gibbonStaff.initials, gibbonStaff.type, gibbonStaff.jobTitle, countryOfOrigin, qualifications, biography, gibbonStaff.gibbonStaffID, firstAidQualified, firstAidQualification, firstAidExpiry, gibbonStaff.fields as fieldsStaff 
                FROM gibbonPerson 
                JOIN gibbonStaff ON (gibbonStaff.gibbonPersonID=gibbonPerson.gibbonPersonID) 
                WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID';
        $result = $pdo->select($sql, $data);
        $row = $result->rowCount() == 1 ? $result->fetch() : null;
    }

    if (empty($row)) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('Staff Directory'), 'staff_view.php', ['search' => $search, 'allStaff' => $allStaff])
        ->add(Format::name('', $row['preferredName'], $row['surname'], 'Student'));

    if (empty($subpage) && empty($hook)) {
        $subpage = 'Overview';
    }

    // Map subpage names to class names for class-based routing
    $subpageClasses = [
        'Overview'           => \Gibbon\Module\Staff\Profile\OverviewPage::class,
        'Personal'           => \Gibbon\Module\Staff\Profile\PersonalPage::class,
        'Family'             => \Gibbon\Module\Staff\Profile\FamilyPage::class,
        'Facilities'         => \Gibbon\Module\Staff\Profile\FacilitiesPage::class,
        'Emergency Contacts' => \Gibbon\Module\Staff\Profile\EmergencyContactsPage::class,
        'Activities'         => \Gibbon\Module\Staff\Profile\ActivitiesPage::class,
        'Timetable'          => \Gibbon\Module\Staff\Profile\TimetablePage::class,
    ];

    // Handle hook-based subpages (third-party integrations)
    if (!empty($hook)) {
        $hook = preg_replace('/[^a-zA-Z0-9-_\s]/', '', $hook);

        $hookPage = $container->get(HookPage::class);
        $hookPage->setStaff($session->get('gibbonSchoolYearID'), $gibbonPersonID);
        $hookPage->setHook($hook, $_GET['gibbonHookID'] ?? '');
        
        echo '<h2>';
        echo $hookPage->getPageName();
        echo '</h2>';
        
        if (!$hookPage->checkAccess()) {
            echo Format::alert(__('Your request failed because you do not have access to this action.'), 'error');
        } else {
            echo $hookPage->getOutput();
        }
    } elseif (isset($subpageClasses[$subpage])) {
        // Handle class-based subpages
        $pageClass = $subpageClasses[$subpage];
        $profilePage = $container->get($pageClass);

        echo '<h2>';
        echo $profilePage->getPageName();
        echo '</h2>';
        
        // Set staff context
        $profilePage->setStaff($session->get('gibbonSchoolYearID'), $gibbonPersonID);
        
        // Check access
        if (!$profilePage->checkAccess()) {
            echo Format::alert(__('Your request failed because you do not have access to this action.'), 'error');
        } else {
            // Render output
            echo $profilePage->getOutput();
        }
    } elseif ($subpage != '') {
        // Invalid subpage
        echo Format::alert(__('You do not have access to this action.'), 'error');
    }

    // Set sidebar
    $sidebar = $container->get(Sidebar::class);
    $sidebar->setStaff($session->get('gibbonSchoolYearID'), $gibbonPersonID, $row['image_240']);

    $session->set('sidebarExtra', $sidebar->getOutput());
}
