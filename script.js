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

    /* Carry the exact configurator selection through as hidden fields, so
       send.php can store a precise tier/add-on record — not just free text. */
    var tierKeyField = document.getElementById('tierKey');
    var addonKeysField = document.getElementById('addonKeys');
    var tierParam = params.get('tier');
    var addonsParam = params.get('addons');
    if (tierKeyField && tierParam) tierKeyField.value = tierParam;
    if (addonKeysField && addonsParam) addonKeysField.value = addonsParam;
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

      var tierKeyEl = document.getElementById('tierKey');
      var addonKeysEl = document.getElementById('addonKeys');

      var payload = {
        name: name,
        email: email,
        projectType: type,
        message: message,
        subject: 'New inquiry: ' + type,
        tierKey: tierKeyEl ? tierKeyEl.value : '',
        addonKeys: addonKeysEl ? addonKeysEl.value : ''
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

  /* ---------- Configurator page: project builder (no pricing shown) ----------
     Tiers and add-ons are managed from the admin panel and loaded live from
     /api/configurator.php, so this page never has hardcoded project data. */
  var calcTierGrid = document.getElementById('calcTierGrid');
  if (calcTierGrid) {

    var calcState = { tier: null, items: {} };
    var catsEl = document.getElementById('calcCategories');
    var estimateValueEl = document.getElementById('estimateValue');
    var getQuoteBtn = document.getElementById('getQuoteBtn');
    var presetNote = document.getElementById('presetNote');

    var TIERS = [];
    var CATEGORIES = [];
    var TIER_PRESETS = {};

    fetch('/api/configurator.php')
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        if (data.error) throw new Error(data.error);
        TIERS = data.tiers || [];
        CATEGORIES = data.categories || [];
        TIERS.forEach(function (t) { TIER_PRESETS[t.key] = t.presets || []; });
        renderTiers();
        renderCategories();
        updateSummary();
      })
      .catch(function (err) {
        console.error('Failed to load configurator:', err);
        calcTierGrid.innerHTML = '<p class="proj-loading">Couldn\'t load the configurator right now — please refresh, or get in touch directly.</p>';
      });

    function renderTiers() {
      calcTierGrid.innerHTML = '';
      TIERS.forEach(function (t) {
        var card = document.createElement('div');
        card.className = 'tier selectable';
        card.dataset.tier = t.key;
        card.dataset.name = t.fullName;

        var featuresHtml = (t.features || []).map(function (f) {
          return '<li>' + escapeHtml(f) + '</li>';
        }).join('');

        card.innerHTML =
          '<div class="tier-tag">' + escapeHtml(t.tag) + '</div>' +
          '<h3>' + escapeHtml(t.name) + '</h3>' +
          (t.durationText ? '<p class="tier-duration">' + escapeHtml(t.durationText) + '</p>' : '') +
          '<ul>' + featuresHtml + '</ul>';

        card.addEventListener('click', function () {
          calcState.tier = t.key;
          calcTierGrid.querySelectorAll('.tier').forEach(function (c) { c.classList.remove('featured'); });
          card.classList.add('featured');
          applyTierPreset(t.key);
          updateSummary();
        });

        calcTierGrid.appendChild(card);
      });
    }

    function applyTierPreset(tierKey) {
      var preset = TIER_PRESETS[tierKey] || [];
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
        var tier = TIERS.find(function (t) { return t.key === tierKey; });
        var tierName = tier ? tier.tag : '';
        presetNote.textContent = 'We\'ve pre-selected the items most ' + tierName +
          ' projects need below — feel free to add or remove anything.';
      }
    }

    // Plain-English "what does this give the client" text, used when the
    // API doesn't yet return an `it.description` for an add-on. Once
    // configurator_addons has a description column and the admin panel
    // exposes it, API values take priority automatically — see getDescription().
    var ADDON_DESCRIPTIONS = {
      login:          'Visitors can create an account and log back in — needed for gated content, saved preferences or a member area.',
      social:         'Sign in with Google or Facebook instead of a password — fewer steps for visitors, less password-reset support for you.',
      roles:          'Different staff members get different access levels in the admin panel, e.g. editor vs. full admin.',
      twofa:          'An extra login step (a one-time code) that protects admin accounts even if a password leaks.',
      pwreset:        'Visitors or admins can reset a forgotten password themselves, without emailing you.',
      blog:           'A news or articles section you can publish to yourself, without touching any code.',
      search:         'A search box so visitors can find content or products across the site.',
      migration:      'Moving your existing content — text, images, products — from your current site or files into the new one.',
      multilang:      'The same site available in more than one language, with a language switcher.',
      cms:            'A simple editor so you can update text and images yourself after launch, without a full admin panel.',
      analytics:      'Privacy-friendly visitor analytics — see traffic and trends without a heavy cookie-consent setup.',
      payments:       'Accept card payments directly on the site, e.g. deposits, product sales or invoices.',
      booking:        'Visitors can book an appointment, table or slot directly on the site.',
      newsletter:     'A signup form that adds visitors to your mailing list, ready to connect to your email tool.',
      maps:           'An embedded map showing your location, with directions.',
      emailtemplates: 'Branded HTML email templates that render correctly in Gmail, Outlook and Apple Mail.',
      chat:           'A live chat widget so visitors can message you directly from the site.',
      hardening:      'Extra server-side security beyond the baseline — rate limiting, stricter headers, brute-force protection.',
      gdpr:           'A GDPR-compliant privacy policy page explaining what data you collect and why.',
      backups:        'Automated, scheduled backups of your site and database, so nothing is lost if something breaks.',
      accessibility:  'A full accessibility pass — screen reader and keyboard-navigation support, meeting WCAG standards.',
      photo:          'Professional editing and optimization of the images you provide.',
      revisions:      'An extra round of revisions, beyond what\'s included in your project tier.',
      training:       'A short walkthrough so you or your team feel confident using the admin panel.',
      seo:            'Extra on-page SEO work — deeper keyword targeting, meta descriptions and content structure.',
      speed:          'A dedicated performance pass — image optimization, caching, and load-time improvements.',
      rush:           'Priority scheduling to get your project delivered faster. Adds 50% to the base price for your tier.',
      schema:         'Structured data that helps Google show richer results — ratings, prices or business info — for your site.',
      consent:        'A cookie consent banner with granular opt-in, needed if the site uses tracking or analytics.',
      a11ystatement:  'A published accessibility statement describing your site\'s compliance level and how to report issues.',
      monitoring:     'Automated uptime and error monitoring, with an email alert if the site goes down or breaks.',
      cdn:            'Serves images, fonts and other files from a nearby server, so the site loads faster worldwide.'
    };

    function getDescription(it) {
      return it.description || ADDON_DESCRIPTIONS[it.k] || '';
    }

    function closeAllTooltips(except) {
      document.querySelectorAll('.info-wrap.show').forEach(function (w) {
        if (w !== except) w.classList.remove('show');
      });
    }
    // Close any open tooltip when clicking elsewhere, or on Escape.
    document.addEventListener('click', function () { closeAllTooltips(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeAllTooltips();
    });

    function renderCategories() {
      catsEl.innerHTML = '';
      CATEGORIES.forEach(function (cat) {
        var block = document.createElement('div');
        block.className = 'calc-category';
        var h3cat = document.createElement('h3');
        h3cat.textContent = cat.title;
        block.appendChild(h3cat);

        (cat.items || []).forEach(function (it) {
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

          var description = getDescription(it);
          if (description) {
            var infoWrap = document.createElement('span');
            infoWrap.className = 'info-wrap';

            var infoBtn = document.createElement('button');
            infoBtn.type = 'button';
            infoBtn.className = 'info-btn';
            infoBtn.textContent = '?';
            infoBtn.setAttribute('aria-label', 'What does ' + it.label + ' include?');

            var tooltip = document.createElement('span');
            tooltip.className = 'info-tooltip';
            tooltip.id = 'info-' + it.k;
            tooltip.setAttribute('role', 'tooltip');
            tooltip.textContent = description;
            infoBtn.setAttribute('aria-describedby', tooltip.id);

            infoBtn.addEventListener('click', function (e) {
              e.stopPropagation();
              var willShow = !infoWrap.classList.contains('show');
              closeAllTooltips(infoWrap);
              infoWrap.classList.toggle('show', willShow);
            });

            infoWrap.appendChild(infoBtn);
            infoWrap.appendChild(tooltip);
            row.appendChild(infoWrap);
          }

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
            if (e.target.closest('.info-wrap')) return; // let the info button handle its own click
            if (e.target !== chk) toggle();
          });
        });

        catsEl.appendChild(block);
      });
    }

    function selectedAddonCount() {
      return Object.keys(calcState.items).filter(function (k) { return calcState.items[k]; }).length;
    }

    function selectedAddonLabels() {
      var labels = [];
      CATEGORIES.forEach(function (cat) {
        (cat.items || []).forEach(function (it) {
          if (calcState.items[it.k]) labels.push(it.label);
        });
      });
      return labels;
    }

    function tierFullName(tierKey) {
      var tier = TIERS.find(function (t) { return t.key === tierKey; });
      return tier ? tier.fullName : '';
    }

    function updateSummary() {
      if (!calcState.tier) {
        estimateValueEl.textContent = 'Select a project size above';
        getQuoteBtn.disabled = true;
        return;
      }

      var tierName = tierFullName(calcState.tier);
      var count = selectedAddonCount();

      estimateValueEl.textContent = count === 0
        ? tierName
        : tierName + ' + ' + count + ' add-on' + (count === 1 ? '' : 's');

      getQuoteBtn.disabled = false;
    }

    getQuoteBtn.addEventListener('click', function () {
      if (!calcState.tier) return;

      var tierName = tierFullName(calcState.tier);
      var addons = selectedAddonLabels();
      var addonKeys = Object.keys(calcState.items).filter(function (k) { return calcState.items[k]; });
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
        '&details=' + encodeURIComponent(detailLines.join('\n')) +
        '&tier=' + encodeURIComponent(calcState.tier) +
        '&addons=' + encodeURIComponent(addonKeys.join(','));
      window.location.href = url;
    });
  }

  /* ---------- Configurator page: maintenance & support plans (Step 3) ----------
     Plans are managed from the admin panel and loaded live from
     /api/maintenance.php — rename API_URL below if your endpoint differs.
     Unlike the project configurator, real prices ARE shown here on purpose. */
  var maintGrid = document.getElementById('maintGrid');
  if (maintGrid) {

    var MAINT_API_URL = '/api/maintenance.php';

    // Fallback figures if the endpoint isn't reachable — keep in sync with the admin panel.
    var MAINT_FALLBACK = [
      {
        plan_key: 'hourly', name: 'Hourly', plan_type: 'hourly',
        hours_included: null, price_kc: 750, response_time_text: '1–2 business days',
        features: ['No commitment, no monthly fee', 'Billed in 15-minute increments', 'Invoiced after work is completed']
      },
      {
        plan_key: 'starter', name: 'Starter', plan_type: 'retainer',
        hours_included: 2, price_kc: 1300, response_time_text: '1–2 business days',
        features: ["Unused hours don't roll over", 'Overage billed at the standard hourly rate']
      },
      {
        plan_key: 'standard', name: 'Standard', plan_type: 'retainer',
        hours_included: 5, price_kc: 3000, response_time_text: '1–2 business days',
        features: ["Unused hours don't roll over", 'Overage billed at the standard hourly rate']
      },
      {
        plan_key: 'priority', name: 'Priority', plan_type: 'retainer',
        hours_included: 10, price_kc: 5500, response_time_text: '24 hours',
        features: ["Unused hours don't roll over", 'Overage billed at the standard hourly rate', 'Faster response time']
      }
    ];

    fetch(MAINT_API_URL)
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        if (data.error) throw new Error(data.error);
        var plans = data.plans || data;
        if (!plans || !plans.length) throw new Error('empty');
        renderMaintenancePlans(plans);
      })
      .catch(function (err) {
        console.error('Failed to load maintenance plans, showing fallback rates:', err);
        renderMaintenancePlans(MAINT_FALLBACK);
      });

    function formatKc(n) {
      return Number(n).toLocaleString('cs-CZ') + ' Kč';
    }

    function renderMaintenancePlans(plans) {
      maintGrid.innerHTML = '';
      plans.forEach(function (p) {
        var featuresHtml = (p.features || []).map(function (f) {
          return '<li>' + escapeHtml(f) + '</li>';
        }).join('');

        var hoursHtml = p.hours_included
          ? '<div class="maint-hours">' + p.hours_included + ' hour' + (p.hours_included > 1 ? 's' : '') + ' included / month</div>'
          : '';
        var responseHtml = p.response_time_text
          ? '<div class="maint-response">Response time: ' + escapeHtml(p.response_time_text) + '</div>'
          : '';

        var card = document.createElement('div');
        card.className = 'maint-card' + (p.plan_key === 'priority' ? ' priority' : '');
        card.innerHTML =
          '<div class="tier-tag">' + (p.plan_type === 'hourly' ? 'Pay as you go' : 'Monthly plan') + '</div>' +
          '<h3>' + escapeHtml(p.name) + '</h3>' +
          '<div class="maint-price">' + formatKc(p.price_kc) +
          '<small>' + (p.plan_type === 'hourly' ? 'per hour' : 'per month') + '</small></div>' +
          hoursHtml + responseHtml +
          '<ul class="maint-features">' + featuresHtml + '</ul>' +
          '<a class="btn ghost small" href="contact.html?type=Maintenance&plan=' + encodeURIComponent(p.plan_key) + '">' +
          (p.plan_type === 'hourly' ? 'Book hourly support' : 'Choose ' + escapeHtml(p.name)) + '</a>';

        maintGrid.appendChild(card);
      });
    }
  }

  /* ---------- Portfolio page: load projects from the API ---------- */
  var projGrid = document.getElementById('projGrid');
  if (projGrid) {
    fetch('/api/portfolio.php')
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        if (data.error) throw new Error(data.error);
        renderProjects(data.projects || []);
      })
      .catch(function (err) {
        console.error('Failed to load portfolio:', err);
        projGrid.innerHTML = '<p class="proj-loading">Couldn\'t load the portfolio right now — please refresh.</p>';
      });

    function renderProjects(projects) {
      projGrid.innerHTML = '';
      if (!projects.length) {
        projGrid.innerHTML = '<p class="proj-loading">More work coming soon.</p>';
        return;
      }
      projects.forEach(function (p) {
        var tagsHtml = (p.tags || []).map(function (t) {
          return '<span>' + escapeHtml(t) + '</span>';
        }).join('');

        var proj = document.createElement('div');
        proj.className = 'proj';
        proj.innerHTML =
          '<div class="proj-thumb"><img src="' + escapeAttr(p.image) + '" alt="' +
          escapeAttr(p.title) + '" loading="lazy" decoding="async"></div>' +
          '<div class="proj-body">' +
          '<div class="proj-tags">' + tagsHtml + '</div>' +
          '<h3>' + escapeHtml(p.title) + '</h3>' +
          '<p>' + escapeHtml(p.description) + '</p>' +
          '<a class="btn small" href="contact.html?type=' + encodeURIComponent(p.contactType || p.title) +
          '">' + escapeHtml(p.ctaText || 'I want something like this') + '</a>' +
          '</div>';
        projGrid.appendChild(proj);
      });
    }
  }

  /* ---------- Small helpers shared by the dynamic renderers above ---------- */
  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function escapeAttr(str) {
    return escapeHtml(str);
  }

});
