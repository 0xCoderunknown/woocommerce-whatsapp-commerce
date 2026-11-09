/**
 * 🔍 FRONTEND EXPLORE: REAL-TIME CLIENT SEARCH ENGINE
 *
 * Provides debounced, zero-request live text filtering for directory grids.
 *
 * @package StatelessWaCommerce
 */
document.addEventListener('DOMContentLoaded', function () {
    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    function initSearch(opts) {
        const input = typeof opts.input === 'string' ? document.getElementById(opts.input) : opts.input;
        if (!input) return;

        const container = opts.target ? document.querySelector(opts.target) : document;
        const noResults = opts.noResultsEl || document.querySelector(opts.noResultsSel || '.swac-empty-state');

        input.addEventListener('input', debounce(function () {
            const term = input.value.toLowerCase().trim();
            let totalMatches = 0;

            const groups = container.querySelectorAll('.swac-letter-section, .swac-explore-container');

            if (groups.length > 0) {
                groups.forEach(function (group) {
                    const items = group.querySelectorAll('.swac-brand-card, .swac-item-card, .swac-search-item');
                    let groupMatches = 0;
                    items.forEach(function (item) {
                        const name = (item.getAttribute('data-title') || item.getAttribute('data-name') || item.textContent || '').toLowerCase();
                        const visible = name.includes(term);
                        item.style.display = visible ? '' : 'none';
                        if (visible) {
                            groupMatches++;
                            totalMatches++;
                        }
                    });
                    group.style.display = groupMatches > 0 ? '' : 'none';
                });
            } else {
                const items = container.querySelectorAll('.swac-brand-card, .swac-item-card, .swac-search-item');
                items.forEach(function (item) {
                    const name = (item.getAttribute('data-title') || item.getAttribute('data-name') || item.textContent || '').toLowerCase();
                    const visible = name.includes(term);
                    item.style.display = visible ? '' : 'none';
                    if (visible) totalMatches++;
                });
            }

            if (noResults) {
                noResults.style.display = (totalMatches === 0 && term !== '') ? 'block' : 'none';
            }
        }, 250));
    }

    // Auto-bind any .swac-live-search inputs
    document.querySelectorAll('.swac-live-search, .swac-cat-search-input').forEach(function (input) {
        const target = input.getAttribute('data-target');
        initSearch({
            input: input,
            target: target || '.swac-explore-wrapper',
        });
    });
});