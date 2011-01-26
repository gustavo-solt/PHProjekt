/**
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 3 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * @category   PHProjekt
 * @package    Application
 * @subpackage Default
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 * @version    Release: @package_version@
 * @author     Gustavo Solt <solt@mayflower.de>
 */

dojo.provide("phpr.Default.SubModule");
dojo.provide("phpr.Default.SubModule.Grid");
dojo.provide("phpr.Default.SubModule.Form");

dojo.declare("phpr.Default.SubModule", phpr.Component, {
    // Internal vars
    gridBox:      null,
    //detailsBox:   null,
    subForm:      null,
    subGrid:      null,
    module:       null,
    parentId:     null,
    gridWidget:   null,
    formWidget:   null,
    sortPosition: 1,

    constructor:function() {
        // Summary:
        //    Set some vars to run the sub module.
        // Description:
        //    Define the current module and the widgets to use.
        this.module     = "DefaultSubModule";
        this.gridWidget = phpr.Default.SubModule.Grid;
        this.formWidget = phpr.Default.SubModule.Form;

        this.loadFunctions();
    },

    loadFunctions:function() {
        // Summary:
        //    Add all the functions for the current module.
        dojo.subscribe(this.module  + ".updateCacheData", this, "updateCacheData");
    },

    /*
    getController:function() {
        // Summary:
        //    Return the controller to use
        // Description:
        //    Return the controller to use
        return 'index';
    },

    setUrl:function(type, id) {
        // Summary:
        //    Set all the urls
        // Description:
        //    Set all the urls
        var url = phpr.webpath + 'index.php/' + this.module + '/' + this.getController();
        switch (type) {
            case 'grid':
                url += '/jsonList/';
                break;
            case 'form':
                url += '/jsonDetail/';
                break;
            case 'save':
                url += '/jsonSave/';
                break;
            case 'delete':
                url += '/jsonDelete/';
                break;
        }
        if (type != 'delete') {
            url += 'nodeId/' + phpr.currentProjectId + '/';
        }
        if (type != 'grid') {
            url += 'id/' + id + '/';
        }
        url += phpr.module.toLowerCase() + 'Id/' + this.parentId;

        return url;
    },
    */

    fillTab:function(nodeId) {
        // Summary:
        //    Create the sub module tab.
        // Description:
        //    Create the divs for contain the grid and the form.
        var content = new dijit.layout.ContentPane({
            region: 'center'
        }, document.createElement('div'));

        var borderContainer = new dijit.layout.BorderContainer({
            design: 'sidebar'
        }, document.createElement('div'));

        this.gridBox = new dijit.layout.ContentPane({
            region: 'center'
        }, document.createElement('div'));

        var detailsBox = new dijit.layout.ContentPane({
            id:     'detailsBox-' + this.module,
            region: 'right',
            style:  'width: 50%; height: 100%;'
        }, document.createElement('div'));

        borderContainer.addChild(this.gridBox);
        borderContainer.addChild(detailsBox);
        content.set("content", borderContainer.domNode);

        dijit.byId(nodeId).set('content', content);
    },

    renderSubModule:function(parentId) {
        // Summary:
        //    Render the grid and the form widgets.
        this.parentId = parentId;

        //this.subGrid = new this.gridWidget('', this, phpr.currentProjectId);
        if (!this.subForm) {
            this.subForm = new this.formWidget(this.module);
        }
        this.subForm.init(0, [], this.parentId);
    },

    updateCacheData:function() {
        // Summary:
        //    Update the grid and the form widgets.
        if (this.subGrid) {
            this.subGrid.updateData();
        }
        if (this.subForm) {
            this.subForm.updateData();
        }
        this.renderSubModule(this.parentId);
    }
});

dojo.declare("phpr.Default.SubModule.Grid", phpr.Default.Grid, {
    // Overwrite functions for use with internal vars
    // This functions can be Rewritten
    updateData:function() {
        phpr.DataStore.deleteData({url: this.url});
    },

    usePencilForEdit:function() {
        return false;
    },

    useIdInGrid:function() {
        return true;
    },

    // Overwrite functions for use with internal vars
    // This functions should not be Rewritten

    setGridLayout:function(meta) {
        // Summary:
        //    Set all the field as not editables
        // Description:
        //    Set all the field as not editables
        this.inherited(arguments);
        for (cell in this.gridLayout) {
            if (typeof(this.gridLayout[cell]['editable']) == 'boolean') {
                this.gridLayout[cell]['editable'] = false;
            } else {
                for (index in this.gridLayout[cell]) {
                    if (typeof(this.gridLayout[cell][index]['editable']) == 'boolean') {
                        this.gridLayout[cell][index]['editable'] = false;
                    }
                }
            }
        }
    },

    setUrl:function() {
        this.url = this.main.setUrl('grid');
    },

    getLinkForEdit:function(id) {
        this.main.subForm = new this.main.formWidget(this.main, id, phpr.module);
    },

    setNode:function() {
        this._node = this.main.gridBox;
    },

    // Set empty functions for avoid them
    // This functions should not be Rewritten

    useCheckbox:function() {
        return false;
    },

    setFilterQuery:function(filters) {
        this.setUrl();
    },

    processActions:function() {
    },

    setExportButton:function(meta) {
    },

    loadGridSorting:function() {
    },

    saveGridSorting:function(e) {
    },

    loadGridScroll:function() {
    },

    saveGridScroll:function() {
    },

    setFilterButton:function(meta) {
    },

    manageFilters:function() {
    },

    showTags:function() {
    }
});

dojo.declare("phpr.Default.SubModule.Form", phpr.Default.Form, {
    _parentId:  0,
    _tabNumber: 99,

    // Events Buttons
    _eventForNew: null,

    init:function(id, params, parentId) {
        // Summary:
        //    Init the form for a new render.
        this._parentId = parentId;

        this.inherited(arguments);
    },

    updateData:function() {
        // Summary:
        //    Delete the cache for this form.
        if (this._id > 0) {
            phpr.DataStore.deleteData({url: this._url});
        }
    },

    /************* Private functions *************/

    _setUrl:function() {
        // Summary:
        //    Set the url for get the data.
        this._url = this._setFormUrl('form', this._id);
    },

    _setFormUrl:function(type, id) {
        // Summary:
        //    Set all the urls for the form.
        var url = phpr.webpath + 'index.php/' + this._module + '/' + this._getController();
        switch (type) {
            case 'form':
                url += '/jsonDetail/';
                break;
            case 'save':
                url += '/jsonSave/';
                break;
            case 'delete':
                url += '/jsonDelete/';
                break;
        }
        if (type != 'delete') {
            url += 'nodeId/' + phpr.currentProjectId + '/';
        }
        url += 'id/' + id + '/';
        url += phpr.module.toLowerCase() + 'Id/' + this._parentId;

        return url;
    },

    _getController:function() {
        // Summary:
        //    Return the controller to use.
        return 'index';
    },

    _initData:function() {
        // Summary:
        //    Init all the data before draw the form.
    },

    _getTabs:function() {
        // Summary:
        //    Change the tab number for don't overwrite the module tab.
        while (dijit.byId('tabBasicData' + this._tabNumber + '-' + phpr.module)) {
            this._tabNumber++;
        }

        return new Array({"id":     this._tabNumber,
                          "name":   phpr.nls.get('Basic Data'),
                          "nameId": 'subModuleTab' + this._tabNumber})
    },

    _setPermissions:function(data) {
        // Summary:
        //    Get the permission for the current user on the item.
        this._writePermissions  = true;
        this._deletePermissions = true;
        this._accessPermissions = false;
    },

    _setCustomFieldValues:function(fieldValues) {
        // Summary:
        //    Change the tab of the fields for don't overwrite the module tab.
        fieldValues['tab'] = fieldValues['tab'] * this._tabNumber;

        return fieldValues;
    },

    _getUploadIframePath:function(itemid) {
        // Summary:
        //    Set the URL for request the upload file.
        return phpr.webpath + 'index.php/' + this._module + '/index/fileForm'
            + '/nodeId/' + phpr.currentProjectId + '/id/' + this._id + '/field/' + itemid
            + '/parentId/'  + this._parentId + '/csrfToken/' + phpr.csrfToken;
    },

    _addBasicFields:function() {
        // Summary:
        //    Add some special fields.
    },

    _addModuleTabs:function(data) {
        // Summary:
        //    Add extra tabs.
    },

    _useHistoryTab:function() {
        // Summary:
        //    Return true or false if the history tab is used.
        return false;
    },

    _postRenderForm:function() {
        // Summary:
        //    User functions after render the form.
        // Description:
        //    Add a "new" buttom and hide the "delete" on new items.
        var newButton = dijit.byId('newButton-' + this._module);
        if (!newButton) {
            var newButton = new dijit.form.Button({
                id:        'newButton-' + this._module,
                label:     phpr.nls.get('New'),
                iconClass: 'add',
                type:      'button',
                style:     'display: inline;',
                disabled:  false
            });

            dojo.byId('buttons-' + this._module + '_div').firstChild.appendChild(newButton.domNode);
        }

        if (!this._eventForNew) {
            this._eventForNew = dojo.connect(newButton, "onClick",
                dojo.hitch(this, function() {
                    this.init(0, [], this._parentId);
                })
            );
            this._events.push('_eventForNew');
        };

        // Hide delete button on new items
        if (this._id < 1) {
            dijit.byId('deleteButton-' + this._module).domNode.style.display = 'none';
        }
    },

    _submitForm:function() {
        // Summary:
        //    Submit the forms.
        if (!this._prepareSubmission()) {
            return false;
        }

        phpr.send({
            url:       this._setFormUrl('save', this._id),
            content:   this._sendData,
            onSuccess: dojo.hitch(this, function(data) {
                new phpr.handleResponse('serverFeedback', data);
                if (data.type == 'success') {
                    dojo.publish(this._module + ".updateCacheData");
                }
            })
        });
    },

    _deleteForm:function() {
        // Summary:
        //    Delete an item.
        phpr.send({
            url:       this._setFormUrl('delete', this._id),
            onSuccess: dojo.hitch(this, function(data) {
                new phpr.handleResponse('serverFeedback', data);
                if (data.type == 'success') {
                    dojo.publish(this._module + ".updateCacheData");
                }
            })
        });
    }
});
