<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker, Sandra Kuipers and the Gibbon community
(https://gibbonedu.org/about/)

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
*/

namespace Gibbon\Auth\Gate;

use Gibbon\Auth\Access\Access;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Domain\User\UserGateway;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Gibbon\Auth\Gate\ApplicationFormAssociationGate
 */
class ApplicationFormAssociationGateTest extends TestCase
{
    /**
     * Feature: student-application-form-association-authorization,
     * Property 1: Caller context determines family acceptance
     *
     * The 130 deterministic cases cover each supported caller context against
     * canonical, malformed, and nonexistent family candidates. Client-only
     * markers are varied for every case and must not change the decision.
     */
    public function testCallerContextDeterminesFamilyAcceptance(): void
    {
        $familyCandidates = [
            '10',
            '0010',
            ' 10 ',
            '20',
            '0020',
            '999',
            '',
            '   ',
            0,
            -1,
            'not-a-family',
            ['10'],
            new \stdClass(),
        ];

        $contexts = [
            [
                'name' => 'public',
                'authenticated' => false,
                'management' => false,
                'sessionPersonID' => null,
                'linkedFamilyID' => null,
            ],
            [
                'name' => 'management-capable',
                'authenticated' => true,
                'management' => true,
                'sessionPersonID' => '900',
                'linkedFamilyID' => null,
            ],
            [
                'name' => 'management-capable-with-malformed-session-person',
                'authenticated' => true,
                'management' => true,
                'sessionPersonID' => ['101'],
                'linkedFamilyID' => null,
            ],
            [
                'name' => 'linked-parent-family-10',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => '101',
                'linkedFamilyID' => '10',
            ],
            [
                'name' => 'linked-parent-family-20',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => 202,
                'linkedFamilyID' => '20',
            ],
            [
                'name' => 'unrelated-parent',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => '999',
                'linkedFamilyID' => null,
            ],
            [
                'name' => 'malformed-session-array',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => ['101'],
                'linkedFamilyID' => '10',
            ],
            [
                'name' => 'malformed-session-object',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => new \stdClass(),
                'linkedFamilyID' => '10',
            ],
            [
                'name' => 'other-authenticated-caller',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => '900',
                'linkedFamilyID' => null,
            ],
            [
                'name' => 'authenticated-with-empty-session-person',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => '',
                'linkedFamilyID' => '10',
            ],
        ];

        $clientMarkers = [
            [
                'applicationType' => 'family',
                'gibbonFamily' => 'TRUE',
                'origin' => 'management',
            ],
            [
                'applicationType' => 'blank',
                'gibbonFamily' => 'FALSE',
                'origin' => 'public',
            ],
            [
                'applicationType' => 'family',
                'gibbonFamily' => 'FALSE',
                'origin' => 'forged-management',
            ],
        ];

        $existingFamilies = [
            '10' => ['gibbonFamilyID' => '10'],
            '20' => ['gibbonFamilyID' => '20'],
        ];
        $adultMembers = [
            '10' => [['gibbonPersonID' => '101']],
            '20' => [['gibbonPersonID' => '202']],
        ];

        $originalPost = $_POST;

        try {
            $caseNumber = 0;
            foreach ($contexts as $context) {
                foreach ($familyCandidates as $submittedFamilyID) {
                    $caseNumber++;
                    $canonicalFamilyID = $this->normalizeIdentifier($submittedFamilyID);
                    $familyExists = $canonicalFamilyID !== null
                        && isset($existingFamilies[$canonicalFamilyID]);
                    $sessionPersonID = $this->normalizeIdentifier($context['sessionPersonID']);
                    $adultIDs = [];
                    foreach ($adultMembers[$canonicalFamilyID] ?? [] as $adult) {
                        $adultID = $this->normalizeIdentifier($adult['gibbonPersonID'] ?? null);
                        if ($adultID !== null) {
                            $adultIDs[$adultID] = true;
                        }
                    }
                    $contextPermitsFamily = $context['management']
                        || ($sessionPersonID !== null
                            && isset($adultIDs[$sessionPersonID]));
                    $expectedFamilyID = $familyExists && $context['authenticated'] && $contextPermitsFamily
                        ? $canonicalFamilyID
                        : null;

                    $baseline = null;
                    foreach ($clientMarkers as $markers) {
                        $_POST = array_merge($markers, [
                            'gibbonFamilyID' => $submittedFamilyID,
                        ]);

                        $gate = $this->createGate(
                            $context,
                            $existingFamilies,
                            $adultMembers
                        );
                        $result = $gate->getValidatedAssociations($submittedFamilyID, null, [], []);
                        $actualFamilyID = $result['familyID'];

                        $this->assertSame(
                            $expectedFamilyID,
                            $actualFamilyID,
                            sprintf(
                                'Case %d (%s, family %s) returned an unexpected family.',
                                $caseNumber,
                                $context['name'],
                                var_export($submittedFamilyID, true)
                            )
                        );
                        $this->assertSame(
                            $expectedFamilyID !== null,
                            $result['usesExistingFamily'],
                            sprintf(
                                'Case %d (%s, family %s) returned an unexpected family mode.',
                                $caseNumber,
                                $context['name'],
                                var_export($submittedFamilyID, true)
                            )
                        );

                        if ($baseline === null) {
                            $baseline = [$actualFamilyID, $result['usesExistingFamily']];
                        } else {
                            $this->assertSame(
                                $baseline,
                                [$actualFamilyID, $result['usesExistingFamily']],
                                sprintf(
                                    'Case %d changed when client markers changed.',
                                    $caseNumber
                                )
                            );
                        }
                    }
                }
            }

            $this->assertSame(130, $caseNumber);
        } finally {
            $_POST = $originalPost;
        }
    }

    /**
     * Feature: student-application-form-association-authorization,
     * Property 2: Association identifiers are existence-checked before emission
     *
     * The 120 deterministic cases exercise valid, nonexistent, empty, whitespace,
     * zero, negative, nonnumeric, array, object, and otherwise malformed person
     * identifiers in both parent and relationship positions. The accepted family
     * deliberately contains numeric IDs that UserGateway rejects so membership
     * cannot substitute for existence.
     */
    public function testAssociationIdentifiersAreExistenceCheckedBeforeEmission(): void
    {
        $context = [
            'authenticated' => true,
            'management' => true,
            'sessionPersonID' => '900',
            'linkedFamilyID' => null,
        ];
        $existingFamilies = [
            '10' => ['gibbonFamilyID' => '10'],
        ];
        $adultMembers = [
            '10' => [
                ['gibbonPersonID' => '101'],
                ['gibbonPersonID' => '999'],
            ],
        ];
        $childMembers = [
            '10' => [
                ['gibbonPersonID' => '102'],
                ['gibbonPersonID' => '998'],
            ],
        ];
        $existingPersons = [
            '101' => ['gibbonPersonID' => '101'],
            '102' => ['gibbonPersonID' => '102'],
        ];
        $personCandidates = [
            '101',
            101,
            ' 101 ',
            '00101',
            '102',
            102,
            ' 102 ',
            '00102',
            '999',
            999,
            ' 999 ',
            '000999',
            '998',
            998,
            ' 998 ',
            '000998',
            '',
            '   ',
            "\t",
            '0',
            0,
            '-1',
            -1,
            'not-a-person',
            '10.1',
            ['101'],
            ['999'],
            new \stdClass(),
        ];

        $originalPost = $_POST;

        try {
            for ($caseNumber = 0; $caseNumber < 120; $caseNumber++) {
                $parentInput = $personCandidates[$caseNumber % count($personCandidates)];
                $relationshipPersonIDs = [];
                $relationships = [];

                foreach ($personCandidates as $index => $personInput) {
                    $relationshipPersonIDs[$index] = $personInput;
                    $relationships[$index] = 'Mother';
                }

                // These values are intentionally unrelated client inputs. The
                // validator must use only the server-derived management context.
                $_POST = [
                    'applicationType' => $caseNumber % 2 === 0 ? 'family' : 'blank',
                    'gibbonFamily' => $caseNumber % 3 === 0 ? 'TRUE' : 'FALSE',
                    'origin' => $caseNumber % 2 === 0 ? 'management' : 'forged-public',
                ];

                $gate = $this->createGate(
                    $context,
                    $existingFamilies,
                    $adultMembers,
                    $existingPersons,
                    $childMembers
                );
                $result = $gate->getValidatedAssociations(
                    '10',
                    $parentInput,
                    $relationshipPersonIDs,
                    $relationships
                );

                $expectedParentID = $this->normalizeIdentifier($parentInput) === '101'
                    ? '101'
                    : null;
                $expectedRelationshipIDs = [];
                foreach ($personCandidates as $personInput) {
                    $normalizedPersonID = $this->normalizeIdentifier($personInput);
                    if (isset($existingPersons[$normalizedPersonID])) {
                        $expectedRelationshipIDs[] = $normalizedPersonID;
                    }
                }

                $actualRelationshipIDs = array_map(
                    static function (array $relationship): string {
                        return $relationship['gibbonPersonID'];
                    },
                    $result['relationships']
                );

                $this->assertSame(
                    $expectedParentID,
                    $result['parentPersonID'],
                    sprintf('Case %d emitted an invalid parent identifier.', $caseNumber + 1)
                );
                $this->assertSame(
                    $expectedRelationshipIDs,
                    $actualRelationshipIDs,
                    sprintf('Case %d emitted an invalid relationship identifier.', $caseNumber + 1)
                );
                $this->assertSame(
                    ['101', '102'],
                    array_values(array_unique($actualRelationshipIDs)),
                    sprintf('Case %d emitted an identifier absent from UserGateway.', $caseNumber + 1)
                );

                foreach ($result['relationships'] as $relationship) {
                    $this->assertArrayHasKey(
                        $relationship['gibbonPersonID'],
                        $existingPersons,
                        sprintf('Case %d copied a nonexistent relationship identifier.', $caseNumber + 1)
                    );
                    $this->assertSame('Mother', $relationship['relationship']);
                }

                if ($expectedParentID === null) {
                    $this->assertNull($result['parentPersonID']);
                } else {
                    $this->assertArrayHasKey($result['parentPersonID'], $existingPersons);
                }
            }
        } finally {
            $_POST = $originalPost;
        }
    }

    /**
     * Feature: student-application-form-association-authorization,
     * Property 3: Accepted parent associations belong to the accepted family
     *
     * The deterministic matrix covers 3,300 family/parent/session combinations,
     * including adult members, existing non-members, nonexistent adult links,
     * malformed submitted identifiers, malformed session identifiers, and
     * contexts where no family can be accepted.
     */
    public function testAcceptedParentAssociationsBelongToAcceptedFamily(): void
    {
        $contexts = [
            [
                'name' => 'management-capable',
                'authenticated' => true,
                'management' => true,
                'sessionPersonID' => '900',
            ],
            [
                'name' => 'linked-adult-family-10',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => '101',
            ],
            [
                'name' => 'linked-adult-family-20',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => 202,
            ],
            [
                'name' => 'unrelated-authenticated-caller',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => '900',
            ],
            [
                'name' => 'malformed-session-array',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => ['101'],
            ],
            [
                'name' => 'malformed-session-object',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => new \stdClass(),
            ],
            [
                'name' => 'empty-session-person',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => '',
            ],
            [
                'name' => 'zero-session-person',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => 0,
            ],
            [
                'name' => 'negative-session-person',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => -1,
            ],
            [
                'name' => 'nonnumeric-session-person',
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => 'not-a-person',
            ],
            [
                'name' => 'public-caller',
                'authenticated' => false,
                'management' => false,
                'sessionPersonID' => null,
            ],
        ];

        $familyCandidates = [
            '10',
            '0010',
            ' 10 ',
            '20',
            '0020',
            ' 20 ',
            '999',
            '',
            '   ',
            0,
            -1,
            '-1',
            'not-a-family',
            ['10'],
            new \stdClass(),
        ];
        $parentCandidates = [
            '101',
            101,
            ' 101 ',
            '00101',
            '202',
            '00202',
            '102',
            '900',
            '998',
            '999',
            '299',
            '404',
            '',
            '   ',
            0,
            -1,
            '-1',
            'not-a-person',
            ['101'],
            new \stdClass(),
        ];
        $existingFamilies = [
            '10' => ['gibbonFamilyID' => '10'],
            '20' => ['gibbonFamilyID' => '20'],
        ];
        $adultMembers = [
            '10' => [
                ['gibbonPersonID' => '101'],
                ['gibbonPersonID' => '999'],
            ],
            '20' => [
                ['gibbonPersonID' => '202'],
                ['gibbonPersonID' => '299'],
            ],
        ];
        $existingPersons = [
            '101' => ['gibbonPersonID' => '101'],
            '102' => ['gibbonPersonID' => '102'],
            '202' => ['gibbonPersonID' => '202'],
            '900' => ['gibbonPersonID' => '900'],
            '998' => ['gibbonPersonID' => '998'],
        ];

        $caseNumber = 0;
        foreach ($contexts as $context) {
            foreach ($familyCandidates as $submittedFamilyID) {
                foreach ($parentCandidates as $submittedParentPersonID) {
                    $caseNumber++;
                    $canonicalFamilyID = $this->normalizeIdentifier($submittedFamilyID);
                    $familyExists = $canonicalFamilyID !== null
                        && isset($existingFamilies[$canonicalFamilyID]);
                    $sessionPersonID = $this->normalizeIdentifier($context['sessionPersonID']);
                    $adultIDs = [];
                    foreach ($adultMembers[$canonicalFamilyID] ?? [] as $adult) {
                        $adultID = $this->normalizeIdentifier($adult['gibbonPersonID']);
                        if ($adultID !== null) {
                            $adultIDs[$adultID] = true;
                        }
                    }

                    $familyAccepted = $familyExists
                        && $context['authenticated']
                        && ($context['management']
                            || ($sessionPersonID !== null && isset($adultIDs[$sessionPersonID])));
                    $parentID = $this->normalizeIdentifier($submittedParentPersonID);
                    $parentAccepted = $familyAccepted
                        && $parentID !== null
                        && isset($existingPersons[$parentID])
                        && isset($adultIDs[$parentID]);
                    $expectedParentID = $parentAccepted ? $parentID : null;

                    $gate = $this->createGate(
                        $context,
                        $existingFamilies,
                        $adultMembers,
                        $existingPersons
                    );
                    $result = $gate->getValidatedAssociations(
                        $submittedFamilyID,
                        $submittedParentPersonID,
                        [],
                        []
                    );

                    $this->assertSame(
                        $familyAccepted ? $canonicalFamilyID : null,
                        $result['familyID'],
                        sprintf(
                            'Case %d (%s, family %s) returned an unexpected family.',
                            $caseNumber,
                            $context['name'],
                            var_export($submittedFamilyID, true)
                        )
                    );
                    $this->assertSame(
                        $expectedParentID,
                        $result['parentPersonID'],
                        sprintf(
                            'Case %d (%s, family %s, parent %s) returned an unexpected parent.',
                            $caseNumber,
                            $context['name'],
                            var_export($submittedFamilyID, true),
                            var_export($submittedParentPersonID, true)
                        )
                    );
                    $this->assertSame($familyAccepted, $result['usesExistingFamily']);

                    if (!$context['management'] && $sessionPersonID === null) {
                        $this->assertNull(
                            $result['familyID'],
                            sprintf(
                                'Case %d allowed a malformed session identifier to authorize a family.',
                                $caseNumber
                            )
                        );
                    }
                }
            }
        }

        $this->assertSame(3300, $caseNumber);
    }

    /**
     * Feature: student-application-form-association-authorization,
     * Property 4: Relationship associations use the accepted family membership union
     *
     * The 120 deterministic cases exercise adult and child members from the
     * accepted family alongside cross-family, nonexistent, malformed, invalid,
     * missing-index, and mismatched entries. Valid entries after invalid pairs
     * must remain in the result.
     */
    public function testRelationshipAssociationsUseAcceptedFamilyMembershipUnion(): void
    {
        $existingFamilies = [
            '10' => ['gibbonFamilyID' => '10'],
            '20' => ['gibbonFamilyID' => '20'],
        ];
        $adultMembers = [
            '10' => [
                ['gibbonPersonID' => '101'],
                ['gibbonPersonID' => '102'],
                ['gibbonPersonID' => '109'],
            ],
            '20' => [
                ['gibbonPersonID' => '301'],
            ],
        ];
        $childMembers = [
            '10' => [
                ['gibbonPersonID' => '201'],
                ['gibbonPersonID' => '202'],
                ['gibbonPersonID' => '209'],
            ],
            '20' => [
                ['gibbonPersonID' => '401'],
            ],
        ];
        $existingPersons = [
            '101' => ['gibbonPersonID' => '101'],
            '102' => ['gibbonPersonID' => '102'],
            '201' => ['gibbonPersonID' => '201'],
            '202' => ['gibbonPersonID' => '202'],
            '301' => ['gibbonPersonID' => '301'],
            '401' => ['gibbonPersonID' => '401'],
        ];
        $acceptedFamilyMemberIDs = ['101', '102', '109', '201', '202', '209'];
        $allowedRelationships = [
            'Mother',
            'Father',
            'Step-Mother',
            'Step-Father',
            'Adoptive Parent',
            'Guardian',
            'Grandmother',
            'Grandfather',
            'Aunt',
            'Uncle',
            'Nanny/Helper',
            'Other',
        ];
        $context = [
            'authenticated' => true,
            'management' => true,
            'sessionPersonID' => '900',
            'linkedFamilyID' => null,
        ];

        $caseNumber = 0;
        for ($caseNumber = 0; $caseNumber < 120; $caseNumber++) {
            $variant = $caseNumber % 12;
            $relationshipPersonIDs = [];
            $relationships = [];

            if ($variant === 0) {
                $relationshipPersonIDs = [
                    0 => ' 101 ',
                    1 => '00201',
                    2 => '301',
                    3 => '401',
                    4 => '999',
                    5 => ['101'],
                    6 => '102',
                    7 => '201',
                    8 => '202',
                ];
                $relationships = [
                    0 => 'Mother',
                    1 => 'Grandfather',
                    2 => 'Father',
                    3 => 'Guardian',
                    4 => 'Mother',
                    5 => 'Father',
                    6 => 'not-allowed',
                    7 => ['Mother'],
                    8 => 'Other',
                ];
            } elseif ($variant === 1) {
                $relationshipPersonIDs = [
                    0 => '101',
                    1 => '201',
                    2 => '102',
                    3 => '202',
                ];
                $relationships = [
                    0 => 'Father',
                    2 => 'Aunt',
                    3 => 'Uncle',
                ];
            } elseif ($variant === 2) {
                $relationshipPersonIDs = [
                    0 => '301',
                    1 => '401',
                    2 => '999',
                    3 => '202',
                ];
                $relationships = [
                    0 => 'Mother',
                    1 => 'Father',
                    2 => 'Guardian',
                    4 => 'Other',
                    5 => 'Grandmother',
                ];
            } elseif ($variant === 3) {
                $relationshipPersonIDs = [
                    0 => '101',
                    2 => '201',
                    4 => '102',
                    5 => '202',
                ];
                $relationships = [
                    0 => 'Step-Mother',
                    1 => 'Father',
                    2 => 'Step-Father',
                    3 => 'Guardian',
                    4 => 'Adoptive Parent',
                ];
            } elseif ($variant === 4) {
                $relationshipPersonIDs = [
                    0 => '',
                    1 => '   ',
                    2 => 0,
                    3 => -1,
                    4 => 'not-a-person',
                    5 => ['201'],
                    6 => new \stdClass(),
                    7 => ' 202 ',
                ];
                $relationships = array_fill(0, 8, 'Other');
            } elseif ($variant === 5) {
                $relationshipPersonIDs = [
                    0 => '101',
                    1 => '201',
                    2 => '102',
                    3 => '202',
                    4 => '109',
                    5 => '209',
                ];
                $relationships = [
                    0 => 'Mother',
                    1 => 'Father',
                    2 => 123,
                    3 => null,
                    4 => 'Guardian',
                    5 => 'Other',
                ];
            } elseif ($variant === 6) {
                $relationshipPersonIDs = [
                    0 => '101',
                    1 => '201',
                    2 => '102',
                    3 => '202',
                    4 => '301',
                    5 => '401',
                    6 => '202',
                ];
                $relationships = [
                    0 => 'Unknown',
                    1 => 'Grandmother',
                    2 => 'Unknown',
                    3 => 'Grandfather',
                    4 => 'Aunt',
                    5 => 'Uncle',
                    6 => 'Nanny/Helper',
                ];
            } elseif ($variant === 7) {
                $relationshipPersonIDs = [
                    0 => '000101',
                    1 => 201,
                    2 => '000102',
                    3 => '000202',
                    4 => '000301',
                    5 => '000401',
                    6 => '000109',
                    7 => '000209',
                ];
                $relationships = [
                    0 => 'Step-Mother',
                    1 => 'Step-Father',
                    2 => 'Adoptive Parent',
                    3 => 'Guardian',
                    4 => 'Mother',
                    5 => 'Father',
                    6 => 'Aunt',
                    7 => 'Other',
                ];
            } elseif ($variant === 8) {
                $relationshipPersonIDs = [
                    0 => '101',
                    1 => '201',
                    2 => '102',
                    3 => '202',
                    4 => '101',
                    5 => '201',
                    6 => '102',
                    7 => '202',
                    8 => '101',
                    9 => '201',
                    10 => '102',
                    11 => '202',
                ];
                $relationships = array_combine(
                    range(0, 11),
                    $allowedRelationships
                );
            } elseif ($variant === 9) {
                $relationshipPersonIDs = [
                    0 => '999',
                    1 => ['101'],
                    2 => '301',
                    3 => '101',
                    4 => '401',
                    5 => '201',
                    6 => 'not-a-person',
                    7 => '202',
                ];
                $relationships = [
                    0 => 'Mother',
                    1 => 'Father',
                    2 => 'Guardian',
                    3 => 'Nanny/Helper',
                    4 => 'Other',
                    5 => 'Grandmother',
                    6 => 'Uncle',
                    7 => 'Grandfather',
                ];
            } elseif ($variant === 10) {
                $relationshipPersonIDs = [
                    0 => '101',
                    1 => '201',
                    2 => '102',
                    3 => '202',
                    4 => '109',
                    5 => '209',
                ];
                $relationships = [
                    0 => 'Mother',
                    1 => 'Father',
                    2 => 'Step-Mother',
                    3 => 'Step-Father',
                    4 => 'Guardian',
                    5 => 'Other',
                    6 => 'Grandmother',
                ];
            } else {
                $relationshipPersonIDs = [
                    0 => '301',
                    1 => '101',
                    2 => '401',
                    3 => '201',
                    4 => '999',
                    5 => '202',
                    6 => '102',
                ];
                $relationships = [
                    0 => 'Mother',
                    1 => 'Father',
                    2 => 'Guardian',
                    3 => 'Grandmother',
                    4 => 'Uncle',
                    5 => 'not-allowed',
                    6 => 'Other',
                ];
            }

            $expectedRelationships = [];
            foreach ($relationshipPersonIDs as $index => $submittedPersonID) {
                if (!array_key_exists($index, $relationships)) {
                    continue;
                }

                $personID = $this->normalizeIdentifier($submittedPersonID);
                $relationship = $relationships[$index];
                $relationshipIsValid = $personID !== null
                    && in_array($personID, $acceptedFamilyMemberIDs, true)
                    && isset($existingPersons[$personID])
                    && is_string($relationship)
                    && in_array($relationship, $allowedRelationships, true);
                if (!$relationshipIsValid) {
                    continue;
                }

                $expectedRelationships[] = [
                    'gibbonPersonID' => $personID,
                    'relationship' => $relationship,
                ];
            }

            $gate = $this->createGate(
                $context,
                $existingFamilies,
                $adultMembers,
                $existingPersons,
                $childMembers
            );
            $result = $gate->getValidatedAssociations(
                '10',
                null,
                $relationshipPersonIDs,
                $relationships
            );

            $this->assertTrue($result['usesExistingFamily']);
            $this->assertSame('10', $result['familyID']);
            $this->assertNull($result['parentPersonID']);
            $this->assertSame(
                $expectedRelationships,
                $result['relationships'],
                sprintf('Case %d emitted an unexpected relationship projection.', $caseNumber + 1)
            );

            foreach ($result['relationships'] as $relationship) {
                $this->assertContains($relationship['gibbonPersonID'], $acceptedFamilyMemberIDs);
                $this->assertArrayHasKey($relationship['gibbonPersonID'], $existingPersons);
            }
        }

        $this->assertSame(120, $caseNumber);
    }

    /**
     * Feature: student-application-form-association-authorization,
     * Property 5: Public and rejected-family contexts emit no person associations
     *
     * The 150 deterministic cases cover public callers and authenticated callers
     * without management capability or an adult link to the submitted family.
     * Family, parent, and relationship inputs include malformed scalar positions,
     * including arrays and objects, and must never produce associations.
     */
    public function testPublicAndRejectedFamilyContextsEmitNoPersonAssociations(): void
    {
        $contexts = [
            [
                'authenticated' => false,
                'management' => false,
                'sessionPersonID' => null,
            ],
            [
                'authenticated' => false,
                'management' => true,
                'sessionPersonID' => ['101'],
            ],
            [
                'authenticated' => false,
                'management' => false,
                'sessionPersonID' => new \stdClass(),
            ],
            [
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => '900',
            ],
            [
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => ['101'],
            ],
            [
                'authenticated' => true,
                'management' => false,
                'sessionPersonID' => new \stdClass(),
            ],
        ];
        $submittedInputs = [
            ['10', '101', ['101'], ['Mother']],
            ['0010', ['101'], [['101']], ['Mother']],
            [['10'], '101', ['101'], ['Mother']],
            [new \stdClass(), ['101'], ['101'], ['Mother']],
            ['20', ['202'], ['202'], ['Father']],
            ['999', '999', ['999'], ['Other']],
            ['', '', [], []],
            ['   ', '   ', [''], ['Mother']],
            [0, 0, [0], ['Mother']],
            [-1, -1, [-1], ['Mother']],
            ['not-a-family', 'not-a-person', ['not-a-person'], ['Mother']],
            ['10', '101', 'not-an-array', 'not-an-array'],
            ['10', ['101'], ['101'], 'Mother'],
            ['10', '101', ['101'], null],
            ['10', '101', ['101'], [0 => 'Mother', 2 => 'Father']],
            [
                '10',
                ['101'],
                [0 => ['101'], 1 => '202'],
                [0 => 'Mother', 1 => ['Father']],
            ],
            ['20', new \stdClass(), [0 => new \stdClass()], [0 => 'Father']],
            [
                '20',
                '202',
                [0 => '202', 1 => ['202']],
                [0 => ['Father'], 1 => 'Mother'],
            ],
            [['20'], ['202'], [['202']], [['Father']]],
            ['0010', '00101', ['000101'], ['Guardian']],
            ['10.1', '10.1', ['10.1'], ['Mother']],
            ['10', '0', ['0'], ['Unknown']],
            ['20', '-2', ['-2'], ['Other']],
            [
                '10',
                '101',
                [0 => '101', 2 => '202'],
                [0 => 'Mother', 1 => 'Father', 2 => ['Other']],
            ],
            ['10', '101', [0 => '101', 1 => '102'], [0 => 'Mother', 1 => 'Father']],
        ];
        $existingFamilies = [
            '10' => ['gibbonFamilyID' => '10'],
            '20' => ['gibbonFamilyID' => '20'],
        ];
        $adultMembers = [
            '10' => [['gibbonPersonID' => '101']],
            '20' => [['gibbonPersonID' => '202']],
        ];
        $existingPersons = [
            '101' => ['gibbonPersonID' => '101'],
            '202' => ['gibbonPersonID' => '202'],
        ];

        $caseNumber = 0;
        foreach ($contexts as $context) {
            foreach ($submittedInputs as $submittedInput) {
                $caseNumber++;
                [$familyID, $parentPersonID, $relationshipPersonIDs, $relationships] = $submittedInput;
                $gate = $this->createGate(
                    $context,
                    $existingFamilies,
                    $adultMembers,
                    $existingPersons
                );
                $result = $gate->getValidatedAssociations(
                    $familyID,
                    $parentPersonID,
                    $relationshipPersonIDs,
                    $relationships
                );

                $this->assertNull(
                    $result['familyID'],
                    sprintf('Case %d accepted a family association.', $caseNumber)
                );
                $this->assertNull(
                    $result['parentPersonID'],
                    sprintf('Case %d emitted a parent association.', $caseNumber)
                );
                $this->assertSame(
                    [],
                    $result['relationships'],
                    sprintf('Case %d emitted relationship associations.', $caseNumber)
                );
                $this->assertFalse(
                    $result['usesExistingFamily'],
                    sprintf('Case %d enabled existing-family mode.', $caseNumber)
                );
            }
        }

        $this->assertSame(150, $caseNumber);
    }

    /**
     * @dataProvider malformedAssociationInputProvider
     *
     * Feature: student-application-form-association-authorization,
     * Example coverage: malformed association input is omitted independently
     *
     * Each case uses deterministic gateway doubles and includes a valid
     * relationship after the malformed or rejected input where the array
     * shape permits independent evaluation.
     *
     * @param mixed $familyID
     * @param mixed $parentPersonID
     * @param mixed $relationshipPersonIDs
     * @param mixed $relationships
     * @param string|null $expectedFamilyID
     * @param string|null $expectedParentPersonID
     * @param array<int, array{gibbonPersonID: string, relationship: string}> $expectedRelationships
     */
    public function testMalformedAssociationInputIsOmittedWithoutNotices(
        $familyID,
        $parentPersonID,
        $relationshipPersonIDs,
        $relationships,
        ?string $expectedFamilyID,
        ?string $expectedParentPersonID,
        array $expectedRelationships
    ): void {
        $gate = $this->createGate(
            [
                'authenticated' => true,
                'management' => true,
                'sessionPersonID' => '900',
            ],
            [
                '10' => ['gibbonFamilyID' => '10'],
            ],
            [
                '10' => [
                    ['gibbonPersonID' => '101'],
                ],
            ],
            [
                '101' => ['gibbonPersonID' => '101'],
                '201' => ['gibbonPersonID' => '201'],
            ],
            [
                '10' => [
                    ['gibbonPersonID' => '201'],
                ],
            ]
        );

        $notices = [];
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line) use (&$notices): bool {
                $notices[] = compact('severity', 'message', 'file', 'line');
                return true;
            }
        );

        try {
            try {
                $result = $gate->getValidatedAssociations(
                    $familyID,
                    $parentPersonID,
                    $relationshipPersonIDs,
                    $relationships
                );
            } catch (\Throwable $exception) {
                $this->fail(sprintf(
                    'Malformed input caused an exception: %s',
                    $exception->getMessage()
                ));
            }
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $notices, 'Malformed input emitted a PHP notice or warning.');
        $this->assertSame($expectedFamilyID, $result['familyID']);
        $this->assertSame($expectedParentPersonID, $result['parentPersonID']);
        $this->assertSame($expectedRelationships, $result['relationships']);
        $this->assertSame($expectedFamilyID !== null, $result['usesExistingFamily']);

        $this->assertSame(
            ['error', 'usesExistingFamily', 'familyID', 'parentPersonID', 'relationships'],
            array_keys($result)
        );
        $this->assertNotSame('999', $result['familyID']);
        $this->assertNotSame('999', $result['parentPersonID']);
    }

    /**
     * @return array<string, array>
     */
    public function malformedAssociationInputProvider(): array
    {
        return [
            'family array in scalar position' => [
                ['10'], '101', [0 => '999', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                null, null, [],
            ],
            'family object in scalar position' => [
                new \stdClass(), '101', [0 => '999', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                null, null, [],
            ],
            'empty family value' => [
                '', '101', [0 => '999', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                null, null, [],
            ],
            'whitespace family value' => [
                '   ', '101', [0 => '999', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                null, null, [],
            ],
            'zero family value' => [
                0, '101', [0 => '999', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                null, null, [],
            ],
            'negative family value' => [
                -1, '101', [0 => '999', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                null, null, [],
            ],
            'nonnumeric family value' => [
                'not-a-family', '101', [0 => '999', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                null, null, [],
            ],
            'missing relationship person array' => [
                '10', '101', null, [0 => 'Mother', 1 => 'Father'],
                '10', '101', [],
            ],
            'missing relationship value array' => [
                '10', '101', [0 => '999', 1 => '201'], null,
                '10', '101', [],
            ],
            'non-array relationship person input' => [
                '10', '101', 'not-an-array', [0 => 'Mother', 1 => 'Father'],
                '10', '101', [],
            ],
            'non-array relationship value input' => [
                '10', '101', [0 => '999', 1 => '201'], 'not-an-array',
                '10', '101', [],
            ],
            'mismatched lengths omit missing value' => [
                '10', '101', [0 => '999', 1 => '201'], [0 => 'Unknown'],
                '10', '101', [],
            ],
            'mismatched indexes retain valid later entry' => [
                '10', '101', [0 => '999', 2 => '201'], [0 => 'Unknown', 2 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'non-scalar person entry retains valid later entry' => [
                '10', '101', [0 => ['101'], 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'object person entry retains valid later entry' => [
                '10', '101', [0 => new \stdClass(), 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'non-scalar relationship entry retains valid later entry' => [
                '10', '101', [0 => '101', 1 => '201'], [0 => ['Mother'], 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'object relationship entry retains valid later entry' => [
                '10', '101', [0 => '101', 1 => '201'], [0 => new \stdClass(), 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'empty person entry retains valid later entry' => [
                '10', '101', [0 => '', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'whitespace person entry retains valid later entry' => [
                '10', '101', [0 => '   ', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'zero person entry retains valid later entry' => [
                '10', '101', [0 => 0, 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'negative person entry retains valid later entry' => [
                '10', '101', [0 => -1, 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'nonnumeric person entry retains valid later entry' => [
                '10', '101', [0 => 'not-a-person', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'nonexistent family returns empty result' => [
                '999', '101', [0 => '999', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                null, null, [],
            ],
            'nonexistent parent and person retain valid later entry' => [
                '10', '999', [0 => '999', 1 => '201'], [0 => 'Mother', 1 => 'Father'],
                '10', null, [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'invalid relationship value retains valid later entry' => [
                '10', '101', [0 => '101', 1 => '201'], [0 => 'Unknown', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'empty relationship value retains valid later entry' => [
                '10', '101', [0 => '101', 1 => '201'], [0 => '', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'whitespace relationship value retains valid later entry' => [
                '10', '101', [0 => '101', 1 => '201'], [0 => '   ', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'numeric relationship value retains valid later entry' => [
                '10', '101', [0 => '101', 1 => '201'], [0 => 0, 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'negative relationship value retains valid later entry' => [
                '10', '101', [0 => '101', 1 => '201'], [0 => -1, 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
            'nonnumeric relationship value retains valid later entry' => [
                '10', '101', [0 => '101', 1 => '201'], [0 => 'not-a-relationship', 1 => 'Father'],
                '10', '101', [
                    ['gibbonPersonID' => '201', 'relationship' => 'Father'],
                ],
            ],
        ];
    }

    public function testInfrastructureFailuresReturnClearedErrorResult(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('has')->willReturn(true);
        $session->method('get')->willReturn('101');

        $access = $this->createMock(Access::class);
        $access->method('allows')->willReturn(true);

        $familyGateway = $this->createMock(FamilyGateway::class);
        $familyGateway->method('getByID')->willThrowException(
            new \RuntimeException('database details must not be exposed')
        );

        $userGateway = $this->createMock(UserGateway::class);
        $gate = new ApplicationFormAssociationGate(
            $session,
            $access,
            $familyGateway,
            $userGateway
        );

        $result = $gate->getValidatedAssociations(
            '10',
            '101',
            ['201'],
            ['Mother']
        );

        $this->assertSame([
            'error' => true,
            'usesExistingFamily' => false,
            'familyID' => null,
            'parentPersonID' => null,
            'relationships' => [],
        ], $result);
        $this->assertStringNotContainsString('database details', serialize($result));
    }

    public function testFetchAllMembershipResultsAreCanonicalizedAndValidated(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('has')->willReturn(true);
        $session->method('get')->willReturn('101');

        $access = $this->createMock(Access::class);
        $access->method('allows')->willReturn(false);

        $familyGateway = $this->createMock(FamilyGateway::class);
        $familyGateway->method('getByID')->willReturn(['gibbonFamilyID' => '10']);
        $familyGateway->method('selectAdultsByFamily')->willReturn(
            new class {
                public function fetchAll(): array
                {
                    return [['gibbonPersonID' => '00101']];
                }
            }
        );
        $familyGateway->method('selectChildrenByFamily')->willReturn(
            new class {
                public function fetchAll(): array
                {
                    return [['gibbonPersonID' => '00201']];
                }
            }
        );

        $userGateway = $this->createMock(UserGateway::class);
        $userGateway->method('getByID')->willReturnCallback(
            static function (string $personID): array {
                return in_array($personID, ['101', '201'], true)
                    ? ['gibbonPersonID' => $personID]
                    : [];
            }
        );

        $gate = new ApplicationFormAssociationGate(
            $session,
            $access,
            $familyGateway,
            $userGateway
        );
        $result = $gate->getValidatedAssociations(
            '0010',
            '00101',
            ['00201'],
            ['Mother']
        );

        $this->assertSame([
            'error' => false,
            'usesExistingFamily' => true,
            'familyID' => '10',
            'parentPersonID' => '101',
            'relationships' => [
                ['gibbonPersonID' => '201', 'relationship' => 'Mother'],
            ],
        ], $result);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, array<string, string>> $existingFamilies
     * @param array<string, array<int, array<string, string>>> $adultMembers
     * @param array<string, array<string, string>> $existingPersons
     * @param array<string, array<int, array<string, string>>> $childMembers
     */
    private function createGate(
        array $context,
        array $existingFamilies,
        array $adultMembers,
        array $existingPersons = [],
        array $childMembers = []
    ): ApplicationFormAssociationGate {
        $session = $this->createMock(Session::class);
        $session->method('has')->willReturn($context['authenticated']);
        $session->method('get')->willReturn($context['sessionPersonID']);

        $access = $this->createMock(Access::class);
        $access->method('allows')->willReturn($context['management']);

        $familyGateway = $this->createMock(FamilyGateway::class);
        $familyGateway->method('getByID')->willReturnCallback(
            static function (string $familyID) use ($existingFamilies): array {
                return $existingFamilies[$familyID] ?? [];
            }
        );
        $familyGateway->method('selectAdultsByFamily')->willReturnCallback(
            static function (string $familyID) use ($adultMembers): array {
                return $adultMembers[$familyID] ?? [];
            }
        );
        $familyGateway->method('selectChildrenByFamily')->willReturnCallback(
            static function (string $familyID) use ($childMembers): array {
                return $childMembers[$familyID] ?? [];
            }
        );

        $userGateway = $this->createMock(UserGateway::class);
        $userGateway->method('getByID')->willReturnCallback(
            static function (string $personID) use ($existingPersons): array {
                return $existingPersons[$personID] ?? [];
            }
        );

        return new ApplicationFormAssociationGate(
            $session,
            $access,
            $familyGateway,
            $userGateway
        );
    }

    /**
     * Mirror the validator's scalar positive identifier normalization for the
     * expected-value calculation in the property test.
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
}
