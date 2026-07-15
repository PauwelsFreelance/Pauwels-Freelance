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

});
