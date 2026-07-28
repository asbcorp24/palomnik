require('./bootstrap');

const bootstrap = require('bootstrap');
const maplibregl = require('maplibre-gl');
const { Html5Qrcode, Html5QrcodeScanner } = require('html5-qrcode');
const QRCode = require('qrcodejs');

window.bootstrap = bootstrap;
window.maplibregl = maplibregl.default || maplibregl;
window.Html5Qrcode = Html5Qrcode;
window.Html5QrcodeScanner = Html5QrcodeScanner;
window.QRCode = QRCode.default || QRCode;
