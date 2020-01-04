
function SelectionQuery() {
    this.tableName = '';
    this.whereConditions = '';
}

function Selection() {
    this.headers = [];
    this.body = [];
    this.directForeignKeys = {};
    this.reverseForeignKeys = {};
}

function AdminerAjaxConnector(connectionUsername, connectionDb) {
    var instance = this;

    instance.getSelectionData = function(tableName, whereConditionsObject, callback) {
        var requestUrl = instance.urlForTableSelection(tableName, whereConditionsObject);

        instance.ajaxRequest(requestUrl, function(pageHtml){
            var tableHtml = instance._getTableFromSelectionHtml(pageHtml);

            var foreignKeysMatch = tableHtml.match(/<meta name="reverse-foreign-keys" content="(.+)"\/>/);
            var reverseForeignKeys = JSON.parse(foreignKeysMatch[1]);
            tableHtml = tableHtml.replace(foreignKeysMatch[0], '');

            var tableElement = document.createElement('table');
            tableElement.innerHTML = tableHtml;

            var selectionData = instance._extractDataFromTableElement(tableElement);
            selectionData = instance._addForeignKeysToTableData(tableName, selectionData, reverseForeignKeys);

            callback(selectionData);
        }, false);
    };

    instance._getTableFromSelectionHtml = function(pageHtml) {
        var start = pageHtml.indexOf('<table');
        var end = pageHtml.indexOf('table>');

        var tableHtml = pageHtml.substr(start, end - start) + 'table>';

        start = tableHtml.indexOf('<script');
        while (start !== -1) {
            end = tableHtml.indexOf('script>') + 7;
            tableHtml = tableHtml.substr(0, start) + tableHtml.substr(end);
            start = tableHtml.indexOf('<script');
        }

        start = tableHtml.indexOf('>') + 1;
        end = tableHtml.indexOf('</table>');
        tableHtml = tableHtml.substr(start, end - start);

        return tableHtml;
    };

    instance._extractDataFromTableElement = function (tableElement) {
        var selectionData = new Selection();

        tableElement.querySelectorAll('th > a').forEach(function(th){
            selectionData.headers.push(th.innerText);
        });

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

    instance._addForeignKeysToTableData = function (tableName, selectionData, reverseForeignKeys) {

        for (var prop in reverseForeignKeys) {
            if (Object.prototype.hasOwnProperty.call(reverseForeignKeys, prop)) {
                var foreignKeysForTable = reverseForeignKeys[prop];

                for (var i = 0; i < foreignKeysForTable.length; i++) {
                    var foreignKey = foreignKeysForTable[i];

                    if (foreignKey.sourceTable === tableName) {
                        for (var j = 0; j < foreignKey.sourceColumns.length; j++) {
                            selectionData.directForeignKeys[foreignKey.sourceColumns[j]] = foreignKey;
                        }
                    }

                    if (foreignKey.targetTable === tableName) {
                        for (var j = 0; j < foreignKey.targetColumns.length; j++) {
                            if (selectionData.reverseForeignKeys[foreignKey.targetColumns[j]] === undefined) {
                                selectionData.reverseForeignKeys[foreignKey.targetColumns[j]] = [];
                            }
                            selectionData.reverseForeignKeys[foreignKey.targetColumns[j]].push(foreignKey);
                        }
                    }
                }
            }
        }


        console.log(selectionData);
        return selectionData;
    };

    instance._createTableElementFromSelectionData = function (tableName, tableData) {
        var tableElement = document.createElement('table');


        var thead = document.createElement('thead');
        tableElement.appendChild(thead);

        var theadRow = document.createElement('tr');
        thead.appendChild(theadRow);

        for (var i = 0; i < tableData.headers.length; i++) {
            var th = document.createElement('th');
            th.innerText = tableData.headers[i];
            theadRow.appendChild(th);
        }


        var tbody = document.createElement('tbody');
        tableElement.appendChild(tbody);

        for (var i = 0; i < tableData.body.length; i++) {
            var dataRow = document.createElement('tr');
            tbody.appendChild(dataRow);

            for (var j = 0; j < tableData.headers.length; j++) {
                var td = document.createElement('td');
                dataRow.appendChild(td);

                // process direct foreign keys
                var foreignKey = tableData.directForeignKeys[tableData.headers[j]];
                if (foreignKey !== undefined) {
                    var link = document.createElement('a');
                    td.appendChild(link);
                    link.innerText = tableData.body[i][tableData.headers[j]];
                    link.className = 'direct-foreign-key';

                    var whereConditions = {};
                    whereConditions[foreignKey.targetColumns[0]] = tableData.body[i][foreignKey.sourceColumns[0]];

                    link.href = instance.urlForTableSelection(foreignKey.targetTable, whereConditions);

                } else {
                    td.innerText = tableData.body[i][tableData.headers[j]];
                }

                // process reverse foreign keys
                var reverseForeignKeys = tableData.reverseForeignKeys[tableData.headers[j]];
                console.log(reverseForeignKeys);
                if (reverseForeignKeys !== undefined) {

                    var linksContainer = document.createElement('div');
                    linksContainer.style.display = 'none';

                    var linkToggleButton = document.createElement('a');
                    linkToggleButton.innerText = ' [R]';
                    linkToggleButton.href = '#!';
                    linkToggleButton.addEventListener('click', function () {
                        if (linksContainer.style.display == 'none') {
                            linksContainer.style.display = 'block';
                        } else {
                            linksContainer.style.display = 'none';
                        }
                    });

                    for (var k = 0; k < reverseForeignKeys.length; k++) {

                        var link = document.createElement('a');
                        link.style.display = 'block';
                        td.appendChild(link);
                        link.innerText = reverseForeignKeys[k].sourceTable + '.' + reverseForeignKeys[k].sourceColumns[0]; //tableData.body[i][tableData.headers[j]];
                        link.className = 'reverse-foreign-key';

                        var whereConditions = {};
                        whereConditions[reverseForeignKeys[k].sourceColumns[0]] = tableData.body[i][reverseForeignKeys[k].targetColumns[0]];

                        link.href = instance.urlForTableSelection(reverseForeignKeys[k].sourceTable, whereConditions);

                        linksContainer.appendChild(link);
                    }

                    td.appendChild(linkToggleButton);
                    td.appendChild(linksContainer);
                }

            }
        }

        return tableElement;
    };

    instance.urlForTableSelection = function(tableName, whereConditionsObject) {
        var urlParts = [];
        urlParts.push('username=' + connectionUsername);
        urlParts.push('db=' + connectionDb);
        urlParts.push('select=' + tableName);

        var index = 0;
        for (var conditionName in whereConditionsObject) {
            if (Object.prototype.hasOwnProperty.call(whereConditionsObject, conditionName)) {
                urlParts.push(instance.urlForWhereCondition(index++, conditionName, whereConditionsObject[conditionName]));
            }
        }

        return '?' + urlParts.join('&');
    };

    instance.tableSelectionForUrl = function(url) {
        var params = (new URL(url)).searchParams;

        var selection = {
            table: null,
            type: null,
            conditions: {}
        };


        if (params.get('select') !== null) {
            selection.table = params.get('select');
            selection.type = 'select';

            var i = 0;
            while (params.get('where[' + i + '][col]') !== null) {

                if (params.get('where[' + i + '][op]') === '=') {
                    selection.conditions[params.get('where[' + i + '][col]')] = params.get('where[' + i + '][val]');
                }

                i++;
            }

            if (params.get('modify') === '1') {
                selection.type = 'modify';
            }
        }

        if (params.get('edit') !== null) {
            selection.table = params.get('edit');
            selection.type = 'edit';

            var keys = params.keys();
            var key = keys.next();
            while (key.done === false) {
                if (key.value.startsWith('where')) {
                    var pair = key.value.replace(']', '').split('[');
                    selection.conditions[pair[1]] = params.get(key.value);
                }

                key = keys.next();
            }
        }



        return selection;
    };

    instance.urlForWhereCondition = function (index, columnName, value) {
        return 'where[' + index + '][col]=' + encodeURIComponent(columnName)
            + '&where[' + index + '][op]=='
            + '&where[' + index + '][val]=' + encodeURIComponent(value)
    };

    instance.ajaxRequest = function(theUrl, callback, sendXRequestHeader) {

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

function HtmlTemplates() {
    this.modalHtml =
        '<div id="tree-modal" style="position:fixed; top:50px; left:50px; width:calc(100% - 100px);height:calc(100% - 100px);background:white; border:1px solid; display:none">' +
        '   <h1>' +
        '       <span class="title">Tree browser</span>' +
        '       <a class="close" href="#!" style="float:right;">close</a>' +
        '   </h1>' +
        '   <div class="modal-content" style="overflow:auto; position:absolute; width:calc(100% - 20px); height:calc(100% - 162px); top:62px; left:0; margin:0 10px; padding-bottom:100px;"></div>' +
        '</div>';

    this.getTemplateAsElement = function(htmlTemplate) {
        var div = document.createElement('div');
        div.innerHTML = htmlTemplate;
        return div.children[0];
    }
}

function AdminerTreeView() {
    var instance = this;
    var templates = new HtmlTemplates();

    var url = new URL(window.location.href.toString());
    var connector = new AdminerAjaxConnector(
        url.searchParams.get('username'),
        url.searchParams.get('db')
    );

    instance.init = function() {
        instance.addTreeViewColumnToTable()
    };

    instance.addTreeViewColumnToTable = function() {
        document.querySelector('#table').querySelectorAll('tr').forEach(function (tr) {
            if (tr.querySelectorAll('th').length > 0) {
                var cell = document.createElement('th');
                cell.innerText = 'Tree';
                tr.appendChild(cell);

            } else {
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

    instance.createTreeModal = function() {

        var modal = templates.getTemplateAsElement(templates.modalHtml);
        modal.querySelector('.close').addEventListener('click', function () {
            modal.style.display = 'none';
        });

        document.body.appendChild(modal);

        return modal;
    };

    instance.openSelectionIntoContainer = function(tableName, whereConditionsObject, containerElement) {
        connector.getSelectionData(tableName, whereConditionsObject, function(selectionData){

            var table = connector._createTableElementFromSelectionData(tableName, selectionData);

            var selection = document.createElement('div');
            selection.className = 'selection';

            var header = document.createElement('h3');
            header.innerText = tableName;
            selection.appendChild(header);

            selection.appendChild(table);

            var subSelectionsBox = document.createElement('div');
            subSelectionsBox.className = 'sub-selections-box';
            subSelectionsBox.style.paddingLeft = '100px';
            selection.appendChild(subSelectionsBox);

            var tableLinks = table.querySelectorAll('a');
            for (var i = 0; i < tableLinks.length; i++) {
                tableLinks[i].addEventListener('click', function (e) {
                    e.stopPropagation();
                    e.preventDefault();

                    if (e.target.className === 'direct-foreign-key' || e.target.className === 'reverse-foreign-key') {
                        var subSection = connector.tableSelectionForUrl(e.target.href);
                        instance.openSelectionIntoContainer(subSection.table, subSection.conditions, subSelectionsBox);
                    }

                    // if (subSection.type === 'select') {
                    // }
                    // if (subSection.type === 'edit' || subSection.type === 'modify') {
                    //     window.open(e.target.href);
                    // }
                });
            }

            containerElement.appendChild(selection);
        });
    };

    instance.displayModal = function(event) {
        var treeModal = document.querySelector('#tree-modal');
        if (treeModal === null) {
            treeModal = instance.createTreeModal()
        }

        var modalContent = treeModal.querySelector('.modal-content');
        modalContent.innerHTML = '';

        var dataRowElement = event.target.parentElement.parentElement;
        var editUrl = dataRowElement.querySelector('a.edit').href;
        var selectionParameters = connector.tableSelectionForUrl(editUrl);

        treeModal.style.display = 'block';

        instance.openSelectionIntoContainer(selectionParameters.table, selectionParameters.conditions, modalContent);

    };

    instance.convertOriginalRowToTableForModal = function(dataRow) {
        var headerRow = dataRow.parentNode.parentNode.querySelector('th').parentNode.parentNode;

        var table = document.createElement('table');
        table.appendChild(headerRow.cloneNode(true));
        table.appendChild(dataRow.cloneNode(true));

        return table;
    };

    window.treeView = instance;
    window.connector = connector;
}
