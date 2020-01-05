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
        // $_GET['select'] indicates that we are on page with selection of table.
        if (isset($_GET['select'])) {

            // string will be replaced during compilation
            echo script("
/** DTO containing information about selection we want to get from server **/
function SelectionQuery() {
    /** name of table */
    this.tableName = '';
    /** associative array of columnName: columnValue of conditions to be used on selection */
    this.whereConditions = {};
}

/** DTO with information about table content and foreign keys */
function SelectionData() {
    /** array of names of columns */
    this.headers = [];
    /** array of rows, each containing associative array of columnName: columnValue */
    this.body = [];
    /** associative array of columnName: foreignKey. For each column up to one foreign key */
    this.directForeignKeys = {};
    /** associative array of columnName: arrayOfReverseForeignKeys. For each column can be multiple foreign keys */
    this.reverseForeignKeys = {};
}

/**
 * Connection to Adminer. Handles AJAX requests and parsing of response to comfortable format.
 * Also takes care about conversions between SelectionQuery and equivalent selection url.
 * @param url string - current url. Used to extract DB name and table name
 * @constructor
 */
function AdminerAjaxConnector(url) {
    var instance = this;

    var connectionUsername = url.searchParams.get('username');
    var connectionDb = url.searchParams.get('db');

    /**
     * Sends AJAX request to server getting page with selection according to selectionQuery. Since call
     * is asynchronous Returns it takes callback function, that will be called after retrieving page and
     * extracting SelectionData from it. SelectionData will be passed to callback function.
     * @param selectionQuery SelectionQuery
     * @param callback function(SelectionData)
     */
    instance.getSelectionData = function(selectionQuery, callback) {
        var requestUrl = instance.urlFromSelectionQuery(selectionQuery);

        instance._ajaxRequest(requestUrl, function(pageHtml){
            var tableHtml = instance._getTableFromSelectionHtml(pageHtml);

            // in case that selection is empty (there are no rows in selection), return empty SelectionData
            if (tableHtml.trim() === '') {
                callback(new SelectionData());
                return;
            }

            // extract foreign keys JSON from meta tag and remove meta tag after that
            var foreignKeysMatch = tableHtml.match(/<meta name=\"foreign-keys\" content=\"(.+)\"\/>/);
            var foreignKeys = JSON.parse(foreignKeysMatch[1]);
            tableHtml = tableHtml.replace(foreignKeysMatch[0], '');

            // convert table HTML to DOM element
            var tableElement = document.createElement('table');
            tableElement.innerHTML = tableHtml;

            // extract table headers and rows
            var selectionData = instance._extractDataFromTableElement(tableElement);

            // add foreign keys from previous meta tag
            selectionData = instance._addForeignKeysToTableData(selectionQuery.tableName, selectionData, foreignKeys);

            // pass extracted selectionData to callback
            callback(selectionData);
        }, false);
    };

    /**
     * extracts html string of content of table from html of whole page
     * @param pageHtml string - HTML of whole page
     * @return {string} HTML string of content of table
     * @private
     */
    instance._getTableFromSelectionHtml = function(pageHtml) {

        // extract table HTML from page HTML
        var start = pageHtml.indexOf('<table');
        var end = pageHtml.indexOf('table>');
        var tableHtml = pageHtml.substr(start, end - start) + 'table>';

        // remove all inline scripts that adminer inserts
        start = tableHtml.indexOf('<script');
        while (start !== -1) {
            end = tableHtml.indexOf('script>') + 7;
            tableHtml = tableHtml.substr(0, start) + tableHtml.substr(end);
            start = tableHtml.indexOf('<script');
        }

        // trim surrounding <table></table> tag
        start = tableHtml.indexOf('>') + 1;
        end = tableHtml.indexOf('</table>');
        tableHtml = tableHtml.substr(start, end - start);

        return tableHtml;
    };

    /**
     * Extracts information about column names and rows with data
     * @param tableElement Element - Dom element
     * @return {SelectionData} SelectionData without information about foreign keys
     * @private
     */
    instance._extractDataFromTableElement = function (tableElement) {
        var selectionData = new SelectionData();

        // loop all table names from headers
        tableElement.querySelectorAll('th > a').forEach(function(th){
            selectionData.headers.push(th.innerText);
        });

        // loop all rows. Skip first column that contains checkbox
        var rows = tableElement.querySelectorAll('tr');
        for (var i = 1; i < rows.length; i++) {
            var bodyRow = {};

            var cells = rows[i].querySelectorAll('td');
            for (var j = 1; j < cells.length; j++) {
                bodyRow[selectionData.headers[j - 1]] = cells[j].innerText;
            }

            selectionData.body.push(bodyRow);
        }

        return selectionData;
    };

    /**
     * Adds information about foreign keys to SelectionData that already contains information about headers and rows
     * @param tableName string - name of current table used to decide if foreign key is direct or reverse
     * @param selectionData SelectionData - selection data with headers and rows
     * @param foreignKeys array - array of all foreign keys in DB
     * @return {SelectionData}
     * @private
     */
    instance._addForeignKeysToTableData = function (tableName, selectionData, foreignKeys) {

        for (var i = 0; i < foreignKeys.length; i++) {
            var foreignKey = foreignKeys[i];

            // if tableName is sourceTable, add as direct foreign key. Direct key is allowed only one per column
            if (foreignKey.sourceTable === tableName) {
                for (var j = 0; j < foreignKey.sourceColumns.length; j++) {
                    selectionData.directForeignKeys[foreignKey.sourceColumns[j]] = foreignKey;
                }
            }

            // if tableName is sourceTable, add as reverse foreign key. Multiple reverse keys are allowed per column
            if (foreignKey.targetTable === tableName) {
                for (var j = 0; j < foreignKey.targetColumns.length; j++) {
                    if (selectionData.reverseForeignKeys[foreignKey.targetColumns[j]] === undefined) {
                        selectionData.reverseForeignKeys[foreignKey.targetColumns[j]] = [];
                    }
                    selectionData.reverseForeignKeys[foreignKey.targetColumns[j]].push(foreignKey);
                }
            }
        }

        return selectionData;
    };

    /**
     * Converts SelectionQuery object to url that opens corresponding selection in adminer.
     * @param selectionQuery SelectionQuery
     * @return {string}
     */
    instance.urlFromSelectionQuery = function(selectionQuery) {
        var urlParts = [];
        urlParts.push('username=' + connectionUsername);
        urlParts.push('db=' + connectionDb);
        urlParts.push('select=' + selectionQuery.tableName);

        var index = 0;
        for (var conditionName in selectionQuery.whereConditions) {
            if (Object.prototype.hasOwnProperty.call(selectionQuery.whereConditions, conditionName)) {
                urlParts.push(instance._urlForWhereCondition(index++, conditionName, selectionQuery.whereConditions[conditionName]));
            }
        }

        return '?' + urlParts.join('&');
    };

    /**
     * Extracts get parameters from url of selection to object SelectionQuery
     * @param url string
     * @return {SelectionQuery}
     */
    instance.selectionQueryFromUrl = function(url) {
        var params = (new URL(url)).searchParams;

        var selectionQuery = new SelectionQuery();

        // select links have format: ...&select=library_book&where[0][col]=book&where[0][op]==&where[0][val]=2
        if (params.get('select') !== null) {
            selectionQuery.tableName = params.get('select');

            for (var i = 0; params.get('where[' + i + '][col]') !== null; i++) {

                if (params.get('where[' + i + '][op]') === '=') {
                    selectionQuery.whereConditions[params.get('where[' + i + '][col]')] = params.get('where[' + i + '][val]');
                }
            }
        }

        // edit links have format: ...&edit=book&where[id]=1
        if (params.get('edit') !== null) {
            selectionQuery.tableName = params.get('edit');

            var keys = params.keys();
            var key = keys.next();
            while (key.done === false) {
                if (key.value.startsWith('where')) {
                    var pair = key.value.replace(']', '').split('[');
                    selectionQuery.whereConditions[pair[1]] = params.get(key.value);
                }

                key = keys.next();
            }
        }

        return selectionQuery;
    };

    /**
     * Creates string with URL part containing GET parameters with one condition for selection
     * @param index number - number of condition in GET query
     * @param columnName string - name of condition column
     * @param value string - value of condition
     * @return {string} URL part containing GET parameters with condition
     * @private
     */
    instance._urlForWhereCondition = function (index, columnName, value) {
        return 'where[' + index + '][col]=' + encodeURIComponent(columnName)
            + '&where[' + index + '][op]=='
            + '&where[' + index + '][val]=' + encodeURIComponent(value)
    };

    /**
     * Calls AJAX and passes response to callback
     * @param theUrl string - url to call
     * @param callback function(string) - callback that will process HTML of response
     * @param sendXRequestHeader bool - if true, header will be sent that directs Adminer to send only body of selection, without rest of page
     * @private
     */
    instance._ajaxRequest = function(theUrl, callback, sendXRequestHeader) {

        var xmlHttp = new XMLHttpRequest();

        xmlHttp.onreadystatechange = function() {
            if (xmlHttp.readyState === 4) {
                callback(xmlHttp.responseText);
            }
        };

        xmlHttp.open('GET', theUrl); // false for synchronous request

        if (sendXRequestHeader) {
            // this header is recognised as ajax and adminer sends table without header
            xmlHttp.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        }

        xmlHttp.send();
    };
}

/**
 * Creates HTML elements that would be too big for AdminerTreeView to create inline and keep clear readability
 * @param adminerAjaxConnector AdminerAjaxConnector - used for creation of URL links
 * @constructor
 */
function HtmlGenerator(adminerAjaxConnector) {
    var instance = this;

    /** Creates modal div with corresponding styling and basic functionality (closing button, more may be added later) */
    instance.getModalElement = function() {
        var modal = instance._getTemplateAsElement(
            '<div id=\"tree-modal\" style=\"position:fixed; top:50px; left:50px; width:calc(100% - 100px);height:calc(100% - 100px);background:white; border:1px solid; display:none\">' +
            '   <h1>' +
            '       <span class=\"title\">Tree browser</span>' +
            '       <a class=\"close\" href=\"#!\" style=\"float:right;\">close</a>' +
            '   </h1>' +
            '   <div class=\"modal-content\" style=\"overflow:auto; position:absolute; width:calc(100% - 20px); height:calc(100% - 162px); top:62px; left:0; margin:0 10px; padding-bottom:100px;\"></div>' +
            '</div>'
        );

        modal.querySelector('.close').addEventListener('click', function () {
            modal.style.display = 'none';
        });

        return modal;
    };

    /**
     * Creates HTML table with data and functionality for selectionQuery. Table is used as one node of tree view.
     * @param selectionQuery SelectionQuery
     * @param selectionData SelectionData
     */
    instance.createTableElementFromSelectionData = function (selectionQuery, selectionData) {
        var tableElement = instance._getTemplateAsElement(
            '<table>' +
            '   <thead>' +
            '       <tr>' +
            '           <th class=\"table-name-cell\">' +
            '               <span class=\"table-name-caption\"></span>' +
            '               <a class=\"modify-all\" style=\"padding-left: 20px\" target=\"_blank\">Modify</a>' +
            '               <a class=\"close\" style=\"padding-left: 20px\" href=\"#!\" target=\"_blank\">Close</a>' +
            '           </th>' +
            '       </tr>' +
            '       <tr class=\"headers\"></tr>' +
            '   </thead>' +
            '   <tbody></tbody>' +
            '</table>'
        );

        // fill first header row with caption
        tableElement.querySelector('.table-name-cell').colSpan = selectionData.headers.length;
        tableElement.querySelector('.table-name-caption').innerText = instance._tableCaptionFromSelectionQuery(selectionQuery);

        // if table is empty, create special body to notify user and stop method
        if (selectionData.body.length === 0) {
            tableElement.querySelector('tbody').innerHTML = '<tr><td><i>Empty result</i></td></tr>';

            // remove modify link, there's noting to modify
            var modifyLink = tableElement.querySelector('.modify-all');
            modifyLink.parentNode.removeChild(modifyLink);

            return tableElement;
        }

        // create url for modifying selection from selectionQuery
        var modifyAllUrl = adminerAjaxConnector.urlFromSelectionQuery(selectionQuery) + '&modify=1';
        tableElement.querySelector('.modify-all').href = modifyAllUrl;

        // add second header with column names
        var theadRow = tableElement.querySelector('.headers');
        for (var i = 0; i < selectionData.headers.length; i++) {
            var th = document.createElement('th');
            th.innerText = selectionData.headers[i];
            theadRow.appendChild(th);
        }

        // add rows to table body
        var tbody = tableElement.querySelector('tbody');
        for (var i = 0; i < selectionData.body.length; i++) {
            var dataRow = document.createElement('tr');
            tbody.appendChild(dataRow);

            // add columns to row
            for (var j = 0; j < selectionData.headers.length; j++) {
                var td = document.createElement('td');
                dataRow.appendChild(td);

                // create link for direct foreign keys, if exists
                var foreignKey = selectionData.directForeignKeys[selectionData.headers[j]];
                if (foreignKey !== undefined) {

                    // create link
                    var link = instance._getTemplateAsElement('<a class=\"direct-foreign-key\" />');
                    link.innerText = selectionData.body[i][selectionData.headers[j]];
                    td.appendChild(link);

                    // create selectionQuery from information about foreign key
                    var selectionQuery = new SelectionQuery();
                    selectionQuery.tableName = foreignKey.targetTable;
                    for (var q = 0; q < foreignKey.targetColumns.length; q++) {
                        selectionQuery.whereConditions[foreignKey.targetColumns[q]] = selectionData.body[i][foreignKey.sourceColumns[q]];
                    }

                    // generate url from selectionQuery
                    link.href = adminerAjaxConnector.urlFromSelectionQuery(selectionQuery);

                } else {
                    td.innerText = selectionData.body[i][selectionData.headers[j]];
                }

                // if there are reverse foreign keys for column
                var reverseForeignKeys = selectionData.reverseForeignKeys[selectionData.headers[j]];
                if (reverseForeignKeys !== undefined) {

                    // create container to be able to store multiple reverse foreign keys
                    var linksContainer = instance._getTemplateAsElement('<div class=\"reverse-foreign-keys\" style=\"display:none\"></div>');

                    // create button to toggle container visibility
                    var linkToggleButton = instance._getTemplateAsElement('<a href=\"#!\"> [R]</a>');
                    linkToggleButton.addEventListener('click', function (e) {
                        var container = e.target.parentNode.querySelector('.reverse-foreign-keys');
                        if (container.style.display === 'none') {
                            container.style.display = 'block';
                        } else {
                            container.style.display = 'none';
                        }
                    });

                    td.appendChild(linkToggleButton);

                    // add each reverse foreign key to foreign keys container
                    for (var k = 0; k < reverseForeignKeys.length; k++) {

                        // create link
                        var link = instance._getTemplateAsElement('<a class=\"reverse-foreign-key\" style=\"display: block;\" />');
                        link.innerText = reverseForeignKeys[k].sourceTable + '.' + reverseForeignKeys[k].sourceColumns[0];
                        td.appendChild(link);

                        // create selectionQuery from information about foreign key
                        var selectionQuery = new SelectionQuery();
                        selectionQuery.tableName = reverseForeignKeys[k].sourceTable;
                        for (var q = 0; q < foreignKey.sourceColumns.length; q++) {
                            selectionQuery.whereConditions[reverseForeignKeys[k].sourceColumns[q]] = selectionData.body[i][reverseForeignKeys[k].targetColumns[q]];
                        }

                        // generate url from selectionQuery
                        link.href = adminerAjaxConnector.urlFromSelectionQuery(selectionQuery);

                        // add link to container
                        linksContainer.appendChild(link);
                    }

                    td.appendChild(linksContainer);
                }
            }
        }

        return tableElement;
    };

    /**
     * Create table caption made of table name and where conditions used to get selection
     * @param selectionQuery SelectionQuery
     * @return {string}
     * @private
     */
    instance._tableCaptionFromSelectionQuery = function (selectionQuery) {

        var whereConditionsList = [];
        for (var conditionName in selectionQuery.whereConditions) {
            if (Object.prototype.hasOwnProperty.call(selectionQuery.whereConditions, conditionName)) {
                whereConditionsList.push(conditionName + \" = \" + selectionQuery.whereConditions[conditionName]);
            }
        }

        return selectionQuery.tableName + \" [\" + whereConditionsList.join(', ') + \"]\";
    };

    /**
     * Creates DOM structure from HTML. WARNING: uses <div> tag as container. Because of thet it doesn't work
     * for creating structures starting structures that requires specific parent such as <tr> (that requires <table>)
     * @param htmlTemplate String
     * @return {HTMLElement}
     * @private
     */
    instance._getTemplateAsElement = function(htmlTemplate) {
        var div = document.createElement('div');
        div.innerHTML = htmlTemplate;
        return div.children[0];
    }
}

/**
 * Entry point of JS part of plugin. Takes care about interaction with user (drawing HTML, handling events, ...)
 * @constructor
 */
function AdminerTreeView() {
    var instance = this;

    /** AdminerAjaxConnector to comunicate with server side of Adminer */
    var connector = new AdminerAjaxConnector(new URL(window.location.href.toString()));
    /** HtmlGenerator to create more complex HTML structures */
    var htmlGenerator = new HtmlGenerator(connector);

    /** Initializes JS part of plugin */
    instance.init = function() {
        instance.addTreeViewColumnToTable()
    };

    /** Adds column 'Tree View' to selection table. By clicking on this column modal is shown for given row of selection */
    instance.addTreeViewColumnToTable = function() {

        // for each row in table add column at the end of row
        document.querySelector('#table').querySelectorAll('tr').forEach(function (tr) {

            // for row with header add cell with caption (without link to modal)
            if (tr.querySelectorAll('th').length > 0) {
                var cell = document.createElement('th');
                cell.innerText = 'Tree';
                tr.appendChild(cell);

            }
            // for row with data add cell with link to open modal
            else {
                var link = document.createElement('a');
                link.href = '#!';
                link.innerText = 'view';
                link.addEventListener('click', instance.displayModal);

                var cell = document.createElement('td');
                cell.appendChild(link);

                tr.appendChild(cell);
            }
        })
    };

    /**
     * Displays modal when modal link is clicked. Intended to be event handler. Direct call not recommended.
     * @param event Event - event of mouse clicking on modal opening link
     */
    instance.displayModal = function(event) {
        // tre to find existing modal
        var treeModal = document.querySelector('#tree-modal');

        // create new modal if existing not found (first time opening modal)
        if (treeModal === null) {
            treeModal = htmlGenerator.getModalElement();
            document.body.appendChild(treeModal);
        }

        // clear content from previous use
        var modalContent = treeModal.querySelector('.modal-content');
        modalContent.innerHTML = '';

        // extract SelectionQuery from link to edit row at the beginning of row (can be found next to row checkbox)
        var dataRowElement = event.target.parentElement.parentElement;
        var editUrl = dataRowElement.querySelector('a.edit').href;
        var selectionQuery = connector.selectionQueryFromUrl(editUrl);

        // display modal
        treeModal.style.display = 'block';

        // load content of selection into modal
        instance.openSelectionIntoContainer(selectionQuery, modalContent);
    };

    /**
     * Creates table for requested selection and inserts it as child to given container
     * @param selectionQuery SelectionQuery - selection to be opened
     * @param containerElement Element - DOM Element to insert loaded selection into
     */
    instance.openSelectionIntoContainer = function(selectionQuery, containerElement) {

        // load SelectionData by AJAX
        connector.getSelectionData(selectionQuery, function(selectionData){

            // create element for selection
            var selection = document.createElement('div');
            selection.className = 'selection';

            // create table element for given selection and put into selection element
            var table = htmlGenerator.createTableElementFromSelectionData(selectionQuery, selectionData);
            selection.appendChild(table);

            // on close remove all selection, not just table
            table.querySelector('.close').addEventListener('click', function(e){
                e.stopPropagation();
                e.preventDefault();

                selection.parentNode.removeChild(selection);
            });

            // add sub-selections box
            var subSelectionsBox = document.createElement('div');
            subSelectionsBox.className = 'sub-selections-box';
            subSelectionsBox.style.paddingLeft = '100px';
            selection.appendChild(subSelectionsBox);

            // add event handlers to load sub-selections when clicked on foreign key link
            var tableLinks = table.querySelectorAll('a');
            for (var i = 0; i < tableLinks.length; i++) {
                tableLinks[i].addEventListener('click', function (e) {
                    if (e.target.className === 'direct-foreign-key' || e.target.className === 'reverse-foreign-key') {
                        e.stopPropagation();
                        e.preventDefault();

                        var subSection = connector.selectionQueryFromUrl(e.target.href);
                        instance.openSelectionIntoContainer(subSection, subSelectionsBox);
                    }
                });
            }

            // append selection to container
            containerElement.appendChild(selection);
        });
    };
}
");

            // after we included script with AdminerTreeView, init it
            echo script('(new AdminerTreeView()).init();');
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
        $tables = array_column(table_status('', true), 'Name');
        $foreignKeysList = [];

        foreach ($tables as $table) {

            // adminer() gets global instance of Adminer
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
