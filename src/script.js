
function SelectionQuery() {
    this.tableName = '';
    this.whereConditions = {};
}

function SelectionData() {
    this.headers = [];
    this.body = [];
    this.directForeignKeys = {};
    this.reverseForeignKeys = {};
}

function AdminerAjaxConnector(connectionUsername, connectionDb) {
    var instance = this;

    instance.getSelectionData = function(selectionQuery, callback) {
        var requestUrl = instance.urlFromSelectionQuery(selectionQuery);

        instance._ajaxRequest(requestUrl, function(pageHtml){
            var tableHtml = instance._getTableFromSelectionHtml(pageHtml);
            console.log(tableHtml);

            if (tableHtml.trim() === '') {
                callback(new SelectionData());
                return;
            }

            var foreignKeysMatch = tableHtml.match(/<meta name="foreign-keys" content="(.+)"\/>/);
            var foreignKeys = JSON.parse(foreignKeysMatch[1]);
            console.log(foreignKeys);
            tableHtml = tableHtml.replace(foreignKeysMatch[0], '');

            var tableElement = document.createElement('table');
            tableElement.innerHTML = tableHtml;

            var selectionData = instance._extractDataFromTableElement(tableElement);
            selectionData = instance._addForeignKeysToTableData(selectionQuery.tableName, selectionData, foreignKeys);

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
        var selectionData = new SelectionData();

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

    instance._addForeignKeysToTableData = function (tableName, selectionData, foreignKeys) {

        for (var i = 0; i < foreignKeys.length; i++) {
            var foreignKey = foreignKeys[i];

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

        return selectionData;
    };

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

    instance.selectionQueryFromUrl = function(url) {
        var params = (new URL(url)).searchParams;

        var selectionQuery = new SelectionQuery();



        if (params.get('select') !== null) {
            selectionQuery.tableName = params.get('select');
            // selection.type = 'select';

            for (var i = 0; params.get('where[' + i + '][col]') !== null; i++) {

                if (params.get('where[' + i + '][op]') === '=') {
                    selectionQuery.whereConditions[params.get('where[' + i + '][col]')] = params.get('where[' + i + '][val]');
                }
            }

            // if (params.get('modify') === '1') {
            //     selection.type = 'modify';
            // }
        }

        if (params.get('edit') !== null) {
            selectionQuery.tableName = params.get('edit');
            // selection.type = 'edit';

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

    instance._urlForWhereCondition = function (index, columnName, value) {
        return 'where[' + index + '][col]=' + encodeURIComponent(columnName)
            + '&where[' + index + '][op]=='
            + '&where[' + index + '][val]=' + encodeURIComponent(value)
    };

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

function HtmlGenerator(adminerAjaxConnector) {
    var instance = this;

    instance.getModalElement = function() {
        var modal = instance._getTemplateAsElement(
            '<div id="tree-modal" style="position:fixed; top:50px; left:50px; width:calc(100% - 100px);height:calc(100% - 100px);background:white; border:1px solid; display:none">' +
            '   <h1>' +
            '       <span class="title">Tree browser</span>' +
            '       <a class="close" href="#!" style="float:right;">close</a>' +
            '   </h1>' +
            '   <div class="modal-content" style="overflow:auto; position:absolute; width:calc(100% - 20px); height:calc(100% - 162px); top:62px; left:0; margin:0 10px; padding-bottom:100px;"></div>' +
            '</div>'
        );

        modal.querySelector('.close').addEventListener('click', function () {
            modal.style.display = 'none';
        });

        return modal;
    };

    instance.createTableElementFromSelectionData = function (tableName, tableData, tableCaption) {
        var tableElement = instance._getTemplateAsElement(
            '<table>' +
            '   <thead>' +
            '       <tr><th class="table-name"></th></tr>' +
            '       <tr class="headers"></tr>' +
            '   </thead>' +
            '   <tbody></tbody>' +
            '</table>'
        );

        var tableNameElement = tableElement.querySelector('.table-name');
        tableNameElement.colSpan = tableData.headers.length;
        tableNameElement.innerText = tableCaption;

        if (tableData.body.length === 0) {
            tableElement.querySelector('tbody').innerHTML = '<tr><td><i>Empty result</i></td></tr>';

            return tableElement;
        }

        var theadRow = tableElement.querySelector('.headers');
        for (var i = 0; i < tableData.headers.length; i++) {
            var th = document.createElement('th');
            th.innerText = tableData.headers[i];
            theadRow.appendChild(th);
        }

        var tbody = tableElement.querySelector('tbody');
        for (var i = 0; i < tableData.body.length; i++) {
            var dataRow = document.createElement('tr');
            tbody.appendChild(dataRow);

            for (var j = 0; j < tableData.headers.length; j++) {
                var td = document.createElement('td');
                dataRow.appendChild(td);

                // process direct foreign keys
                var foreignKey = tableData.directForeignKeys[tableData.headers[j]];
                if (foreignKey !== undefined) {
                    var link = instance._getTemplateAsElement('<a class="direct-foreign-key" />');
                    link.innerText = tableData.body[i][tableData.headers[j]];
                    td.appendChild(link);

                    var selectionQuery = new SelectionQuery();
                    selectionQuery.tableName = foreignKey.targetTable;
                    selectionQuery.whereConditions[foreignKey.targetColumns[0]] = tableData.body[i][foreignKey.sourceColumns[0]];

                    link.href = adminerAjaxConnector.urlFromSelectionQuery(selectionQuery);

                } else {
                    td.innerText = tableData.body[i][tableData.headers[j]];
                }

                // process reverse foreign keys
                var reverseForeignKeys = tableData.reverseForeignKeys[tableData.headers[j]];
                console.log(reverseForeignKeys);
                if (reverseForeignKeys !== undefined) {
                    var linksContainer = instance._getTemplateAsElement('<div class="reverse-foreign-keys" style="display:none"></div>');

                    var linkToggleButton = instance._getTemplateAsElement('<a href="#!" >[R]</a>');
                    linkToggleButton.addEventListener('click', function (e) {
                        var container = e.target.parentNode.querySelector('.reverse-foreign-keys');
                        if (container.style.display === 'none') {
                            container.style.display = 'block';
                        } else {
                            container.style.display = 'none';
                        }
                    });

                    td.appendChild(linkToggleButton);

                    for (var k = 0; k < reverseForeignKeys.length; k++) {

                        var link = instance._getTemplateAsElement('<a class="reverse-foreign-key" style="display: block;" />');
                        link.innerText = reverseForeignKeys[k].sourceTable + '.' + reverseForeignKeys[k].sourceColumns[0]; //tableData.body[i][tableData.headers[j]];
                        td.appendChild(link);

                        var selectionQuery = new SelectionQuery();
                        selectionQuery.tableName = reverseForeignKeys[k].sourceTable;
                        selectionQuery.whereConditions[reverseForeignKeys[k].sourceColumns[0]] = tableData.body[i][reverseForeignKeys[k].targetColumns[0]];

                        link.href = adminerAjaxConnector.urlFromSelectionQuery(selectionQuery);

                        linksContainer.appendChild(link);
                    }

                    td.appendChild(linksContainer);
                }
            }
        }

        return tableElement;
    };

    instance._getTemplateAsElement = function(htmlTemplate) {
        var div = document.createElement('div');
        div.innerHTML = htmlTemplate;
        return div.children[0];
    }
}

function AdminerTreeView() {
    var instance = this;

    var url = new URL(window.location.href.toString());
    var connector = new AdminerAjaxConnector(
        url.searchParams.get('username'),
        url.searchParams.get('db')
    );

    var htmlGenerator = new HtmlGenerator(connector);

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

    instance.openSelectionIntoContainer = function(selectionQuery, containerElement) {
        connector.getSelectionData(selectionQuery, function(selectionData){

            var tableCaption = instance.tableCaptionFromSelectionQuery(selectionQuery);
            var table = htmlGenerator.createTableElementFromSelectionData(selectionQuery.tableName, selectionData, tableCaption);

            var selection = document.createElement('div');
            selection.className = 'selection';
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
                        var subSection = connector.selectionQueryFromUrl(e.target.href);
                        instance.openSelectionIntoContainer(subSection, subSelectionsBox);
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

    instance.tableCaptionFromSelectionQuery = function (selectionQuery) {

        var whereConditionsList = [];
        for (var conditionName in selectionQuery.whereConditions) {
            if (Object.prototype.hasOwnProperty.call(selectionQuery.whereConditions, conditionName)) {
                whereConditionsList.push(conditionName + " = " + selectionQuery.whereConditions[conditionName]);
            }
        }

        return selectionQuery.tableName + " [" + whereConditionsList.join(', ') + "]";
    };

    instance.displayModal = function(event) {
        var treeModal = document.querySelector('#tree-modal');

        if (treeModal === null) {
            treeModal = htmlGenerator.getModalElement();
            document.body.appendChild(treeModal);
        }

        var modalContent = treeModal.querySelector('.modal-content');
        modalContent.innerHTML = '';

        var dataRowElement = event.target.parentElement.parentElement;
        var editUrl = dataRowElement.querySelector('a.edit').href;
        var selectionQuery = connector.selectionQueryFromUrl(editUrl);

        treeModal.style.display = 'block';

        instance.openSelectionIntoContainer(selectionQuery, modalContent);

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
