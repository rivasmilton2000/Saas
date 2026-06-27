(function() {
  'use strict';

  var themeStorageKey = 'sietelsa|theme';

  function storedTheme() {
    try {
      return localStorage.getItem(themeStorageKey);
    } catch (error) {
      return null;
    }
  }

  function currentTheme() {
    var saved = storedTheme();
    if (saved === 'dark' || saved === 'light') {
      return saved;
    }

    return 'light';
  }

  function setStoredTheme(theme) {
    try {
      localStorage.setItem(themeStorageKey, theme);
    } catch (error) {
      return;
    }
  }

  function syncThemeControls(theme) {
    var isDark = theme === 'dark';
    var toggles = document.querySelectorAll('[data-sietelsa-theme-toggle]');

    Array.prototype.forEach.call(toggles, function(toggle) {
      toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      toggle.setAttribute('aria-label', isDark ? 'Activar modo claro' : 'Activar modo oscuro');
      toggle.setAttribute('title', isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');

      var label = toggle.querySelector('[data-sietelsa-theme-label]');
      if (label) {
        label.textContent = isDark ? 'Claro' : 'Oscuro';
      }

      var icon = toggle.querySelector('[data-sietelsa-theme-icon]');
      if (icon) {
        icon.className = isDark ? 'mdi mdi-white-balance-sunny me-1' : 'mdi mdi-weather-night me-1';
      }
    });
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-sietelsa-theme', theme);
    document.documentElement.style.colorScheme = theme;
    syncThemeControls(theme);
  }

  applyTheme(currentTheme());

  document.addEventListener('DOMContentLoaded', function() {
    var toggles = document.querySelectorAll('[data-sietelsa-theme-toggle]');
    Array.prototype.forEach.call(toggles, function(toggle) {
      toggle.addEventListener('click', function() {
        var nextTheme = document.documentElement.getAttribute('data-sietelsa-theme') === 'dark' ? 'light' : 'dark';
        setStoredTheme(nextTheme);
        applyTheme(nextTheme);
      });
    });

    syncThemeControls(document.documentElement.getAttribute('data-sietelsa-theme') || currentTheme());
  });
})();

(function($) {
  'use strict';
  $(function() {
    var body = $('body');
    var contentWrapper = $('.content-wrapper');
    var scroller = $('.container-scroller');
    var footer = $('.footer');
    var sidebar = $('.sidebar');

    //Add active class to nav-link based on url dynamically
    //Active class can be hard coded directly in html file also as required

    function addActiveClass(element) {
      if (current === "") {
        //for root url
        if (element.attr('href').indexOf("index.html") !== -1) {
          element.parents('.nav-item').last().addClass('active');
          if (element.parents('.sub-menu').length) {
            element.closest('.collapse').addClass('show');
            element.addClass('active');
          }
        }
      } else {
        //for other url
        if (element.attr('href').indexOf(current) !== -1) {
          element.parents('.nav-item').last().addClass('active');
          if (element.parents('.sub-menu').length) {
            element.closest('.collapse').addClass('show');
            element.addClass('active');
          }
          if (element.parents('.submenu-item').length) {
            element.addClass('active');
          }
        }
      }
    }

    var current = location.pathname.split("/").slice(-1)[0].replace(/^\/|\/$/g, '');
    $('.nav li a', sidebar).each(function() {
      var $this = $(this);
      addActiveClass($this);
    })

    $('.horizontal-menu .nav li a').each(function() {
      var $this = $(this);
      addActiveClass($this);
    })

    //Close other submenu in sidebar on opening any

    sidebar.on('show.bs.collapse', '.collapse', function() {
      sidebar.find('.collapse.show').collapse('hide');
    });


    //Change sidebar and content-wrapper height
    applyStyles();

    function applyStyles() {
      //Applying perfect scrollbar
      if (!body.hasClass("rtl")) {
        if ($('.settings-panel .tab-content .tab-pane.scroll-wrapper').length) {
          const settingsPanelScroll = new PerfectScrollbar('.settings-panel .tab-content .tab-pane.scroll-wrapper');
        }
        if ($('.chats').length) {
          const chatsScroll = new PerfectScrollbar('.chats');
        }
        if (body.hasClass("sidebar-fixed")) {
          if($('#sidebar').length) {
            var fixedSidebarScroll = new PerfectScrollbar('#sidebar .nav');
          }
        }
      }
    }

    $('[data-toggle="minimize"]').on("click", function() {
      if ((body.hasClass('sidebar-toggle-display')) || (body.hasClass('sidebar-absolute'))) {
        body.toggleClass('sidebar-hidden');
      } else {
        body.toggleClass('sidebar-icon-only');
      }
    });

    //checkbox and radios
    $(".form-check label,.form-radio label").append('<i class="input-helper"></i>');

    //Horizontal menu in mobile
    $('[data-toggle="horizontal-menu-toggle"]').on("click", function() {
      $(".horizontal-menu .bottom-navbar").toggleClass("header-toggled");
    });
    // Horizontal menu navigation in mobile menu on click
    var navItemClicked = $('.horizontal-menu .page-navigation >.nav-item');
    navItemClicked.on("click", function(event) {
      if(window.matchMedia('(max-width: 991px)').matches) {
        if(!($(this).hasClass('show-submenu'))) {
          navItemClicked.removeClass('show-submenu');
        }
        $(this).toggleClass('show-submenu');
      }        
    })

    $(window).scroll(function() {
      if(window.matchMedia('(min-width: 992px)').matches) {
        var header = $('.horizontal-menu');
        if ($(window).scrollTop() >= 70) {
          $(header).addClass('fixed-on-scroll');
        } else {
          $(header).removeClass('fixed-on-scroll');
        }
      }
    });
  });

  // focus input when clicking on search icon
  $('#navbar-search-icon').click(function() {
    $("#navbar-search-input").focus();
  });

  var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
  var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl)
  })  
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  })
  
})(jQuery);
