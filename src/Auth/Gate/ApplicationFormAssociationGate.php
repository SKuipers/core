<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
(https://gibbonedu.org/about/)
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

namespace Gibbon\Auth\Gate;

use Gibbon\Auth\Access\Access;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Forms\FormFactory;

/**
 * Authorizes and validates family associations submitted with a Student Application Form.
 */
final class ApplicationFormAssociationGate extends Gate
{
    protected Access $access;

    protected FamilyGateway $familyGateway;

    protected UserGateway $userGateway;

    protected $allowedRelationships = [];

    public function __construct(
        Session $session,
        Access $access,
        FormFactory $formFactory,
        FamilyGateway $familyGateway,
        UserGateway $userGateway
    ) {
        parent::__construct($session);

        $this->access = $access;
        $this->familyGateway = $familyGateway;
        $this->userGateway = $userGateway;

        $this->allowedRelationships = array_keys($formFactory->createSelectRelationship('values')->getOptions());
    }

    /**
     * Validate submitted family, parent, and relationship associations.
     *
     * Invalid input is omitted. Gateway and other infrastructure failures are
     * returned as a safe error result without exposing exception details.
     *
     * @param mixed $submittedFamilyID
     * @param mixed $submittedParentPersonID
     * @param mixed $submittedRelationshipPersonIDs
     * @param mixed $submittedRelationships
     * @return array{
     *     error: bool,
     *     usesExistingFamily: bool,
     *     familyID: string|null,
     *     parentPersonID: string|null,
     *     relationships: array<int, array{gibbonPersonID: string, relationship: string}>
     * }
     */
    public function getAllowedAssociations(
        $submittedFamilyID,
        $submittedParentPersonID,
        $submittedRelationshipPersonIDs,
        $submittedRelationships
    ): array {
        try {
            // Public requests cannot attach to an existing family or person.
            if (!$this->session->has('username')) {
                return $this->emptyResult();
            }

            // Normalize before any lookup so malformed input never reaches a gateway.
            $familyID = $this->normalizeIdentifier($submittedFamilyID);
            if ($familyID === null) {
                // A blank family is valid for an authenticated user's own parent record.
                return $this->blankFamilyResult(
                    $this->validateBlankFamilyParent($submittedParentPersonID)
                );
            }

            $family = $this->familyGateway->getByID($familyID);
            if (empty($family)) {
                return $this->emptyResult();
            }

            // Family IDs are zero-filled in the database. Preserve that form for
            // FIND_IN_SET membership queries while returning the canonical ID.
            $familyLookupID = $family['gibbonFamilyID'] ?? $familyID;
            if ($familyLookupID === ''
                || (!is_string($familyLookupID) && !is_int($familyLookupID))
                || (is_int($familyLookupID) && $familyLookupID <= 0)
            ) {
                $familyLookupID = $familyID;
            }

            $adults = $this->familyGateway->selectAdultsByFamily($familyLookupID, true);
            $adultIDs = $this->membershipSet($adults);
            $sessionPersonID = $this->normalizeIdentifier($this->session->get('gibbonPersonID'));
            $managementAuthorized = $this->access->allows('Students', 'applicationForm_manage');

            // Non-management callers must be an adult member of this family.
            if (!$managementAuthorized && ($sessionPersonID === null || !isset($adultIDs[$sessionPersonID]))) {
                return $this->emptyResult();
            }

            // Use the accepted family's adult and child sets for every association.
            $children = $this->familyGateway->selectChildrenByFamily($familyLookupID, true);
            $childIDs = $this->membershipSet($children);

            return [
                'error' => false,
                'usesExistingFamily' => true,
                'familyID' => $familyID,
                'parentPersonID' => $this->validateParentPerson($submittedParentPersonID, $adultIDs),
                'relationships' => $this->validateRelationships(
                    $submittedRelationshipPersonIDs,
                    $submittedRelationships,
                    $adultIDs,
                    $childIDs
                ),
            ];
        } catch (\Throwable) {
            // Fail closed on infrastructure errors; never return unverified IDs.
            return $this->errorResult();
        }
    }

    /**
     * Keep an authenticated user's own existing person association for a new family.
     *
     * @param mixed $submittedPersonID
     */
    private function validateBlankFamilyParent($submittedPersonID): ?string
    {
        $sessionPersonID = $this->normalizeIdentifier($this->session->get('gibbonPersonID'));
        $personID = $this->normalizeIdentifier($submittedPersonID);

        if ($sessionPersonID === null || $personID !== $sessionPersonID) {
            return null;
        }

        return empty($this->userGateway->getByID($personID)) ? null : $personID;
    }

    /**
     * Return the effective new-family state with only the caller's safe parent ID.
     *
     * @param string|null $parentPersonID
     * @return array{
     *     error: bool,
     *     usesExistingFamily: bool,
     *     familyID: null,
     *     parentPersonID: string|null,
     *     relationships: array{}
     * }
     */
    private function blankFamilyResult(?string $parentPersonID): array
    {
        return [
            'error' => false,
            'usesExistingFamily' => false,
            'familyID' => null,
            'parentPersonID' => $parentPersonID,
            'relationships' => [],
        ];
    }

    /**
     * Validate a parent against the accepted family's adult membership and person table.
     *
     * @param mixed $submittedPersonID
     * @param array<string, bool> $adultIDs
     */
    private function validateParentPerson($submittedPersonID, array $adultIDs): ?string
    {
        $personID = $this->normalizeIdentifier($submittedPersonID);
        if ($personID === null || !isset($adultIDs[$personID])) {
            return null;
        }

        return empty($this->userGateway->getByID($personID)) ? null : $personID;
    }

    /**
     * Filter relationship pairs independently against accepted membership and allowed values.
     *
     * @param mixed $submittedPersonIDs
     * @param mixed $submittedRelationships
     * @param array<string, bool> $adultIDs
     * @param array<string, bool> $childIDs
     * @return array<int, array{gibbonPersonID: string, relationship: string}>
     */
    private function validateRelationships(
        $submittedPersonIDs,
        $submittedRelationships,
        array $adultIDs,
        array $childIDs
    ): array {
        if (!is_array($submittedPersonIDs) || !is_array($submittedRelationships)) {
            return [];
        }

        $relationships = [];
        $memberIDs = $adultIDs + $childIDs;

        foreach ($submittedPersonIDs as $index => $submittedPersonID) {
            if (!array_key_exists($index, $submittedRelationships)) {
                continue;
            }

            $personID = $this->normalizeIdentifier($submittedPersonID);
            $relationship = $submittedRelationships[$index];

            if ($personID === null || !isset($memberIDs[$personID])) {
                continue;
            }

            if (!is_string($relationship) || !in_array($relationship, $this->allowedRelationships, true)) {
                continue;
            }

            if (!empty($this->userGateway->getByID($personID))) {
                $relationships[] = [
                    'gibbonPersonID' => $personID,
                    'relationship' => $relationship,
                ];
            }
        }

        return $relationships;
    }

    /**
     * Convert a positive scalar identifier to its canonical, unpadded form.
     *
     * @param mixed $value
     */
    private function normalizeIdentifier($value): ?string
    {
        if (is_int($value)) {
            return $value > 0 ? (string) $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        $value = ltrim($value, '0');
        return $value === '' ? null : $value;
    }

    /**
     * Build a canonical membership set from arrays or gateway result objects.
     *
     * @param mixed $memberships
     * @return array<string, bool>
     */
    private function membershipSet($memberships): array
    {
        if (is_object($memberships) && method_exists($memberships, 'fetchAll')) {
            $memberships = $memberships->fetchAll();
        }

        if (!is_array($memberships)) {
            return [];
        }

        $memberIDs = [];
        foreach ($memberships as $membership) {
            if (!is_array($membership) || !array_key_exists('gibbonPersonID', $membership)) {
                continue;
            }

            $personID = $this->normalizeIdentifier($membership['gibbonPersonID']);
            if ($personID !== null) {
                $memberIDs[$personID] = true;
            }
        }

        return $memberIDs;
    }

    /**
     * Return the normal non-error result when no existing association is accepted.
     *
     * @return array{
     *     error: bool,
     *     usesExistingFamily: bool,
     *     familyID: null,
     *     parentPersonID: null,
     *     relationships: array{}
     * }
     */
    private function emptyResult(): array
    {
        return [
            'error' => false,
            'usesExistingFamily' => false,
            'familyID' => null,
            'parentPersonID' => null,
            'relationships' => [],
        ];
    }

    /**
     * Return a cleared result when infrastructure prevented validation.
     *
     * @return array{
     *     error: bool,
     *     usesExistingFamily: bool,
     *     familyID: null,
     *     parentPersonID: null,
     *     relationships: array{}
     * }
     */
    private function errorResult(): array
    {
        return [
            'error' => true,
            'usesExistingFamily' => false,
            'familyID' => null,
            'parentPersonID' => null,
            'relationships' => [],
        ];
    }
}
