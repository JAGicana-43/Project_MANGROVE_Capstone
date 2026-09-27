// Reports route: renders the "fused" data dashboard -- suitability
// breakdown, per-parameter ranges, and collection-over-time -- from
// window.workspaceConfig.reportsChartData (computed server-side by
// ReportDataAggregator, the same aggregator the downloaded PDF uses, so
// this view and the PDF never disagree about the numbers).
document.addEventListener('DOMContentLoaded', function () {
    if (window.workspaceConfig?.currentRoute !== 'reports') return;

    // A compact legend strip under the mini-map, not a floating overlay on
    // top of it -- the card is only 400px tall, too tight to also host a
    // floating panel the way the full-size Orthomosaic/Sampling Points
    // maps do. Same legend data (ClassificationController::status()'s
    // 'legend' field / existingClassification.legend) as those maps.
    function renderMiniMapLegend(legend) {
        var el = document.getElementById('reportMiniMapLegend');
        if (!el) return;
        if (!legend || !legend.length) {
            el.hidden = true;
            return;
        }
        el.innerHTML = legend.map(function (entry) {
            return '<span class="report-mini-map-legend-item">'
                + '<span class="suitability-legend-swatch" style="background:' + entry.color + '"></span>'
                + entry.label
                + '</span>';
        }).join('');
        el.hidden = false;
    }

    // Same widget as map-workspace.js's initCompassWidget() -- duplicated,
    // not shared, since map-workspace.js isn't loaded on this route at all
    // (Reports has no full map stack, just this one small preview map).
    // Cosmetic only: this map never rotates, so the compass always points
    // up = North.
    function initCompassWidget(targetMap) {
        if (!targetMap || typeof L === 'undefined') return;

        var Compass = L.Control.extend({
            options: { position: 'topright' },
            onAdd: function () {
                var wrap = L.DomUtil.create('div', 'map-compass-widget');

                wrap.innerHTML =
                    '<div class="map-compass-rose">'
                    + '<svg viewBox="0 0 64 64" width="64" height="64">'
                    + '<circle cx="32" cy="32" r="29" fill="#fff" stroke="#2D6A4F" stroke-width="2"/>'
                    + '<polygon points="32,7 38,32 32,29 26,32" fill="#B42318"/>'
                    + '<polygon points="32,57 38,32 32,35 26,32" fill="#9CA3AF"/>'
                    + '<text x="32" y="18" text-anchor="middle" font-size="9" font-weight="700" fill="#212529">N</text>'
                    + '</svg>'
                    + '</div>';

                L.DomEvent.disableClickPropagation(wrap);

                return wrap;
            }
        });

        targetMap.addControl(new Compass());
    }

    function renderMiniMap() {
        var container = document.getElementById('reportMiniMap');
        var existing = window.workspaceConfig?.existingOrthomosaic;
        if (!container || typeof L === 'undefined' || !existing?.preview_url || !existing?.bounds_coordinates) return;

        var map = L.map('reportMiniMap', {
            zoomControl: false,
            attributionControl: false,
            scrollWheelZoom: false
        });
        initCompassWidget(map);

        var b = existing.bounds_coordinates;
        var bounds = L.latLngBounds([b[0], b[1]], [b[2], b[3]]);
        L.imageOverlay(existing.preview_url, bounds).addTo(map);

        // Suitability overlay on top of the orthomosaic, same as the
        // Orthomosaic/Sampling Points maps -- opacity 0.65 so the source
        // photo is still visible underneath for cross-checking.
        var classification = window.workspaceConfig?.existingClassification;
        if (classification?.overlay_url) {
            L.imageOverlay(classification.overlay_url + '?t=' + Date.now(), bounds, { opacity: 0.65 }).addTo(map);
        }
        renderMiniMapLegend(classification?.legend);

        var ratingColors = { suitable: '#2D6A4F', marginal: '#B7791F', unsuitable: '#B42318' };
        var points = window.workspaceConfig?.existingClassification?.points || [];
        points.forEach(function (point) {
            var color = ratingColors[point.reading?.suitability_rating] || '#9CA3AF';
            L.circleMarker([point.latitude, point.longitude], {
                radius: 5,
                color: '#fff',
                weight: 1.5,
                fillColor: color,
                fillOpacity: 1
            }).addTo(map);
        });

        // fitBounds() run at init time computes against whatever size the
        // container happens to have right then, which can be 0 (or wrong)
        // before the surrounding card/grid layout has actually settled --
        // confirmed directly: the map rendered with the orthomosaic
        // correctly positioned but tiny, stranded in a mostly-empty gray
        // viewport, exactly what an early fitBounds() against a bad
        // container size produces. invalidateSize() alone only corrects
        // Leaflet's internal size bookkeeping -- it does not redo the fit,
        // so fitBounds() has to be called again afterward, not just
        // invalidateSize() on its own.
        window.setTimeout(function () {
            map.invalidateSize();
            map.fitBounds(bounds);
        }, 200);
    }

    renderMiniMap();

    var data = window.workspaceConfig?.reportsChartData;
    // Land cover comes from classification alone and can exist with zero
    // soil readings synced yet -- each render function below checks its
    // own canvas/data independently rather than gating everything behind
    // one "has readings" flag (the blade template already decides per-
    // chart whether a canvas exists at all vs. an empty-state message).
    if (!data) return;

    // Status colors, not an arbitrary categorical palette -- these match
    // the same suitable/marginal/unsuitable/not-rated semantics already
    // used everywhere else in this app (map pins, PDF badges), so a
    // researcher reading multiple views never has to re-learn what a
    // color means from screen to screen.
    var STATUS_COLORS = {
        suitable: '#2D6A4F',
        marginal: '#B7791F',
        unsuitable: '#B42318',
        'not rated': '#9CA3AF'
    };

    // Colors computed directly from suitability_scoring.py's
    // suitability_color() function at each land cover's real
    // DEFAULT_SUITABILITY_SCORES value (bare_soil=1.0, vegetation=0.5),
    // same as RunClassificationJob's overlay legend -- confirmed directly
    // that this previously did NOT match (vegetation was hardcoded to
    // #B7791F, an unrelated amber "marginal" status color, while the real
    // overlay pixel color for a 0.5-scored cell is #735F2D). This map sits
    // right next to the actual overlay on the same Reports page, so a
    // mismatch here is exactly as visible as it was on the Orthomosaic
    // map's own legend.
    var LAND_COVER_COLORS = {
        'Bare Soil': '#1EA03C',
        'Vegetation': '#735F2D',
        'Open Water': '#6482A0'
    };

    function renderLandCoverChart() {
        var canvas = document.getElementById('reportLandCoverChart');
        if (!canvas || typeof Chart === 'undefined') return;

        var rows = data.land_cover_breakdown || [];

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: rows.map(function (row) { return row.label; }),
                datasets: [{
                    data: rows.map(function (row) { return row.count; }),
                    backgroundColor: rows.map(function (row) { return LAND_COVER_COLORS[row.label] || '#9CA3AF'; }),
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } }
                }
            }
        });
    }

    function renderSuitabilityChart() {
        var canvas = document.getElementById('reportSuitabilityChart');
        if (!canvas || typeof Chart === 'undefined') return;

        var labels = data.suitability_breakdown.map(function (row) { return row.label; });
        var counts = data.suitability_breakdown.map(function (row) { return row.count; });
        var colors = data.suitability_breakdown.map(function (row) { return STATUS_COLORS[row.rating] || '#9CA3AF'; });

        if (data.not_rated_count > 0) {
            labels.push('Not rated');
            counts.push(data.not_rated_count);
            colors.push(STATUS_COLORS['not rated']);
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: colors,
                    borderRadius: 4,
                    maxBarThickness: 28
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    function renderParameterRanges() {
        var container = document.getElementById('reportParameterRanges');
        if (!container) return;

        var rows = data.parameter_stats.filter(function (p) { return p.count > 0; });
        if (rows.length === 0) {
            container.innerHTML = '<p class="aoi-metric-hint">No measurements recorded yet.</p>';
            return;
        }

        container.innerHTML = rows.map(function (param) {
            var span = param.max - param.min;
            var avgPercent = span > 0 ? ((param.avg - param.min) / span) * 100 : 50;
            return (
                '<div class="param-range-row">'
                + '<div class="param-range-label">' + param.label + '</div>'
                + '<div class="param-range-track"><div class="param-range-avg-marker" style="left:' + avgPercent + '%"></div></div>'
                + '<div class="param-range-values">' + param.min.toFixed(2) + ' – ' + param.max.toFixed(2) + ' (avg ' + param.avg.toFixed(2) + ')</div>'
                + '</div>'
            );
        }).join('');
    }

    function renderTimelineChart() {
        var canvas = document.getElementById('reportTimelineChart');
        if (!canvas || typeof Chart === 'undefined') return;

        var timeline = data.collection_timeline || [];
        if (timeline.length === 0) {
            canvas.replaceWith(Object.assign(document.createElement('p'), {
                className: 'aoi-metric-hint',
                textContent: 'No collection dates recorded yet.'
            }));
            return;
        }

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: timeline.map(function (row) { return row.date; }),
                datasets: [{
                    data: timeline.map(function (row) { return row.count; }),
                    borderColor: '#2D6A4F',
                    backgroundColor: 'rgba(45, 106, 79, 0.12)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#2D6A4F',
                    fill: true,
                    tension: 0.2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    renderLandCoverChart();
    renderSuitabilityChart();
    renderParameterRanges();
    renderTimelineChart();
});
