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
                ->sortBy('changeTimestamp', 'DESC')
                ->fromArray($_POST);

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
                ->format(function($row) {
                    return !empty($row['primaryName'])? $row['primaryName'] : '<i class="small subdued">'.__('N/A').'</i>';
                });
        }

        $table->addColumn('changeTimestamp', __('Date & Time'))->format(Format::using('dateTime', 'changeTimestamp'));
        $table->addColumn('person', __('Person'))
            ->sortable(['gibbonPerson.preferredName', 'gibbonPerson.surname'])
            ->format(Format::using('name', ['', 'preferredName', 'surname', 'Staff']));

        $table->addActionColumn()
            ->format(function ($row, $actions) use ($gateway) {
                if ( ($row['event'] == 'Updated' || $row['event'] == 'Created') && !empty($row['primaryKeyValue'])) {
                    $actions->addAction('edit', __('Edit'))
                        ->addParam($gateway->getPrimaryKey(), $row['primaryKeyValue'])
                        ->setURL('/modules/'.$row['moduleName'].'/'.$row['gibbonActionURL']);
                }
    
                if ($row['event'] == 'Deleted') {
                    $actions->addAction('restore', __('Restore'))
                        ->setIcon('refresh')
                        ->setURL('/modules/'.$row['moduleName'].'/'.$row['gibbonActionURL']);
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
