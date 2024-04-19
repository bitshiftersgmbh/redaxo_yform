<?php

/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

$historyId = $this->getVar('history_id', null);
$datasetId = $this->getVar('dataset_id', null);
$currentDataset = $this->getVar('current_dataset', null);

/* @var $table rex_yform_manager_table */
$table = $this->getVar('table', null);

$sql = rex_sql::factory();
$timestamp = (string) $sql->setQuery(sprintf('SELECT `timestamp` FROM %s WHERE id = %d', rex::getTable('yform_history'), $historyId))->getValue('timestamp');

$data = $sql->getArray(sprintf('SELECT * FROM %s WHERE history_id = %d', rex::getTable('yform_history_field'), $historyId));
$data = array_column($data, 'value', 'field');


if(!is_null($historyId) && !is_null($table)):

$diffs = [
    'changed' => ['icon' => 'fas fa-not-equal', 'count' => 0, 'rows' => ''],
    'unchanged' => ['icon' => 'fas fa-equals', 'count' => 0, 'rows' => ''],
    'added' => ['icon' => 'fas fa-layer-plus', 'count' => 0, 'rows' => ''],
    'removed' => ['icon' => 'far fa-layer-minus', 'count' => 0, 'rows' => ''],
];

$fieldsInDataset = [];
$tableFields = $table->getFields();

foreach ($table->getValueFields() as $field) {
    if (!array_key_exists($field->getName(), $data)) {
        continue;
    }

    $change = 'unchanged';

    $fieldsInDataset[] = $field->getName();
    $historyValue = $data[$field->getName()];
    $currentValue = ($currentDataset->hasValue($field->getName()) ? $currentDataset->getValue($field->getName()) : '-');

    $class = 'rex_yform_value_' . $field->getTypeName();

    // count diffs
    if(!$currentDataset->hasValue($field->getName())) {
        $change = 'deleted';
    } elseif("".$historyValue != "".$currentValue) {
        $change = 'changed';
    }

    $diffs[$change]['count']++;

    if (is_callable($class, 'getListValue') && !in_array($field->getTypeName(), ['text','textarea'])) {
        /** @var $class rex_yform_value_abstract */

        // to ensure correct replacement with list value, ensure datatype by current dataset
        if(gettype($currentValue) != gettype($historyValue)) {
            settype($historyValue, gettype($currentValue));
        }

        // get (formatted) value for history entry
        $historyValue = $class::getListValue([
            'value' => $historyValue,
            'subject' => $historyValue,
            'field' => $field->getName(),
            'params' => [
                'field' => $field->toArray(),
                'fields' => $this->table->getFields(),
            ],
        ]);

        // get (formatted) value for current entry
        if($currentDataset->hasValue($field->getName())) {
            $currentValue = $class::getListValue([
                'value' => $currentValue,
                'subject' => $currentValue,
                'field' => $field->getName(),
                'params' => [
                    'field' => $field->toArray(),
                    'fields' => $tableFields,
                ],
            ]);
        } else {
            $currentValue = '-';
        }
    } else {
        $historyValue = rex_escape($historyValue);
        $currentValue = rex_escape($currentValue);
    }

    // diff values for specific fields
    if($change == 'changed') {
        switch($field->getTypeName()) {
            case 'text':
            case 'textarea':
                $diff = rex_yform_history_helper::diffStringsToHtml($currentValue, $historyValue);
                $historyValue = $diff;
                break;

            default:
                if($historyValue != $currentValue) {
                    $historyValue = '<span class="diff">'. $historyValue .'</span>';
                }
                break;
        }
    }

    $diffs[$change]['rows'] .= '
        <tr>
            <th>' . $field->getLabel() . '</th>
            <td>' . $currentValue . '</td>
            <td>' . $historyValue . '</td>
        </tr>';
}

//

// build restore url
$restoreUrl = http_build_query([
    'table_name' => $table->getTableName(),
    'func' => 'history',
    'subfunc' => 'restore',
    'data_id' => $datasetId,
    'history_id' => $historyId
]).http_build_query(rex_csrf_token::factory($this->getVar('csrf_key', ''))->getUrlParams());

$content = '
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">
            ' . rex_i18n::msg('yform_history_dataset_id') . ': ' . $datasetId . '
            <small>[' . date('d.m.Y H:i:s', strtotime($timestamp)) . ']</small>
        </h4>
    </div>
    <div class="modal-body panel-default">
';

foreach ($diffs as $change => $diff) {
    $content .= '
        <header class="panel-heading" '.($diff['rows'] != '' ? 'data-toggle="collapse" data-target="#collapse-history-table-'. $change .'"' : '').'>
            <div class="panel-title"><i class="rex-icon '. $diff['icon'] .'"></i> '. rex_i18n::msg('yform_history_diff_headline_'.$change) .' ['. $diff['count'] .']</div>
        </header>
        <div id="collapse-history-table-'. $change .'" '.($diff['rows'] != '' ? 'class="panel-collapse collapse '.($change == 'changed' ? 'in' : '').'"' : '').'>';

    if($diff['rows'] != '') {
        $content .= '       
            <table class="table history-diff-table" data-change-mode="'. $change .'">
                <thead>
                    <tr>
                        <th class="rex-table-width-6">' . rex_i18n::msg('yform_tablefield') . '</th>                    
                        <th class="rex-table-width-10">' . rex_i18n::msg('yform_history_dataset_current') . '</th>
                        <th class="rex-table-width-10">'. date('d.m.Y H:i:s', strtotime($timestamp)) .'</th>
                    </tr>
                </thead>
                <tbody>' . $diff['rows'] . '</tbody>
            </table>';
    }

    $content .= '</div>';
}

$content .= '
    </div>
    <div class="modal-footer">
        <a href="index.php?page=yform/manager/data_edit?'. $restoreUrl .'" class="btn btn-warning" onclick="return confirm(\'' . rex_i18n::msg('yform_history_restore_confirm') . '\')">'.
            rex_i18n::msg('yform_history_restore_this').
        '</a>
        <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true">&times;</button>
    </div>
';

echo $content;
endif;