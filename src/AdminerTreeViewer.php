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
    private $scriptUrl;

    function __construct($scriptUrl) {
        $this->scriptUrl = $scriptUrl;
    }

    function navigation($ve) {

        // $_GET['select'] indicates that we are on page with selection of table.
        if (isset($_GET['select'])) {

            // link to js file
            echo Adminer\script_src($this->scriptUrl);

            // after we included script with AdminerTreeView, init it
            echo Adminer\script('(new AdminerTreeView()).init();');
        }
    }

    function rowDescriptions($rows, $foreignKeys) {

        // prepare foreign keys in format suitable for JS
        $foreignKeysList = $this->getForeignKeysList();
        $foreignKeysJson = json_encode($foreignKeysList);

        // echo meta tag at the beginning of selection table for use in JS
        echo '<meta name="foreign-keys" content="' . $foreignKeysJson . '"/>';

        return $rows;
    }

    private function getForeignKeysList() {

        // table_status gets list of table names for current DB
        $tables = array_column(Adminer\table_status('', true), 'Name');
        $foreignKeysList = [];

        foreach ($tables as $table) {

            // Adminer\adminer() gets global instance of Adminer
            foreach (Adminer\adminer()->foreignKeys($table) as $foreignKey) {

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
