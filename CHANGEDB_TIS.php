<?php
//USE ;end TO SEPERATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

"
ALTER TABLE `gibbonHouse` CHANGE `name` `name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `nameShort` `nameShort` VARCHAR(6) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;end
ALTER TABLE `gibbonCourse` CHANGE `name` `name` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `nameShort` `nameShort` VARCHAR(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '';end
ALTER TABLE `gibbonPerson` CHANGE `surname` `surname` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `firstName` `firstName` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `preferredName` `preferredName` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '', CHANGE `profession` `profession` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `employer` `employer` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `jobTitle` `jobTitle` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `emergency1Name` `emergency1Name` VARCHAR(90) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `emergency2Name` `emergency2Name` VARCHAR(90) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;end
ALTER TABLE `gibbonTTImport` CHANGE `courseNameShort` `courseNameShort` VARCHAR(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Timetable'), 'View Timetable by Person_my', 0, 'View Timetables', 'Allows users to view their own timetable', 'tt.php, tt_view.php', 'tt.php', 'Y', 'Y', 'Y', 'N', 'Y', 'Y', 'Y', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Timetable' AND gibbonAction.name='View Timetable by Person_my'));end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '2', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Timetable' AND gibbonAction.name='View Timetable by Person_my'));end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '3', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Timetable' AND gibbonAction.name='View Timetable by Person_my'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Timetable'), 'View Timetable by Person_myChildren', 0, 'View Timetables', 'Allows parents to view their students timetable', 'tt.php, tt_view.php', 'tt.php', 'N', 'N', 'N', 'Y', 'N', 'N', 'N', 'Y', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '4', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Timetable' AND gibbonAction.name='View Timetable by Person_myChildren'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Students'), 'View Student Profile_my', 2, 'Profiles', 'Allows individuals to view their own information', 'student_view.php, student_view_details.php', 'student_view.php', 'N', 'N', 'Y', 'N', 'N', 'Y', 'Y', 'Y', 'Y');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '3', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Students' AND gibbonAction.name='View Student Profile_my'));end
ALTER TABLE `gibbonPerson` ADD `middleName` VARCHAR(60) NOT NULL AFTER `firstName`;end
ALTER TABLE `gibbonCourse` ADD `credits` DECIMAL(4,2) NULL DEFAULT NULL AFTER `description`, ADD `weight` DECIMAL(4,2) NULL DEFAULT NULL AFTER `credits`;end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Activities'), 'My Activities_viewEditEnrolment', '0', 'Actions', 'Allows a user to manage enrolment for activities they are involved in.', 'activities_my.php,activities_my_full.php,activities_manage_enrolment.php,activities_manage_enrolment_add.php,activities_manage_enrolment_edit.php,activities_manage_enrolment_delete.php', 'activities_my.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Activities' AND gibbonAction.name='My Activities_viewEditEnrolment'));end
CREATE TABLE `gibbonFamilyAdditionalPerson` ( `gibbonFamilyAdditionalPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT , `gibbonFamilyID` INT(7) UNSIGNED ZEROFILL NOT NULL , `sequenceNumber` INT(2) NOT NULL , `name` VARCHAR(120) NULL , `relationship` VARCHAR(60) NULL , `image_240` VARCHAR(255) NULL , `timestamp` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , PRIMARY KEY (`gibbonFamilyAdditionalPersonID`), UNIQUE `unique` (`gibbonFamilyID`, `sequenceNumber`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 ;end
ALTER TABLE `gibbonPerson` CHANGE `canLogin` `canLogin` ENUM('Y','N','A') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'Y';end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Data Updater'), 'Update Family Photos_any', 0, 'Photos', 'Allows users to update family photos', 'data_family_photos.php', 'data_family_photos.php', 'Y', 'Y', 'Y', 'N', 'Y', 'Y', 'Y', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Data Updater' AND gibbonAction.name='Update Family Photos_any'));end
";

// v14
"
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Planner'), 'Student Learning_all', 0, 'Curriculum Overview', 'Allow users to view units and lessons by student.', 'curriculum_viewByStudent.php', 'curriculum_viewByStudent.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Planner' AND gibbonAction.name='Student Learning_all'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Planner'), 'Student Learning_myChildren', 0, 'Curriculum Overview', 'Allow users to view units and lessons by student.', 'curriculum_viewByStudent.php', 'curriculum_viewByStudent.php', 'N', 'N', 'N', 'Y', 'N', 'N', 'N', 'Y', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '4', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Planner' AND gibbonAction.name='Student Learning_myChildren'));end
";


// v15
"
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Planner'), 'Student Learning_all', 0, 'Curriculum Overview', 'Allow users to view units and lessons by student.', 'curriculum_viewByStudent.php', 'curriculum_viewByStudent.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Planner' AND gibbonAction.name='Student Learning_all'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Planner'), 'Student Learning_myChildren', 0, 'Curriculum Overview', 'Allow users to view units and lessons by student.', 'curriculum_viewByStudent.php', 'curriculum_viewByStudent.php', 'N', 'N', 'N', 'Y', 'N', 'N', 'N', 'Y', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '4', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Planner' AND gibbonAction.name='Student Learning_myChildren'));end
ALTER TABLE `gibbonCourse` ADD `gibbonCourseIDParent` INT(8) UNSIGNED ZEROFILL NULL AFTER `gibbonYearGroupIDList`;end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Activities'), 'Activity Choices by Roll Group', 0, 'Reports', 'View all student activity choices in the current year for a given roll group.', 'report_activityChoices_byRollGroup.php', 'report_activityChoices_byRollGroup.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Activities' AND gibbonAction.name='Activity Choices by Roll Group'));end
";

// v16
"DELETE FROM `gibbonPermission` WHERE gibbonActionID=(SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Data Updater' AND gibbonAction.name='Update Family Photos_any');end
DELETE FROM `gibbonAction` WHERE gibbonAction.name='Update Family Photos_any';end
CREATE TABLE `gibbonActivityType` ( `gibbonActivityTypeID` INT(6) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT , `name` VARCHAR(60) NULL, `description` TEXT NULL , `access` ENUM('None','View','Register') NULL DEFAULT 'Register', `enrolmentType` ENUM('Competitive','Selection') NULL DEFAULT 'Competitive', `maxPerStudent` INT(3) NOT NULL DEFAULT '0' , `waitingList` ENUM('Y','N') NULL DEFAULT 'Y', `backupChoice` ENUM('Y','N') NULL DEFAULT 'Y', PRIMARY KEY (`gibbonActivityTypeID`), UNIQUE KEY (`name`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;end
UPDATE `gibbonAction` SET `URLList` = 'activitySettings.php,activitySettings_type_add.php,activitySettings_type_edit.php,activitySettings_type_delete.php' WHERE `name`='Manage Activity Settings' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='School Admin');end
";

// v17
"ALTER TABLE `gibbonApplicationForm` ADD `gibbonPersonIDStudent` INT(10) UNSIGNED ZEROFILL NULL AFTER `dateStart`;end
ALTER TABLE `gibbonApplicationForm` ADD `parent2gibbonPersonID` INT(10) UNSIGNED ZEROFILL NULL AFTER `parent1employer`;end
UPDATE gibbonAction SET urlList='applicationForm_manage.php, applicationForm_manage_edit.php, applicationForm_manage_delete.php, applicationForm_manage_accept.php, applicationForm_manage_reject.php, applicationForm_manage_add.php, applicationForm_manage_family.php' WHERE name='Manage Applications_editDelete' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Students');end
UPDATE gibbonAction SET urlList='applicationForm_manage.php, applicationForm_manage_edit.php, applicationForm_manage_accept.php, applicationForm_manage_reject.php, applicationForm_manage_add.php, applicationForm_manage_family.php' WHERE name='Manage Applications_edit' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Students');end
ALTER TABLE `gibbonExternalAssessmentStudent` ADD `label` VARCHAR(90) NULL AFTER `attachment`;end";


// v18 Sub Booking

"INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='School Admin'), 'Manage Staff Settings', 0, 'People', 'Manage settings for the Staff module', 'staffSettings.php,staffSettings_manage_add.php,staffSettings_manage_edit.php,staffSettings_manage_delete.php', 'staffSettings.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='School Admin' AND gibbonAction.name='Manage Staff Settings'));end
CREATE TABLE `gibbonStaffAbsence` (
    `gibbonStaffAbsenceID` INT(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonStaffAbsenceTypeID` INT(6) UNSIGNED ZEROFILL NOT NULL,
    `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `reason` VARCHAR(60) NULL,
    `comment` TEXT NULL,
    `status` ENUM('Pending Approval','Approved','Declined') DEFAULT 'Approved',
    `gibbonPersonIDApproval` int(10) UNSIGNED ZEROFILL NULL,
    `timestampApproval` timestamp NULL,
    `notesApproval` TEXT NULL,
    `gibbonPersonIDCreator` int(10) UNSIGNED ZEROFILL NOT NULL,
    `timestampCreator` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notificationSent` ENUM('N','Y') DEFAULT 'N',
    `notificationList` TEXT NULL,
    PRIMARY KEY (`gibbonStaffAbsenceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
CREATE TABLE `gibbonStaffAbsenceDate` (
    `gibbonStaffAbsenceDateID` INT(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonStaffAbsenceID` INT(14) UNSIGNED ZEROFILL NULL,
    `gibbonStaffCoverageID` INT(14) UNSIGNED ZEROFILL NULL,
    `date` DATE NULL,
    `allDay` ENUM('N','Y') DEFAULT 'Y',
    `timeStart` time NULL DEFAULT NULL,
    `timeEnd` time NULL DEFAULT NULL,
    PRIMARY KEY (`gibbonStaffAbsenceDateID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
CREATE TABLE `gibbonStaffAbsenceType` (
    `gibbonStaffAbsenceTypeID` INT(6) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(60) NULL,
    `nameShort` VARCHAR(10) NULL,
    `active` ENUM('N','Y') DEFAULT 'Y',
    `requiresApproval` ENUM('N','Y') DEFAULT 'N',
    `reasons` TEXT NULL,
    `sequenceNumber` INT(3) NOT NULL,
    PRIMARY KEY (`gibbonStaffAbsenceTypeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
INSERT INTO `gibbonStaffAbsenceType` (`gibbonStaffAbsenceTypeID`, `name`, `nameShort`, `active`, `requiresApproval`, `reasons`, `sequenceNumber`) VALUES (000001, 'Sick Leave', 'S', 'Y', 'N', '', 1), (000002, 'Personal Leave', 'P', 'Y', 'N', '', 2), (000003, 'Non-paid Leave', 'NP', 'Y', 'N', '', 3), (000004, 'School Related', 'D', 'Y', 'N', 'PD,Sports Trip,Offsite Event,Other', 4);end
CREATE TABLE `gibbonStaffCoverage` (
    `gibbonStaffCoverageID` INT(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonStaffAbsenceID` INT(14) UNSIGNED ZEROFILL NOT NULL,
    `status` ENUM('Requested','Accepted','Declined','Cancelled') DEFAULT 'Requested',
    `requestType` ENUM('Individual','Broadcast','Assigned') DEFAULT 'Broadcast',
    `gibbonPersonIDRequested` int(10) UNSIGNED ZEROFILL NOT NULL,
    `timestampRequested` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notesRequested` TEXT NULL,
    `gibbonPersonIDCoverage` int(10) UNSIGNED ZEROFILL NULL,
    `timestampCoverage` timestamp NULL,
    `notesCoverage` TEXT NULL,
    `notificationSent` ENUM('N','Y') DEFAULT 'N',
    `notificationList` TEXT NULL,
    PRIMARY KEY (`gibbonStaffCoverageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
CREATE TABLE `gibbonSubstitute` (
    `gibbonSubstituteID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `active` ENUM('Y','N') DEFAULT 'Y',
    `type` VARCHAR(30) NULL,
    `details` VARCHAR(255) NULL,
    `priority` INT(2) NOT NULL DEFAULT '0',
    `contactCall` ENUM('Y','N') DEFAULT 'Y',
    `contactSMS` ENUM('Y','N') DEFAULT 'Y',
    `contactEmail` ENUM('Y','N') DEFAULT 'Y',
    UNIQUE KEY `gibbonPersonID` (`gibbonPersonID`),
    PRIMARY KEY (`gibbonSubstituteID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
CREATE TABLE `gibbonSubstituteUnavailable` (
    `gibbonSubstituteUnavailableID` INT(14) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` INT(14) UNSIGNED ZEROFILL NOT NULL,
    `date` DATE NULL,
    `allDay` ENUM('N','Y') DEFAULT 'Y',
    `timeStart` time NULL DEFAULT NULL,
    `timeEnd` time NULL DEFAULT NULL,
    UNIQUE KEY `personAndDate` (`gibbonPersonID`, `date`),
    PRIMARY KEY (`gibbonSubstituteUnavailableID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'New Absence_mine', 0, 'Absences', 'Allows a user to submit their own staff absences.', 'absences_manage_add.php', 'absences_manage_add.php', 'Y', 'Y', 'N', 'N', 'Y', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'New Absence_any', 2, 'Absences', 'Submit staff absences for any user.', 'absences_manage_add.php', 'absences_manage_add.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='New Absence_any'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'View Absences_mine', 0, 'Absences', 'Provides an overview of staff absences for the selected user.', 'absences_view_byPerson.php,absences_view_details.php', 'absences_view_byPerson.php', 'Y', 'Y', 'N', 'N', 'Y', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'View Absences_any', 2, 'Absences', 'Provides an overview of staff absences for the selected user.', 'absences_view_byPerson.php,absences_view_details.php', 'absences_view_byPerson.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='View Absences_any'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'Staff Absence Summary', 0, 'Reports', 'Provides an overview of staff absences for the school year.', 'report_absences_summary.php', 'report_absences_summary.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='Staff Absence Summary'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'Manage Staff Absences', 0, 'Absences', 'Allows administrators to edit and delete staff absences.', 'absences_manage.php,absences_manage_edit.php,absences_manage_delete.php', 'absences_manage.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='Manage Staff Absences'));end
INSERT INTO `gibbonNotificationEvent` (`event`, `moduleName`, `actionName`, `type`, `scopes`, `active`) VALUES ('New Staff Absence', 'Staff', 'View Absences_any', 'Core', 'All', 'Y');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'Weekly Absences', 0, 'Absences', 'A week-by-week overview of staff absences.', 'report_absences_weekly.php', 'report_absences_weekly.php', 'Y', 'Y', 'N', 'N', 'N', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='Weekly Absences'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'My Coverage', 0, 'Coverage', 'Provides an overview of coverage for staff absences.', 'coverage_my.php,coverage_view_details.php,coverage_availability.php,coverage_view_cancel.php', 'coverage_my.php', 'Y', 'Y', 'N', 'N', 'Y', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='My Coverage'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `menuShow`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'Request Coverage', 0, 'Coverage', 'Allows a staff member to request coverage for their absences.', 'coverage_request.php', 'coverage_request.php', 'N', 'Y', 'Y', 'N', 'N', 'Y', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='Request Coverage'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'Open Requests', 0, 'Coverage', 'Users can view and accept any available coverage requests.', 'coverage_view.php,coverage_view_accept.php,coverage_view_decline.php', 'coverage_view.php', 'Y', 'N', 'N', 'N', 'Y', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='Open Requests'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'Manage Staff Coverage', 0, 'Coverage', 'Allows administrators to manage coverage requests.', 'coverage_manage.php,coverage_manage_edit.php,coverage_manage_delete.php,coverage_view_details.php', 'coverage_manage.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='Manage Staff Coverage'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'Manage Substitutes', 0, 'Staff Management', 'Edit information for users who can provide staff coverage.', 'subs_manage.php,subs_manage_add.php,subs_manage_edit.php,subs_manage_delete.php,coverage_availability.php', 'subs_manage.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='Manage Substitutes'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'Substitute Availability', 0, 'Coverage', 'Allows users to view the availability of subs by date.', 'report_subs_availability.php', 'report_subs_availability.php', 'Y', 'Y', 'N', 'N', 'N', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='Substitute Availability'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Staff'), 'Approve Staff Absences', 0, 'Absences', 'Allows users to approve or decline staff absences.', 'absences_approval.php,absences_approval_action.php', 'absences_approval.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'Y');end
INSERT INTO `gibbonPermission` (`gibbonRoleID` ,`gibbonActionID`) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Staff' AND gibbonAction.name='Approve Staff Absences'));end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Staff', 'substituteTypes', 'Substitute Types', 'A comma-separated list.', 'Internal Substitute,External Substitute');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Staff', 'urgencyThreshold', 'Urgency Threshold', 'Notifications in this time-span are sent immediately, day or night.', '3');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Staff', 'urgentNotifications', 'Urgent Notifications', 'Which contact methods should be used to notify users.', 'Email');end
INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Staff', 'absenceApprovers', 'Absence Approvers', 'Users who can approve staff absences.', '');end
";
