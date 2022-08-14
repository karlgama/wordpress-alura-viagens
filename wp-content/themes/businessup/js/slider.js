jQuery(document).ready(function ($) {
function homeslider() {
    const Homemain = new Swiper('#businessup-slider', {
        direction: 'horizontal',
        loop: true,
        autoplay: true,
        autoplay: {
           delay: 2000,
        },
        slidesPerView: 1,
        // Navigation arrows
        navigation: {
          nextEl: '.swiper-button-next',
          prevEl: '.swiper-button-prev'
        },
        pagination: {
                  el: ".swiper-pagination",
                  dynamicBullets: true,
        },
    });
}
homeslider();
 });


/* =================================
===         SCROLL TOP       ====
=================================== */
(function($) {
  "use strict";
jQuery(".ta_upscr").hide(); 
function taupr() {
  jQuery(window).on('scroll', function() {
    if ($(this).scrollTop() > 500) {
        $('.ta_upscr').fadeIn();
    } else {
      $('.ta_upscr').fadeOut();
    }
  });   
  $('a.ta_upscr').on('click', function()  {
    $('body,html').animate({
      scrollTop: 0
    }, 800);
    return false;
  });
}    
taupr();
})(jQuery);

