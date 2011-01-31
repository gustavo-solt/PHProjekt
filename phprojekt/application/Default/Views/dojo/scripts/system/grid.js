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

dojo.provide("phpr.grid");
dojo.provide("phpr.grid.cells.Select");
dojo.provide("phpr.grid._View");
dojo.provide("phpr.Filter.ExpandoPane");

phpr.grid.formatDateTime = function(date) {
    if (!date || !String(date).match(/\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}/)) {
        return date;
    }
    var iso = String(date).replace(" ", "T"); // Make it a real date ISO string
    var dateObj = dojo.date.stamp.fromISOString(iso);
    return dojo.date.locale.format(dateObj, {formatLength:'short', selector:'dateTime'});
};

phpr.grid.formatTime = function(value) {
    var isoRegExp = /^(?:(\d{2})(\d{2})?)$/;
    var match = isoRegExp.exec(value);
    if (match) {
        match.shift();
        return match[0] + ':' + match[1];
    } else {
        return value;
    }
},

phpr.grid.formatUpload = function(value) {
    if (value.indexOf('|') > 0) {
        files = value.split('||');
        value = '';
        for (p in files) {
            if (p > 0) {
                value += ', ';
            }
            value += files[p].substring(files[p].indexOf('|') + 1, files[p].length);
        }
    }
    return value;
},

phpr.grid.formatIcon = function(value) {
    data = value.split('||');
    if (!data[1]) {
        data[1] = "";
    }

    return '<div class="' + data[0] + '" title="' + data[1] + '"></div>';
},

dojo.declare("phpr.grid.cells.Percentage", dojox.grid.cells._Widget, {
    // summary:
    //    Redefine the function to return the correct value
    // description:
    //    Redefine the function to return the correct value
    widgetClass: phpr.form.HorizontalSlider,

    getValue:function(inRowIndex) {
        return dojo.number.round(this.widget.get('value'), 1);
    },

    format:function(inRowIndex, inItem) {
        var f, i=this.grid.edit.info, d=this.get ? this.get(inRowIndex, inItem) : (this.value || this.defaultValue);
        if (this.editable && (this.alwaysEditing || (i.rowIndex==inRowIndex && i.cell==this))){
            return this.formatEditing(d, inRowIndex);
        } else {
            var v = dojo.number.round(d, 1);
            return (typeof v == "undefined" ? this.defaultValue : v);
        }
    }
});

dojo.declare("phpr.grid.cells.Select", dojox.grid.cells.Select, {
    // summary:
    //    Redefine the function to return the correct value
    // description:
    //    Redefine the function to return the correct value
    format:function(inRowIndex, inItem) {
        var f, i=this.grid.edit.info, d=this.get ? this.get(inRowIndex, inItem) : (this.value || this.defaultValue);
        if (this.editable && (this.alwaysEditing || (i.rowIndex==inRowIndex && i.cell==this))){
            return this.formatEditing(d, inRowIndex);
        } else {
            var v = '';
            for (var i=0, o; ((o=this.options[i]) !== undefined); i++){
                if (d == this.values[i]) {
                    v = o;
                }
            }
            return (typeof v == "undefined" ? this.defaultValue : v);
        }
    }
});

dojo.declare("phpr.grid.cells.DateTextBox", dojox.grid.cells.DateTextBox, {
    // summary:
    //    Redefine the function to work with iso format
    // description:
    //    Redefine the function to work with iso format
    widgetClass: phpr.form.DateTextBox,

    getValue:function(inRowIndex) {
        var date = this.widget.get('value');
        var day = date.getDate();
        if (day < 10) {
            day = '0'+day;
        }
        var month = (date.getMonth()+1);
        if (month < 10) {
            month = '0'+month
        }
        return date.getFullYear() + '-' + month + '-' + day;
    },

    setValue:function(inRowIndex, inValue) {
        if (this.widget) {
            var parts = inValue.split("-");
            var year  = parts[0];
            var month = parts[1]-1;
            var day   = parts[2];
            this.widget.set('value', new Date(year, month, day));
        } else {
            this.inherited(arguments);
        }
    },

    getWidgetProps:function(inDatum) {
        var parts = inDatum.split("-");
        var year  = parts[0];
        var month = parts[1]-1;
        var day   = parts[2];
        return dojo.mixin(this.inherited(arguments), {
            value: new Date(year, month, day)
        });
    }
});

dojo.declare("phpr.grid.cells.Text", dojox.grid.cells._Widget, {
    setValue:function(inRowIndex, inValue) {
        if (this.widget && this.widget.setValue) {
            this.widget.set('value', inValue);
        } else {
            this.inherited(arguments);
        }
    },

    format:function(inRowIndex, inItem) {
        var f, i=this.grid.edit.info, d=this.get ? this.get(inRowIndex, inItem) : (this.value || this.defaultValue);
        if (this.editable && (this.alwaysEditing || (i.rowIndex==inRowIndex && i.cell==this))){
            return this.formatEditing(d, inRowIndex);
        } else {
            if (d) {
                var maxLength = (this.getHeaderNode().offsetWidth - 21) / 7;
                var output    = d.toString();

                if (output.length > maxLength) {
                    output = output.substr(0, maxLength) + '...';
                }
                output = output.replace(/&/g, "&amp;");
                output = output.replace(/</g, "&lt;");
                output = output.replace(/>/g, "&gt;");
            } else {
                var output = '';
            }

            return output;
        }
    },

    attachWidget:function(inNode, inDatum, inRowIndex){
        // Add fix for IE
        if (dojo.isIE) {
            this.widget.domNode.unselectable = 'off';
        }
        this.inherited(arguments);
    }
});

dojo.declare("phpr.grid.cells.Textarea", phpr.grid.cells.Text, {
    format:function(inRowIndex, inItem) {
        var f, i=this.grid.edit.info, d=this.get ? this.get(inRowIndex, inItem) : (this.value || this.defaultValue);
        if (this.editable && (this.alwaysEditing || (i.rowIndex==inRowIndex && i.cell==this))){
            return this.formatEditing(d, inRowIndex);
        } else {
            var maxLength = (this.getHeaderNode().offsetWidth - 21) / 7;
            var output    = this.strip_tags(d);
            if (output.length > maxLength) {
                output = output.substr(0, maxLength) + '...';
            }
            output = output.replace(/&/g, "&amp;");
            output = output.replace(/</g, "&lt;");
            output = output.replace(/>/g, "&gt;");

            return output;
        }
    },

    strip_tags:function(str, allowed_tags) {
        // Summary
        //    Strip tags function by Kevin van Zonneveld (http://kevin.vanzonneveld.net) improved by Luke Godfrey
        // Example of use
        //    strip_tags('<p>Kevin</p> <br /><b>van</b> <i>Zonneveld</i>', '<i><b>');
        //    Returns: 'Kevin <b>van</b> <i>Zonneveld</i>'

        var key = '', allowed = false;
        var matches = [];
        var allowed_array = [];
        var allowed_tag = '';
        var i = 0;
        var k = '';
        var html = '';

        var replacer = function(search, replace, str) {
            return str.split(search).join(replace);
        };

        // Build allowes tags associative array
        if (allowed_tags) {
            allowed_array = allowed_tags.match(/([a-zA-Z]+)/gi);
        }

        str += '';

        // Match tags
        matches = str.match(/(<\/?[\S][^>]*>)/gi);

        // Go through all HTML tags
        for (key in matches) {
            if (isNaN(key)) {
                // IE7 Hack
                continue;
            }

            // Save HTML tag
            html = matches[key].toString();

            // Is tag not in allowed list? Remove from str!
            allowed = false;

            // Go through all allowed tags
            for (k in allowed_array) {
                // Init
                allowed_tag = allowed_array[k];
                i = -1;

                if (i != 0) { i = html.toLowerCase().indexOf('<'+allowed_tag+'>');}
                if (i != 0) { i = html.toLowerCase().indexOf('<'+allowed_tag+' ');}
                if (i != 0) { i = html.toLowerCase().indexOf('</'+allowed_tag)   ;}

                // Determine
                if (i == 0) {
                    allowed = true;
                    break;
                }
            }

            if (!allowed) {
                str = replacer(html, "", str); // Custom replace. No regexing
            }
        }
        return str;
    }
});

dojo.declare("phpr.grid.cells.Time", dojox.grid.cells._Widget, {
    setValue:function(inRowIndex, inValue) {
        inValue = phpr.Date.getIsoTime(inValue);
        if (this.widget && this.widget.setValue) {
            this.widget.set('value', inValue);
        } else {
            this.inherited(arguments);
        }
    },

    getValue:function(inRowIndex) {
        var value = this.widget.get('value');
        return phpr.Date.getIsoTime(value);
    },

    format:function(inRowIndex, inItem) {
        var f, i=this.grid.edit.info, d=this.get ? this.get(inRowIndex, inItem) : (this.value || this.defaultValue);
        if (this.editable && (this.alwaysEditing || (i.rowIndex==inRowIndex && i.cell==this))){
            var d = phpr.Date.getIsoTime(d);
            return this.formatEditing(d, inRowIndex);
        } else {
            return phpr.Date.getIsoTime(d);
        }
    }
});

var dgc = dojox.grid.cells;
dgc.DateTextBox.markupFactory = function(node, cell){
    dgc._Widget.markupFactory(node, cell);
};

dojo.declare('phpr.grid._View', [dojox.grid._View], {
    // Summary
    //    Extend the normal grid view
    // Description
    //    Add a div after the grid for allow multiple actions
    templateString: '<div class="dojoxGridView" wairole="presentation">\r\n\t<div class="dojoxGridHeader" '
        + 'dojoAttachPoint="headerNode" wairole="presentation">\r\n\t\t<div dojoAttachPoint="headerNodeContainer" '
        + 'style="width:9000em" wairole="presentation">\r\n\t\t\t<div dojoAttachPoint="headerContentNode" '
        + 'wairole="row"></div>\r\n\t\t</div>\r\n\t</div>\r\n\t<input type="checkbox" class="dojoxGridHiddenFocus" '
        + 'dojoAttachPoint="hiddenFocusNode" wairole="presentation" />\r\n\t<input type="checkbox" '
        + 'class="dojoxGridHiddenFocus" wairole="presentation" />\r\n\t<div class="dojoxGridScrollbox" '
        + 'dojoAttachPoint="scrollboxNode" wairole="presentation">\r\n\t\t<div class="dojoxGridContent" '
        + 'dojoAttachPoint="contentNode" hidefocus="hidefocus" wairole="presentation"></div>\r\n\t\t'
        + '<div dojoAttachPoint="gridActions" class="gridActions" style="vertical-align: baseline;">'
        + '</div>\r\n\t</div>\r\n</div>\r\n',

    doStyleRowNode:function(inRowIndex, inRowNode) {
        // Summary
        //    Change the style of the row
        // Description
        //    Marck as checked the checked rows
        if (inRowNode) {
            var row = this.grid.rows.prepareStylingRow(inRowIndex, inRowNode);
            this.grid.onStyleRow(row);
            var item = this.grid.getItem(inRowIndex);
            if (item) {
                if (item['gridComboBox'] == "true") {
                    row.customClasses += " dojoxGridRowChecked";
                }
            }
            this.grid.rows.applyStyles(row);
        }
    },

    doHeaderEvent:function(e) {
        // Summary
        //    Re-write the function for remove effect on the action bar
        // Description
        //    Re-write the function for remove effect on the action bar
        if(this.header.decorateEvent(e)){
            if (e.type == 'click') {
                dojo.style(this.gridActions, 'display', 'none');
                this.grid.onHeaderEvent(e);
                dojo.style(this.gridActions, 'display', 'inline');
            } else {
                this.grid.onHeaderEvent(e);
            }
        }
    },

	renderHeader:function() {
	    if (this.headerContentNode.innerHTML == '') {
            this.headerContentNode.innerHTML = this.header.generateHtml(this._getHeaderContent);
	    }
		if(this.flexCells){
			this.contentWidth = this.getContentWidth();
			this.headerContentNode.firstChild.style.width = this.contentWidth;
		}
		dojox.grid.util.fire(this, "onAfterRow", [-1, this.structure.cells, this.headerContentNode]);
	}
});

dojo.declare('phpr.Filter.ExpandoPane', [dojox.layout.ExpandoPane], {
    _startupSizes:function() {
        // Summary
        //    Re-write the function for allow height 0
        // Description
        //    Re-write the function for allow height 0
        this._container   = this.getParent();
        this._titleHeight = dojo.marginBox(this.titleWrapper).h;
        this._closedSize  = 0;

        this._currentSize = dojo.contentBox(this.domNode);
        this._showSize    = this._currentSize["h"];
        this._setupAnims();

        if (this.startExpanded) {
            this._showing = true;
        } else {
            this._showing = false;
            this._hideWrapper();
            this._hideAnim.gotoPercent(99, true);
        }

        this._hasSizes = true;
    },

    resize:function(/* Object? */psize) {
        // Summary
        //    Re-write the function for allow height 0
        // Description
        //    Re-write the function for allow height 0
        if (!this._hasSizes) {
            this._startupSizes(psize);
        }

        var size = (psize && psize.h) ? psize : dojo.marginBox(this.domNode);

        this._contentBox = {
            w: size.w || dojo.marginBox(this.domNode).w,
            h: size.h - 26
        };

        if (this._contentBox.h < 0) {
            this._contentBox.h = 0;
        }
        dojo.style(this.containerNode, "height", this._contentBox.h + "px");
        dojo.style(this.containerNode, "overflowX", "hidden");
        this._layoutChildren();
    }
});

dojo.extend(dojox.grid.DataGrid, {
    doclick:function(e) {
        dojo.publish(phpr.module + '.gridProxy', ['doClick', e]);
    },

    dodblclick:function(e) {
        dojo.publish(phpr.module + '.gridProxy', ['doDblClick', e]);
    },

    onRowClick:function(e) {
    }
});

dojo.declare("phpr.Default.GridLayout", null, {
    // Summary:
    //    Class for set the layout for each field.
    // Description:
    //    This class set the diferent settings for each field in the grid.
    gridLayout: [],

    _maxLength: 0,
    _opts:      [],
    _vals:      [],
    _max:       0,

    constructor:function() {
        this.gridLayout  = [];
        this._maxLength  = 0;
        this._opts       = [];
        this._vals       = [];
        this._max        = 0;
    },

    addCheckField:function() {
        var meta = {
            label:    " ",
            key:      'gridComboBox',
            readOnly: false
        }
        var field       = this._initData(meta);
        field.type      = dojox.grid.cells.Bool;
        field.width     = "20px";
        field.filterkey = null;

        this.gridLayout['gridComboBox'] = field;
    },

    addEditField:function() {
        var meta = {
            label:    " ",
            key:      'gridEdit',
            readOnly: true
        }
        var field       = this._initData(meta);
        field.width     = "20px";
        field.styles    = "vertical-align: middle;";
        field.formatter = 'phpr.grid.formatIcon';
        field.filterkey = null;

        this.gridLayout['gridEdit'] = field;
    },

    addIdField:function() {
        var meta = {
            label:    "ID",
            key:      'id',
            readOnly: true
        }
        var field         = this._initData(meta);
        field.width       = "40px";
        field.styles      = "text-align: right;";
        field.filterKey   = '_fixedId';

        this.gridLayout['gridId'] = field;
    },

    addModuleFields:function(meta) {
        for (var i = 0; i < meta.length; i++) {
            var data = this._initData(meta[i]);
            switch(meta[i]["type"]) {
                case 'selectbox':
                    this._setRangeValues(meta[i]);
                    data.styles        = "text-align: center;";
                    data.type          = phpr.grid.cells.Select;
                    data.width         = (this._maxLength * 8) + 'px';
                    data.options       = this._opts;
                    data.values        = this._vals;
                    data.filterType    = 'selectbox';
                    data.filterOptions = meta[i]['range'];

                    this.gridLayout[data.field] = data;
                    break;

                case 'date':
                    data.width         = '90px';
                    data.styles        = "text-align: center;";
                    data.type          = phpr.grid.cells.DateTextBox;
                    data.promptMessage = 'yyyy-MM-dd';
                    data.constraint    = {formatLength: 'short', selector: "date", datePattern:'yyyy-MM-dd'};
                    data.filterType    = 'date';

                    this.gridLayout[data.field] = data;
                    break;

                case 'datetime':
                    var dateData           = dojo.clone(data);
                    dateData.name          = dateData.name + ' (' + phpr.nls.get('Date') + ')';
                    dateData.field         = dateData.field + '_forDate';
                    dateData.width         = '90px';
                    dateData.styles        = "text-align: center;";
                    dateData.type          = phpr.grid.cells.DateTextBox;
                    dateData.promptMessage = 'yyyy-MM-dd';
                    dateData.constraint    = {formatLength: 'short', selector: "date", datePattern:'yyyy-MM-dd'};
                    dateData.filterType    = 'date';

                    this.gridLayout[dateData.field] = dateData;

                    var timeData        = dojo.clone(data);
                    timeData.name       = timeData.name + ' (' + phpr.nls.get('Hour') + ')';
                    timeData.field      = timeData.field + '_forTime';
                    timeData.width      = '60px';
                    timeData.styles     = 'text-align: center;';
                    timeData.type       = phpr.grid.cells.Time;
                    timeData.filterType = 'time';

                    this.gridLayout[timeData.field] = timeData;
                    break;

                case 'percentage':
                    data.width  = '90px';
                    data.styles = 'text-align: center;';
                    data.type   = phpr.grid.cells.Percentage;

                    this.gridLayout[data.field] = data;
                    break;

                case 'time':
                    date.width      = '60px';
                    data.styles     = 'text-align: center;';
                    data.type       = phpr.grid.cells.Time;
                    data.filterType = 'time';

                    this.gridLayout[data.field] = data;
                    break;

                case 'upload':
                    data.styles    = 'text-align: center;';
                    data.type      = dojox.grid.cells._Widget;
                    data.formatter = phpr.grid.formatUpload;
                    data.editable  = false;

                    this.gridLayout[data.field] = data;
                    break;

                case 'display':
                    // Has it values for translating an Id into a descriptive String?
                    if (meta[i]['range'][0] != undefined) {
                        this._setRangeValues(meta[i]);
                        data.styles        = "text-align: center;";
                        data.type          = phpr.grid.cells.Select;
                        data.width         = (this._maxLength * 8) + 'px';
                        data.options       = this._opts;
                        data.values        = this._vals;
                        data.filterType    = 'selectbox';
                        data.filterOptions = meta[i]['range'];
                        data.editable      = false;
                    } else {
                        data.styles        = "text-align: center;";
                        data.type          = phpr.grid.cells.Text;
                        data.editable      = false;
                    }

                    this.gridLayout[data.field] = data;
                    break;

                case 'text':
                    data.type = phpr.grid.cells.Text;

                    this.gridLayout[data.field] = data;
                    break;

                case 'textarea':
                    data.type     = phpr.grid.cells.Textarea;
                    data.editable = false;

                    this.gridLayout[data.field] = data;
                    break;

                case 'rating':
                    this._setRatingValues(meta[i]);
                    data.styles        = "text-align: center;";
                    data.type          = phpr.grid.cells.Select;
                    data.width         = (this._maxLength * 8) + 'px';
                    data.options       = this._opts;
                    data.values        = this._vals;
                    data.filterType    = 'rating';
                    data.filterOptions = meta[i]['range'];
                    data.editable      = false;
                    data.filterOptions = this._max;

                    this.gridLayout[data.field] = data;
                    break;

                default:
                    this.gridLayout[data.field] = data;
                    break;
            }
        }
    },

    addExtraField:function(key) {
        var meta = {
            label:    " ",
            key:      key,
            readOnly: true
        };

        var field       = this._initData(meta);
        field.width     = "20px";
        field.styles    = "vertical-align: middle";
        field.filterkey = null;

        this.gridLayout[key] = field;
    },

    _initData:function(meta) {
        return {
            name:          meta['label'],
            field:         meta['key'],
            styles:        "",
            type:          dojox.grid.cells.Cell,
            width:         'auto',
            options:       null,
            values:        null,
            promptMessage: null,
            constraint:    null,
            formatter:     null,
            editable:      (meta['readOnly']) ? false : true,
            filterkey:     meta['key'],
            filterLabel:   meta['label'],
            filterType:    'text',
            filterOptions: null
        };
    },

    _setRangeValues:function(data) {
        this._opts      = [];
        this._vals      = [];
        this._maxLength = data['key'].length;
        for (var j in data['range']){
            this._vals.push(data['range'][j]['id']);
            this._opts.push(data['range'][j]['name']);
            if (data['range'][j]['name'].length > this._maxLength) {
                this._maxLength = data['range'][j]["name"].length;
            }
        }
    },

    _setRatingValues:function(data) {
        this._max       = parseInt(data['range']['id']);
        this._opts      = [];
        this._vals      = [];
        this._maxLength = data['key'].length;
        for (var j = 1; j <= this._max; j++){
            this._vals.push(j);
            this._opts.push(j);
        }
    }
});
