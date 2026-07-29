/* ==========================================================================
   Pauwels Freelance — site script
   ========================================================================== */

/* Contact form endpoint. On Český hosting this is your own PHP script. */
var FORM_ENDPOINT = 'send.php';

/* Only used if you ever switch to Web3Forms. Leave null for send.php. */
var WEB3FORMS_KEY = null;

/* Fallback if the endpoint is unreachable — opens the visitor's mail app. */
var CONTACT_EMAIL = 'info@pauwels-freelance.cz';


document.addEventListener('DOMContentLoaded', function () {

  /* ---------- Mobile nav toggle ---------- */
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('mainnav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('open');
    });
  }

  /* ---------- Contact page: carry the configuration through from ?type= ---------- */
  var projectTypeField = document.getElementById('projectType');

  if (projectTypeField) {
    var params = new URLSearchParams(window.location.search);
    var type = params.get('type');

    if (type) {
      projectTypeField.value = type;

      var banner = document.getElementById('tierBanner');
      var bannerText = document.getElementById('tierBannerText');
      if (banner && bannerText) {
        bannerText.textContent = type;
        banner.classList.add('show');
      }
    } else {
      projectTypeField.value = 'General inquiry';
    }

    /* Prefill the message field from the configurator, if it sent one. */
    var details = params.get('details');
    var messageField = document.getElementById('message');
    if (details && messageField && !messageField.value) {
      messageField.value = details;
    }
  }

  /* ---------- Contact form submit ---------- */
  var form = document.getElementById('contactForm');
  if (form) {
    var statusEl = document.getElementById('formStatus');
    var submitBtn = document.getElementById('submitBtn');

    var setStatus = function (msg, state) {
      statusEl.textContent = msg;
      statusEl.className = 'form-status show ' + (state || '');
    };

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var name = document.getElementById('name').value.trim();
      var email = document.getElementById('email').value.trim();
      var type = document.getElementById('projectType').value;
      var message = document.getElementById('message').value.trim();
      var honeypot = document.getElementById('company').value;

      if (honeypot) {
        setStatus('Thanks — your message has been sent.', 'ok');
        form.reset();
        return;
      }

      if (!name || !email || !message) {
        setStatus('Please fill in your name, email and a short description.', 'error');
        return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        setStatus('That email address doesn\'t look right.', 'error');
        return;
      }

      submitBtn.disabled = true;
      setStatus('Sending…', '');

      var payload = {
        name: name,
        email: email,
        projectType: type,
        message: message,
        subject: 'New inquiry: ' + type
      };
      if (WEB3FORMS_KEY) payload.access_key = WEB3FORMS_KEY;

      fetch(FORM_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function (res) {
          if (!res.ok) throw new Error('HTTP ' + res.status);
          return res.json().catch(function () { return {}; });
        })
        .then(function () {
          form.reset();
          if (unsureNote) unsureNote.classList.remove('show');
          setStatus('Thanks — your message has been sent. I\'ll reply within 1–2 business days.', 'ok');
          submitBtn.disabled = false;
        })
        .catch(function (err) {
          console.error('Form submission failed:', err);
          submitBtn.disabled = false;

          var subject = encodeURIComponent('New inquiry: ' + type);
          var body = encodeURIComponent(
            'Name: ' + name + '\n' +
            'Email: ' + email + '\n' +
            'Interested in: ' + type + '\n\n' +
            message
          );
          setStatus('Couldn\'t send automatically — opening your email app instead.', 'error');
          window.location.href = 'mailto:' + CONTACT_EMAIL + '?subject=' + subject + '&body=' + body;
        });
    });
  }

  /* ---------- Auto-calculated experience ---------- */
  var exp = document.getElementById('experience');
  if (exp && exp.dataset.start) {
    var start = new Date(exp.dataset.start);
    var now = new Date();

    var totalMonths = (now.getFullYear() - start.getFullYear()) * 12
      + (now.getMonth() - start.getMonth());
    if (now.getDate() < start.getDate()) totalMonths--;
    if (totalMonths < 0) totalMonths = 0;

    var years = Math.floor(totalMonths / 12);
    var months = totalMonths % 12;

    var plural = function (n, word) {
      return n + ' ' + word + (n === 1 ? '' : 's');
    };

    var text;
    if (years === 0 && months === 0) {
      text = 'Just started';
    } else if (years === 0) {
      text = plural(months, 'month');
    } else if (months === 0) {
      text = plural(years, 'year');
    } else {
      text = plural(years, 'year') + ', ' + plural(months, 'month');
    }

    exp.textContent = text;
  }

  /* ---------- Configurator page: project builder (no pricing shown) ---------- */
  var calcTierGrid = document.getElementById('calcTierGrid');
  if (calcTierGrid) {

    var CATEGORIES = [
      { title: 'Accounts & access', items: [
        { k: 'login',     label: 'User sign-up & login' },
        { k: 'social',    label: 'Social login (Google, etc.)' },
        { k: 'roles',     label: 'Roles & permission levels' },
        { k: 'twofa',     label: 'Extra login security (2FA)' },
        { k: 'pwreset',   label: 'Self-service password reset' }
      ]},
      { title: 'Content & data', items: [
        { k: 'blog',       label: 'Blog / news section' },
        { k: 'search',     label: 'Search or filtering across listings' },
        { k: 'migration',  label: 'Migrating content from an existing site' },
        { k: 'multilang',  label: 'Multiple languages' },
        { k: 'cms',        label: 'Editable content via admin panel' },
        { k: 'analytics',  label: 'Analytics & visitor reporting setup' }
      ]},
      { title: 'Integrations', items: [
        { k: 'payments',      label: 'Online payments' },
        { k: 'booking',       label: 'Booking / scheduling' },
        { k: 'newsletter',    label: 'Newsletter or CRM connection' },
        { k: 'maps',          label: 'Maps or other third-party embeds' },
        { k: 'emailtemplates',label: 'Custom HTML email templates (branded, tested across clients)' },
        { k: 'chat',          label: 'Live chat widget' }
      ]},
      { title: 'Trust & security', items: [
        { k: 'hardening',    label: 'Extra security hardening' },
        { k: 'gdpr',         label: 'GDPR & cookie consent' },
        { k: 'backups',      label: 'Automated backups' },
        { k: 'accessibility',label: 'Accessibility (WCAG) improvements' }
      ]},
      { title: 'Extras', items: [
        { k: 'photo',      label: 'Custom illustrations or photography' },
        { k: 'revisions',  label: 'Extra rounds of revisions' },
        { k: 'training',   label: 'Training on the admin panel' },
        { k: 'seo',        label: 'Advanced SEO & structured data' },
        { k: 'speed',      label: 'Performance & image optimization' },
        { k: 'rush',       label: 'Rush delivery' }
      ]}
    ];

    /* Commonly-needed items pre-selected per project size. The visitor can
       still tick or untick anything — these are just a sensible starting point. */
    var TIER_PRESETS = {
      small:  ['gdpr'],
      normal: ['gdpr', 'backups', 'analytics', 'newsletter'],
      large:  ['login', 'roles', 'gdpr', 'backups', 'accessibility', 'hardening', 'training']
    };

    var calcState = { tier: null, items: {} };
    var catsEl = document.getElementById('calcCategories');
    var estimateValueEl = document.getElementById('estimateValue');
    var getQuoteBtn = document.getElementById('getQuoteBtn');

    var presetNote = document.getElementById('presetNote');

    /* Tier cards — reuse the existing .tier / .featured styling for the "selected" look */
    var tierCards = calcTierGrid.querySelectorAll('.tier.selectable');
    tierCards.forEach(function (card) {
      card.addEventListener('click', function () {
        calcState.tier = card.dataset.tier;
        tierCards.forEach(function (c) { c.classList.remove('featured'); });
        card.classList.add('featured');
        applyTierPreset(calcState.tier);
        updateSummary();
      });
    });

    function applyTierPreset(tier) {
      var preset = TIER_PRESETS[tier] || [];
      Object.keys(calcState.items).forEach(function (k) {
        var shouldCheck = preset.indexOf(k) !== -1;
        calcState.items[k] = shouldCheck;
        var chk = document.getElementById('calc-' + k);
        if (chk) {
          chk.checked = shouldCheck;
          var row = chk.closest('.calc-row');
          if (row) row.classList.toggle('checked', shouldCheck);
        }
      });
      if (presetNote) {
        var tierNames = { small: 'Small', normal: 'Normal', large: 'Large' };
        presetNote.textContent = 'We\'ve pre-selected the items most ' + tierNames[tier] +
          ' projects need below — feel free to add or remove anything.';
      }
    }

    /* Add-on category checklist */
    CATEGORIES.forEach(function (cat) {
      var block = document.createElement('div');
      block.className = 'calc-category';
      var h4 = document.createElement('h4');
      h4.textContent = cat.title;
      block.appendChild(h4);

      cat.items.forEach(function (it) {
        calcState.items[it.k] = false;

        var row = document.createElement('div');
        row.className = 'calc-row';

        var chk = document.createElement('input');
        chk.type = 'checkbox';
        chk.id = 'calc-' + it.k;

        var lbl = document.createElement('label');
        lbl.setAttribute('for', chk.id);
        lbl.textContent = it.label;

        row.appendChild(chk);
        row.appendChild(lbl);
        block.appendChild(row);

        var toggle = function () {
          chk.checked = !chk.checked;
          calcState.items[it.k] = chk.checked;
          row.classList.toggle('checked', chk.checked);
          updateSummary();
        };
        chk.addEventListener('click', function (e) {
          e.stopPropagation();
          calcState.items[it.k] = chk.checked;
          row.classList.toggle('checked', chk.checked);
          updateSummary();
        });
        row.addEventListener('click', function (e) {
          if (e.target !== chk) toggle();
        });
      });

      catsEl.appendChild(block);
    });

    function selectedAddonCount() {
      return Object.keys(calcState.items).filter(function (k) { return calcState.items[k]; }).length;
    }

    function selectedAddonLabels() {
      var labels = [];
      CATEGORIES.forEach(function (cat) {
        cat.items.forEach(function (it) {
          if (calcState.items[it.k]) labels.push(it.label);
        });
      });
      return labels;
    }

    function updateSummary() {
      if (!calcState.tier) {
        estimateValueEl.textContent = 'Select a project size above';
        getQuoteBtn.disabled = true;
        return;
      }

      var tierCard = calcTierGrid.querySelector('.tier[data-tier="' + calcState.tier + '"]');
      var tierName = tierCard.dataset.name;
      var count = selectedAddonCount();

      estimateValueEl.textContent = count === 0
        ? tierName
        : tierName + ' + ' + count + ' add-on' + (count === 1 ? '' : 's');

      getQuoteBtn.disabled = false;
    }

    getQuoteBtn.addEventListener('click', function () {
      if (!calcState.tier) return;

      var tierCard = calcTierGrid.querySelector('.tier[data-tier="' + calcState.tier + '"]');
      var tierName = tierCard.dataset.name;
      var addons = selectedAddonLabels();
      var count = addons.length;

      var typeStr = count === 0
        ? tierName
        : tierName + ' + ' + count + ' add-on' + (count === 1 ? '' : 's');

      var detailLines = ['I\'m interested in: ' + tierName];
      if (count > 0) {
        detailLines.push('');
        detailLines.push('Also interested in:');
        addons.forEach(function (a) { detailLines.push('- ' + a); });
      }

      var url = 'contact.html?type=' + encodeURIComponent(typeStr) +
        '&details=' + encodeURIComponent(detailLines.join('\n'));
      window.location.href = url;
    });

    updateSummary();
  }

});