/**
 * $Id$
 *
 * @author Moxiecode
 * @copyright Copyright � 2004-2006, Moxiecode Systems AB, All rights reserved.
 */

/* Import plugin specific language pack */
tinyMCE.importPluginLanguagePack('fullscreen');

var TinyMCE_FullScreenPlugin = {
	getInfo : function() {
		return {
			longname : 'Fullscreen',
			author : 'Moxiecode Systems AB',
			authorurl : 'http://tinymce.moxiecode.com',
			infourl : 'http://tinymce.moxiecode.com/tinymce/docs/plugin_fullscreen.html',
			version : tinyMCE.majorVersion + "." + tinyMCE.minorVersion
		};
	},

	initInstance : function(inst) {
		if (!tinyMCE.settings['fullscreen_skip_plugin_css'])
			tinyMCE.importCSS(inst.getDoc(), tinyMCE.baseURL + "/plugins/fullscreen/css/content.css");
	},

	getControlHTML : function(cn) {
		switch (cn) {
			case "fullscreen":
				return tinyMCE.getButtonHTML(cn, 'lang_fullscreen_desc', '{$pluginurl}/images/fullscreen.gif', 'mceFullScreen');
		}

		return "";
	},

	execCommand : function(editor_id, element, command, user_interface, value) {
		// Handle commands
		switch (command) {
			case "mceFullScreen":
				this._toggleFullscreen(tinyMCE.getInstanceById(editor_id));
				return true;
		}

		// Pass to next handler in chain
		return false;
	},

	_toggleFullscreen : function(inst) {
		var ds = inst.getData('fullscreen'), editorContainer, tableElm, iframe, vp, cw, cd, re, w, h, si;

		cw = inst.getContainerWin();
		cd = cw.document;
		editorContainer = cd.getElementById(inst.editorId + '_parent');
		tableElm = editorContainer.firstChild;
		iframe = inst.iframeElement;
		re = cd.getElementById(inst.editorId + '_resize');

		if (!ds.enabled) {
			ds.parents = [];

			tinyMCE.getParentNode(tableElm.parentNode, function (n) {
				var st = n.style;

				if (n.nodeType == 1 && st) {
					if (n.nodeName == 'BODY')
						return true;

					ds.parents.push({
						el : n,
						position : st.position,
						left : st.left,
						top : st.top,
						right : st.right,
						bottom : st.bottom,
						width : st.width,
						height : st.height,
						margin : st.margin,
						padding : st.padding,
						border : st.border
					});

					st.position = 'static';
					st.left = st.top = st.margin = st.padding = st.border = '0';
					st.width = st.height = st.right = st.bottom = 'auto';
				}

				return false;
			});

			ds.oldOverflow = cd.body.style.overflow;
			cd.body.style.overflow = 'hidden';

			if (re)
				re.style.display = 'none';

			vp = tinyMCE.getViewPort(cw);

			ds.oldWidth = iframe.style.width ? iframe.style.width : iframe.offsetWidth;
			ds.oldHeight = iframe.style.height ? iframe.style.height : iframe.offsetHeight;
			ds.oldTWidth = tableElm.style.width ? tableElm.style.width : tableElm.offsetWidth;
			ds.oldTHeight = tableElm.style.height ? tableElm.style.height : tableElm.offsetHeight;

			// Handle % width
			if (ds.oldWidth && ds.oldWidth.indexOf)
				ds.oldTWidth = ds.oldWidth.indexOf('%') != -1 ? ds.oldWidth : ds.oldTWidth;

			tableElm.style.position = 'absolute';
			tableElm.style.zIndex = 1000;
			tableElm.style.left = tableElm.style.top = '0';

			tableElm.style.width = vp.width + 'px';
			tableElm.style.height = vp.height + 'px';

			if (tinyMCE.isRealIE) {
				iframe.style.width = vp.width + 'px';
				iframe.style.height = vp.height + 'px';

				// Calc new width/height based on overflow
				w = iframe.parentNode.clientWidth - (tableElm.offsetWidth - vp.width);
				h = iframe.parentNode.clientHeight - (tableElm.offsetHeight - vp.height);
			} else {
				w = iframe.parentNode.clientWidth;
				h = iframe.parentNode.clientHeight;
			}

			iframe.style.width = w + "px";
			iframe.style.height = h + "px";

			tinyMCE.selectElements(cd, 'SELECT,INPUT,BUTTON,TEXTAREA', function (n) {
				tinyMCE.addCSSClass(n, 'mceItemFullScreenHidden');

				return false;
			});

			tinyMCE.switchClass(inst.editorId + '_fullscreen', 'mceButtonSelected');
			ds.enabled = true;
		} else {
			si = 0;
			tinyMCE.getParentNode(tableElm.parentNode, function (n) {
				var st = n.style, s = ds.parents[si++];

				if (n.nodeName == 'BODY')
					return true;

				if (st) {
					st.position = s.position;
					st.left = s.left;
					st.top = s.top;
					st.bottom = s.bottom;
					st.right = s.right;
					st.width = s.width;
					st.height = s.height;
					st.margin = s.margin;
					st.padding = s.padding;
					st.border = s.border;
				}
			});

			ds.parents = [];

			cd.body.style.overflow = ds.oldOverflow ? ds.oldOverflow : '';

			if (re && tinyMCE.getParam("theme_advanced_resizing", false))
				re.style.display = 'block';

			tableElm.style.position = 'static';
			tableElm.style.zIndex = '';
			tableElm.style.width = '';
			tableElm.style.height = '';

			tableElm.style.width = ds.oldTWidth ? ds.oldTWidth : '';
			tableElm.style.height = ds.oldTHeight ? ds.oldTHeight : '';

			iframe.style.width = ds.oldWidth ? ds.oldWidth : '';
			iframe.style.height = ds.oldHeight ? ds.oldHeight : '';

			tinyMCE.selectElements(cd, 'SELECT,INPUT,BUTTON,TEXTAREA', function (n) {
				tinyMCE.removeCSSClass(n, 'mceItemFullScreenHidden');

				return false;
			});

			tinyMCE.switchClass(inst.editorId + '_fullscreen', 'mceButtonNormal');
			ds.enabled = false;
		}
	}
};

tinyMCE.addPlugin("fullscreen", TinyMCE_FullScreenPlugin);
