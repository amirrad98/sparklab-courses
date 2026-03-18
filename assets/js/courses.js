/**
 * SparkLab Courses — Quiz, progress tracking, UI interactions.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'sparklab-courses-progress';

  /* ─── Progress (localStorage) ─── */

  function getProgress() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
    } catch (e) {
      return {};
    }
  }

  function saveProgress(progress) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(progress));
    } catch (e) { /* ignore */ }
  }

  function markComplete(slug) {
    var progress = getProgress();
    progress[slug] = true;
    saveProgress(progress);
  }

  function isComplete(slug) {
    return !!getProgress()[slug];
  }

  /* ─── Update UI based on progress ─── */

  function updateProgressUI() {
    var progress = getProgress();

    // Archive page: update course card statuses.
    document.querySelectorAll('.slc-course-status[data-slug]').forEach(function (el) {
      var slug = el.getAttribute('data-slug');
      if (progress[slug]) {
        el.classList.add('is-complete');
        var textEl = el.querySelector('.slc-status-text');
        if (textEl) textEl.textContent = 'Completed';
      }
    });

    // Archive page: update course card styling.
    document.querySelectorAll('.slc-course-card[data-course-slug]').forEach(function (card) {
      var slug = card.getAttribute('data-course-slug');
      if (progress[slug]) {
        card.classList.add('completed');
      }
    });

    // Sidebar: show checkmarks for completed courses.
    document.querySelectorAll('.slc-sidebar-check[data-slug]').forEach(function (el) {
      var slug = el.getAttribute('data-slug');
      if (progress[slug]) {
        el.style.display = '';
      }
    });

    // Progress bar.
    var pctEl = document.getElementById('slc-progress-pct');
    var fillEl = document.getElementById('slc-progress-fill');
    if (pctEl && fillEl) {
      // Count total courses from sidebar buttons (top-level only).
      var courseBtns = document.querySelectorAll('.slc-module-btn:not(.slc-module-btn--child)[data-course-slug]');
      var total = courseBtns.length;
      var done = 0;
      courseBtns.forEach(function (btn) {
        if (progress[btn.getAttribute('data-course-slug')]) done++;
      });
      var pct = total > 0 ? Math.round((done / total) * 100) : 0;
      pctEl.textContent = pct + '%';
      fillEl.style.width = pct + '%';
    }

    // Cert banner on archive page.
    var banner = document.getElementById('slc-cert-banner');
    if (banner) {
      var slugs = (banner.getAttribute('data-course-slugs') || '').split(',').filter(Boolean);
      var allDone = slugs.length > 0 && slugs.every(function (s) { return progress[s]; });
      banner.style.display = allDone ? '' : 'none';
    }
  }

  /* ─── Quiz handling ─── */

  function initQuiz() {
    var quizEl = document.getElementById('slc-quiz');
    if (!quizEl) return;

    var courseSlug = quizEl.getAttribute('data-course-slug');
    var correct = parseInt(quizEl.getAttribute('data-correct'), 10);
    var feedbackArea = document.getElementById('slc-quiz-feedback');
    var options = quizEl.querySelectorAll('.slc-quiz-option');
    var answered = false;

    // If already completed, disable quiz.
    if (isComplete(courseSlug)) {
      answered = true;
      options.forEach(function (btn) {
        btn.disabled = true;
        var idx = parseInt(btn.getAttribute('data-option'), 10);
        if (idx === correct) {
          btn.classList.add('correct');
          btn.querySelector('.slc-quiz-indicator').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
        }
      });
      feedbackArea.innerHTML = buildFeedback(true, "You've already completed this assessment.");
      return;
    }

    options.forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (answered) return;

        var selected = parseInt(this.getAttribute('data-option'), 10);
        var isCorrect = selected === correct;

        // Disable all options.
        options.forEach(function (b) { b.disabled = true; });
        answered = true;

        // Highlight selected.
        if (isCorrect) {
          this.classList.add('correct');
          this.querySelector('.slc-quiz-indicator').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
          markComplete(courseSlug);
          updateProgressUI();
        } else {
          this.classList.add('incorrect');
          this.querySelector('.slc-quiz-indicator').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
          // Allow retry after a delay.
          setTimeout(function () {
            options.forEach(function (b) {
              b.disabled = false;
              b.classList.remove('incorrect');
              b.querySelector('.slc-quiz-indicator').innerHTML = '';
            });
            answered = false;
            feedbackArea.innerHTML = '';
          }, 2000);
        }

        feedbackArea.innerHTML = buildFeedback(
          isCorrect,
          isCorrect
            ? "Correct! You've mastered this course."
            : 'Incorrect. Please review the module material and try again.'
        );
      });
    });
  }

  function buildFeedback(success, message) {
    var cls = success ? 'success' : 'error';
    var icon = success
      ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>'
      : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
    var status = success ? 'Status: Approved' : 'Status: Rejected';

    return '<div class="slc-quiz-feedback ' + cls + '">' +
      '<span class="slc-quiz-feedback-icon">' + icon + '</span>' +
      '<div>' +
      '<div class="slc-quiz-feedback-status">' + status + '</div>' +
      '<div class="slc-quiz-feedback-msg">' + message + '</div>' +
      '</div></div>';
  }

  /* ─── Mobile sidebar toggle ─── */

  function initMobileNav() {
    var toggle = document.getElementById('slc-mobile-nav-toggle');
    var sidebar = document.getElementById('slc-sidebar');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });

    // Close sidebar when clicking outside.
    document.addEventListener('click', function (e) {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

  /* ─── Lite YouTube embeds ─── */

  function initLiteVideos() {
    document.querySelectorAll('.slc-lite-video[data-youtube-src]').forEach(function (shell) {
      var button = shell.querySelector('.slc-lite-video__button');
      if (!button) return;

      button.addEventListener('click', function () {
        if (shell.classList.contains('is-loaded')) return;

        var src = shell.getAttribute('data-youtube-src');
        if (!src) return;

        var iframe = document.createElement('iframe');
        iframe.src = src + (src.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1';
        iframe.title = shell.getAttribute('data-youtube-title') || 'YouTube video';
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('loading', 'lazy');
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');

        shell.innerHTML = '';
        shell.appendChild(iframe);
        shell.classList.add('is-loaded');
      }, { once: true });
    });
  }

  /* ─── Init ─── */
  updateProgressUI();
  initQuiz();
  initMobileNav();
  initLiteVideos();

})();
