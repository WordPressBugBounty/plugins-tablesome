(()=>{function e(t){return(e="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(t)}function t(t,n){for(var i=0;i<n.length;i++){var o=n[i];o.enumerable=o.enumerable||!1,o.configurable=!0,"value"in o&&(o.writable=!0),Object.defineProperty(t,(void 0,a=function(t,n){if("object"!==e(t)||null===t)return t;var i=t[Symbol.toPrimitive];if(void 0!==i){var o=i.call(t,"string");if("object"!==e(o))return o;throw new TypeError("@@toPrimitive must return a primitive value.")}return String(t)}(o.key),"symbol"===e(a)?a:String(a)),o)}var a}var n="#csf-modal-tablesome-shortcode",i=function(){function e(){return function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e),e.instance?e.instance:(e.instance=this,this)}var i,o;return i=e,(o=[{key:"getInstance",value:function(){return void 0===this.instance&&(this.instance=this),this.instance}},{key:"init",value:function(){this.eventListeners()}},{key:"eventListeners",value:function(){var e=this;jQuery(n).on("change",".csf-modal-table .csf-modal-content .csf-fields .tablesome__fields--table_id",(function(){var t=jQuery(this).find(".csf-fieldset select[name*='table_id']").val();if(""==t)return alert(translation_strings.enter_table_id_alert),!1;e.sendRequest(t)}))}},{key:"sendRequest",value:function(e){var t=this;jQuery.get(tablesome_ajax_object.ajax_url,{action:"get_table_columns",nonce:tablesome_ajax_object.nonce,table_id:e,dataType:"JSON"},(function(e){if("failed"==e.status)return alert("Please enter the valid tablesome table id"),!1;var i=e.data,o=t.getExcludeColumnOptionsHTML(i),a=jQuery(n).find(".csf-modal-table .csf-modal-content .csf-fields .tablesome__fields--exclude_columns .csf-fieldset select[name='_pseudo']");a.empty().append(o),a.trigger("chosen:updated")}))}},{key:"getExcludeColumnOptionsHTML",value:function(e){return e.map((function(e){return"<option value="+e.id+">"+e.name+"</option>"})).join("")}}])&&t(i.prototype,o),Object.defineProperty(i,"prototype",{writable:!1}),e}();jQuery(document).on("ready",(function(){(new i).init()}))})();