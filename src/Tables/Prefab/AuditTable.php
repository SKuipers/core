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

namespace Gibbon\Tables\Prefab;

use Gibbon\Domain\DataSet;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\AuditableGateway;

/**
 * AuditTable
 *
 * @version v17
 * @since   v17
 */
class AuditTable extends DataTable
{
    protected $audits;

    /**
     * Helper method to create a report data table, which can display as a table, printable page or export.
     *
     * @param string $id
     * @param QueryCriteria $criteria
     * @param string $viewMode
     * @param string $guid
     * @return self
     */
    public static function createTable(AuditableGateway $gateway, $primaryKeyValue = null)
    {
        $gateway = $gateway;

        $criteria = $gateway->newQueryCriteria()
            ->sortBy('timestamp', 'DESC')
            ->fromPost('dataAudits');

        $audits = $gateway->queryAudits($criteria, $primaryKeyValue);
                
        // DATA TABLE
        $table = parent::createPaginated('dataAudits', $criteria)->withData($audits);
        $table->getRenderer()->addClass('smallIntBorder');

        // if (!empty($primaryKeyValue)) {
            $table->addExpandableColumn('eventData', __('Data'))
                ->notSortable()
                ->format(function($row) {
                    if ($row['event'] != 'Updated') return '';

                    $eventData = json_decode($row['eventData'], true);
                    $changes = array_map(function($key, $value) {
                        return array('field' => $key, 'valueOld' => $value['old'], 'valueNew' => $value['new'] );
                    }, array_keys($eventData), $eventData);

                    $table = DataTable::create('changes');
                    $table->getRenderer()->setClass('mini blank');
                    $table->addColumn('field', __('Field'))->width('25%');
                    $table->addColumn('valueOld', __('Original Value'))->width('25%');
                    $table->addColumn('valueNew', __('New View'))->width('25%');

                    return '<strong>'.__('Changes').'</strong>: <br/>'.$table->render(new DataSet($changes));
                });
        // }

        $table->addColumn('event', __('Event'))
            ->format(function($row) {
                $eventData = json_decode($row['eventData'], true);
                return ucfirst(strtolower($row['event'])).($row['event'] == 'Updated' ? ' <i>('.count($eventData).')</i>' : '');
            });

        if (empty($primaryKeyValue)) {
            $table->addColumn('record', __('Record'))
                ->notSortable()
                ->format(function($row) use ($gateway) {
                    if ($row['event'] == 'Deleted') {
                        $eventData = json_decode($row['eventData'], true);
                        $primaryName = isset($eventData[$gateway->getPrimaryName()])? $eventData[$gateway->getPrimaryName()] : '';
                    } else {
                        $primaryName = isset($row['primaryName'])? $row['primaryName'] : '';
                    }

                    return !empty($primaryName)? $primaryName : '<i class="small subdued">'.__('N/A').'</i>';
                });
        }

        $table->addColumn('timestamp', __('Date & Time'))->format(Format::using('dateTime', 'timestamp'));
        $table->addColumn('person', __('Person'))
            ->sortable(['gibbonPerson.preferredName', 'gibbonPerson.surname'])
            ->format(Format::using('name', ['', 'preferredName', 'surname', 'Staff']));

        $table->addActionColumn()
            ->addParam('gibbonDataAuditID')
            ->addParam('redirect', $_GET['q'])
            ->format(function ($row, $actions) use ($gateway) {
                if ( ($row['event'] == 'Updated') && !empty($row['primaryKeyValue'])) {
                    $actions->addAction('revert', __('Undo Changes'))
                        
                        ->setIcon('reincarnate')
                        ->isModal(650, 135)
                        ->setURL('/modules/System Admin/dataAudit_revert.php');
                }
    
                if ($row['event'] == 'Deleted') {
                    $actions->addAction('restore', __('Restore Record'))
                        ->setIcon('reincarnate')
                        ->isModal(650, 135)
                        ->setURL('/modules/System Admin/dataAudit_restore.php');
                }
            });
        

        return $table;
    }

    public function getOutput() 
    {
        $output = '';
        if ($this->data->count() > 0) {
            $output .= '<section class="dataAudit activatable">';
            $output .= '<button class="dataAuditMessage">';
            $output .= '<img src="./themes/Default/img/zoom.png" style="vertical-align:bottom;" height="14" /> <small>'.__('Change Log').' ('.$this->data->getResultCount().')'.'</small>';
            $output .= '</button>';

            $output .= '<div class="dataAuditChanges">';
            $output .= '<h5 style="margin-top:20px;">';
            $output .= __('Change Log');
            $output .= '</h5>';

            $output .= parent::getOutput();
            $output .= '</div>';
            $output .= '</section>';
        }

        return $output;
    }

}
