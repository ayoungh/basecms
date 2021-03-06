"use strict";

var h = {

    /*
     * Cookie functions courtesy Scott Andrew and Peter-Paul Koch
     * http://www.quirksmode.org/js/cookies.html
     *
     */
     
    set_cookie: function (name, value, days) {
        
        var expires;
        
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days*24*60*60*1000));
            expires = "; expires="+date.toGMTString();
        } else
            expires = "";
            
        document.cookie = name+"="+value+expires+"; path=/";
        
    },
    
    read_cookie: function (name) {
        
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        
        for(var i=0; i < ca.length; i++) {
            
            var c = ca[i];
            while (c.charAt(0) == ' ') 
                c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) 
                return c.substring(nameEQ.length, c.length);
                
        }
        
        return null;
        
    },

    clear_cookie: function (name) {
        h.set_cookie(name, "", -1);
    },
    
    open: function (url, target) {
        h.store('base_'+target, url);
        window.open(url, target);
    },
    
    has_local_storage: (function() {
        try {
            return 'localStorage' in window && window['localStorage'] !== null;
        } catch (e) {
            return false;
        }    
    })(),
    
    /*
     * Local storage functions, falling back to cookies. Only useful for small
     * bits of data.
     *
     */
    store: function(key, data) {
        if (h.has_local_storage)
            window.localStorage.setItem(key, data);    
        else
            h.set_cookie(key, data, 0);
    },
    
    get: function(key) {
        if (h.has_local_storage)
            return window.localStorage.getItem(key);
        else
            return h.read_cookie(key);
    },
    
    clear: function(key) {
        if (h.has_local_storage)
            return window.localStorage.removeItem(key);
        else
            return h.clear_cookie(key);
    },
    
    requestFullscreenMethod: (function() {
        var el = document.createElement('div'),
            vnd = ['requestFullscreen', 'mozRequestFullScreen', 'webkitRequestFullscreen'],
            i;
        for (i=0;i<vnd.length;i++) {
            if (el[vnd[i]]) return vnd[i];    
        }
        return null;
    })(),
    
    cancelFullscreenMethod: (function() {
        var vnd = ['cancelFullscreen', 'mozCancelFullScreen', 'webkitCancelFullscreen'],
            i;
        for (i=0;i<vnd.length;i++) {
            if (document[vnd[i]]) return vnd[i];    
        }
        return null
    })(),
    
    fullscreenElement: (function() {
        var vnd = ['fullscreenElement', 'mozFullScreenElement', 'webkitFullscreenElement'],
            i;
        for (i=0;i<vnd.length;i++) {
            if (vnd[i] in document) return vnd[i];    
        }
        return null; 
    })(),
    
    lastFullscreenEl: null,
    
    // Unifying method for fullscreen functionality
    requestFullscreen: function(element) {
        if (h.requestFullscreenMethod && !document[h.fullscreenElement]) {
            element[h.requestFullscreenMethod](element.ALLOW_KEYBOARD_INPUT);
            h.lastFullscreenEl = element;
        } else if (h.requestFullscreenMethod && h.lastFullscreenEl) {
            document[h.cancelFullscreenMethod]();
        } else {
            alert('Your browser does not support viewing this in full screen.');    
        }
    }
    
};

$(document).on('mozfullscreenchange webkitfullscreenchange fullscreenchange', function(e) {
    $(h.lastFullscreenEl).toggleClass('fullscreen'); 
});

$(document).ready(function() {
    
    /*
     * Hotkeys!
     * 
     */
    function buttonTrigger($btn, e) {
        e.preventDefault();
        // Check to see if there is a menu open with an 'add' button first.
        $btn = $(window.top.frames[0].document.getElementsByClassName('btn add').item(0)) || $('.btn.add');
        if ($btn.length) {
            if ($btn.get(0).tagName == 'A' && $btn.attr('href'))
                window.top.open($btn.attr('href'), $btn.attr('target') || window.top.name);
            else
                $btn.click();
        }
    }
    
    if (!window.top.keypress) 
        window.top.keypress = function(e) {
            if (!(e.ctrlKey || e.metaKey)) return;
            switch (e.charCode) {
                case 110:
                    // CTRL-N: New
                    var $btn = $(window.top.frames[0].document.getElementsByClassName('btn add').item(0)) || $('.btn.add');
                    buttonTrigger($btn, e);
                    break;
                case 101:
                    // CTRL-S: Save
                    var $btn = $(window.top.frames[1].document.getElementsByClassName('btn save').item(0)) || $('.btn.save');
                    buttonTrigger($btn, e);
                    break;
                    break;
            }
        };
    
    $('body').on('keypress', window.top.keypress);
    
});