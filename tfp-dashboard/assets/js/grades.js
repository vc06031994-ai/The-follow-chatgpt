/**
 * TFP Dashboard — Grades Page Interactive Features
 * 1. Filter grades table by week without page reload
 * 2. Section Mastery pagination (< 1-3 of 12 >) without page reload
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initWeekFilter();
        initMasteryPagination();
        initSectionMasteryInteractions();
    });

    /**
     * Week filter dropdown for Grades table
     */
    function initWeekFilter() {
        var weekSelect = document.getElementById('tfp-grades-week-select');
        var rows = document.querySelectorAll('.tfp-grade-row');

        if (!weekSelect || !rows.length) return;

        weekSelect.addEventListener('change', function () {
            var val = this.value;

            rows.forEach(function (row) {
                var rowWeek = row.getAttribute('data-week-num');
                if (val === 'all' || val === rowWeek) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            var scrollContainer = document.querySelector('.tfp-grades-table-scroll');
            if (scrollContainer) {
                scrollContainer.scrollTop = 0;
            }
        });
    }

    /**
     * Section Mastery pagination controls (< and >)
     */
    function initMasteryPagination() {
        var prevBtn = document.getElementById('tfpMasteryPrev');
        var nextBtn = document.getElementById('tfpMasteryNext');
        var counter = document.getElementById('tfpMasteryCounter');
        var cards = document.querySelectorAll('.tfp-section-card');

        if (!prevBtn || !nextBtn || !counter || !cards.length) return;

        var total = cards.length;
        var currentPage = 1;

        function getPageSize() {
            // Mobile shows 3 cards, Desktop shows 9 cards
            if (window.innerWidth <= 768) {
                return 3;
            }
            return 9;
        }

        function updateView() {
            var pageSize = getPageSize();
            var totalPages = Math.ceil(total / pageSize);

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }
            if (currentPage < 1) {
                currentPage = 1;
            }

            var startIdx = (currentPage - 1) * pageSize;
            var endIdx = Math.min(startIdx + pageSize, total);

            cards.forEach(function (card, index) {
                if (index >= startIdx && index < endIdx) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });

            // Update counter text (e.g. "1-3 of 12" on mobile, "1-9 of 12" on desktop)
            counter.textContent = (startIdx + 1) + '-' + endIdx + ' of ' + total;

            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
        }

        prevBtn.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                updateView();
            }
        });

        nextBtn.addEventListener('click', function () {
            var pageSize = getPageSize();
            var totalPages = Math.ceil(total / pageSize);
            if (currentPage < totalPages) {
                currentPage++;
                updateView();
            }
        });

        window.addEventListener('resize', function () {
            updateView();
        });

        // Initialize view
        updateView();
    }

    /**
     * Section Mastery Detail View Interactions
     * Handles instant transition between Section Overview and Section Mastery Detail
     */
    function initSectionMasteryInteractions() {
        var dataScript = document.getElementById('tfpMasteryDataJSON');
        if (!dataScript) return;

        var allData = {};
        try {
            allData = JSON.parse(dataScript.textContent);
        } catch (e) {
            console.error('Error parsing tfpMasteryDataJSON', e);
            return;
        }

        var overviewHeader = document.getElementById('tfpGradesOverviewHeader');
        var masteryHeader = document.getElementById('tfpGradesMasteryHeader');
        var overviewView = document.getElementById('tfpGradesOverview');
        var masteryView = document.getElementById('tfpGradesMasteryDetail');
        var backBtn = document.getElementById('tfpBtnBackSections');
        var backLinks = document.querySelectorAll('.tfp-btn-back-sections-crumb');
        var sectionCards = document.querySelectorAll('.tfp-section-card');

        if (!overviewView || !masteryView) return;

        function showMasteryView(sectionId, updateUrl) {
            var data = allData[sectionId];
            if (!data) return;

            renderMasteryDetail(data);

            if (overviewHeader) overviewHeader.style.display = 'none';
            if (masteryHeader) masteryHeader.style.display = '';
            overviewView.style.display = 'none';
            masteryView.style.display = '';

            if (updateUrl !== false) {
                var url = new URL(window.location.href);
                url.searchParams.set('mastery', sectionId);
                history.pushState({ view: 'mastery', section: sectionId }, '', url.toString());
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showOverviewView(updateUrl) {
            if (masteryHeader) masteryHeader.style.display = 'none';
            if (overviewHeader) overviewHeader.style.display = '';
            masteryView.style.display = 'none';
            overviewView.style.display = '';

            if (updateUrl !== false) {
                var url = new URL(window.location.href);
                url.searchParams.delete('mastery');
                url.searchParams.delete('section');
                history.pushState({ view: 'overview' }, '', url.toString());
            }

            var scrollTarget = document.getElementById('tfpMasterySectionWrap');
            if (scrollTarget) {
                scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function renderMasteryDetail(data) {
            // Update Title & Subtitle & Crumb
            var crumbEl = document.getElementById('tfpMasteryCrumbCurrent');
            if (crumbEl) crumbEl.textContent = data.crumb_section;

            var titleNameEl = document.getElementById('tfpMasteryTitleName');
            if (titleNameEl) titleNameEl.textContent = data.title;

            var subEl = document.getElementById('tfpMasterySubtitleText');
            if (subEl) subEl.textContent = data.subtitle;

            // Benchmarks
            var benchmarksContainer = document.getElementById('tfpMasteryBenchmarks');
            if (benchmarksContainer && data.benchmarks) {
                benchmarksContainer.innerHTML = data.benchmarks.map(function (b, idx) {
                    var activeCls = idx === 0 ? ' is-active' : '';
                    return '<div class="tfp-benchmark-pill' + activeCls + '" data-code="' + escapeHtml(b.code) + '">' +
                        '<span class="tfp-benchmark-pill__code">' + escapeHtml(b.code) + '</span>' +
                        '<span class="tfp-benchmark-pill__title">' + escapeHtml(b.title) + '</span>' +
                        '<span class="tfp-benchmark-pill__score">' + escapeHtml(b.score) + '</span>' +
                        '</div>';
                }).join('');

                // Attach pill click
                benchmarksContainer.querySelectorAll('.tfp-benchmark-pill').forEach(function (pill) {
                    pill.addEventListener('click', function () {
                        benchmarksContainer.querySelectorAll('.tfp-benchmark-pill').forEach(function (p) {
                            p.classList.remove('is-active');
                        });
                        pill.classList.add('is-active');
                    });
                });
            }

            // 4 Metrics
            var m = data.metrics || {};
            setElementText('tfpMetricMasteryPct', (m.mastery_pct || 0) + '%');
            setElementText('tfpMetricMasterySub', m.mastery_sub || '');
            setElementText('tfpMetricConceptsRatio', m.concepts_ratio || '');
            setElementText('tfpMetricConceptsSub', m.concepts_sub || '');
            setElementText('tfpMetricQuizAvg', (m.quiz_avg || 0) + '%');
            setElementText('tfpMetricQuizSub', m.quiz_sub || '');
            setElementText('tfpMetricTestScore', (m.test_score || 0) + '%');
            setElementText('tfpMetricTestSub', m.test_sub || '');

            // Know Well Titles
            var dynTitles = document.querySelectorAll('.tfp-dyn-section-title');
            dynTitles.forEach(function (t) {
                t.textContent = data.title;
            });

            // Know Well list
            var knowWellList = document.getElementById('tfpKnowWellList');
            if (knowWellList && data.know_well) {
                knowWellList.innerHTML = data.know_well.map(function (kw) {
                    return '<div class="tfp-concept-bar-row">' +
                        '<span class="tfp-concept-bar-row__label">' + escapeHtml(kw.label) + '</span>' +
                        '<div class="tfp-concept-bar-row__meter">' +
                        '<div class="tfp-concept-bar-track">' +
                        '<div class="tfp-concept-bar-fill tfp-concept-bar-fill--teal" style="width: ' + kw.pct + '%;"></div>' +
                        '</div>' +
                        '<span class="tfp-concept-bar-row__pct tfp-concept-bar-row__pct--teal">' + kw.pct + '%</span>' +
                        '</div>' +
                        '</div>';
                }).join('');
            }

            // Concepts to Review
            var reviewList = document.getElementById('tfpReviewList');
            if (reviewList && data.review_concepts) {
                reviewList.innerHTML = data.review_concepts.map(function (rc) {
                    var col = rc.color_class || 'red';
                    return '<div class="tfp-concept-bar-row">' +
                        '<span class="tfp-concept-bar-row__label">' + escapeHtml(rc.label) + '</span>' +
                        '<div class="tfp-concept-bar-row__meter">' +
                        '<div class="tfp-concept-bar-track">' +
                        '<div class="tfp-concept-bar-fill tfp-concept-bar-fill--' + col + '" style="width: ' + rc.pct + '%;"></div>' +
                        '</div>' +
                        '<span class="tfp-concept-bar-row__pct tfp-concept-bar-row__pct--' + col + '">' + rc.pct + '%</span>' +
                        '</div>' +
                        '</div>';
                }).join('');
            }

            // Suggested Review
            var suggList = document.getElementById('tfpSuggestedReviewItems');
            if (suggList && data.suggested_review) {
                suggList.innerHTML = data.suggested_review.map(function (sr) {
                    return '<a href="' + escapeHtml(sr.url) + '" class="tfp-suggested-review__item">' +
                        '<span class="tfp-suggested-review__icon" aria-hidden="true">' + escapeHtml(sr.icon) + '</span>' +
                        '<span class="tfp-suggested-review__text">' + escapeHtml(sr.text) + '</span>' +
                        '</a>';
                }).join('');
            }

            // Weekly Grades Table
            var gradesBody = document.getElementById('tfpMasteryWeeklyGradesBody');
            if (gradesBody && data.weekly_grades) {
                gradesBody.innerHTML = data.weekly_grades.map(function (wg) {
                    var wkPadded = (wg.wk < 10 ? '0' : '') + wg.wk;
                    return '<tr>' +
                        '<td class="tfp-col-wk">' + wkPadded + '</td>' +
                        '<td class="tfp-col-lesson">' + escapeHtml(wg.lesson) + '</td>' +
                        '<td class="tfp-col-badge">' + renderCircleBadge(wg.hw) + '</td>' +
                        '<td class="tfp-col-badge">' + renderCircleBadge(wg.quiz) + '</td>' +
                        '<td class="tfp-col-badge">' + renderCircleBadge(wg.test) + '</td>' +
                        '</tr>';
                }).join('');
            }

            // Performance Trends
            var trendsList = document.getElementById('tfpTrendsList');
            if (trendsList && data.trends) {
                trendsList.innerHTML = data.trends.map(function (tr) {
                    var col = tr.color_class || 'teal';
                    return '<div class="tfp-trend-row">' +
                        '<div class="tfp-trend-row__head">' +
                        '<span class="tfp-trend-row__label">' + escapeHtml(tr.label) + '</span>' +
                        '<div class="tfp-trend-row__stats">' +
                        '<span class="tfp-trend-row__pct tfp-trend-row__pct--' + col + '">' + tr.pct + '%</span>' +
                        '<span class="tfp-trend-row__status tfp-trend-row__status--' + col + '">' + escapeHtml(tr.status) + '</span>' +
                        '</div>' +
                        '</div>' +
                        '<div class="tfp-trend-track">' +
                        '<div class="tfp-trend-fill tfp-trend-fill--' + col + '" style="width: ' + tr.pct + '%;"></div>' +
                        '</div>' +
                        '</div>';
                }).join('');
            }
        }

        function setElementText(id, text) {
            var el = document.getElementById(id);
            if (el) el.textContent = text;
        }

        function renderCircleBadge(letter) {
            if (!letter || letter === '—') {
                return '<span class="tfp-grade-circle tfp-grade-circle--empty">—</span>';
            }
            var l = letter.toUpperCase();
            var cls = 'tfp-grade-circle--default';
            if (l === 'A') cls = 'tfp-grade-circle--a';
            else if (l === 'B') cls = 'tfp-grade-circle--b';
            else if (l === 'C') cls = 'tfp-grade-circle--c';
            else if (l === 'D') cls = 'tfp-grade-circle--d';
            else if (l === 'F') cls = 'tfp-grade-circle--f';
            return '<span class="tfp-grade-circle ' + cls + '">' + l + '</span>';
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // Section Cards click event
        sectionCards.forEach(function (card) {
            card.addEventListener('click', function (e) {
                if (e) e.preventDefault();
                var sIdx = parseInt(card.getAttribute('data-section-index'), 10);
                if (sIdx >= 1) {
                    showMasteryView(sIdx, true);
                }
            });
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    var sIdx = parseInt(card.getAttribute('data-section-index'), 10);
                    if (sIdx >= 1) {
                        showMasteryView(sIdx, true);
                    }
                }
            });
        });

        // Back button event
        if (backBtn) {
            backBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showOverviewView(true);
            });
        }

        backLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                showOverviewView(true);
            });
        });

        // Handle Browser Back/Forward navigation
        window.addEventListener('popstate', function () {
            var url = new URL(window.location.href);
            var mParam = url.searchParams.get('mastery') || url.searchParams.get('section');
            if (mParam) {
                var sIdx = parseInt(mParam, 10);
                if (sIdx >= 1 && allData[sIdx]) {
                    showMasteryView(sIdx, false);
                    return;
                }
            }
            showOverviewView(false);
        });

        // Initial check on load
        var initialUrl = new URL(window.location.href);
        var initialMastery = initialUrl.searchParams.get('mastery') || initialUrl.searchParams.get('section');
        if (initialMastery) {
            var initId = parseInt(initialMastery, 10);
            if (initId >= 1 && allData[initId]) {
                showMasteryView(initId, false);
            }
        }
    }
})();
