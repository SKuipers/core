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
along with this program. If not, see <http:// www.gnu.org/licenses/>.
*/

use Gibbon\Domain\DataUpdater\DataUpdaterGateway;
use Gibbon\View\Page;

/**
 * BOOTSTRAP
 *
 * The bootstrapping process creates the essential variables and services for
 * Gibbon. These are required for all scripts: page views, CLI and API.
 */
// Gibbon system-wide include
require_once './gibbon.php';

// Module include: Messenger has a bug where files have been relying on these
// functions because this file was included via getNotificationTray()
// TODO: Fix that :)
require_once './modules/Messenger/moduleFunctions.php';

// Setup the Page and Session objects
$page = $container->get('page');
$session = $container->get('session');

$isLoggedIn = $session->has('username') && $session->has('gibbonRoleIDCurrent');

/**
 * CACHE & INITIAL PAGE LOAD
 *
 * The 'pageLoads' value is used to run code when the user first logs in, and
 * also to reload cached content based on the $caching value in config.php
 *
 * TODO: When we implement routing, these can become part of the HTTP middleware.
 */
$session->set('pageLoads', !$session->exists('pageLoads') ? 0 : $session->get('pageLoads', -1)+1);

$cacheLoad = true;
$caching = $gibbon->getConfig('caching');
if (!empty($caching) && is_numeric($caching)) {
    $cacheLoad = $session->get('pageLoads') % intval($caching) == 0;
}

/**
 * SYSTEM SETTINGS
 *
 * Checks to see if system settings are set from database. If not, tries to
 * load them in. If this fails, something horrible has gone wrong ...
 *
 * TODO: Move this to the Session creation logic.
 * TODO: Handle the exit() case with a pre-defined error template.
 */
if (!$session->has('systemSettingsSet')) {
    getSystemSettings($guid, $connection2);

    if (!$session->has('systemSettingsSet')) {
        exit(__('System Settings are not set: the system cannot be displayed'));
    }
}

/**
 * USER REDIRECTS
 *
 * TODO: When we implement routing, these can become part of the HTTP middleware.
 */

// Check for force password reset flag
if ($session->has('passwordForceReset')) {
    if ($session->get('passwordForceReset') == 'Y' and $session->get('address') != 'preferences.php') {
        $URL = $session->get('absoluteURL').'/index.php?q=preferences.php';
        $URL = $URL.'&forceReset=Y';
        header("Location: {$URL}");
        exit();
    }
}

// Redirects after login
if ($session->get('pageLoads') == 0 && !$session->has('address')) { // First page load, so proceed

    if ($session->has('username')) { // Are we logged in?
        $roleCategory = getRoleCategory($session->get('gibbonRoleIDCurrent'), $connection2);

        // Deal with attendance self-registration redirect
        // Are we a student?
        if ($roleCategory == 'Student') {
            // Can we self register?
            if (isActionAccessible($guid, $connection2, '/modules/Attendance/attendance_studentSelfRegister.php')) {
                // Check to see if student is on site
                $studentSelfRegistrationIPAddresses = getSettingByScope(
                    $connection2,
                    'Attendance',
                    'studentSelfRegistrationIPAddresses'
                );
                $realIP = getIPAddress();
                if ($studentSelfRegistrationIPAddresses != '' && !is_null($studentSelfRegistrationIPAddresses)) {
                    $inRange = false ;
                    foreach (explode(',', $studentSelfRegistrationIPAddresses) as $ipAddress) {
                        if (trim($ipAddress) == $realIP) {
                            $inRange = true ;
                        }
                    }
                    if ($inRange) {
                        $currentDate = date('Y-m-d');
                        if (isSchoolOpen($guid, $currentDate, $connection2, true)) { // Is school open today
                            // Check for existence of records today
                            try {
                                $data = array('gibbonPersonID' => $session->get('gibbonPersonID'), 'date' => $currentDate);
                                $sql = "SELECT type FROM gibbonAttendanceLogPerson WHERE gibbonPersonID=:gibbonPersonID AND date=:date ORDER BY timestampTaken DESC";
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $page->addError($e->getMessage());
                            }

                            if ($result->rowCount() == 0) {
                                // No registration yet
                                // Redirect!
                                $URL = $session->get('absoluteURL').
                                    '/index.php?q=/modules/Attendance'.
                                    '/attendance_studentSelfRegister.php'.
                                    '&redirect=true';
                                $session->set('pageLoads', null);
                                header("Location: {$URL}");
                                exit;
                            }
                        }
                    }
                }
            }
        }

        // Deal with Data Updater redirect (if required updates are enabled)
        $requiredUpdates = getSettingByScope($connection2, 'Data Updater', 'requiredUpdates');
        if ($requiredUpdates == 'Y') {
            if (isActionAccessible($guid, $connection2, '/modules/Data Updater/data_updates.php')) { // Can we update data?
                $redirectByRoleCategory = getSettingByScope(
                    $connection2,
                    'Data Updater',
                    'redirectByRoleCategory'
                );
                $redirectByRoleCategory = explode(',', $redirectByRoleCategory);

                // Are we the right role category?
                if (in_array($roleCategory, $redirectByRoleCategory)) {
                    $gateway = new DataUpdaterGateway($pdo);

                    $updatesRequiredCount = $gateway->countAllRequiredUpdatesByPerson($session->get('gibbonPersonID'));
                    
                    if ($updatesRequiredCount > 0) {
                        $URL = $session->get('absoluteURL').'/index.php?q=/modules/Data Updater/data_updates.php&redirect=true';
                        $session->set('pageLoads', null);
                        header("Location: {$URL}");
                        exit;
                    }
                }
            }
        }
    }
}

/**
 * SIDEBAR SETUP
 *
 * TODO: move all of the sidebar session variables to the $page->addSidebarExtra() method.
 */

// Set sidebar extra content values via Session.
$session->set('sidebarExtra', '');
$session->set('sidebarExtraPosition', 'top');

// Don't display the sidebar if the URL 'sidebar' param is explicitly set to 'false'
$showSidebar = !isset($_GET['sidebar']) || (strtolower($_GET['sidebar']) !== 'false');

// Check the current Action 'entrySidebar' to see if we should display a sidebar
if ($showSidebar && $page->getAction()) {
    $showSidebar = $page->getAction()['entrySidebar'] != 'N';
}

/**
 * SESSION TIMEOUT
 *
 * Set session duration, which will be passed via JS config to setup the
 * session timeout. Ensures a minimum session duration of 1200.
 */
$sessionDuration = -1;
if ($isLoggedIn) {
    $sessionDuration = $session->get('sessionDuration');
    $sessionDuration = max(intval($sessionDuration), 1200);
}

/**
 * LOCALE
 *
 * Sets the i18n locale for jQuery UI DatePicker (if the file exists, otherwise
 * falls back to en-GB)
 */
$localeCode = str_replace('_', '-', $session->get('i18n')['code']);
$localeCodeShort = substr($session->get('i18n')['code'], 0, 2);
$localePath = $session->get('absolutePath').'/lib/jquery-ui/i18n/jquery.ui.datepicker-%1$s.js';

$datepickerLocale = 'en-GB';
if (is_file(sprintf($localePath, $localeCode))) {
    $datepickerLocale = $localeCode;
} elseif (is_file(sprintf($localePath, $localeCodeShort))) {
    $datepickerLocale = $localeCodeShort;
}

/**
 * JAVASCRIPT
 *
 * The config array defines a set of PHP values that are encoded and passed to
 * the setup.js file, which handles initialization of js libraries.
 */
$javascriptConfig = [
    'config' => [
        'datepicker' => [
            'locale' => $datepickerLocale,
        ],
        'thickbox' => [
            'pathToImage' => $session->get('absoluteURL').'/lib/thickbox/loadingAnimation.gif',
        ],
        'tinymce' => [
            'valid_elements' => getSettingByScope($connection2, 'System', 'allowableHTML'),
        ],
        'sessionTimeout' => [
            'sessionDuration' => $sessionDuration,
            'message' => __('Your session is about to expire: you will be logged out shortly.'),
        ]
    ],
];

/**
 * There are currently a handful of scripts that must be in the page <HEAD>.
 * Otherwise, the preference is to add javascript to the 'foot' at the bottom
 * of the page, which speeds up rendering by deferring their execution until
 * after all content has loaded.
 */

// Set page scripts: head
$page->scripts()->addMultiple([
    'lv'             => 'lib/LiveValidation/livevalidation_standalone.compressed.js',
    'jquery'         => 'lib/jquery/jquery.js',
    'jquery-migrate' => 'lib/jquery/jquery-migrate.min.js',
    'jquery-ui'      => 'lib/jquery-ui/js/jquery-ui.min.js',
    'jquery-time'    => 'lib/jquery-timepicker/jquery.timepicker.min.js',
    'jquery-chained' => 'lib/chained/jquery.chained.min.js',
    'jquery-exif'    => 'lib/jquery-cropit/exif.js',
    'jquery-cropit'  => 'jquery-cropit/jquery.cropit.js',
    'core'           => 'resources/assets/js/core.js',
], ['context' => 'head']);

// Set page scripts: foot - jquery
$page->scripts()->addMultiple([
    'jquery-latex'    => 'lib/jquery-jslatex/jquery.jslatex.js',
    'jquery-form'     => 'lib/jquery-form/jquery.form.js',
    'jquery-date'     => 'lib/jquery-ui/i18n/jquery.ui.datepicker-'.$datepickerLocale.'.js',
    'jquery-autosize' => 'lib/jquery-autosize/jquery.autosize.min.js',
    'jquery-timeout'  => 'lib/jquery-sessionTimeout/jquery.sessionTimeout.min.js',
    'jquery-token'    => 'lib/jquery-tokeninput/src/jquery.tokeninput.js',
], ['context' => 'foot']);

// Set page scripts: foot - misc
$thickboxInline = 'var tb_pathToImage="'.$session->get('absoluteURL').'/lib/thickbox/loadingAnimation.gif";';
$page->scripts()->add('thickboxi', $thickboxInline, ['type' => 'inline']);
$page->scripts()->addMultiple([
    'thickbox' => 'lib/thickbox/thickbox-compressed.js',
    'tinymce'  => 'lib/tinymce/tinymce.min.js',
], ['context' => 'foot']);

// Set page scripts: foot - core
$page->scripts()->add('core-config', 'window.Gibbon = '.json_encode($javascriptConfig).';', ['type' => 'inline']);
$page->scripts()->add('core-setup', 'resources/assets/js/setup.js');

// Set system analytics code from session cache
$page->addHeadExtra($session->get('analytics'));

/**
 * STYLESHEETS & CSS
 */
$page->stylesheets()->addMultiple([
    'jquery-ui'    => 'lib/jquery-ui/css/blitzer/jquery-ui.css',
    'jquery-time'  => 'lib/jquery-timepicker/jquery.timepicker.css',
    'jquery-token' => 'lib/jquery-tokeninput/styles/token-input-facebook.css',
    'thickbox'     => 'lib/thickbox/thickbox.css',
]);

// Set personal background
if (getSettingByScope($connection2, 'User Admin', 'personalBackground') == 'Y' && $session->has('personalBackground')) {
    $backgroundImage = htmlPrep($session->get('personalBackground'));
} else {
    $backgroundImage = $session->get('absoluteURL').'/themes/'.$session->get('gibbonThemeName').'/img/backgroundPage.jpg';
}

$page->stylesheets()->add(
    'personal-background',
    'body { background: url('.$backgroundImage.') repeat scroll center top #A88EDB!important; }',
    ['type' => 'inline']
);

/**
 * USER CONFIGURATION
 *
 * This should be moved to a one-time process to run after login, which can be
 * handled by HTTP middleware.
 */

// Try to auto-set user's calendar feed if not set already
if ($session->exists('calendarFeedPersonal') && $session->exists('googleAPIAccessToken')) {
    if (!$session->has('calendarFeedPersonal') && $session->has('googleAPIAccessToken')) {
        include_once $session->get('absolutePath').'/lib/google/google-api-php-client/vendor/autoload.php';

        $client2 = new Google_Client();
        $client2->setAccessToken($session->get('googleAPIAccessToken'));
        $service = new Google_Service_Calendar($client2);
        $calendar = $service->calendars->get('primary');

        if ($calendar['id'] != '') {
            try {
                $dataCalendar = [
                    'calendarFeedPersonal' => $calendar['id'],
                    'gibbonPersonID' => $session->get('gibbonPersonID'),
                ];
                $sqlCalendar = 'UPDATE gibbonPerson SET
                    calendarFeedPersonal=:calendarFeedPersonal
                    WHERE gibbonPersonID=:gibbonPersonID';
                $resultCalendar = $connection2->prepare($sqlCalendar);
                $resultCalendar->execute($dataCalendar);
            } catch (PDOException $e) {
                exit($e->getMessage());
            }
            $session->set('calendarFeedPersonal', $calendar['id']);
        }
    }
}

// Get house logo and set session variable, only on first load after login (for performance)
if ($session->get('pageLoads') == 0 and $session->has('username') and !$session->has('gibbonHouseIDLogo')) {
    $dataHouse = array('gibbonHouseID' => $session->get('gibbonHouseID'));
    $sqlHouse = 'SELECT logo, name FROM gibbonHouse
        WHERE gibbonHouseID=:gibbonHouseID';
    $house = $pdo->selectOne($sqlHouse, $dataHouse);

    if (!empty($house)) {
        $session->set('gibbonHouseIDLogo', $house['logo']);
        $session->set('gibbonHouseIDName', $house['name']);
    }
}

// Show warning if not in the current school year
// TODO: When we implement routing, these can become part of the HTTP middleware.
if ($isLoggedIn) {
    if ($session->get('gibbonSchoolYearID') != $session->get('gibbonSchoolYearIDCurrent')) {
        $page->addWarning('<b><u>'.sprintf(__('Warning: you are logged into the system in school year %1$s, which is not the current year.'), $session->get('gibbonSchoolYearName')).'</b></u>'.__('Your data may not look quite right (for example, students who have left the school will not appear in previous years), but you should be able to edit information from other years which is not available in the current year.'));
    }
}

/**
 * RETURN PROCESS
 *
 * Adds an alert to the index based on the URL 'return' parameter.
 *
 * TODO: Remove all returnProcess() from pages. We could add a method to the
 * Page class to allow them to register custom messages, or use Session flash
 * to add the message directly from the Process pages.
 */
if (!$session->has('address') && !empty($_GET['return'])) {
    $customReturns = [
        'success1' => __('Password reset was successful: you may now log in.')
    ];

    if ($alert = returnProcessGetAlert($_GET['return'], '', $customReturns)) {
        $page->addAlert($alert['context'], $alert['text']);
    }
}

/**
 * GET PAGE CONTENT
 *
 * TODO: move queries into Gateway classes.
 * TODO: rewrite welcome page & dashboards as template files.
 */
if (!$session->has('address')) {
    // Welcome message
    if (!$isLoggedIn) {
        // Create auto timeout message
        if (isset($_GET['timeout']) && $_GET['timeout'] == 'true') {
            $page->addWarning(__('Your session expired, so you were automatically logged out of the system.'));
        }

        // Set welcome message
        $page->write('<h2>'.__('Welcome').'</h2><p>'.$session->get('indexText').'</p>');

        // Student public applications permitted?
        $publicApplications = getSettingByScope($connection2, 'Application Form', 'publicApplications');
        if ($publicApplications == 'Y') {
            $page->write("<h2 style='margin-top: 30px'>".
                __('Student Applications').'</h2>'.
                '<p>'.
                sprintf(__('Parents of students interested in study at %1$s may use our %2$s online form%3$s to initiate the application process.'), $session->get('organisationName'), "<a href='".$session->get('absoluteURL')."/?q=/modules/Students/applicationForm.php'>", '</a>').
                '</p>');
        }

        // Staff public applications permitted?
        $staffApplicationFormPublicApplications = getSettingByScope($connection2, 'Staff Application Form', 'staffApplicationFormPublicApplications');
        if ($staffApplicationFormPublicApplications == 'Y') {
            $page->write("<h2 style='margin-top: 30px'>" .
                __('Staff Applications') .
                '</h2>'.
                '<p>'.
                sprintf(__('Individuals interested in working at %1$s may use our %2$s online form%3$s to view job openings and begin the recruitment process.'), $session->get('organisationName'), "<a href='".$session->get('absoluteURL')."/?q=/modules/Staff/applicationForm_jobOpenings_view.php'>", '</a>').
                '</p>');
        }

        // Public departments permitted?
        $makeDepartmentsPublic = getSettingByScope($connection2, 'Departments', 'makeDepartmentsPublic');
        if ($makeDepartmentsPublic == 'Y') {
            $page->write("<h2 style='margin-top: 30px'>".
                __('Departments').
                '</h2>'.
                '<p>'.
                sprintf(__('Please feel free to %1$sbrowse our departmental information%2$s, to learn more about %3$s.'), "<a href='".$session->get('absoluteURL')."/?q=/modules/Departments/departments.php'>", '</a>', $session->get('organisationName')).
                '</p>');
        }

        // Public units permitted?
        $makeUnitsPublic = getSettingByScope($connection2, 'Planner', 'makeUnitsPublic');
        if ($makeUnitsPublic == 'Y') {
            $page->write("<h2 style='margin-top: 30px'>".
                __('Learn With Us').
                '</h2>'.
                '<p>'.
                sprintf(__('We are sharing some of our units of study with members of the public, so you can learn with us. Feel free to %1$sbrowse our public units%2$s.'), "<a href='".$session->get('absoluteURL')."/?q=/modules/Planner/units_public.php&sidebar=false'>", '</a>', $session->get('organisationName')).
                '</p>');
        }

        // Get any elements hooked into public home page, checking if they are turned on
        try {
            $dataHook = array();
            $sqlHook = "SELECT * FROM gibbonHook WHERE type='Public Home Page' ORDER BY name";
            $resultHook = $connection2->prepare($sqlHook);
            $resultHook->execute($dataHook);
        } catch (PDOException $e) {
        }
        while ($rowHook = $resultHook->fetch()) {
            $options = unserialize(str_replace("'", "\'", $rowHook['options']));
            $check = getSettingByScope($connection2, $options['toggleSettingScope'], $options['toggleSettingName']);
            if ($check == $options['toggleSettingValue']) { // If its turned on, display it
                $page->write("<h2 style='margin-top: 30px'>".
                    $options['title'].
                    '</h2>'.
                    '<p>'.
                    stripslashes($options['text']).
                    '</p>');
            }
        }
    } else {
        // Custom content loader
        if (!$session->exists('index_custom.php')) {
            $session->set('index_custom.php', $page->fetchFromFile('./index_custom.php'));
        } elseif ($session->has('index_custom.php')) {
            $page->write($session->get('index_custom.php'));
        }

        // DASHBOARDS!
        // Get role category
        $category = getRoleCategory($session->get('gibbonRoleIDCurrent'), $connection2);
        if ($category == false) {
            $page->write('<div class="error">'.__('Your current role type cannot be determined.').'</div>');
        } elseif ($category == 'Parent') {
            // Display Parent Dashboard
            $count = 0;
            try {
                $data = ['gibbonPersonID' => $session->get('gibbonPersonID')];
                $sql = "SELECT * FROM gibbonFamilyAdult WHERE
                    gibbonPersonID=:gibbonPersonID AND childDataAccess='Y'";
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                $page->addError($e->getMessage());
            }

            if ($result->rowCount() > 0) {
                // Get child list
                $count = 0;
                $options = '';
                $students = array();
                while ($row = $result->fetch()) {
                    try {
                        $dataChild = [
                            'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'),
                            'gibbonFamilyID' => $row['gibbonFamilyID'],
                            'today' => date('Y-m-d'),
                        ];
                        $sqlChild = "SELECT
                            gibbonPerson.gibbonPersonID,image_240, surname,
                            preferredName, dateStart,
                            gibbonYearGroup.nameShort AS yearGroup,
                            gibbonRollGroup.nameShort AS rollGroup,
                            gibbonRollGroup.website AS rollGroupWebsite,
                            gibbonRollGroup.gibbonRollGroupID
                            FROM gibbonFamilyChild JOIN gibbonPerson ON (gibbonFamilyChild.gibbonPersonID=gibbonPerson.gibbonPersonID)
                            JOIN gibbonStudentEnrolment ON (gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID)
                            JOIN gibbonYearGroup ON (gibbonStudentEnrolment.gibbonYearGroupID=gibbonYearGroup.gibbonYearGroupID)
                            JOIN gibbonRollGroup ON (gibbonStudentEnrolment.gibbonRollGroupID=gibbonRollGroup.gibbonRollGroupID)
                            WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                            AND gibbonFamilyID=:gibbonFamilyID
                            AND gibbonPerson.status='Full'
                            AND (dateStart IS NULL OR dateStart<=:today)
                            AND (dateEnd IS NULL OR dateEnd>=:today)
                            ORDER BY surname, preferredName ";
                        $resultChild = $connection2->prepare($sqlChild);
                        $resultChild->execute($dataChild);
                    } catch (PDOException $e) {
                        $page->addError($e->getMessage());
                    }
                    while ($rowChild = $resultChild->fetch()) {
                        $students[$count][0] = $rowChild['surname'];
                        $students[$count][1] = $rowChild['preferredName'];
                        $students[$count][2] = $rowChild['yearGroup'];
                        $students[$count][3] = $rowChild['rollGroup'];
                        $students[$count][4] = $rowChild['gibbonPersonID'];
                        $students[$count][5] = $rowChild['image_240'];
                        $students[$count][6] = $rowChild['dateStart'];
                        $students[$count][7] = $rowChild['gibbonRollGroupID'];
                        $students[$count][8] = $rowChild['rollGroupWebsite'];
                        ++$count;
                    }
                }
            }

            if ($count > 0) {
                include_once './modules/Timetable/moduleFunctions.php';

                $output = '<h2>'.__('Parent Dashboard').'</h2>';

                for ($i = 0; $i < $count; ++$i) {
                    $output .= '<h4>'.
                        $students[$i][1].' '.$students[$i][0].
                        '</h4>';

                    $output .= "<div style='margin-right: 1%; float:left; width: 15%; text-align: center'>".
                        getUserPhoto($guid, $students[$i][5], 75).
                        "<div style='height: 5px'></div>".
                        "<span style='font-size: 70%'>".
                        "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID='.$students[$i][4]."'>".__('Student Profile').'</a><br/>';

                    if (isActionAccessible($guid, $connection2, '/modules/Roll Groups/rollGroups_details.php')) {
                        $output .= "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/Roll Groups/rollGroups_details.php&gibbonRollGroupID='.$students[$i][7]."'>".__('Roll Group').' ('.$students[$i][3].')</a><br/>';
                    }
                    if ($students[$i][8] != '') {
                        $output .= "<a target='_blank' href='".$students[$i][8]."'>".$students[$i][3].' '.__('Website').'</a>';
                    }

                    $output .= '</span>';
                    $output .= '</div>';
                    $output .= "<div style='margin-bottom: 30px; margin-left: 1%; float: left; width: 83%'>";
                    $dashboardContents = getParentDashboardContents($connection2, $guid, $students[$i][4]);
                    if ($dashboardContents == false) {
                        $output .= "<div class='error'>".__('There are no records to display.').'</div>';
                    } else {
                        $output .= $dashboardContents;
                    }
                    $output .= '</div>';
                }

                $page->write($output);
            }
        } elseif ($category == 'Student') {
            // Display Student Dashboard
            $output = '<h2>'.
                __('Student Dashboard').
                '</h2>'.
                "<div style='margin-bottom: 30px; margin-left: 1%; float: left; width: 100%'>";
            $dashboardContents = getStudentDashboardContents($connection2, $guid, $session->get('gibbonPersonID'));
            if ($dashboardContents == false) {
                $output .= "<div class='error'>".
                    __('There are no records to display.').
                    '</div>';
            } else {
                $output .= $dashboardContents;
            }
            $output .= '</div>';

            $page->write($output);
        } elseif ($category == 'Staff') {
            // Display Staff Dashboard

            $output = '';
            $smartWorkflowHelp = getSmartWorkflowHelp($connection2, $guid);
            if ($smartWorkflowHelp != false) {
                $output .= $smartWorkflowHelp;
            }

            $output .= '<h2>'.
                __('Staff Dashboard').
                '</h2>'.
                "<div style='margin-bottom: 30px; margin-left: 1%; float: left; width: 100%'>";
            $dashboardContents = getStaffDashboardContents($connection2, $guid, $session->get('gibbonPersonID'));
            if ($dashboardContents == false) {
                $output .= "<div class='error'>".
                    __('There are no records to display.').
                    '</div>';
            } else {
                $output .= $dashboardContents;
            }
            $output .= '</div>';

            $page->write($output);
        }
    }
} else {
    $address = trim($page->getAddress(), ' /');

    if ($page->isAddressValid($address) == false) {
        $page->addError(__('Illegal address detected: access denied.'));
    } else {
        // Pass these globals into the script of the included file, for backwards compatibility.
        // These will be removed when we begin the process of ooifying action pages.
        $globals = [
            'guid'        => $guid,
            'gibbon'      => $gibbon,
            'version'     => $version,
            'pdo'         => $pdo,
            'connection2' => $connection2,
            'autoloader'  => $autoloader,
            'container'   => $container,
        ];

        if (is_file('./'.$address)) {
            $page->writeFromFile('./'.$address, $globals);
        } else {
            $page->writeFromFile('./error.php', $globals);
        }
    }
}

/**
 * GET SIDEBAR CONTENT
 *
 * TODO: rewrite the sidebar() function as a template file.
 */
$sidebarContents = '';
if ($showSidebar) {
    $page->addSidebarExtra($session->get('sidebarExtra'));
    $session->set('sidebarExtra', '');

    ob_start();
    sidebar($gibbon, $pdo);
    $sidebarContents = ob_get_clean();
}

/**
 * MENU ITEMS & FAST FINDER
 *
 * TODO: Move this somewhere more sensible. Refactor to gateway classes.
 */
if ($isLoggedIn) {
    if ($cacheLoad || !$session->has('fastFinder')) {
        $session->set('fastFinder', getFastFinder($connection2, $guid));
    }

    if ($cacheLoad || !$session->has('menuMainItems')) {
        $mainMenuCategoryOrder = getSettingByScope($connection2, 'System', 'mainMenuCategoryOrder');
        $data = array('gibbonRoleID' => $session->get('gibbonRoleIDCurrent'), 'menuOrder' => $mainMenuCategoryOrder);
        $sql = "SELECT gibbonModule.category, gibbonModule.name, gibbonModule.type, gibbonModule.entryURL, gibbonAction.entryURL as alternateEntryURL, (CASE WHEN gibbonModule.type <> 'Core' THEN gibbonModule.name ELSE NULL END) as textDomain
                FROM gibbonModule 
                JOIN gibbonAction ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) 
                JOIN gibbonPermission ON (gibbonPermission.gibbonActionID=gibbonAction.gibbonActionID) 
                WHERE gibbonModule.active='Y' 
                AND gibbonAction.menuShow='Y' 
                AND gibbonPermission.gibbonRoleID=:gibbonRoleID 
                GROUP BY gibbonModule.name 
                ORDER BY FIND_IN_SET(gibbonModule.category, :menuOrder), gibbonModule.category, gibbonModule.name, gibbonAction.name";

        $menuMainItems = $pdo->select($sql, $data)->fetchGrouped();

        foreach ($menuMainItems as $category => &$items) {
            foreach ($items as &$item) {
                $modulePath = '/modules/'.$item['name'];
                $entryURL = isActionAccessible($guid, $connection2, $modulePath.'/'.$item['entryURL'])
                    ? $item['entryURL']
                    : $item['alternateEntryURL'];

                $item['url'] = $session->get('absoluteURL').'/index.php?q='.$modulePath.'/'.$entryURL;
            }
        }

        $session->set('menuMainItems', $menuMainItems);
    }

    if ($page->getModule()) {
        $currentModule = $page->getModule()->getName();
        $menuModule = $session->get('menuModuleName');
        
        if ($cacheLoad || !$session->has('menuModuleItems') || $currentModule != $menuModule) {
            $data = array('gibbonModuleID' => $page->getModule()->getID(), 'gibbonRoleID' => $session->get('gibbonRoleIDCurrent'));
            $sql = "SELECT gibbonAction.category, gibbonModule.entryURL AS moduleEntry, gibbonModule.name AS moduleName, gibbonAction.name as actionName, gibbonModule.type, gibbonAction.precedence, gibbonAction.entryURL, URLList, SUBSTRING_INDEX(gibbonAction.name, '_', 1) as name, (CASE WHEN gibbonModule.type <> 'Core' THEN gibbonModule.name ELSE NULL END) AS textDomain
            FROM gibbonModule
            JOIN gibbonAction ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID)
            JOIN gibbonPermission ON (gibbonAction.gibbonActionID=gibbonPermission.gibbonActionID)
            WHERE (gibbonModule.gibbonModuleID=:gibbonModuleID)
            AND (gibbonPermission.gibbonRoleID=:gibbonRoleID)
            AND NOT gibbonAction.entryURL=''
            AND gibbonAction.menuShow='Y'
            GROUP BY name
            ORDER BY gibbonModule.name, gibbonAction.category, gibbonAction.name, precedence DESC";

            $menuModuleItems = $pdo->select($sql, $data)->fetchGrouped();
        } else {
            $menuModuleItems = $session->get('menuModuleItems');
        }
        
        // Update the menu items to indicate the current active action
        foreach ($menuModuleItems as $category => &$items) {
            foreach ($items as &$item) {
                $item['active'] = stripos($item['URLList'], $session->get('action')) !== false;
                $item['url'] = $session->get('absoluteURL').'/index.php?q=/modules/'
                        .$item['moduleName'].'/'.$item['entryURL'];
            }
        }

        $session->set('menuModuleItems', $menuModuleItems);
        $session->set('menuModuleName', $currentModule);
    } else {
        $session->forget(['menuModuleItems', 'menuModuleName']);
    }
}

/**
 * TEMPLATE DATA
 *
 * These values are merged with the Page class settings & content, then passed
 * into the template engine for rendering. They're a work in progress, but once
 * they're more finalized we can document them for theme developers.
 */
$templateData = [
    'isLoggedIn'        => $isLoggedIn,
    'gibbonThemeName'   => $session->get('gibbonThemeName'),
    'gibbonHouseIDLogo' => $session->get('gibbonHouseIDLogo'),
    'organisationLogo'  => $session->get('organisationLogo'),
    'minorLinks'        => getMinorLinks($connection2, $guid, $cacheLoad),
    'notificationTray'  => getNotificationTray($connection2, $guid, $cacheLoad),
    'sidebar'           => $showSidebar,
    'sidebarContents'   => $sidebarContents,
    'sidebarPosition'   => $session->get('sidebarExtraPosition'),
    'version'           => $gibbon->getVersion(),
    'versionName'       => 'v'.$gibbon->getVersion().($session->get('cuttingEdgeCode') == 'Y'? 'dev' : ''),
];

if ($isLoggedIn) {
    $templateData += [
        'menuMain'   => $session->get('menuMainItems', []),
        'menuModule' => $session->get('menuModuleItems', []),
        'fastFinder' => $session->get('fastFinder'),
    ];
}

/**
 * DONE!!
 */
echo $page->render('index.twig.html', $templateData);
