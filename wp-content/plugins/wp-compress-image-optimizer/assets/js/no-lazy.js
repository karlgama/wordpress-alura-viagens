var regularImages = [];
var pictureTag = [];
var active;
var activeRegular;
var activeBG = false;
var img_count = 1;
var browserWidth;
var mobileWidth;
var forceWidth = 0;
var jsDebug = 0;
var isMobile = false;

var wpc_u = window.location.href;
var wpc_c = document.images.length;
var wpc_s = false;
var wpc_z = wpc_vars.zoneName;
var wpc_d = {wpc_c: wpc_c, wpc_u: wpc_u, wpc_z: wpc_z};

document.addEventListener("visibilitychange", function logData() {
    if (document.visibilityState === "hidden" && wpc_s === false) {
        navigator.sendBeacon("https://cdn.zapwp.net/hello", JSON.stringify(wpc_d));
        wpc_s = true
    }
});
window.addEventListener("beforeunload", function logData() {
    if (wpc_s === false) {
        navigator.sendBeacon("https://cdn.zapwp.net/hello", JSON.stringify(wpc_d));
        wpc_s = true
    }
});
window.addEventListener("pagehide", function logData() {
    if (wpc_s === false) {
        navigator.sendBeacon("https://cdn.zapwp.net/hello", JSON.stringify(wpc_d));
        wpc_s = true
    }
});

function checkMobile() {
    if (/Android|webOS|iPhone|iPad|Mac|Macintosh|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth <= 680) {
        isMobile = true;
        mobileWidth = window.innerWidth;
    }
}

checkMobile();

var WPCgetParents = function (elem) {

    // Set up a parent array
    var parents = [];

    // Push each parent element to the array
    for (; elem && elem !== document; elem = elem.parentNode) {
        if (elem.childElementCount > 1) {
            break;
        }
        else {
            parents.push(elem);
        }
    }

    // Return our parent array
    return parents;

};

function load() {
    browserWidth = window.innerWidth;
    regularImages = [].slice.call(document.querySelectorAll("img"));
    pictureTag = [].slice.call(document.querySelectorAll("picture.wps-ic-picture-tag"));
    active = false;
    activeRegular = false;
    regularLoad();
    pictureLoad();
    //findSliderImage();
    //findBGImage();
}

var bgs = [];

function findSliderImage() {
    if (activeBG === false) {
        activeBG = true;
        var bgElement = '';
        var bgUrl = '';
        var bgWidth = 1;
        var newBgURL = '';
        bgs = document.querySelectorAll('li.lslide');

        for (i = 0; i < bgs.length; ++i) {
            bgElement = bgs[i];

            if (bgElement.dataset.scanned == 'true') {
                continue;
            }

            bgElement.setAttribute('data-scanned', 'true');

            if (typeof bgElement.dataset.thumb !== 'undefined' && bgElement.dataset.thumb != '') {
                // LazyLoad Things
                image_parent_type = bgElement.parentNode.nodeName.toLowerCase();

                if (image_parent_type == 'a') {
                    image_parent = bgElement.parentNode.parentElement;
                }
                else {
                    image_parent = bgElement.parentNode;
                }

                parent_style = window.getComputedStyle(image_parent);
                parent_width = Math.round(parseInt(parent_style.width));

                if (isNaN(parent_width)) {
                    image_parent = image_parent.parentNode;
                    parent_style = window.getComputedStyle(image_parent);
                    parent_width = Math.round(parseInt(parent_style.width));
                }

                if (isNaN(parent_width) || parent_width < 1) {
                    bgWidth = 1;
                }
                else {
                    bgWidth = parent_width;
                }

                bgUrl = bgElement.dataset.thumb;
                //bgUrl = bgUrl.substr(bgUrl.indexOf("url") + 4, bgUrl.lastIndexOf(")") - (bgUrl.indexOf("url") + 4) );

                bgWidth = Math.round(parseInt(bgWidth * 1.20));
                newBgURL = bgUrl.replace(/w:(\d{1,5})/g, 'w:' + bgWidth);

                bgElement.setAttribute('data-thumb', newBgURL);
            }
        }

        activeBG = false;
    }
}


if (wpc_vars.js_debug == 'true') {
    jsDebug = 1;
    console.log('JS Debug is Enabled');
}

var isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
if (isSafari) {
    wpc_vars.webp_enabled = 'false';
    if (jsDebug) {
        console.log('Safari Disable WebP');
    }
}
if (jsDebug) {
    console.log('Safari: ' + isSafari);
}

function pictureLoad() {
    pictureTag.forEach(function (pictureImage) {

        imgWidth = 0;
        var children = pictureImage.children;
        var pictureParent = WPCgetParents(pictureImage.parentNode);

        var last = Object.keys(pictureParent)[pictureParent.length - 1];
        pictureParent = Object.values(pictureParent)[last];

        parent_style = window.getComputedStyle(pictureParent);
        var widthIsPercent = parent_style.width.indexOf("%") > -1;

        if (widthIsPercent) {
            pictureParent = pictureParent.parentNode;
            parent_style = window.getComputedStyle(pictureParent);
        }

        var widthIsPercent = parent_style.width.indexOf("%") > -1;
        if (widthIsPercent) {
            parent_width = 1;
        }
        else {
            parent_width = Math.round(parseInt(parent_style.width));
        }

        if ((parent_width !== 0 && typeof parent_width !== 'undefined')) {
            // We found a great image size, use it
            imgWidth = parent_width;
        }
        else {
            imgWidth = 1;
        }

        for (var i = 0; i < children.length; i++) {
            var srcset = children[i].srcset;
            var src = children[i].src;
            if (srcset) {

                newApiURL = children[i].srcset;
                //newApiURL = newApiURL.replace(/w:(\d{1,5})/g, 'w:' + imgWidth);

                if ((window.devicePixelRatio >= 2 && wpc_vars.retina_enabled == 'true') || wpc_vars.force_retina == 'true') {
                    newApiURL = newApiURL.replace(/r:0/g, 'r:1');

                    if (jsDebug) {
                        console.log('Retina set to True');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/r:1/g, 'r:0');

                    if (jsDebug) {
                        console.log('Retina set to False');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }

                }

                if (wpc_vars.webp_enabled == 'true' && isSafari == false) {
                    newApiURL = newApiURL.replace(/wp:0/g, 'wp:1');

                    if (jsDebug) {
                        console.log('WebP set to True');
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/wp:1/g, 'wp:0');

                    if (jsDebug) {
                        console.log('WebP set to False');
                    }

                }

                if (wpc_vars.exif_enabled == 'true') {
                    newApiURL = newApiURL.replace(/exif:false/g, 'exif:true');
                }
                else {
                    newApiURL = newApiURL.replace(/\/exif:true/g, '');
                    newApiURL = newApiURL.replace(/\/exif:false/g, '');
                }

                children[i].srcset = newApiURL;
            }
            if (src) {

                newApiURL = children[i].src;
                newApiURL = newApiURL.replace(/w:(\d{1,5})/g, 'w:' + imgWidth);

                if ((window.devicePixelRatio >= 2 && wpc_vars.retina_enabled == 'true') || wpc_vars.force_retina == 'true') {
                    newApiURL = newApiURL.replace(/r:0/g, 'r:1');

                    if (jsDebug) {
                        console.log('Retina set to True');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/r:1/g, 'r:0');

                    if (jsDebug) {
                        console.log('Retina set to False');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }

                }

                if (wpc_vars.webp_enabled == 'true' && isSafari == false) {
                    newApiURL = newApiURL.replace(/wp:0/g, 'wp:1');

                    if (jsDebug) {
                        console.log('WebP set to True');
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/wp:1/g, 'wp:0');

                    if (jsDebug) {
                        console.log('WebP set to False');
                    }

                }

                if (wpc_vars.exif_enabled == 'true') {
                    newApiURL = newApiURL.replace(/exif:false/g, 'exif:true');
                }
                else {
                    newApiURL = newApiURL.replace(/\/exif:true/g, '');
                    newApiURL = newApiURL.replace(/\/exif:false/g, '');
                }

                children[i].src = newApiURL;
            }
        }


    });
}

function regularLoad() {
    if (activeRegular === false) {
        activeRegular = true;

        regularImages.forEach(function (Image) {

            if (Image.classList.contains('wps-ic-loaded')) {
                return;
            }

            hasWidth = false;
            hasHeight = false;
            imageExtension = '';

            if (typeof Image.dataset.src !== 'undefined' && Image.dataset.src != '') {
                imageExtension = Image.dataset.src.split('.').pop();
            }
            else if (typeof Image.src !== 'undefined' && Image.src != '') {
                imageExtension = Image.src.split('.').pop();
            }

            if (imageExtension !== '') {
                if (imageExtension !== 'jpg' && imageExtension !== 'jpeg' && imageExtension !== 'gif' && imageExtension !== 'png' && imageExtension !== 'svg' && imageExtension !== 'webp') {
                    return;
                }
            }

            if (wpc_vars.speed_test == '1') {

                if (typeof Image.getAttribute('width') !== 'undefined' && Image.getAttribute('width') !== 'null' && Image.getAttribute('width') > 0) {
                    hasWidth = true;
                }

                if (typeof Image.getAttribute('height') !== 'undefined' && Image.getAttribute('height') !== 'null' && Image.getAttribute('height') > 0) {
                    hasHeight = true;
                }

                if (!hasWidth && !hasHeight) {
                    Image.setAttribute('width', Image.width);
                    Image.setAttribute('height', Image.height);
                }

                if (img_count >= 6) {
                    return;
                }
                else {
                    forceWidth = 320;
                }

                img_count++;
            }

            imageStyle = window.getComputedStyle(Image);
            ImageWidthPreloaded = Math.round(parseInt(imageStyle.width));

            image_parent = WPCgetParents(Image);

            if (jsDebug) {
                console.log('lazyImage parent:');
                console.log(image_parent);
                console.log(image_parent.length);
                console.log(Object.keys(image_parent)[image_parent.length - 1]);
                console.log('--lazyImage parent end--');
            }

            var last = Object.keys(image_parent)[image_parent.length - 1];
            image_parent = Object.values(image_parent)[last];

            parent_style = window.getComputedStyle(image_parent);
            var widthIsPercent = parent_style.width.indexOf("%") > -1;

            if (jsDebug) {
                console.log('Parent from wcgetparents:');
                console.log(parent_style);
                console.log(parent_style.width);
                console.log('Is Percent: ' + widthIsPercent);
            }

            if (widthIsPercent) {
                image_parent = image_parent.parentNode;
                parent_style = window.getComputedStyle(image_parent);
            }

            widthIsPercent = parent_style.width.indexOf("%") > -1;
            if (widthIsPercent) {
                parent_width = 1;
            }
            else {
                parent_width = Math.round(parseInt(parent_style.width));
            }

            if (jsDebug) {
                console.log(widthIsPercent);
                console.log(image_parent);
                console.log(parent_style);
                console.log('Parent Width: ' + parent_style.width);
                console.log('--lazyImage parent end--');
            }

            imageIsZoom = false;
            imageIsLogo = false;

            imageClass = [].slice.call(Image.classList);
            imageClass = imageClass.join(" ");
            imageIsZoom = imageClass.toLowerCase().includes("zoom");
            imageIsLogoClass = imageClass.toLowerCase().includes("logo");

            if (jsDebug) {
                console.log('Zoom: ' + imageIsZoom);
                console.log(Image);
            }

            if ((parent_width !== 0 && typeof parent_width !== 'undefined')) {

                // Check image width attributes
                imageWidthNatural = Image.width;

                if (parent_width > imageWidthNatural) {
                    parent_width = imageWidthNatural;
                }

                // We found a great image size, use it
                imgWidth = parent_width;
                if (jsDebug) {
                    console.log('Set width of: ' + imgWidth);
                }

            }
            else {
                if (jsDebug) {
                    console.log('Not Set width of: ' + imgWidth);
                }
                imageWidth = ImageWidthPreloaded;

                imageFilename = Image.dataset.src;
                if (typeof imageFilename == 'undefined') {
                    imageFilename = Image.src;
                }

                imageIsLogoSrc = imageFilename.toLowerCase().includes("logo");

                imageWidthNatural = Image.dataset.width;
                imageHeightNatural = Image.dataset.height;

                if (imageClass.toLowerCase().includes("no-wpc-load")) {
                    return;
                }

                if (imageIsLogoClass || imageIsLogoSrc) {
                    imageIsLogo = true;
                }

                if (jsDebug) {
                    console.log('Image logo: ' + imageIsLogo);
                    console.log('Image natural width: ' + imageWidthNatural);
                }

                if (typeof imageIsLogo == 'undefined' || !imageIsLogo) {
                    imageIsLogo = false;

                    if (wpc_vars.adaptive_enabled == '1' || wpc_vars.adaptive_enabled == 'true') {
                        if (!imageWidth || imageWidth == 0 || typeof imageWidth == 'undefined') {

                            if (jsDebug) {
                                console.log('Image Width Preloaded ' + imageWidth);
                            }

                            // LazyLoad Things
                            image_parent_type = Image.parentNode.nodeName.toLowerCase();

                            if (image_parent_type == 'a') {
                                image_parent = Image.parentNode.parentElement;
                            }
                            else {
                                image_parent = Image.parentNode;
                            }

                            parent_style = window.getComputedStyle(image_parent);


                            if (parent_style.width == 'auto') {
                                image_parent = image_parent.parentNode;
                                parent_style = window.getComputedStyle(image_parent);
                            }

                            parent_width = Math.round(parseInt(parent_style.width));
                            imgWidth = Math.round(parseInt(parent_style.width));

                            if (jsDebug) {
                                console.log('Image Width set to: ' + imgWidth);
                                console.log(image_parent);
                            }

                            if (imgWidth == parent_width) {
                                image_parent = image_parent.parentNode;
                                parent_style = window.getComputedStyle(image_parent);
                                parent_width = Math.round(parseInt(parent_style.width));
                            }

                            if (jsDebug) {
                                console.log('Parent set to #131: ' + image_parent);
                            }

                            if (isNaN(imgWidth) || imgWidth <= 0) {
                                imgWidth = browserWidth;
                            }

                        }
                        else {
                            imgWidth = Math.round(parseInt(imageWidth));

                            // PArent
                            image_parent_type = Image.parentNode.nodeName.toLowerCase();

                            if (image_parent_type == 'a') {
                                image_parent = Image.parentNode.parentElement;
                            }
                            else {
                                image_parent = Image.parentNode;
                            }

                            parent_style = window.getComputedStyle(image_parent);
                            parent_width = Math.round(parseInt(parent_style.width));
                            parent_height = Math.round(parseInt(parent_style.height));

                            if (jsDebug) {
                                console.log('Image Width set to #158: ' + imgWidth);
                                console.log(image_parent);
                                console.log(parent_width);
                            }

                            if (isNaN(parent_width)) {
                                image_parent = image_parent.parentNode;
                                parent_style = window.getComputedStyle(image_parent);
                                parent_width = Math.round(parseInt(parent_style.width));
                                parent_height = Math.round(parseInt(parent_style.height));
                            }

                            if (imgWidth == parent_width) {
                                image_parent = image_parent.parentNode;
                                parent_style = window.getComputedStyle(image_parent);
                                parent_width = Math.round(parseInt(parent_style.width));
                                parent_height = Math.round(parseInt(parent_style.height));
                            }


                            if (isNaN(imgWidth) || isNaN(parent_width)) {
                                imgWidth = browserWidth;
                            }

                            if (imgWidth > browserWidth) {
                                imgWidth = browserWidth;
                            }

                        }
                    }
                    else {
                        imgWidth = Math.round(parseInt(window.getComputedStyle(Image).width));
                        image_parent = Image.parentNode;
                        parent_style = window.getComputedStyle(image_parent);
                        parent_width = Math.round(parseInt(parent_style.width));
                        parent_height = Math.round(parseInt(parent_style.height));
                    }
                }
                else {
                    if (wpc_vars.adaptive_enabled == '1' || wpc_vars.adaptive_enabled == 'true') {
                        imgWidth = 400;
                        image_parent = Image.parentNode;
                        parent_style = window.getComputedStyle(image_parent);
                        parent_width = Math.round(parseInt(parent_style.width));
                        parent_height = Math.round(parseInt(parent_style.height));
                    }
                    else {
                        imgWidth = 1;
                        image_parent = Image.parentNode;
                        parent_style = window.getComputedStyle(image_parent);
                        parent_width = Math.round(parseInt(parent_style.width));
                        parent_height = Math.round(parseInt(parent_style.height));
                    }
                }


                if (imgWidth > browserWidth) {
                    imgWidth = browserWidth;
                }

                if (typeof imgWidth == 'undefined' || !imgWidth || imgWidth == 0) {
                    imgWidth = 1;
                }

                imageRatio = imageWidthNatural / imageHeightNatural;


                if (typeof parent_height == 'undefined' || !parent_height || parent_height == 0) {
                    parent_height = Math.round(parseInt(parent_style.height));
                }

                if (typeof parent_height == 'undefined' || !parent_height || parent_height == 0) {
                    parent_height = Image.dataset.height;
                }

                if (imageRatio < 1) {
                    newWidth = (parent_height * imageRatio);
                    imgWidth = Math.round(newWidth);
                }

                if (wpc_vars.retina_enabled == 'false' && wpc_vars.adaptive_enabled == 'false') {
                    imgWidth = 1;
                }

                if (typeof imgWidth == 'undefined' || imageIsLogo && (imgWidth < 200 || (!imgWidth || imgWidth == 0))) {
                    imgWidth = 200;
                }

            }

            if (forceWidth > 0 && imgWidth > 320) {
                imgWidth = forceWidth;
            }

            if (jsDebug) {
                console.log('Image:');
                console.log(Image);
                console.log('Image Width: ' + imgWidth);
            }

            if (typeof imgWidth == 'undefined' || !imgWidth || imgWidth == 0 || isNaN(imgWidth)) {
                imgWidth = 1;
            }

            if (isNaN(imgWidth) || imgWidth <= 10) {
                imgWidth = 1;
            }

            if (imageIsZoom) {
                imgWidth = 1;
            }

            if (isMobile) {
                if (imgWidth > mobileWidth) {
                    imgWidth = mobileWidth;
                }
            }

            if (wpc_vars.retina_enabled == 'false' && wpc_vars.adaptive_enabled == 'false') {
                imgWidth = 1;
            }

            if (typeof Image.srcset !== 'undefined' && Image.srcset != '') {
                newApiURL = Image.srcset;
                if (jsDebug) {
                    console.log('Image has srcset');
                    console.log(Image.srcset);
                    console.log(newApiURL);
                }
                if ((window.devicePixelRatio >= 2 && wpc_vars.retina_enabled == 'true') || wpc_vars.force_retina == 'true') {
                    newApiURL = newApiURL.replace(/r:0/g, 'r:1');

                    if (jsDebug) {
                        console.log('Retina set to True');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/r:1/g, 'r:0');

                    if (jsDebug) {
                        console.log('Retina set to False');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }
                }

                if (wpc_vars.webp_enabled == 'true' && isSafari == false) {
                    newApiURL = newApiURL.replace(/wp:0/g, 'wp:1');

                    if (jsDebug) {
                        console.log('WebP set to True');
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/wp:1/g, 'wp:0');

                    if (jsDebug) {
                        console.log('WebP set to False');
                    }

                }

                if (isMobile) {
                    newApiURL = getSrcset(newApiURL.split(","), imgWidth);
                }

                Image.srcset = newApiURL;
            }
            else if (typeof Image.dataset.srcset !== 'undefined' && Image.dataset.srcset != '') {
                newApiURL = Image.dataset.srcset;
                if (jsDebug) {
                    console.log('Image does not have srcset');
                    console.log(Image.srcset);
                    console.log(newApiURL);
                }
                if ((window.devicePixelRatio >= 2 && wpc_vars.retina_enabled == 'true') || wpc_vars.force_retina == 'true') {
                    newApiURL = newApiURL.replace(/r:0/g, 'r:1');

                    if (jsDebug) {
                        console.log('Retina set to True');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/r:1/g, 'r:0');

                    if (jsDebug) {
                        console.log('Retina set to False');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }
                }

                if (wpc_vars.webp_enabled == 'true' && isSafari == false) {
                    newApiURL = newApiURL.replace(/wp:0/g, 'wp:1');

                    if (jsDebug) {
                        console.log('WebP set to True');
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/wp:1/g, 'wp:0');

                    if (jsDebug) {
                        console.log('WebP set to False');
                    }

                }

                if (isMobile) {
                    newApiURL = getSrcset(newApiURL.split(","), imgWidth);
                }

                Image.srcset = newApiURL;
            }

            if (typeof Image.dataset.src !== 'undefined' && Image.dataset.src != '') {
                newApiURL = Image.dataset.src;
                newApiURL = newApiURL.replace(/w:(\d{1,5})/g, 'w:' + imgWidth);

                if ((window.devicePixelRatio >= 2 && wpc_vars.retina_enabled == 'true') || wpc_vars.force_retina == 'true') {
                    newApiURL = newApiURL.replace(/r:0/g, 'r:1');

                    if (jsDebug) {
                        console.log('Retina set to True');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/r:1/g, 'r:0');

                    if (jsDebug) {
                        console.log('Retina set to False');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }

                }

                if (wpc_vars.webp_enabled == 'true' && isSafari == false) {
                    newApiURL = newApiURL.replace(/wp:0/g, 'wp:1');

                    if (jsDebug) {
                        console.log('WebP set to True');
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/wp:1/g, 'wp:0');

                    if (jsDebug) {
                        console.log('WebP set to False');
                    }

                }

                if (wpc_vars.exif_enabled == 'true') {
                    newApiURL = newApiURL.replace(/exif:false/g, 'exif:true');
                }
                else {
                    newApiURL = newApiURL.replace(/\/exif:true/g, '');
                    newApiURL = newApiURL.replace(/\/exif:false/g, '');
                }

                Image.src = newApiURL;
            }
            else if (typeof Image.src !== 'undefined' && Image.src != '') {
                newApiURL = Image.src;
                newApiURL = newApiURL.replace(/w:(\d{1,5})/g, 'w:' + imgWidth);

                if ((window.devicePixelRatio >= 2 && wpc_vars.retina_enabled == 'true') || wpc_vars.force_retina == 'true') {
                    newApiURL = newApiURL.replace(/r:0/g, 'r:1');

                    if (jsDebug) {
                        console.log('Retina set to True');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/r:1/g, 'r:0');

                    if (jsDebug) {
                        console.log('Retina set to False');
                        console.log('DevicePixelRation ' + window.devicePixelRatio);
                    }

                }

                if (wpc_vars.webp_enabled == 'true' && isSafari == false) {
                    newApiURL = newApiURL.replace(/wp:0/g, 'wp:1');

                    if (jsDebug) {
                        console.log('WebP set to True');
                    }

                }
                else {
                    newApiURL = newApiURL.replace(/wp:1/g, 'wp:0');

                    if (jsDebug) {
                        console.log('WebP set to False');
                    }

                }

                if (wpc_vars.exif_enabled == 'true') {
                    newApiURL = newApiURL.replace(/exif:false/g, 'exif:true');
                }
                else {
                    newApiURL = newApiURL.replace(/\/exif:true/g, '');
                    newApiURL = newApiURL.replace(/\/exif:false/g, '');
                }

                Image.src = newApiURL;
            }

            Image.classList.add("wps-ic-loaded");
            //Image.removeAttribute('data-src'); => Had issues with Woo Zoom
            Image.removeAttribute('data-srcset');
        });

        activeRegular = false;
    }
}

function srcSetUpdateWidth(srcSetUrl, imgWidth) {
    let srcSetWidth = srcSetUrl.split(' ').pop();
    if (srcSetWidth.endsWith('w')) {
        // Remove w from width string
        let Width = srcSetWidth.slice(0, -1);
        if (parseInt(Width) <= 5) {
            Width = 1;
        }

        if (Width < imgWidth) {
            imgWidth = Width;
        }

        //srcSetUrl = srcSetUrl.replace(/w:(\d{1,5})/g, 'w:' + Width);
        srcSetUrl = srcSetUrl.replace(/w:(\d{1,5})/g, 'w:' + imgWidth);
    }
    else if (srcSetWidth.endsWith('x')) {
        let Width = srcSetWidth.slice(0, -1);
        if (parseInt(Width) <= 3) {
            Width = 1;
        }

        if (Width < imgWidth) {
            imgWidth = Width;
        }

        //srcSetUrl = srcSetUrl.replace(/w:(\d{1,5})/g, 'w:' + Width);
        srcSetUrl = srcSetUrl.replace(/w:(\d{1,5})/g, 'w:' + imgWidth);
    }
    return srcSetUrl;
}

function getSrcset(sourceArray, imgWidth) {
    let changedSrcset = '';

    sourceArray.forEach(function (imageSource) {

        if (jsDebug) {
            console.log('Image src part from array');
            console.log(imageSource);
        }

        newApiURL = srcSetUpdateWidth(imageSource.trimStart(), imgWidth);
        changedSrcset += newApiURL + ",";
    });

    return changedSrcset.slice(0, -1); // Remove last comma
}

var mutationObserver = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
        //load();
        //findSliderImage();
    });
});

mutationObserver.observe(document.documentElement, {
    attributes: true, characterData: true, childList: true, subtree: true, attributeOldValue: true, characterDataOldValue: true
});

window.addEventListener("resize", regularLoad);
window.addEventListener("orientationchange", regularLoad);
document.addEventListener("scroll", regularLoad);
document.addEventListener("DOMContentLoaded", load);
if ('undefined' !== typeof jQuery) {
    jQuery(document).on('elementor/popup/show', function (ev) {
        load();
    })
}