/**
 * @file
 * Search, cards/table toggle, and card expanders for the shared studies page.
 *
 * Rows are rendered server-side (see SharedStudiesController); this only
 * filters and re-presents them. The current search and view are carried in
 * the URL ("?q=" / "?view=table") so they survive the reload after adding or
 * removing a study.
 */
(function (Drupal, once) {

  'use strict';

  /**
   * Hides rows that don't contain the query; returns the visible count.
   */
  function filterRows(rows, query) {
    var q = (query || '').trim().toLowerCase();
    var visible = 0;
    rows.forEach(function (row) {
      var match = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
      row.style.display = match ? '' : 'none';
      if (match) {
        visible++;
      }
    });
    return visible;
  }

  /**
   * Adds a "View more" button to every row; refreshExpanders() decides
   * which cards actually need it.
   */
  function installExpanders(rows) {
    rows.forEach(function (row) {
      var main = row.querySelector('td');
      if (!main || row.querySelector('.card-toggle')) {
        return;
      }
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'card-toggle';
      button.setAttribute('aria-expanded', 'false');
      button.textContent = Drupal.t('View more');
      button.addEventListener('click', function () {
        var expanded = row.classList.toggle('expanded');
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        button.textContent = expanded ? Drupal.t('View less') : Drupal.t('View more');
      });
      main.appendChild(button);
    });
  }

  /**
   * Collapses only the cards whose full content is taller than the
   * collapsed height, so short cards don't get a pointless toggle.
   */
  function refreshExpanders(table, rows) {
    var cards = table.classList.contains('cards');
    var limit = parseInt(getComputedStyle(table.closest('.shared-studies')).getPropertyValue('--shared-collapsed-height'), 10) || 340;

    rows.forEach(function (row) {
      var button = row.querySelector('.card-toggle');
      if (!button || row.style.display === 'none' || row.classList.contains('expanded')) {
        return;
      }
      row.classList.remove('is-collapsible');
      var needsToggle = cards && row.scrollHeight > limit;
      row.classList.toggle('is-collapsible', needsToggle);
      button.style.display = needsToggle ? '' : 'none';
    });
  }

  Drupal.behaviors.studyManagerSharedStudies = {
    attach: function (context) {
      once('shared-studies', '.js-shared-studies-table', context).forEach(function (table) {
        var page = table.closest('.shared-studies');
        var search = page.querySelector('.js-shared-studies-search');
        var status = page.querySelector('.js-shared-studies-status');
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var params = new URLSearchParams(window.location.search);

        function applyFilter() {
          var visible = filterRows(rows, search.value);
          status.textContent = search.value.trim()
            ? Drupal.t('Showing @visible of @total studies', {'@visible': visible, '@total': rows.length})
            : Drupal.formatPlural(rows.length, '1 study', '@count studies');
          refreshExpanders(table, rows);
        }

        if (params.get('view') === 'table') {
          table.classList.remove('cards');
        }
        search.value = params.get('q') || '';

        installExpanders(rows);
        applyFilter();

        var debounce;
        search.addEventListener('input', function () {
          clearTimeout(debounce);
          debounce = setTimeout(applyFilter, 120);
        });

        page.querySelector('.js-shared-studies-clear').addEventListener('click', function () {
          search.value = '';
          applyFilter();
        });

        page.querySelector('.js-shared-studies-toggle').addEventListener('click', function () {
          table.classList.toggle('cards');
          refreshExpanders(table, rows);
        });

        // Clicking a location or detail tag searches for it.
        table.querySelectorAll('.js-search-tag').forEach(function (tag) {
          tag.addEventListener('click', function (e) {
            e.preventDefault();
            search.value = tag.dataset.term || '';
            applyFilter();
            search.scrollIntoView({behavior: 'smooth', block: 'center'});
          });
        });

        // Carry the current search/view (and the card's position) through
        // the add/remove redirect.
        table.querySelectorAll('.js-shared-studies-action').forEach(function (link) {
          link.addEventListener('click', function () {
            var url = new URL(link.href, window.location.origin);
            var destination = new URL(url.searchParams.get('destination') || window.location.pathname, window.location.origin);
            if (search.value.trim()) {
              destination.searchParams.set('q', search.value.trim());
            }
            if (!table.classList.contains('cards')) {
              destination.searchParams.set('view', 'table');
            }
            destination.hash = link.closest('tr').id;
            url.searchParams.set('destination', destination.pathname + destination.search + destination.hash);
            link.href = url.toString();
          });
        });

        var resize;
        window.addEventListener('resize', function () {
          clearTimeout(resize);
          resize = setTimeout(function () {
            refreshExpanders(table, rows);
          }, 120);
        });
      });

      // Switching studies reloads the page; keep the search/view across it.
      once('shared-studies-picker', '.js-shared-studies-picker', context).forEach(function (form) {
        var submit = form.querySelector('.js-shared-studies-picker-submit');
        if (submit) {
          submit.hidden = true;
        }
        form.querySelector('select').addEventListener('change', function () {
          var page = form.closest('.shared-studies');
          var search = page.querySelector('.js-shared-studies-search');
          var table = page.querySelector('.js-shared-studies-table');
          var extras = {
            q: search ? search.value.trim() : '',
            view: table && !table.classList.contains('cards') ? 'table' : ''
          };
          Object.keys(extras).forEach(function (name) {
            if (extras[name]) {
              var input = document.createElement('input');
              input.type = 'hidden';
              input.name = name;
              input.value = extras[name];
              form.appendChild(input);
            }
          });
          form.submit();
        });
      });
    }
  };

})(Drupal, once);
