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

dojo.provide("phpr.Tree");

phpr.treePaths               = new Array();
phpr.treeLastProjectSelected = null;

dojo.declare("phpr.Tree", phpr.Component, {
    // Summary: This class is responsible for rendering the Tree of a default module
    url:      null,
    idName:   null,

    constructor:function() {
        this.setUrl();
        this.setId(null);
    },

    loadTree:function() {
        // Summary:
        //    Init the tree
        // Description:
        //    Init the tree if not exists,
        //    in the other case, select the current project,
        //    draw the breadcrum and fix the size
        if (!this.idName) {
            // Data of the tree
            phpr.DataStore.addStore({url: this.url});
            phpr.DataStore.requestData({url: this.url, processData: dojo.hitch(this, function() {
                if (!phpr.Tree.tree) {
                    phpr.Tree.tree = phpr.Tree.getTree();
                    phpr.Tree.tree.startup();
                    phpr.Tree.getNode().set('content', phpr.Tree.tree.domNode);
                    dojo.connect(phpr.Tree.tree, "onClick", dojo.hitch(this, "onItemClick"));
                    dojo.byId("navigation-container-title").innerHTML = phpr.nls.get('Projects');
                } else {
                    this.processDataDiff();
                }
                phpr.Tree.setId(phpr.Tree.tree.id);
                phpr.Tree.finishDraw();
            })});
        } else {
            this.finishDraw();
        }
    },

    finishDraw:function() {
        // Summary:
        //    Finish the draw process
        // Description:
        //    Fix width and select the current project
        this.checkTreeSize();
        this.drawBreadCrumb();
        this.selecteCurrent(phpr.currentProjectId);
    },

    getModel:function() {
        // Summary:
        //    Create a new tree model with a new store

        // Create the store
        var store          = new dojo.data.ItemFileWriteStore({});
        store.clearOnClose = true;
        store.data         = this.processData(dojo.clone(phpr.DataStore.getData({url: this.url})));

        // Create the model
        return new dijit.tree.ForestStoreModel({
            store: store,
            query: {parent: '1'}
        });
    },

    getTree:function() {
        // Summary:
        //    Create a new dijit.tree
        return new dijit.Tree({
            model:    this.getModel(),
            showRoot: false,
            persist:  false,
            _onNodeMouseEnter: function(node) {
                if (node.item.cut == 'true') {
                    dijit.showTooltip(node.item.longName, node.domNode);
                }
            },
            _onNodeMouseLeave: function(node) {
                if (node.item.cut == 'true') {
                    dijit.hideTooltip(node.domNode);
                }
            }
        }, document.createElement('div'));
    },

    getUrl:function() {
        // Summary:
        //    Return the url for get the tree
        return this.url;
    },

    setUrl:function() {
        // Summary:
        //    Set the url for get the tree
        this.url = phpr.webpath + 'index.php/Project/index/jsonTree';
    },

    getNode:function() {
        // Summary:
        //    Set the node to put the tree
        return dijit.byId("treeBox");
    },

    setId:function(id) {
        // Summary:
        //    Set the id of the widget
        this.idName = id;
    },

    updateData:function() {
        this.setId(null);
        phpr.DataStore.deleteData({url: this.url});
    },

    onItemClick:function(item) {
        // Summary:
        //    Publishes "changeProject" as soon as a tree Node is clicked
        if (!item) {
          item = [];
        }
        this.publish("changeProject", [item.id]);
    },

    selecteCurrent:function(id) {
        // Summary:
        //    Select the current projects and open all the parents
        if (phpr.treeLastProjectSelected != id) {
            // Remove last bold
            var node = this.getNodeByidentity(phpr.treeLastProjectSelected);
            if (node) {
                dojo.removeClass(node.rowNode, "selected");
            }

            if (id > 1) {
                // Expan the parents
                var _tree = this.tree;
                this.tree.model.store.fetchItemByIdentity({identity: id,
                    onItem:function(item) {
                        if (item) {
                            var paths = item.path.toString().split("\/");
                            for (var i in paths) {
                                if (parseInt(paths[i]) > 1) {
                                    phpr.Tree.tree._expandNode(phpr.Tree.getNodeByidentity(paths[i]));
                                }
                            }
                        }
                }});

                // Add new bold
                var node = this.getNodeByidentity(id);
                if (node) {
                    this.tree.focusNode(node);
                    dojo.addClass(node.rowNode, "selected");
                    phpr.treeLastProjectSelected = id;
                }
            }
        }
    },

    getNodeByidentity:function(identity) {
        // Summary:
        //    Return the node by identity
        var nodes = this.tree._itemNodesMap[identity];
        if (nodes && nodes.length){
            // Select the first item
            node = nodes[0];
        } else {
            node = nodes;
        }

        return node;
    },

    processData:function(data) {
        // Summary:
        //    Process the data for the tree
        // Description:
        //    Collect path and change the long names
        var width = dojo.byId('navigation-container').style.width.replace(/px/, "");
        for(var i in data.items) {
            var name  = data.items[i]['name'].toString();
            var depth = data.items[i]['path'].match(/\//g).length;
            if (depth > 5) {
                depth = 5;
            }
            var maxLength = Math.round((width / 11) - (depth - 1));
            data.items[i]['cut'] = false;
            if (name.length > maxLength) {
                data.items[i]['longName'] = name;
                data.items[i]['name']     = name.substr(0, maxLength) + '...';
                data.items[i]['cut']      = true;
            }
            phpr.treePaths[data.items[i]['id']] = data.items[i]['path'];
        }

        return data;
    },

    getParentId:function(id) {
        // Summary:
        //    Return the parent id of one project
        if (phpr.treePaths[id]) {
            var paths = phpr.treePaths[id].toString().split("\/").reverse();
            for (i in paths) {
                if (paths[i] > 0) {
                    return paths[i];
                }
            }
        }

        return 1;
    },

    checkTreeSize:function() {
        // Summary
        //    This avoids unwanted vertical scrollbar in the tree when general height is not too much
        var treeHeight = dojo.byId('treeBox').offsetHeight;
        if (treeHeight < 300) {
            dojo.byId('tree-navigation').style.height = '90%';
        }
    },

    drawBreadCrumb:function() {
        // Summary:
        //    Set the Breadcrumb with all the projects and the module
        if (!phpr.isGlobalModule(phpr.module)) {
            if (phpr.treeLastProjectSelected != phpr.currentProjectId || phpr.currentProjectId == 1) {
                var projects = new Array();
                this.tree.model.store.fetchItemByIdentity({identity: phpr.currentProjectId,
                    onItem:function(item) {
                        if (item) {
                            var paths = phpr.treePaths[phpr.currentProjectId].toString().split("\/");
                            for (var i in paths) {
                                if (paths[i] > 0 && paths[i] != phpr.currentProjectId) {
                                    phpr.Tree.tree.model.store.fetchItemByIdentity({identity: paths[i],
                                        onItem:function(item) {
                                            if (item) {
                                                projects.push({"id":   item.id,
                                                               "name": item.longName || item.name});
                                            }
                                    }});
                                }
                            }
                            projects.push({"id":   item.id,
                                           "name": item.longName || item.name});
                        }
                }});
                phpr.BreadCrumb.setProjects(projects);
            }
        } else {
            phpr.BreadCrumb.setProjects(projects);
        }
        phpr.BreadCrumb.setModule();
        phpr.BreadCrumb.draw();
    },

    fadeOut:function() {
        // Summary:
        //     Manage the visibility of the tree panel
        if (dojo.style("treeBox", "opacity") != 0.5) {
            dojo.style("treeBox", "opacity", 0.5);
        }
    },

    fadeIn:function() {
        // Summary:
        //     Manage the visibility of the tree panel
        if (dojo.style("treeBox", "opacity") != 1) {
            dojo.style("treeBox", "opacity", 1);
        }
    },

    processDataDiff:function() {
        // Summary:
        //    Process the new data for the tree
        // Description:
        //    Check for changes between the store and the new data.
        //    - Add new nodes
        //    - Edit existing nodes
        //    - Move nodes
        //    - Delete nodes
        var data    = this.processData(dojo.clone(phpr.DataStore.getData({url: this.url})));
        var newData = data.items;
        this.tree.model.store.fetch({
            queryOptions: {deep: true},
            onComplete: dojo.hitch(this, function(oldData) {
                for (var i = 0; i < oldData.length; i++) {
                    // Search for a change
                    var found = false;
                    for (var j = 0; j < newData.length; j++) {
                        if (newData[j]['id'] == oldData[i].id) {
                            if (newData[j]['name'] != oldData[i].name) {
                                // The name was changed
                                phpr.Tree.tree.model.store.fetchItemByIdentity({
                                    identity: newData[j].id,
                                    onItem:   dojo.hitch(this, function(item) {
                                        phpr.Tree.tree.model.store.setValue(item, 'name', newData[j].name);
                                        phpr.Tree.tree.model.store.setValue(item, 'cut', newData[j].cut);
                                        phpr.Tree.tree.model.store.save({});
                                    })
                                });
                            }

                            if (newData[j]['parent'] != oldData[i].parent) {
                                // The parent was changed
                                if (oldData[i].id > 1) {
                                    phpr.Tree.tree.model.store.fetchItemByIdentity({
                                        identity: newData[j].id,
                                        onItem:   dojo.hitch(this, function(item) {
                                            phpr.Tree.tree.model.store.fetchItemByIdentity({
                                                identity: oldData[i].parent,
                                                onItem:   dojo.hitch(this, function(from) {
                                                    phpr.Tree.tree.model.store.fetchItemByIdentity({
                                                        identity: newData[j].parent,
                                                        onItem:   dojo.hitch(this, function(to) {
                                                            phpr.Tree.tree.model.pasteItem(item, from, to, false);
                                                            phpr.Tree.tree.model.store.setValue(item,
                                                                'parent', newData[j].parent);
                                                            phpr.Tree.tree.model.store.setValue(item,
                                                                'path', newData[j].path);
                                                            phpr.Tree.tree.model.store.save({});
                                                        })
                                                    });
                                                })
                                            });
                                        })
                                    });
                                }
                            }

                            // Mark as exists
                            found = true;
                            newData[j]['exists'] = true;
                            break;
                        }
                    }

                    if (!found) {
                        // The node don't exists => delete
                        phpr.Tree.tree.model.store.deleteItem(oldData[i]);
                        phpr.Tree.tree.model.store.save({});
                    }
                }

                // Search for new items
                for (var j = 0; j < newData.length; j++) {
                    if (!newData[j]['exists']) {
                        this.tree.model.store.fetchItemByIdentity({
                            identity: newData[j].parent,
                            onItem:   dojo.hitch(this, function(item) {
				                phpr.Tree.tree.model.newItem({
				                   id:     newData[j].id,
					               name:   newData[j].name,
					               parent: newData[j].parent,
					               path:   newData[j].path,
					               cut:    newData[j].cut
                                }, item);
                                phpr.Tree.tree.model.store.save({});
                            })
                        });
                    }
                }
            })
        });
    }
});
