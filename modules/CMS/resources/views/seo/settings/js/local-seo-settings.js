/**
 * Local SEO Settings Manager
 * Handles UI interactions, schema preview, dynamic business hours, and SEO strength scoring.
 */

(function () {
    // ========================================
    // SEO Strength Score Configuration
    // ========================================

    // Field weights for Organization type (total: 100 points)
    const FIELD_WEIGHTS_ORGANIZATION = {
        // Basic Info - 35 points (most important for schema)
        basic: {
            name: 10, // Required, high impact
            description: 8, // Important for search
            logo_image: 7, // Visual branding
            url: 5, // Website link
            business_type: 5, // Schema categorization
        },
        // Contact - 15 points
        contact: {
            phone: 8, // Critical for local SEO
            email: 7, // Business contact
        },
        // Address - 25 points (very important for local)
        address: {
            street_address: 6,
            locality: 6, // City
            region: 4, // State
            postal_code: 5,
            country_code: 4,
        },
        // Business Hours - 10 points
        hours: {
            has_hours: 10, // Either 24/7 or custom hours set
        },
        // Social - 15 points (5 platforms, 3 points each)
        social: {
            facebook_url: 3,
            twitter_url: 3,
            linkedin_url: 3,
            instagram_url: 3,
            youtube_url: 3,
        },
    };

    // Field weights for Person type (total: 100 points, redistributed)
    const FIELD_WEIGHTS_PERSON = {
        // Basic Info - 45 points
        basic: {
            name: 15,
            description: 12,
            logo_image: 10,
            url: 8,
        },
        // Contact - 25 points
        contact: {
            phone: 12,
            email: 13,
        },
        // Address - 15 points (less critical for persons)
        address: {
            locality: 5,
            region: 4,
            country_code: 6,
        },
        // Social - 15 points
        social: {
            facebook_url: 3,
            twitter_url: 3,
            linkedin_url: 3,
            instagram_url: 3,
            youtube_url: 3,
        },
    };

    // Grade thresholds and messages
    const GRADES = [
        {
            min: 90,
            grade: 'A',
            color: '#198754',
            stroke: '#198754',
            message: 'Excellent! Your Local SEO is fully optimized.',
        },
        {
            min: 75,
            grade: 'B',
            color: '#20c997',
            stroke: '#20c997',
            message: 'Great job! Just a few more details to perfect it.',
        },
        {
            min: 50,
            grade: 'C',
            color: '#ffc107',
            stroke: '#ffc107',
            message: 'Good start! Add more info to improve visibility.',
        },
        {
            min: 25,
            grade: 'D',
            color: '#fd7e14',
            stroke: '#fd7e14',
            message: 'Keep going! More details will help search engines.',
        },
        {
            min: 0,
            grade: 'F',
            color: '#dc3545',
            stroke: '#dc3545',
            message: 'Start filling out your business information.',
        },
    ];

    // Debounce timer
    let scoreDebounceTimer = null;

    // ========================================
    // Helper Functions
    // ========================================

    function parseJsonSafe(value, fallback = []) {
        try {
            if (!value) return fallback;
            return JSON.parse(value);
        } catch (e) {
            return fallback;
        }
    }

    function getOpeningDays(form) {
        const daysAttr = form?.dataset?.openingDays || document.getElementById('hours-list')?.dataset?.openingDays;
        return parseJsonSafe(daysAttr, []);
    }

    function debounce(func, wait) {
        return function executedFunction(...args) {
            clearTimeout(scoreDebounceTimer);
            scoreDebounceTimer = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function getFieldValue(id) {
        const el = document.getElementById(id);
        if (!el) return '';
        if (el.type === 'checkbox') return el.checked ? 'true' : '';
        return el.value?.trim() || '';
    }

    function getFormFieldValue(form, name) {
        const el = form?.querySelector(`[name="${name}"]`);
        if (!el) return '';
        return el.value?.trim() || '';
    }

    function hasBusinessHours() {
        const is247 = document.getElementById('is_opening_hour_24_7')?.checked;
        if (is247) return true;

        // Check if at least one hour row has times filled
        const openingHours = document.querySelectorAll('[name="opening_hours[]"]');
        const closingHours = document.querySelectorAll('[name="closing_hours[]"]');

        for (let i = 0; i < openingHours.length; i++) {
            if (openingHours[i]?.value && closingHours[i]?.value) {
                return true;
            }
        }
        return false;
    }

    function createHourRowHtml(days) {
        return `
            <div class="hour-row row g-2 mb-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-1">Day</label>
                    <select class="form-select form-select-sm" name="opening_hour_day[]">
                        ${days.map((d) => `<option value="${d}">${d}</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Opens</label>
                    <input type="time" class="form-control form-control-sm" name="opening_hours[]">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Closes</label>
                    <input type="time" class="form-control form-control-sm" name="closing_hours[]">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeHourRow(this)">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </div>
        `;
    }

    // ========================================
    // SEO Score Calculation
    // ========================================

    function calculateSeoScore(form) {
        const isOrganization = document.getElementById('type')?.value === 'Organization';
        const weights = isOrganization ? FIELD_WEIGHTS_ORGANIZATION : FIELD_WEIGHTS_PERSON;

        let totalScore = 0;
        let earnedScore = 0;
        const sectionScores = {};

        // Calculate each section
        for (const [section, fields] of Object.entries(weights)) {
            let sectionTotal = 0;
            let sectionEarned = 0;
            let sectionFilled = 0;
            let sectionCount = Object.keys(fields).length;

            for (const [field, weight] of Object.entries(fields)) {
                sectionTotal += weight;

                let hasValue = false;

                // Special handling for hours
                if (field === 'has_hours') {
                    hasValue = hasBusinessHours();
                }
                // Social URLs are in form, not ids
                else if (section === 'social') {
                    hasValue = !!getFormFieldValue(form, field);
                }
                // Standard fields
                else {
                    hasValue = !!getFieldValue(field);
                }

                if (hasValue) {
                    sectionEarned += weight;
                    sectionFilled++;
                }
            }

            sectionScores[section] = {
                filled: sectionFilled,
                total: sectionCount,
                earnedPoints: sectionEarned,
                totalPoints: sectionTotal,
            };

            totalScore += sectionTotal;
            earnedScore += sectionEarned;
        }

        const percentage = totalScore > 0 ? Math.round((earnedScore / totalScore) * 100) : 0;
        const gradeInfo = GRADES.find((g) => percentage >= g.min) || GRADES[GRADES.length - 1];

        return {
            percentage,
            earnedScore,
            totalScore,
            grade: gradeInfo.grade,
            color: gradeInfo.color,
            stroke: gradeInfo.stroke,
            message: gradeInfo.message,
            sections: sectionScores,
            isOrganization,
        };
    }

    function updateScoreUI(scoreData) {
        // Update circular progress ring
        const progressRing = document.querySelector('.seo-score-ring-progress');
        const scoreValue = document.getElementById('seo-score-value');
        const scoreBadge = document.getElementById('seo-score-badge');
        const scoreMessage = document.getElementById('seo-score-message');

        if (progressRing) {
            // Circle circumference = 2 * π * r = 2 * 3.14159 * 60 ≈ 377
            const circumference = 377;
            const offset = circumference - (scoreData.percentage / 100) * circumference;
            progressRing.style.strokeDashoffset = offset;
            progressRing.style.stroke = scoreData.stroke;
        }

        if (scoreValue) {
            scoreValue.textContent = scoreData.percentage;
            // Remove all color classes and add appropriate one
            scoreValue.className = 'seo-score-value seo-score-' + scoreData.grade.toLowerCase();
        }

        if (scoreBadge) {
            scoreBadge.textContent = scoreData.grade;
            // Update badge color based on grade
            scoreBadge.className = 'badge fs-6';
            switch (scoreData.grade) {
                case 'A':
                    scoreBadge.classList.add('bg-success');
                    break;
                case 'B':
                    scoreBadge.classList.add('bg-success-subtle', 'text-success');
                    break;
                case 'C':
                    scoreBadge.classList.add('bg-warning-subtle', 'text-warning');
                    break;
                case 'D':
                    scoreBadge.classList.add('bg-warning');
                    break;
                default:
                    scoreBadge.classList.add('bg-danger');
                    break;
            }
        }

        if (scoreMessage) {
            scoreMessage.textContent = scoreData.message;
        }

        // Update section badges
        for (const [section, data] of Object.entries(scoreData.sections)) {
            const badge = document.getElementById(`section-badge-${section}`);
            if (badge) {
                badge.textContent = `${data.filled}/${data.total}`;

                // Update badge color
                badge.className = 'badge section-badge';
                if (data.filled === 0) {
                    badge.classList.add('bg-light', 'text-muted');
                } else if (data.filled === data.total) {
                    badge.classList.add('bg-success-subtle', 'text-success');
                } else {
                    badge.classList.add('bg-warning-subtle', 'text-warning');
                }
            }
        }
    }

    // Debounced score update function
    const debouncedScoreUpdate = debounce(function (form) {
        const scoreData = calculateSeoScore(form);
        updateScoreUI(scoreData);
    }, 300);

    // ========================================
    // Main Initialization
    // ========================================

    function initializeLocalSeoSettings() {
        const form = document.getElementById('local-seo-form');
        if (!form || form.dataset.initialized === 'true') return;
        form.dataset.initialized = 'true';

        const schemaSwitch = document.getElementById('is_schema');
        const typeSelect = document.getElementById('type');
        const is247Switch = document.getElementById('is_opening_hour_24_7');
        const fieldsContainer = document.getElementById('local-seo-fields');
        const sidebar = document.getElementById('local-seo-sidebar');
        const orgFields = document.querySelectorAll('.organization-field');

        function toggleFields() {
            const show = schemaSwitch?.checked;
            if (fieldsContainer) fieldsContainer.style.display = show ? 'block' : 'none';
            if (sidebar) sidebar.style.display = show ? 'block' : 'none';
            if (show) window.updatePreview?.();
        }

        function toggleOrgFields() {
            const isOrg = typeSelect?.value === 'Organization';
            orgFields.forEach((el) => (el.style.display = isOrg ? '' : 'none'));
        }

        function toggleHoursFields() {
            const is247 = is247Switch?.checked;
            const hoursList = document.getElementById('hours-list');
            if (hoursList) {
                hoursList.style.display = is247 ? 'none' : 'block';
            }
        }

        window.updatePreview = function () {
            const previewCode = document.getElementById('preview-code');
            if (!previewCode) return;

            const schema = {
                '@context': 'https://schema.org',
                '@type':
                    typeSelect?.value === 'Person'
                        ? 'Person'
                        : document.getElementById('business_type')?.value || 'LocalBusiness',
                name: document.getElementById('name')?.value || '',
                url: document.getElementById('url')?.value || '',
            };

            const desc = document.getElementById('description')?.value;
            if (desc) schema.description = desc;

            const phone = document.getElementById('phone')?.value;
            if (phone) schema.telephone = phone;

            const email = document.getElementById('email')?.value;
            if (email) schema.email = email;

            const street = document.getElementById('street_address')?.value;
            const city = document.getElementById('locality')?.value;
            const region = document.getElementById('region')?.value;
            const postal = document.getElementById('postal_code')?.value;
            const country = document.getElementById('country_code')?.value;

            if (street || city || region || postal || country) {
                schema.address = {
                    '@type': 'PostalAddress',
                    ...(street && { streetAddress: street }),
                    ...(city && { addressLocality: city }),
                    ...(region && { addressRegion: region }),
                    ...(postal && { postalCode: postal }),
                    ...(country && { addressCountry: country }),
                };
            }

            const sameAs = [];
            ['facebook_url', 'twitter_url', 'linkedin_url', 'instagram_url', 'youtube_url'].forEach((name) => {
                const val = form.querySelector(`[name="${name}"]`)?.value;
                if (val) sameAs.push(val);
            });
            if (sameAs.length) schema.sameAs = sameAs;

            previewCode.textContent = JSON.stringify(schema, null, 2);
        };

        window.addHourRow = function () {
            const list = document.getElementById('hours-list');
            if (!list) return;
            const days = getOpeningDays(form);
            list.insertAdjacentHTML('beforeend', createHourRowHtml(days));
        };

        window.removeHourRow = function (btn) {
            btn.closest('.hour-row')?.remove();
            // Trigger score recalculation when hours removed
            debouncedScoreUpdate(form);
        };

        // ========================================
        // Score Calculation Event Listeners
        // ========================================

        // Trigger score update function
        function triggerScoreUpdate() {
            debouncedScoreUpdate(form);
        }

        // Listen to all input/select/textarea changes in the form
        form.addEventListener('input', triggerScoreUpdate);
        form.addEventListener('change', triggerScoreUpdate);

        // Special handling for media picker (logo_image)
        const logoInput = document.getElementById('logo_image');
        if (logoInput) {
            // Use MutationObserver to watch for value changes on hidden input
            const observer = new MutationObserver(triggerScoreUpdate);
            observer.observe(logoInput, { attributes: true, attributeFilter: ['value'] });

            // Also listen for custom events that media picker might dispatch
            logoInput.addEventListener('change', triggerScoreUpdate);
        }

        toggleFields();
        toggleOrgFields();
        toggleHoursFields();

        schemaSwitch?.addEventListener('change', toggleFields);
        typeSelect?.addEventListener('change', toggleOrgFields);
        typeSelect?.addEventListener('change', triggerScoreUpdate); // Recalc score when type changes
        is247Switch?.addEventListener('change', toggleHoursFields);
        is247Switch?.addEventListener('change', triggerScoreUpdate); // Recalc score when 24/7 changes

        setTimeout(() => window.updatePreview?.(), 100);

        // Initial score calculation
        setTimeout(() => {
            const scoreData = calculateSeoScore(form);
            updateScoreUI(scoreData);
        }, 150);
    }

    window.initializeLocalSeoSettings = initializeLocalSeoSettings;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => window.initializeLocalSeoSettings?.(), 50);
        });
    } else {
        setTimeout(() => window.initializeLocalSeoSettings?.(), 50);
    }

    document.addEventListener('up:fragment:inserted', (event) => {
        if (event.target?.querySelector?.('#local-seo-form')) {
            window.initializeLocalSeoSettings?.();
        }
    });
})();
