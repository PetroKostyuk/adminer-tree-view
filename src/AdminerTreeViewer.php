<?php

/*
Seznamte se s webovým nástrojem Adminer, určeným k pohodlné správě relačních databází ve webovém prohlížeči. Nastudujte
možnosti jeho rozšíření a relevantní PHP API určené pro tvoru pluginů.

Na základě zkušeností z bakalářské práce navrhněte uživatelsky přívětivý způsob stomového procházení relačně provázaných
dat. Naimplementujte tuto funkcionalitu jako plugin do nástroje Adminer.

Zhodnoťte, zda-li bylo rozhraní Admineru vhodné a použitelné pro tento úkol. Uvažte, je-li publikované rozšíření
nezávislé na použité databázi, či vázané na konkrétní RDBMS.
*/

class AdminerTreeViewer {

    function navigation($ve)
    {
        if (isset($_GET['select'])) {
            echo script("<<<%SCRIPT_JS%>>>");
            echo script('(new AdminerTreeView()).init();');
        }
    }

    function rowDescriptions($rows, $foreignKeys) {

        $foreignKeysList = $this->getForeignKeysList();
        $foreignKeysJson = json_encode($foreignKeysList);

        // echo meta tag at the beginning of selection table for use in JS
        echo '<meta name="foreign-keys" content="' . $foreignKeysJson . '"/>';

        return $rows;
    }

    private function getForeignKeysList() {
        $tables = array_column(table_status('', true), 'Name');
        $foreignKeysList = [];

        foreach ($tables as $table) {
            foreach (adminer()->foreignKeys($table) as $foreignKey) {

                $foreignKeysList[] = [
                    'sourceTable' => $table,
                    'sourceColumns' => $foreignKey['source'],
                    'targetTable' => $foreignKey['table'],
                    'targetColumns' => $foreignKey['target']
                ];
            }
        }

        return $foreignKeysList;
    }

}
