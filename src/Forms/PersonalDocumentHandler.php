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

namespace Gibbon\Forms;

use Gibbon\FileUploader;
use Gibbon\Services\Format;
use Gibbon\Domain\User\PersonalDocumentGateway;

class PersonalDocumentHandler
{
    protected $personalDocumentGateway;
    protected $documents;
    protected $fields;

    public function __construct(PersonalDocumentGateway $personalDocumentGateway, FileUploader $fileUploader)
    {
        $this->personalDocumentGateway = $personalDocumentGateway;
        $this->fileUploader = $fileUploader;

        $this->documents = [
            'Passport' => __('Passport'),
            'ID Card'  => __('ID Card'),
            'Document' => __('Document'),
        ];

        $this->fields = [
            'documentName'   => __('Name on Document'),
            'documentNumber' => __('Document Number'),
            'documentType'   => __('Residency/Visa Type'),
            'country'        => __('Issuing Country'),
            'dateIssue'      => __('Issue Date'),
            'dateExpiry'     => __('Expiry Date'),
            'filePath'       => __('File Upload'),
        ];
    }

    public function getDocuments()
    {
        return $this->documents;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function updateDocumentsFromPOST($foreignTable = null, $foreignTableID = null, $params = [], &$personalDocumentFail = false)
    {
        $documents = $this->personalDocumentGateway->selectPersonalDocuments(null, null, $params)->fetchAll();
        if (empty($documents)) return;

        foreach ($documents as $document) {
            $fields = json_decode($document['fields']);
            $data = [];

            foreach ($fields as $field) {
                $value = $_POST['document'][$document['gibbonPersonalDocumentTypeID']][$field] ?? null;

                if ($field == 'dateIssue' || $field == 'dateExpiry') {
                    // Handle date conversion
                    $data[$field] = !empty($value) ? Format::dateConvert($value) : null;
                } elseif ($field == 'filePath') {
                    // Handle file uploads
                    $file = $_FILES['document'.$document['gibbonPersonalDocumentTypeID'].$field] ?? null;
                    $attachment = $_POST['attachment'][$document['gibbonPersonalDocumentTypeID']][$field] ?? null;

                    if (!empty($file['tmp_name'])) {
                        $this->fileUploader->setFileSuffixType(FileUploader::FILE_SUFFIX_ALPHANUMERIC);
                        $data[$field] = $this->fileUploader->uploadFromPost($file, $foreignTable.$foreignTableID.'_scan');

                        if (empty($value)) {
                            $personalDocumentFail = true;
                        }
                    } else {
                        $data[$field] = $attachment;
                    }
                } else {
                    // Handle all other data
                    $data[$field] = !empty($value) ? $value : null;
                }
            }

            $omit = $_POST['document'][$document['gibbonPersonalDocumentTypeID']]['omit'] ?? null;
            $exists = $_POST['document'][$document['gibbonPersonalDocumentTypeID']]['gibbonPersonalDocumentID'] ?? null;

            // Skip any documents that are entirely empty
            if (count(array_filter($data)) == 0 && $omit != 'Y' && !$exists) continue;

            $data['gibbonPersonalDocumentTypeID'] = $document['gibbonPersonalDocumentTypeID'];
            $data['foreignTable'] = $foreignTable;
            $data['foreignTableID'] = $foreignTableID;
            $data['timestamp'] = date('Y-m-d H:i:s');

            $success = $this->personalDocumentGateway->insertAndUpdate($data, $data);
            $personalDocumentFail &= !$success;
        }
    }

    public function addPersonalDocumentsToDataUpdate(&$form, $gibbonPersonID, $gibbonPersonUpdateID, $params)
    {
        $documentsOld = $this->personalDocumentGateway->selectPersonalDocuments('gibbonPerson', $gibbonPersonID, $params)->fetchGroupedUnique();
        $documentsNew = $this->personalDocumentGateway->selectPersonalDocuments('gibbonPersonUpdate', $gibbonPersonUpdateID, $params + ['notEmpty' => true])->fetchGroupedUnique();
        if (empty($documentsOld) && empty($documentsNew)) return;

        foreach ($documentsOld as $gibbonPersonalDocumentTypeID => $document) {
            $row = $form->addRow()->setClass('head heading')->addContent(__($document['name']));

            // Add the existing document ID, so we can check against it later when processing the update
            if (!empty($documentsNew[$gibbonPersonalDocumentTypeID])) {
                $form->addHiddenValue("document[$gibbonPersonalDocumentTypeID][gibbonPersonalDocumentID]", $documentsNew[$gibbonPersonalDocumentTypeID]['gibbonPersonalDocumentID']);
            }
                
            $fields = json_decode($document['fields']);
            foreach ($fields as $field) {
                $oldValue = $documentsOld[$gibbonPersonalDocumentTypeID][$field] ?? null;
                $newValue = $documentsNew[$gibbonPersonalDocumentTypeID][$field] ?? null;

                if (empty($documentsNew)) { // Handle updates after they have been accepted and documents deleted
                    $newValue = $oldValue;
                }

                $newValueLabel = $newValue;
                if ($field == 'dateIssue' || $field == 'dateExpiry') {
                    $oldValue = Format::date($oldValue);
                    $newValue = Format::date($newValue);
                } elseif ($field == 'filePath') {
                    $oldValue = !empty($oldValue) ? Format::link('./'.$oldValue, __('Attachment'), ['target' => '_blank']) : '';
                    $newValueLabel = !empty($newValue) ? Format::link('./'.$newValue, __('Attachment'), ['target' => '_blank']) : '';
                }

                $isMatching = ($oldValue != $newValue);

                $row = $form->addRow();
                $row->addLabel('document'.$field.'On', __($this->fields[$field]));
                $row->addContent($oldValue);
                $row->addContent($newValueLabel)->addClass($isMatching ? 'matchHighlightText' : '');

                if ($isMatching) {
                    $row->addCheckbox("document[$gibbonPersonalDocumentTypeID][{$field}On]")->checked(true)->setClass('textCenter');
                    $form->addHiddenValue("document[$gibbonPersonalDocumentTypeID][{$field}]", $newValue);
                } else {
                    $row->addContent();
                }
            }
        }
    }

    public function updatePersonalDocumentsFromDataUpdate($gibbonPersonID, $gibbonPersonUpdateID, $params = [])
    {
        $documents = $this->personalDocumentGateway->selectPersonalDocuments('gibbonPersonUpdate', $gibbonPersonUpdateID, $params)->fetchAll();
        if (empty($documents)) return;

        foreach ($documents as $document) {
            $fields = json_decode($document['fields']);
            $data = [];

            foreach ($fields as $field) {
                if (!isset($_POST['document'][$document['gibbonPersonalDocumentTypeID']][$field.'On'])) continue;
                if (!isset($_POST['document'][$document['gibbonPersonalDocumentTypeID']][$field])) continue;

                $value = $_POST['document'][$document['gibbonPersonalDocumentTypeID']][$field] ?? '';

                if ($field == 'dateIssue' || $field == 'dateExpiry') {
                    $value = Format::dateConvert($value);
                }

                $data[$field] = $value;
            }

            $exists = $_POST['document'][$document['gibbonPersonalDocumentTypeID']]['gibbonPersonalDocumentID'] ?? null;

            // Skip any documents that are entirely empty
            if (count(array_filter($data)) == 0 && !$exists) continue;

            $data['gibbonPersonalDocumentTypeID'] = $document['gibbonPersonalDocumentTypeID'];
            $data['foreignTable'] = 'gibbonPerson';
            $data['foreignTableID'] = $gibbonPersonID;
            $data['timestamp'] = date('Y-m-d H:i:s');

            if ($this->personalDocumentGateway->insertAndUpdate($data, $data)) {
                $this->personalDocumentGateway->deleteWhere(['gibbonPersonalDocumentTypeID' => $document['gibbonPersonalDocumentTypeID'], 'foreignTable' => 'gibbonPersonUpdate', 'foreignTableID' => $gibbonPersonUpdateID]);
            }
        }

        return json_encode($fields);
    }
}
