/**
 * Ofnoacomps Post Badges — Admin JS
 * Live preview & dynamic position options
 */
(function ($) {
  'use strict';

  var $badge   = $('#opb-preview-badge');
  var $form    = $('#opb-settings-form');
  var $posWrap = $('#opb-position-options');

  // ── Live preview updates ────────────────────────────────────────────────

  function updatePreview () {
    var text   = $('#opb_text').val()          || 'Badge';
    var bg     = $('#opb_bg').val()            || '#e74c3c';
    var color  = $('#opb_color').val()         || '#ffffff';
    var size   = parseInt($('#opb_font_size').val(), 10) || 14;
    var radius = parseInt($('#opb_radius').val(), 10)    || 6;
    var shadow = $('#opb_shadow').is(':checked');
    var shadowColor = $('#opb_shadow_color').val() || 'rgba(0,0,0,0.35)';

    var type     = $('input[name="' + OPB_OPTION + '[type]"]:checked').val()     || 'square';
    var position = $('input[name="' + OPB_OPTION + '[position]"]:checked').val() || 'top-right';

    // Update badge text
    $badge.text(text);

    // Update badge style
    $badge.css({
      'background-color': bg,
      'color':            color,
      'font-size':        size + 'px',
      'border-radius':    (type === 'square') ? radius + 'px' : '0',
      'text-shadow':      shadow ? ('1px 1px 3px ' + shadowColor) : 'none'
    });

    // Update badge classes
    $badge.attr('class', 'opb-badge opb-type--' + type + ' opb-pos--' + position);

    // Show/hide shadow color picker
    if (shadow) {
      $('#opb_shadow_color_wrap').show();
    } else {
      $('#opb_shadow_color_wrap').hide();
    }

    // Show/hide border-radius card
    if (type === 'square') {
      $('#opb-radius-card').show();
    } else {
      $('#opb-radius-card').hide();
    }
  }

  // ── Position options rebuild when type changes ──────────────────────────

  function rebuildPositions (type) {
    var positions = window.OPB_POSITIONS[type] || {};
    var optKey    = window.OPB_OPTION + '[position]';
    var html      = '';

    $.each(positions, function (slug, label) {
      html += '<label class="opb-pos-option">' +
              '<input type="radio" name="' + optKey + '" value="' + slug + '" class="opb-live" data-preview="position"> ' +
              label + '</label>';
    });

    $posWrap.html(html);

    // Auto-select first
    $posWrap.find('input[type="radio"]:first').prop('checked', true).closest('.opb-pos-option').addClass('is-active');

    updatePreview();
  }

  // ── Range slider display ────────────────────────────────────────────────

  $form.on('input', '.opb-slider', function () {
    var displayId = $(this).data('display');
    if (displayId) {
      $('#' + displayId).text($(this).val() + 'px');
    }
  });

  // ── Badge type card active state ────────────────────────────────────────

  $form.on('change', 'input[name="' + OPB_OPTION + '[type]"]', function () {
    $('.opb-type-card').removeClass('is-active');
    $(this).closest('.opb-type-card').addClass('is-active');
    rebuildPositions($(this).val());
  });

  // ── Position radio active state ─────────────────────────────────────────

  $form.on('change', 'input[name="' + OPB_OPTION + '[position]"]', function () {
    $('.opb-pos-option').removeClass('is-active');
    $(this).closest('.opb-pos-option').addClass('is-active');
    updatePreview();
  });

  // ── Any other .opb-live element ─────────────────────────────────────────

  $form.on('input change', '.opb-live', function () {
    updatePreview();
  });

  // ── Filter mode toggle ──────────────────────────────────────────────────
  $form.on('change', '.opb-filter-radio', function () {
    var mode = $(this).val();
    $('.opb-filter-mode').removeClass('is-active');
    $(this).closest('.opb-filter-mode').addClass('is-active');
    $('#opb-filter-categories, #opb-filter-tags').hide();
    if (mode === 'categories') $('#opb-filter-categories').show();
    if (mode === 'tags')       $('#opb-filter-tags').show();
  });

  // ── Init ────────────────────────────────────────────────────────────────

  // Mark active type card on load
  $('input[name="' + OPB_OPTION + '[type]"]:checked').closest('.opb-type-card').addClass('is-active');

  // Mark active position on load
  $('input[name="' + OPB_OPTION + '[position]"]:checked').closest('.opb-pos-option').addClass('is-active');

  updatePreview();

}(jQuery));
