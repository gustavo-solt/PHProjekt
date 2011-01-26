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

dojo.provide("phpr.Component");

dojo.declare("phpr.Component", null, {
    main:   null,
    module: "",

    render:function(template, node, content) {
        var context = new dojox.dtl.Context(content);
        // Use the cached template
        var tplContent = __phpr_templateCache[template[0] + "." + template[1]];
        if (!__phpr_templateObjectCache[template[0] + "." + template[1]]) {
            __phpr_templateObjectCache[template[0] + "." + template[1]] = new dojox.dtl.Template(tplContent, true);
        }

        var html = __phpr_templateObjectCache[template[0] + "." + template[1]].render(context);
        context  = null;

        /*
if(dojo.isIE){
	dojo.addOnWindowUnload(function(){
		var cache = dijit._Templated._templateCache;
		for(var key in cache){
			var value = cache[key];
			if(typeof value == "object"){ // value is either a string or a DOM node template
				dojo.destroy(value);
			}
			delete cache[key];
		}
	});
}
        */

        if (node) {
            var dojoType = node.getAttribute('dojoType');
            if ((dojoType == 'dijit.layout.ContentPane') ||
                (dojoType == 'dijit.layout.BorderContainer') ) {

                if (dijit.byId(node.getAttribute('id'))) {
                    dijit.byId(node.getAttribute('id')).destroyDescendants(true);
                }

                dijit.byId(node.getAttribute('id')).set('content', html);
            } else {
                node.innerHTML = html;
                phpr.initWidgets(node);
            }
        } else {
            return html;
        }
    },

    publish:function(/*String*/ name, /*array*/args) {
        // summary:
        //    Publish the topic for the current module, its always prefixed with the module.
        // description:
        //    I.e. if the module name is "project" this.publish("open)
        //    will then publish the topic "project.open".
        // name: String
        //    The topic of this module that shall be published.
        // args: Array
        //    Arguments that should be published with the topic
        dojo.publish(phpr.module + "." + name, args);
    },

    subscribe:function(/*String*/name, /*String or null*/ context, /*String or function*/ method ) {
        // summary:
        //    Subcribe topic which was published for the current module, its always prefixed with the module.
        // description:
        //    I.e. if the module name is "project" this.subscribe("open)
        //    will then subscribe the topic "project.open".
        // name: String
        //    The topic of this module that shall be published.
        // args: Array
        //    Arguments that should be published with the topic
        dojo.subscribe(phpr.module + "." + name, context, method);
    }
});
