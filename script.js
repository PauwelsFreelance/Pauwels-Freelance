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

  /* ---------- Contact page: pre-select project type from ?type= ---------- */
  var select = document.getElementById('projectType');
  var unsureNote = document.getElementById('unsureNote');

  if (select) {
    var params = new URLSearchParams(window.location.search);
    var type = params.get('type');

    if (type) {
      var match = Array.from(select.options).find(function (o) {
        return o.value === type;
      });
      if (!match) {
        var temp = document.createElement('option');
        temp.textContent = type;
        temp.value = type;
        select.insertBefore(temp, select.firstChild);
      }
      select.value = type;

      var banner = document.getElementById('tierBanner');
      var bannerText = document.getElementById('tierBannerText');
      if (banner && bannerText) {
        bannerText.textContent = type;
        banner.classList.add('show');
      }
    }

    /* Prefill the message field from the pricing calculator, if it sent one. */
    var details = params.get('details');
    var messageField = document.getElementById('message');
    if (details && messageField && !messageField.value) {
      messageField.value = details;
    }

    if (unsureNote) {
      var toggleNote = function () {
        unsureNote.classList.toggle('show', select.value === 'Not sure yet');
      };
      select.addEventListener('change', toggleNote);
      toggleNote();
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

  /* ---------- Pricing page: project calculator ---------- */
  var calcTierGrid = document.getElementById('calcTierGrid');
  if (calcTierGrid) {

    var TIER_BASE = { small: 9900, normal: 24900, large: 54900 };

    var CATEGORIES = [
      { title: 'Accounts & access', items: [
        { k: 'login',   label: 'User sign-up & login',            add: 4000 },
        { k: 'social',  label: 'Social login (Google, etc.)',     add: 2000 },
        { k: 'roles',   label: 'Roles & permission levels',       add: 5000 },
        { k: 'twofa',   label: 'Extra login security (2FA)',      add: 3000 }
      ]},
      { title: 'Content & data', items: [
        { k: 'blog',       label: 'Blog / news section',                add: 4000 },
        { k: 'search',     label: 'Search or filtering across listings', add: 3500 },
        { k: 'migration',  label: 'Migrating content from an existing site', add: 3000 },
        { k: 'multilang',  label: 'Multiple languages',                 add: 6000 }
      ]},
      { title: 'Integrations', items: [
        { k: 'payments',   label: 'Online payments',                add: 5000 },
        { k: 'booking',    label: 'Booking / scheduling',           add: 4000 },
        { k: 'newsletter', label: 'Newsletter or CRM connection',   add: 2500 },
        { k: 'maps',       label: 'Maps or other third-party embeds', add: 1500 }
      ]},
      { title: 'Trust & security', items: [
        { k: 'hardening', label: 'Extra security hardening',   add: 3500 },
        { k: 'gdpr',      label: 'GDPR & cookie consent',      add: 3000 },
        { k: 'backups',   label: 'Automated backups',          add: 2000 }
      ]},
      { title: 'Extras', items: [
        { k: 'photo',      label: 'Custom illustrations or photography', add: 5000 },
        { k: 'revisions',  label: 'Extra rounds of revisions',           add: 2500 },
        { k: 'training',   label: 'Training on the admin panel',         add: 2000 },
        { k: 'rush',       label: 'Rush delivery',                       add: 4000 }
      ]}
    ];

    var calcState = { tier: null, items: {} };
    var catsEl = document.getElementById('calcCategories');
    var estimateValueEl = document.getElementById('estimateValue');
    var getQuoteBtn = document.getElementById('getQuoteBtn');

    /* Tier cards — reuse the existing .tier / .featured styling for the "selected" look */
    var tierCards = calcTierGrid.querySelectorAll('.tier.selectable');
    tierCards.forEach(function (card) {
      card.addEventListener('click', function () {
        calcState.tier = card.dataset.tier;
        tierCards.forEach(function (c) { c.classList.remove('featured'); });
        card.classList.add('featured');
        updateEstimate();
      });
    });

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
          updateEstimate();
        };
        chk.addEventListener('click', function (e) {
          e.stopPropagation();
          calcState.items[it.k] = chk.checked;
          row.classList.toggle('checked', chk.checked);
          updateEstimate();
        });
        row.addEventListener('click', function (e) {
          if (e.target !== chk) toggle();
        });
      });

      catsEl.appendChild(block);
    });

    function roundHundred(n) {
      return Math.round(n / 100) * 100;
    }

    function fmtKc(n) {
      return n.toLocaleString('cs-CZ') + ' Kč';
    }

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

    function updateEstimate() {
      if (!calcState.tier) {
        estimateValueEl.textContent = 'Select a project size above';
        getQuoteBtn.disabled = true;
        return;
      }

      var base = TIER_BASE[calcState.tier];
      var addSum = 0;
      CATEGORIES.forEach(function (cat) {
        cat.items.forEach(function (it) { if (calcState.items[it.k]) addSum += it.add; });
      });

      var subtotal = roundHundred(base + addSum);
      var count = selectedAddonCount();

      if (count === 0) {
        estimateValueEl.textContent = 'from ' + fmtKc(subtotal);
      } else {
        var high = roundHundred(subtotal * 1.3);
        estimateValueEl.textContent = fmtKc(subtotal) + ' – ' + fmtKc(high);
      }

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
      detailLines.push('');
      detailLines.push('Rough estimate shown on the pricing page: ' + estimateValueEl.textContent +
        ' (final price confirmed on our call).');

      var url = 'contact.html?type=' + encodeURIComponent(typeStr) +
        '&details=' + encodeURIComponent(detailLines.join('\n'));
      window.location.href = url;
    });

    updateEstimate();
  }

});