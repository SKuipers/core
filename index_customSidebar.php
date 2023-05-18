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

$output = '';

$roleCategory = getRoleCategory($_SESSION[$guid]['gibbonRoleIDCurrent'], $connection2);

if ($roleCategory == 'Staff') {
    $output .= '<h2>' . __('Staff Portal') . '</h2>';
    $output .= '<p>'.__('Please visit the portal each morning:').'<br/>';
    $output .= '<input type="button" 
                       class="fullWidth" value="' . __("Today's Announcements ⇒") . '" 
                       onClick="window.open(\''.$_SESSION[$guid]['webLink'].'/portal/\')" 
                       style="height:32px;margin-bottom:15px; background:#C4ECFF; cursor: pointer;">';
    $output .= '</p>';
} else if ($roleCategory == 'Parent' && isActionAccessible($guid, $connection2, '/modules/Health Code/hc_upload.php')) {
    // $i18n = $_SESSION[$guid]['i18n']['code'];
    // $header = $i18n == 'zh_CN' || $i18n == 'zh_HK' ? '上傳澳門健康碼（幼稚園至小六）' :  __('Upload Macau Health Code PK-Grade 6');
    // $body = $i18n == 'zh_CN' || $i18n == 'zh_HK' ? '請於孩子進入學校前上傳他們的澳門健康碼截圖：' : __('Please upload your child\'s Macau Health Code screenshots each morning before they enter the building:');
    // $button = $i18n == 'zh_CN' || $i18n == 'zh_HK' ? '上傳澳門健康碼' :  __('Upload Macau Health Code');

	// $output .= '<h2>' . $header . '</h2>';
	// $output .= '<p>'. $body .'<br/>';
    // $output .= '<a class="button" href="'.$_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Health Code/hc_upload.php" style="border: 1px solid #222222;background-color: #eeeeee;;color: #444444;font-weight: bold;font-size: 13px ;text-decoration:none;padding: 8px;display:block;text-align:center;">';
    // $output .= $button;
    // $output .= '</a>';
    // $output .= '</p>';
}

return $output;
