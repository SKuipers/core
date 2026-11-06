<?php

namespace Gibbon\Tests\UnitTest\Students;

use Gibbon\Auth\Access\Access;
use Gibbon\Auth\Gate\ApplicationFormAssociationGate;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Domain\User\UserGateway;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Gibbon\Auth\Gate\ApplicationFormAssociationGate
 */
class ApplicationFormProcessTest extends TestCase
{
    private const PROCESSOR_PATH = __DIR__.'/../../../modules/Students/applicationFormProcess.php';

    public function testScalarFamilyNormalizationPrecedesDynamicRelationshipKeys(): void
    {
        $source = $this->processorSource();
        $normalization = strpos($source, 'if (is_scalar($submittedFamilyID)');
        $relationshipKey = strpos(
            $source,
            '$_POST['.'$canonicalFamilyID.' . "'-relationships']"
        );
        $relationshipPersonKey = strpos(
            $source,
            '$_POST['.'$canonicalFamilyID.' . "'-relationshipsGibbonPersonID']"
        );

        $this->assertNotFalse($normalization);
        $this->assertNotFalse($relationshipKey);
        $this->assertNotFalse($relationshipPersonKey);
        $this->assertLessThan($relationshipKey, $normalization);
        $this->assertLessThan($relationshipPersonKey, $normalization);
    }

    /**
     * Feature: student-application-form-association-authorization,
     * Property 6: Persistence is a projection of validated associations
     *
     * The processor's relationship persistence path consumes only the gate's
     * validated relationship collection and projects its plain-array fields.
     */
    public function testPersistenceUsesOnlyValidatedRelationshipProjection(): void
    {
        $source = $this->processorSource();
        $relationshipSectionStart = strpos($source, '//Deal with family relationships');
        $relationshipSectionEnd = strpos($source, '//Deal with required documents', $relationshipSectionStart);

        $this->assertNotFalse($relationshipSectionStart);
        $this->assertNotFalse($relationshipSectionEnd);

        $relationshipSection = substr(
            $source,
            $relationshipSectionStart,
            $relationshipSectionEnd - $relationshipSectionStart
        );

        $this->assertStringContainsString(
            'foreach ($validatedRelationships as $relationship)',
            $relationshipSection
        );
        $this->assertStringContainsString("'gibbonPersonID' => \$relationship['gibbonPersonID']", $relationshipSection);
        $this->assertStringContainsString("'relationship' => \$relationship['relationship']", $relationshipSection);
        $this->assertStringNotContainsString("\$_POST['gibbonFamily']", $relationshipSection);
        $this->assertStringNotContainsString('-relationships', $relationshipSection);
    }

    /**
     * Feature: student-application-form-association-authorization,
     * Property 7: Server capability is independent of client flow markers
     *
     * The processor passes canonical association candidates to the gate and
     * never lets application-type or submitted-family mode markers authorize
     * an association.
     */
    public function testClientMarkersCannotAlterGateDecisions(): void
    {
        $source = $this->processorSource();

        $this->assertStringContainsString(
            'use Gibbon\\Auth\\Gate\\ApplicationFormAssociationGate;',
            $source
        );
        $this->assertStringNotContainsString("\$_POST['applicationType']", $source);
        $this->assertStringNotContainsString("\$_POST['gibbonFamily']", $source);
        $this->assertStringContainsString(
            "getValidatedAssociations(\n                \$canonicalFamilyID,",
            $source
        );

        $gate = $this->createDeterministicGate();
        $results = [];
        foreach (['family', 'blank', 'forged-management', 'forged-public'] as $marker) {
            $results[$marker] = $gate->getValidatedAssociations(
                '0010',
                '00101',
                ['00201'],
                ['Mother']
            );
        }

        $this->assertSame($results['family'], $results['blank']);
        $this->assertSame($results['family'], $results['forged-management']);
        $this->assertSame($results['family'], $results['forged-public']);
        $this->assertSame(
            [
                'error' => false,
                'usesExistingFamily' => true,
                'familyID' => '10',
                'parentPersonID' => '101',
                'relationships' => [
                    ['gibbonPersonID' => '201', 'relationship' => 'Mother'],
                ],
            ],
            $results['family']
        );
    }

    /**
     * Feature: student-application-form-association-authorization,
     * Property 8: Effective mode controls every downstream association branch
     *
     * Effective mode and association values are established from the plain
     * gate result before required-field validation and all later branches.
     */
    public function testEffectiveAssociationModeIsAssignedBeforeDownstreamBranches(): void
    {
        $source = $this->processorSource();
        $validateInputs = strpos($source, '//VALIDATE INPUTS');
        $effectiveMode = strpos($source, '$gibbonFamily = $associations[\'usesExistingFamily\']');

        $this->assertNotFalse($validateInputs);
        $this->assertNotFalse($effectiveMode);
        $this->assertLessThan($validateInputs, $effectiveMode);

        foreach ([
            '$gibbonFamilyID = $associations[\'familyID\'];',
            '$parent1gibbonPersonID = $associations[\'parentPersonID\'];',
            '$validatedRelationships = $associations[\'relationships\'];',
        ] as $assignment) {
            $assignmentPosition = strpos($source, $assignment);
            $this->assertNotFalse($assignmentPosition, $assignment);
            $this->assertLessThan($validateInputs, $assignmentPosition, $assignment);
        }

        foreach ([
            "if (\$gibbonFamily == 'TRUE')",
            "if (\$gibbonFamily == 'FALSE')",
            "'gibbonFamilyID' => \$gibbonFamilyID",
            "'parent1gibbonPersonID' => \$parent1gibbonPersonID",
            'foreach ($validatedRelationships as $relationship)',
        ] as $downstreamUse) {
            $usePosition = strpos($source, $downstreamUse, $validateInputs);
            $this->assertNotFalse($usePosition, $downstreamUse);
        }
    }

    /**
     * Feature: student-application-form-association-authorization,
     * Property 9: Gate infrastructure failures fail closed through error2
     *
     * Gate construction and invocation failures use the existing error2
     * redirect before persistence, documents, notifications, or payments, and
     * the caught exception is never rendered into the response.
     */
    public function testGateFailuresRedirectBeforeSideEffectsWithoutExceptionText(): void
    {
        $source = $this->processorSource();
        $gateTryNeedle = "try {\n            \$associationGate = "
            .'$container->get('
            .'ApplicationFormAssociationGate::class);';
        $gateTry = strpos($source, $gateTryNeedle);
        $gateCatch = strpos($source, '} catch (\\Throwable $exception) {', $gateTry);
        $gateErrorRedirect = strpos($source, "\$URL .= '&return=error2';", $gateCatch);
        $gateErrorBranch = strpos($source, "if ((\$associations['error'] ?? false) === true)");
        $validateInputs = strpos($source, '//VALIDATE INPUTS');

        $this->assertNotFalse($gateTry);
        $this->assertNotFalse($gateCatch);
        $this->assertNotFalse($gateErrorRedirect);
        $this->assertNotFalse($gateErrorBranch);
        $this->assertNotFalse($validateInputs);
        $this->assertLessThan($gateErrorBranch, $gateErrorRedirect);
        $this->assertLessThan($validateInputs, $gateErrorBranch);

        $catchBodyEnd = strpos($source, "        }\n\n        if ((\$associations", $gateCatch);
        $this->assertNotFalse($catchBodyEnd);
        $catchBody = substr($source, $gateCatch, $catchBodyEnd - $gateCatch);
        $this->assertStringNotContainsString('$exception->', $catchBody);
        $this->assertStringNotContainsString('getMessage', $catchBody);
        $this->assertStringNotContainsString('getTrace', $catchBody);

        foreach ([
            '$connection2->prepare',
            'PersonalDocumentHandler::class',
            'new NotificationEvent',
            '$payment->requestPayment',
        ] as $sideEffect) {
            $sideEffectPosition = strpos($source, $sideEffect);
            $this->assertNotFalse($sideEffectPosition, $sideEffect);
            $this->assertLessThan($sideEffectPosition, $gateErrorRedirect, $sideEffect);
        }

        $gate = $this->createInfrastructureFailureGate();
        $result = $gate->getValidatedAssociations('10', '101', ['201'], ['Mother']);

        $this->assertTrue($result['error']);
        $this->assertSame(
            ['error', 'usesExistingFamily', 'familyID', 'parentPersonID', 'relationships'],
            array_keys($result)
        );
        $this->assertStringNotContainsString('database details', serialize($result));
    }

    private function processorSource(): string
    {
        $source = file_get_contents(self::PROCESSOR_PATH);
        $this->assertNotFalse($source);

        return $source;
    }

    private function createDeterministicGate(): ApplicationFormAssociationGate
    {
        $session = $this->createMock(Session::class);
        $session->method('has')->willReturn(true);
        $session->method('get')->willReturn('900');

        $access = $this->createMock(Access::class);
        $access->method('allows')->willReturn(true);

        $familyGateway = $this->createMock(FamilyGateway::class);
        $familyGateway->method('getByID')->willReturn(['gibbonFamilyID' => '10']);
        $familyGateway->method('selectAdultsByFamily')->willReturn([
            ['gibbonPersonID' => '00101'],
        ]);
        $familyGateway->method('selectChildrenByFamily')->willReturn([
            ['gibbonPersonID' => '00201'],
        ]);

        $userGateway = $this->createMock(UserGateway::class);
        $userGateway->method('getByID')->willReturnCallback(
            static function (string $personID): array {
                return in_array($personID, ['101', '201'], true)
                    ? ['gibbonPersonID' => $personID]
                    : [];
            }
        );

        return new ApplicationFormAssociationGate(
            $session,
            $access,
            $familyGateway,
            $userGateway
        );
    }

    private function createInfrastructureFailureGate(): ApplicationFormAssociationGate
    {
        $session = $this->createMock(Session::class);
        $session->method('has')->willReturn(true);
        $session->method('get')->willReturn('900');

        $access = $this->createMock(Access::class);
        $access->method('allows')->willReturn(true);

        $familyGateway = $this->createMock(FamilyGateway::class);
        $familyGateway->method('getByID')->willThrowException(
            new \RuntimeException('database details must not be exposed')
        );

        return new ApplicationFormAssociationGate(
            $session,
            $access,
            $familyGateway,
            $this->createMock(UserGateway::class)
        );
    }
}
