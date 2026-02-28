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

use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\System\HookGateway;
use Gibbon\Services\Format;

/**
 * HookPage
 * 
 * Handles third-party hook integrations for the staff profile system.
 * This class encapsulates the logic for rendering hook-based subpages,
 * including permission verification and error handling.
 * 
 * Hook-based subpages allow third-party modules to extend the staff profile
 * with custom functionality through the Gibbon hook system.
 * 
 * @package Gibbon\Module\Staff\Profile
 */
class HookPage extends ProfilePage
{
    private HookGateway $hookGateway;
    private ?array $hookData = null;

    public function __construct(
        Session $session,
        HookGateway $hookGateway
    ) {
        parent::__construct($session);
        $this->hookGateway = $hookGateway;
    }

    public function getPageName(): string
    {
        // Return the hook name if available, otherwise return 'Hook'
        if ($this->hookData !== null && isset($this->hookData['name'])) {
            return $this->hookData['name'];
        }
        return 'Hook';
    }

    public function checkAccess(): bool
    {
        // Guard: validate hook ID parameter
        $gibbonHookID = $_GET['gibbonHookID'] ?? '';
        if (empty($gibbonHookID)) {
            return false;
        }

        // Fetch hook data
        $this->hookData = $this->hookGateway->getByID($gibbonHookID);

        // Guard: check if hook exists
        if (empty($this->hookData)) {
            return false;
        }

        // Unserialize hook options
        $options = unserialize($this->hookData['options']);

        // Guard: validate options
        if (empty($options)) {
            return false;
        }

        // Check for permission to access this hook
        $hookPermission = $this->hookGateway->getHookPermission(
            $this->hookData['gibbonHookID'],
            $this->session->get('gibbonRoleIDCurrent'),
            $options['sourceModuleName'] ?? '',
            $options['sourceModuleAction'] ?? ''
        );

        // Return true if user has permission to access the hook
        return !empty($hookPermission);
    }

    public function getOutput(): string
    {
        // Guard: validate hook data was loaded during checkAccess()
        if ($this->hookData === null) {
            return Format::alert(__('The selected page cannot be displayed due to a hook error.'), 'error');
        }

        // Unserialize hook options
        $options = unserialize($this->hookData['options']);

        // Guard: validate options
        if (empty($options)) {
            return Format::alert(__('The selected page cannot be displayed due to a hook error.'), 'error');
        }

        // Build the path to the hook include file
        $include = $this->session->get('absolutePath') . '/modules/' . 
                   $options['sourceModuleName'] . '/' . 
                   $options['sourceModuleInclude'];

        // Guard: check if hook file exists
        if (!file_exists($include)) {
            return Format::alert(__('The selected page cannot be displayed due to a hook error.'), 'error');
        }

        // Capture the output of the included hook file
        ob_start();
        try {
            include $include;
            $output = ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            return Format::alert(__('An error occurred while rendering the hook content.'), 'error');
        }

        return $output;
    }
}
