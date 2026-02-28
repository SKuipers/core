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

use Gibbon\Domain\System\HookGateway;
use Gibbon\Forms\CustomFieldHandler;
use Gibbon\Http\Url;
use Gibbon\Services\Format;
use Gibbon\Support\Facades\Access;
use Gibbon\Tables\DataTable;

// Module includes for User Admin (for custom fields)
include './modules/User Admin/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Staff/staff_view_details.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    $highestActionManage = getHighestGroupedAction($guid, "/modules/Staff/staff_manage.php", $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';
        $gibbonPersonID = str_pad($gibbonPersonID, 10, 0, STR_PAD_LEFT);

        if ($gibbonPersonID == '' ) {
            $page->addError(__('You have not specified one or more required parameters.'));
        } else {
            $hookGateway = $container->get(HookGateway::class);
            $search = $_GET['search'] ?? '';
            $allStaff = $_GET['allStaff'] ?? '';
            $hook = $_GET['hook'] ?? '';

            if ($highestAction == 'Staff Directory_brief') {
                // Proceed!
                $data = ['gibbonPersonID' => $gibbonPersonID];
                $sql = "SELECT title, surname, preferredName, type, gibbonStaff.jobTitle, email, website, countryOfOrigin, qualifications, biography, image_240 FROM gibbonPerson JOIN gibbonStaff ON (gibbonStaff.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND gibbonPerson.gibbonPersonID=:gibbonPersonID";
                $result = $connection2->prepare($sql);
                $result->execute($data);

                if ($result->rowCount() != 1) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                } else {
                    $row = $result->fetch();

                    $page->breadcrumbs
                        ->add(__('Staff Directory'), 'staff_view.php')
                        ->add(Format::name('', $row['preferredName'], $row['surname'], 'Student'));

                    if ($search != '') {
                        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('Staff', 'staff_view.php')->withQueryParam('search', $search));
                    }

                    // Overview
                    $table = DataTable::createDetails('overview');

                    $col = $table->addColumn('Basic Information');

                    $col->addColumn('preferredName', __('Name'))
                        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Parent']));
                    $col->addColumn('type', __('Staff Type'));
                    $col->addColumn('jobTitle', __('Job Title'));
                    $col->addColumn('email', __('Email'))->format(Format::using('link', 'email'));
                    $col->addColumn('website', __('Website'))->format(Format::using('link', 'website'));

                    $col = $table->addColumn('Biography', __('Biography'));

                    $col->addColumn('countryOfOrigin', __('Country Of Origin'));
                    $col->addColumn('qualifications', __('Qualifications'))->addClass('col-span-2');
                    $col->addColumn('biography', __('Biography'))->addClass('col-span-3');

                    echo $table->render([$row]);

                    $page->addSidebarExtra(Format::userPhoto($row['image_240'], 240));
                }
            } else {
                try {
                    $data = array('gibbonPersonID' => $gibbonPersonID);
                    if ($allStaff != 'on') {
                        $sql = "SELECT gibbonPerson.*, gibbonStaff.initials, gibbonStaff.type, gibbonStaff.jobTitle, countryOfOrigin, qualifications, biography, gibbonStaff.gibbonStaffID, firstAidQualified, firstAidQualification, firstAidExpiry, gibbonStaff.fields as fieldsStaff FROM gibbonPerson JOIN gibbonStaff ON (gibbonStaff.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND gibbonPerson.gibbonPersonID=:gibbonPersonID";
                    } else {
                        $sql = 'SELECT gibbonPerson.*, gibbonStaff.initials, gibbonStaff.type, gibbonStaff.jobTitle, countryOfOrigin, qualifications, biography, gibbonStaff.gibbonStaffID, firstAidQualified, firstAidQualification, firstAidExpiry, gibbonStaff.fields as fieldsStaff FROM gibbonPerson JOIN gibbonStaff ON (gibbonStaff.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID';
                    }
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                }

                if ($result->rowCount() != 1) {
                    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
                } else {
                    $row = $result->fetch();

                    $customFieldHandler = $container->get(CustomFieldHandler::class);
                    $hooks = $hookGateway->selectHooksByType('Staff Profile')->fetchGroupedUnique();
                    $hooks = array_map(function ($item) {
                        $item['options'] = unserialize($item['options']);
                        return $item;
                    }, $hooks);

                    $page->breadcrumbs
                        ->add(__('Staff Directory'), 'staff_view.php', ['search' => $search, 'allStaff' => $allStaff])
                        ->add(Format::name('', $row['preferredName'], $row['surname'], 'Student'));

                    $subpage = null;
                    if (isset($_GET['subpage'])) {
                        $subpage = $_GET['subpage'] ?? '';
                    }
                    if ($subpage == '' and $hook == '') {
                        $subpage = 'Overview';
                    }

                    // Map subpage names to class names
                    $subpageClasses = [
                        'Overview' => \Gibbon\Module\Staff\Profile\OverviewPage::class,
                        'Personal' => \Gibbon\Module\Staff\Profile\PersonalPage::class,
                        'Family' => \Gibbon\Module\Staff\Profile\FamilyPage::class,
                        'Facilities' => \Gibbon\Module\Staff\Profile\FacilitiesPage::class,
                        'Emergency Contacts' => \Gibbon\Module\Staff\Profile\EmergencyContactsPage::class,
                        'Activities' => \Gibbon\Module\Staff\Profile\ActivitiesPage::class,
                        'Timetable' => \Gibbon\Module\Staff\Profile\TimetablePage::class,
                    ];

                    if ($search != '') {
                        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('Staff', 'staff_view.php')->withQueryParam('search', $search));
                    }

                    echo '<h2>';
                    if ($subpage != '') {
                        echo __($subpage);
                    } else {
                        echo $hook;
                    }
                    echo '</h2>';

                    // Handle hook-based subpages (third-party integrations)
                    if (!empty($hook)) {
                        $rowHook = $hookGateway->getByID($_GET['gibbonHookID'] ?? '');
                        if (empty($rowHook)) {
                            echo $page->getBlankSlate();
                        } else {
                            $options = unserialize($rowHook['options']);

                            // Check for permission to hook
                            $hookPermission = $hookGateway->getHookPermission($rowHook['gibbonHookID'], $session->get('gibbonRoleIDCurrent'), $options['sourceModuleName'] ?? '', $options['sourceModuleAction'] ?? '');

                            if (empty($options) || empty($hookPermission)) {
                                echo Format::alert(__('Your request failed because you do not have access to this action.'), 'error');
                            } else {
                                $include = $session->get('absolutePath').'/modules/'.$options['sourceModuleName'].'/'.$options['sourceModuleInclude'];
                                if (!file_exists($include)) {
                                    echo Format::alert(__('The selected page cannot be displayed due to a hook error.'), 'error');
                                } else {
                                    include $include;
                                }
                            }
                        }
                    }
                    // Handle class-based subpages
                    elseif (isset($subpageClasses[$subpage])) {
                        // Instantiate the subpage class using the container
                        $pageClass = $subpageClasses[$subpage];
                        $subpageInstance = $container->get($pageClass);
                        
                        // Set staff context (school year ID, person ID, and userImage)
                        $subpageInstance->setStaff($session->get('gibbonSchoolYearID'), $gibbonPersonID, $row['image_240'] ?? '');
                        
                        // Check access permissions
                        if (!$subpageInstance->checkAccess()) {
                            echo Format::alert(__('Your request failed because you do not have access to this action.'), 'error');
                        } else {
                            // Render and display output
                            echo $subpageInstance->getOutput();
                        }
                    }
                    // Fallback for invalid subpage parameters
                    elseif (!empty($subpage)) {
                        echo Format::alert(__('Invalid subpage specified.'), 'error');
                    }

                    $page->addSidebarExtra($page->fetchFromTemplate('profile/sidebar.twig.html', [
                        'canViewEmergency' => ($highestActionManage == 'Manage Staff_confidential') ? true : false,
                        'userPhoto' => Format::userPhoto($row['image_240'], 240),
                        'canViewTimetable' => Access::allows('Timetable', 'tt_view'),
                        'gibbonPersonID' => $gibbonPersonID,
                        'subpage' => $subpage,
                        'search' => $search,
                        'allStaff' => $allStaff,
                        'hooks' => $hooks,
                        'currentHook' => $hook,
                        'q' => $_GET['q'] ?? '',
                    ]));
                }
            }
        }
    }
}
