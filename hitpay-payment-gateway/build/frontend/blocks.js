!function(t){var e={};function n(r){if(e[r])return e[r].exports;var o=e[r]={i:r,l:!1,exports:{}};return t[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}n.m=t,n.c=e,n.d=function(t,e,r){n.o(t,e)||Object.defineProperty(t,e,{enumerable:!0,get:r})},n.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},n.t=function(t,e){if(1&e&&(t=n(t)),8&e)return t;if(4&e&&"object"==typeof t&&t&&t.__esModule)return t;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:t}),2&e&&"string"!=typeof t)for(var o in t)n.d(r,o,function(e){return t[e]}.bind(null,o));return r},n.n=function(t){var e=t&&t.__esModule?function(){return t.default}:function(){return t};return n.d(e,"a",e),e},n.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},n.p="",n(n.s=5)}([function(t,e){t.exports=window.wp.element},function(t,e){t.exports=window.wc.wcSettings},function(t,e){t.exports=window.wp.htmlEntities},function(t,e){t.exports=window.wp.i18n},function(t,e){t.exports=window.wc.wcBlocksRegistry},function(t,e,n){"use strict";n.r(e);var r=n(0),o=n(3),i=n(4),c=n(2),a=n(1);const u=()=>{const t=Object(a.getSetting)("hitpay_data",null);if(!t||"object"!=typeof t)throw new Error("Hitpay initialization data is not available");return t},l=(Object(a.getSetting)("hitpay_data",{}),Object(o.__)("Hitpay Payment Gateway","woo-gutenberg-products-block")),s=Object(c.decodeEntities)(u().title)||l,p=()=>Object(c.decodeEntities)(u().description||""),d=Object.entries(u().icons).map(t=>{let[e,{src:n,alt:r}]=t;return{id:e,src:n,alt:r}}),f={name:"hitpay",label:Object(r.createElement)(t=>{const{PaymentMethodLabel:e}=t.components;return Object(r.createElement)(e,{text:s})},null),content:Object(r.createElement)(p,null),edit:Object(r.createElement)(p,null),icons:d,canMakePayment:()=>!0,ariaLabel:s,supports:{features:u().supports}};Object(i.registerPaymentMethod)(f)}]);