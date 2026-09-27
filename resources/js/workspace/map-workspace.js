        document.addEventListener('DOMContentLoaded', function () {
            var davaoOccidentalBounds = L.latLngBounds([5.15, 125.15], [6.95, 126.2]);
            // Admin-toggleable (Settings > Restrict map to Davao Occidental,
            // default on). Off lets AOI drawing/search/upload work anywhere
            // in the world -- needed to test with sample orthomosaics from
            // outside the province, since fitBounds() on a real orthomosaic's
            // own georeferenced bounds (see renderGeoraster()) is otherwise
            // clamped to whatever maxBounds the map has.
            var restrictToDavao = window.workspaceConfig?.restrictMapToDavaoOccidental !== false;
            // Separate toggle (Settings > Lock map to confirmed AOI, default
            // on): whether confirming an AOI boxes the map in around it.
            // Named aoiLockEnabled, not lockMapToAoi, to avoid colliding
            // with the lockMapToAoi() function defined further down.
            var aoiLockEnabled = window.workspaceConfig?.lockMapToAoi !== false;

            // Shared by both sampling-point marker renderers below (the
            // Orthomosaic route's live view and the Sampling Points route's
            // read-only view) so a point's collection progress -- pending,
            // assigned to a technician, or already collected -- reads the
            // same regardless of which page it's shown on.
            function samplingPointColor(status) {
                if (status === 'collected') return '#2D6A4F';
                if (status === 'transmitted') return '#1D7A9C';
                return '#9CA3AF';
            }

            // Soil Readings route: color keyed off the recorded suitability
            // rating instead of collection status -- a different axis of
            // meaning for the same point (has this spot turned out to be
            // good for planting, not just "has it been visited yet").
            function soilReadingColor(point) {
                var rating = point.reading?.suitability_rating;
                if (rating === 'suitable') return '#2D6A4F';
                if (rating === 'marginal') return '#B7791F';
                if (rating === 'unsuitable') return '#B42318';
                return '#9CA3AF';
            }

            // Circular numbered badge -- the point's priority rank sits
            // directly on the marker (centered coordinate, not an offset
            // pin-tip) so it's readable at a glance without opening a
            // tooltip/popup first. Shared by every place sampling points
            // render (Sampling Points, Soil Readings). Inline SVG in a
            // divIcon so it can be colored per point via the same color
            // functions above, rather than shipping a separate pre-colored
            // image per status/rating.
            function samplingPointIcon(color, label) {
                var size = 28;
                var half = size / 2;
                var text = (label !== undefined && label !== null) ? String(label) : '';
                // Font size steps down for 2/3-digit priority numbers so
                // they still fit inside the circle without overflowing it.
                var fontSize = text.length >= 3 ? 9 : text.length === 2 ? 10.5 : 12;
                return L.divIcon({
                    className: 'sampling-pin-icon',
                    html: '<svg width="' + size + '" height="' + size + '" viewBox="0 0 ' + size + ' ' + size + '" xmlns="http://www.w3.org/2000/svg">'
                        + '<circle cx="' + half + '" cy="' + half + '" r="' + (half - 2) + '" '
                        + 'fill="' + color + '" stroke="#fff" stroke-width="2.5"/>'
                        + (text
                            ? '<text x="' + half + '" y="' + half + '" text-anchor="middle" dominant-baseline="central" '
                                + 'font-size="' + fontSize + '" font-weight="700" font-family="Inter, sans-serif" fill="#fff">' + text + '</text>'
                            : '')
                        + '</svg>',
                    iconSize: [size, size],
                    iconAnchor: [half, half],
                    popupAnchor: [0, -half]
                });
            }

            // Shared show/hide wiring for a layer-list toggle item, same
            // show/hide + checkmark behavior as the existing suitability
            // overlay toggle -- factored out so the sampling-points toggle
            // (present on three separate routes, each with its own local
            // layer group instance) doesn't need this logic copy-pasted
            // three times.
            function wireLayerToggle(toggleId, checkId, layerGroup) {
                var toggle = document.getElementById(toggleId);
                var check = document.getElementById(checkId);
                if (!toggle) return;

                function setVisible(visible) {
                    if (visible) {
                        map.addLayer(layerGroup);
                    } else {
                        map.removeLayer(layerGroup);
                    }
                    toggle.setAttribute('aria-pressed', String(visible));
                    check?.classList.toggle('checked', visible);
                    if (check) check.innerHTML = visible ? '<i class="fa-solid fa-check"></i>' : '';
                }

                toggle.addEventListener('click', function () {
                    setVisible(!map.hasLayer(layerGroup));
                });
            }

            // Cosmetic polish, not a real navigation instrument -- this
            // app's maps never rotate (always north-up, no bearing control
            // wired up anywhere), so the compass can't track real map
            // orientation. It always points up = North, with a subtle
            // decorative idle wobble so it doesn't look completely inert.
            function initCompassWidget(targetMap) {
                if (!targetMap) return;

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

            // Suitability overlay color legend -- shown wherever the
            // overlay itself is shown (Orthomosaic, Sampling Points).
            // Legend entries come from the classification run itself
            // (ClassificationController::status()'s 'legend' field /
            // existingClassification.legend), not hardcoded here, so this
            // stays correct if the pipeline's own color/label choices ever
            // change.
            function renderSuitabilityLegend(legend) {
                var panel = document.getElementById('suitabilityLegend');
                var itemsEl = document.getElementById('suitabilityLegendItems');
                if (!panel || !itemsEl) return;

                if (!legend || !legend.length) {
                    panel.hidden = true;
                    delete panel.dataset.hasLegend;
                    return;
                }

                itemsEl.innerHTML = legend.map(function (entry) {
                    return '<div class="suitability-legend-item">'
                        + '<span class="suitability-legend-swatch" style="background:' + entry.color + '"></span>'
                        + entry.label
                        + '</div>';
                }).join('');
                panel.hidden = false;
                panel.dataset.hasLegend = '1';
            }

            var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                // Verified directly against the tile server (not a guess):
                // z18 returns real, sharp imagery for the Davao Occidental
                // area this app targets; z19 returns Esri's literal "Map
                // data not yet available" placeholder tile there. z17 was
                // leaving a full level of real detail unused, forcing an
                // extra level of digital upscaling (blur) below maxZoom.
                // Coverage can still vary tile-by-tile across the province,
                // so maxZoom stays higher as a graceful degrade, not a
                // promise of real data past 18 everywhere.
                maxNativeZoom: 18,
                maxZoom: 20,
                attribution: 'Tiles &copy; Esri, Maxar, Earthstar Geographics, and the GIS User Community'
            });

            var map = L.map('workspace-map', {
                center: [6.4150, 125.6117],
                zoom: 10,
                minZoom: restrictToDavao ? 9 : 2,
                maxZoom: 20,
                maxBounds: restrictToDavao ? davaoOccidentalBounds : null,
                maxBoundsViscosity: 0.9,
                zoomControl: false,
                layers: [satellite]
            });

            L.control.zoom({ position: 'bottomright' }).addTo(map);
            L.control.scale({ position: 'bottomright', metric: true, imperial: false, maxWidth: 100 }).addTo(map);
            window.workspaceMap = map;
            initCompassWidget(map);

            // Shared CSRF-header fetch wrapper — every authenticated POST/
            // DELETE call in this file and in drone-mission-panel.js was
            // hand-building the same {X-CSRF-TOKEN, X-Requested-With,
            // Accept} header object (plus Content-Type for a JSON body).
            // Exposed on window (not a closure-local function) since it's
            // called from multiple independent scopes: this file's early
            // orthomosaic-upload IIFE, its later AOI-handling code, and
            // drone-mission-panel.js's separate Alpine component.
            // Pass `json` for a JSON body (sets Content-Type + stringifies);
            // omit it for a bodyless request or when passing FormData
            // directly as `body` (its own multipart Content-Type is left
            // alone).
            window.apiFetch = function (url, options) {
                options = options || {};
                var headers = Object.assign({
                    'X-CSRF-TOKEN': window.workspaceConfig?.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }, options.headers || {});

                var body = options.body;
                if (options.json !== undefined) {
                    headers['Content-Type'] = 'application/json';
                    body = JSON.stringify(options.json);
                }

                return fetch(url, { method: options.method || 'GET', headers: headers, body: body });
            };

            // --- Reference grid: a metric grid overlay (like a GIS grid
            // tool) so users can eyeball area/distance on the map. Cell size
            // auto-picks a "nice" round number (1/2/5 x 10^n meters) that
            // keeps cells roughly a consistent on-screen size at any zoom,
            // the same approach map scale bars use to avoid an oddly-sized
            // measurement. Lines are drawn in Web Mercator meters (the map's
            // native projection) so they line up cleanly regardless of
            // latitude distortion.
            (function () {
                var gridToggle = document.getElementById('gridLayerToggle');
                var gridCheck = document.getElementById('gridLayerCheck');
                var gridMeta = document.getElementById('gridLayerMeta');
                if (!gridToggle) return;

                var GRID_STEPS_M = [1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000, 100000];
                var gridEnabled = true;
                var gridLayerGroup = L.layerGroup().addTo(map);

                function formatSpacing(meters) {
                    return meters >= 1000 ? (meters / 1000) + ' km' : meters + ' m';
                }

                function pickGridSpacing(zoom, lat) {
                    var metersPerPixel = 156543.03392 * Math.cos(lat * Math.PI / 180) / Math.pow(2, zoom);
                    var idealMeters = metersPerPixel * 110;

                    for (var i = 0; i < GRID_STEPS_M.length; i++) {
                        if (GRID_STEPS_M[i] >= idealMeters) return GRID_STEPS_M[i];
                    }
                    return GRID_STEPS_M[GRID_STEPS_M.length - 1];
                }

                function renderGrid() {
                    gridLayerGroup.clearLayers();
                    if (!gridEnabled) return;

                    var bounds = map.getBounds().pad(0.25);
                    var center = map.getCenter();
                    var spacing = pickGridSpacing(map.getZoom(), center.lat);

                    var nw = L.CRS.EPSG3857.project(bounds.getNorthWest());
                    var se = L.CRS.EPSG3857.project(bounds.getSouthEast());
                    var minX = Math.min(nw.x, se.x), maxX = Math.max(nw.x, se.x);
                    var minY = Math.min(nw.y, se.y), maxY = Math.max(nw.y, se.y);

                    var lineStyle = { color: '#F8F9FA', weight: 1, opacity: 0.55, interactive: false, className: 'reference-grid-line' };

                    for (var x = Math.ceil(minX / spacing) * spacing; x <= maxX; x += spacing) {
                        gridLayerGroup.addLayer(L.polyline([
                            L.CRS.EPSG3857.unproject(L.point(x, minY)),
                            L.CRS.EPSG3857.unproject(L.point(x, maxY))
                        ], lineStyle));
                    }

                    for (var y = Math.ceil(minY / spacing) * spacing; y <= maxY; y += spacing) {
                        gridLayerGroup.addLayer(L.polyline([
                            L.CRS.EPSG3857.unproject(L.point(minX, y)),
                            L.CRS.EPSG3857.unproject(L.point(maxX, y))
                        ], lineStyle));
                    }

                    if (gridMeta) gridMeta.textContent = formatSpacing(spacing) + ' cells';
                }

                function applyGridUiState() {
                    gridToggle.setAttribute('aria-pressed', String(gridEnabled));
                    if (gridCheck) gridCheck.innerHTML = gridEnabled ? '<i class="fa-solid fa-check"></i>' : '';
                    gridCheck?.classList.toggle('checked', gridEnabled);
                    if (gridMeta) gridMeta.hidden = !gridEnabled;
                }

                function toggleGrid() {
                    gridEnabled = !gridEnabled;
                    applyGridUiState();
                    renderGrid();
                }

                gridToggle.addEventListener('click', toggleGrid);
                gridToggle.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        toggleGrid();
                    }
                });

                map.on('moveend zoomend', renderGrid);

                // On by default -- sync the toggle's visual state (checkmark,
                // meta label) to match gridEnabled=true above, and draw it
                // immediately rather than waiting for the first pan/zoom.
                applyGridUiState();
                renderGrid();
            })();

            // --- Orthomosaic upload + client-side GeoTIFF rendering ---
            // GeoTIFFs are parsed entirely in the browser (georaster.js reads the
            // embedded GeoKeys/tie points, so no server-side geo parsing is
            // needed) and drawn via georaster-layer-for-leaflet, which tiles the
            // raster onto canvas rather than decoding it at full native
            // resolution — keeping large orthomosaics from freezing the page.
            (function () {
                var fileInput = document.getElementById('orthomosaicFileInput');
                var uploadBtn = document.getElementById('orthomosaicUploadBtn');
                var uploadIcon = document.getElementById('orthomosaicUploadIcon');
                var uploadLabel = document.getElementById('orthomosaicUploadLabel');
                var statusEl = document.getElementById('orthomosaicStatus');
                var uploadStateEl = document.getElementById('orthomosaicUploadState');
                var manageStateEl = document.getElementById('orthomosaicManageState');
                var manageInfoEl = document.getElementById('orthomosaicManageInfo');
                var removeBtn = document.getElementById('orthomosaicRemoveBtn');
                // analyzeBtn itself belongs to the separate classification
                // IIFE further down the file -- these are independent
                // references to the same DOM element, not a shared
                // variable, since showManageState below needs to disable it
                // synchronously during initial page load (restoring an
                // already-saved orthomosaic), before that other IIFE has
                // even run yet.
                var analyzeBtnForUpload = document.getElementById('orthomosaicAnalyzeBtn');
                var analyzeLabelForUpload = document.getElementById('orthomosaicAnalyzeLabel');
                var currentLayer = null;
                var currentRemoveUrl = null;

                if (!fileInput || !uploadBtn) return;

                function setBusy(isBusy, label) {
                    uploadBtn.disabled = isBusy;
                    if (uploadIcon) uploadIcon.className = 'fa-solid ' + (isBusy ? 'fa-spinner fa-spin' : 'fa-cloud-arrow-up');
                    if (uploadLabel) uploadLabel.textContent = label || 'Upload Orthomosaic';
                }

                function setStatus(message, isError) {
                    if (!statusEl) return;
                    statusEl.textContent = message || '';
                    statusEl.classList.toggle('error', !!isError);
                }

                // isBusy=true covers two distinct windows where Analyze
                // can't actually work yet: (1) a fresh upload, between the
                // map preview rendering (instant, parsed client-side) and
                // the real server upload finishing -- there's no file on
                // disk server-side yet; (2) reloading the workspace with an
                // already-saved orthomosaic, between the panel showing and
                // the GeoTIFF actually being re-fetched/parsed/rendered
                // back onto the map -- clicking Analyze before that
                // finishes would run against a map that doesn't actually
                // show the orthomosaic yet. Shown-but-disabled rather than
                // hidden either way, so the user isn't staring at nothing.
                function showManageState(meta, isBusy, busyMessage) {
                    currentRemoveUrl = (meta && meta.remove_url) || null;
                    if (manageInfoEl) {
                        manageInfoEl.textContent = isBusy
                            ? (busyMessage || 'Uploading to project...')
                            : (meta && meta.uploaded_at
                                ? 'Orthomosaic uploaded ' + meta.uploaded_at
                                : 'Orthomosaic uploaded.');
                    }
                    if (uploadStateEl) uploadStateEl.hidden = true;
                    if (manageStateEl) manageStateEl.hidden = false;
                    if (removeBtn) removeBtn.disabled = !!isBusy;
                    if (analyzeBtnForUpload) analyzeBtnForUpload.disabled = !!isBusy;
                    if (analyzeLabelForUpload) {
                        analyzeLabelForUpload.textContent = isBusy ? (busyMessage || 'Uploading...') : 'Analyze Orthomosaic';
                    }
                }

                function showUploadState() {
                    currentRemoveUrl = null;
                    if (uploadStateEl) uploadStateEl.hidden = false;
                    if (manageStateEl) manageStateEl.hidden = true;
                }

                function renderGeoraster(georaster) {
                    if (currentLayer) {
                        map.removeLayer(currentLayer);
                        currentLayer = null;
                    }

                    currentLayer = new GeoRasterLayer({
                        georaster: georaster,
                        // Sample density for the on-screen preview tiles,
                        // independent of the source file's real resolution
                        // (classification always reads the full file
                        // directly via rasterio, untouched by this). 256 is
                        // a meaningful step up in clarity from the previous
                        // 128 while AOIs stay capped at 4ha -- a small,
                        // bounded data volume, not a large mosaic -- so this
                        // shouldn't visibly cost responsiveness. Tune
                        // further based on how it actually looks/feels.
                        resolution: 256
                    });
                    currentLayer.addTo(map);

                    var bounds = L.latLngBounds(
                        [georaster.ymin, georaster.xmin],
                        [georaster.ymax, georaster.xmax]
                    );
                    map.fitBounds(bounds);

                    return bounds;
                }

                function uploadToServer(file, bounds) {
                    var uploadUrl = window.workspaceConfig?.orthomosaicUploadUrl;
                    if (!uploadUrl) return Promise.resolve(null);

                    var formData = new FormData();
                    formData.append('file', file);
                    if (bounds) {
                        // bounds_coordinates stored as [min_lat, min_lng, max_lat, max_lng]
                        formData.append('bounds_coordinates[0]', bounds.getSouth());
                        formData.append('bounds_coordinates[1]', bounds.getWest());
                        formData.append('bounds_coordinates[2]', bounds.getNorth());
                        formData.append('bounds_coordinates[3]', bounds.getEast());
                    }

                    return window.apiFetch(uploadUrl, {
                        method: 'POST',
                        body: formData
                    }).then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) throw new Error(data.message || 'Upload failed.');
                            return data;
                        });
                    });
                }

                uploadBtn.addEventListener('click', function () {
                    fileInput.click();
                });

                fileInput.addEventListener('change', function () {
                    var file = fileInput.files && fileInput.files[0];
                    if (!file) return;

                    setBusy(true, 'Rendering...');
                    setStatus('Parsing GeoTIFF...');

                    file.arrayBuffer().then(function (buffer) {
                        return parseGeoraster(buffer);
                    }).then(function (georaster) {
                        var bounds = renderGeoraster(georaster);
                        showManageState(null, true);
                        setBusy(true, 'Uploading...');
                        setStatus('Saving to project...');
                        return uploadToServer(file, bounds);
                    }).then(function (data) {
                        setBusy(false);
                        setStatus((data && data.message) || 'Orthomosaic rendered.');
                        if (data && data.orthomosaic) showManageState(data.orthomosaic);
                    }).catch(function (error) {
                        setBusy(false);
                        setStatus(error.message || 'Could not render this file. Make sure it is a valid GeoTIFF.', true);
                        // The preview rendered but there's no saved
                        // orthomosaic to manage -- back to the upload
                        // screen so the user can retry, instead of leaving
                        // a permanently-disabled Analyze button on screen.
                        showUploadState();
                        if (currentLayer) {
                            map.removeLayer(currentLayer);
                            currentLayer = null;
                        }
                    }).finally(function () {
                        fileInput.value = '';
                    });
                });

                // In-page overlay instead of window.confirm() -- a native
                // browser popup is easy to blow past on reflex and doesn't
                // read as part of the app; this reuses the AOI redraw
                // modal's exact styling for a consistent, more visible
                // confirmation.
                var removeModalBackdrop = document.getElementById('orthomosaicRemoveModalBackdrop');
                var removeModalCard = document.getElementById('orthomosaicRemoveModalCard');
                var removeModalConfirm = document.getElementById('orthomosaicRemoveModalConfirm');

                function openRemoveModal() {
                    removeModalBackdrop?.classList.add('is-open');
                    removeModalCard?.classList.add('is-open');
                }

                function closeRemoveModal() {
                    removeModalBackdrop?.classList.remove('is-open');
                    removeModalCard?.classList.remove('is-open');
                }

                removeBtn?.addEventListener('click', function () {
                    if (!currentRemoveUrl) return;
                    openRemoveModal();
                });

                removeModalBackdrop?.addEventListener('click', closeRemoveModal);
                document.getElementById('orthomosaicRemoveModalCancel')?.addEventListener('click', closeRemoveModal);
                removeModalConfirm?.addEventListener('click', function () {
                    if (!currentRemoveUrl) return;

                    removeModalConfirm.disabled = true;
                    removeBtn.disabled = true;

                    window.apiFetch(currentRemoveUrl, { method: 'DELETE' }).then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) throw new Error(data.message || 'Could not remove this orthomosaic.');
                            return data;
                        });
                    }).then(function (data) {
                        if (currentLayer) {
                            map.removeLayer(currentLayer);
                            currentLayer = null;
                        }
                        showUploadState();
                        setStatus((data && data.message) || 'Orthomosaic removed.');
                    }).catch(function (error) {
                        setStatus(error.message || 'Could not remove this orthomosaic.', true);
                    }).finally(function () {
                        removeModalConfirm.disabled = false;
                        removeBtn.disabled = false;
                        closeRemoveModal();
                    });
                });

                // Re-render the project's most recently uploaded orthomosaic (if
                // any) when the section is revisited, so it isn't lost on reload.
                var existing = window.workspaceConfig?.existingOrthomosaic;
                if (existing && existing.preview_url && existing.bounds_coordinates) {
                    // Fast path: a small pre-downscaled PNG (see
                    // OrthomosaicPreviewGenerator) shown as a plain image
                    // overlay against the stored geographic bounds -- no
                    // fetch-the-whole-GeoTIFF-and-parse-it-in-JS cost.
                    // Verified directly: this replaced a 131MB
                    // download+client-side-parse on every single page load.
                    setStatus('Loading saved orthomosaic...');
                    showManageState(existing, true, 'Loading...');
                    var b = existing.bounds_coordinates;
                    var previewBounds = L.latLngBounds([b[0], b[1]], [b[2], b[3]]);
                    currentLayer = L.imageOverlay(existing.preview_url, previewBounds).addTo(map);
                    // Delayed, not immediate -- something else on this page
                    // (the read-only AOI context layer) also calls
                    // fitBounds() during its own init and was winning the
                    // race, silently panning the view back to the AOI right
                    // after this ran, leaving the orthomosaic correctly
                    // loaded but off-screen. Matches this file's existing
                    // "let other init settle first" convention (see the
                    // 120ms invalidateSize() delay elsewhere).
                    window.setTimeout(function () {
                        map.fitBounds(previewBounds);
                    }, 200);
                    showManageState(existing);
                    setStatus('Showing the last uploaded orthomosaic.');
                } else if (existing && existing.file_url) {
                    // Fallback: no preview yet (background job still
                    // running, or this orthomosaic predates the preview
                    // feature) -- same full-file fetch/parse this always
                    // did, just no longer the common case.
                    setStatus('Loading saved orthomosaic...');
                    // Analyze stays disabled until the fetch/parse/render
                    // below actually finishes -- clicking it while the map
                    // doesn't yet show the orthomosaic would run analysis
                    // disconnected from what's on screen.
                    showManageState(existing, true, 'Loading...');
                    fetch(existing.file_url)
                        .then(function (response) {
                            if (!response.ok) throw new Error('Could not load the saved orthomosaic.');
                            return response.arrayBuffer();
                        })
                        .then(function (buffer) {
                            return parseGeoraster(buffer);
                        })
                        .then(function (georaster) {
                            renderGeoraster(georaster);
                            showManageState(existing);
                            setStatus('Showing the last uploaded orthomosaic.');
                        })
                        .catch(function (error) {
                            // The failure here is the client-side preview
                            // (fetch/parse of the file for on-screen
                            // display) -- Analyze only needs the file to
                            // exist server-side, which it does regardless,
                            // so it stays usable even though the map
                            // preview didn't render. Previously left the
                            // button permanently disabled on any preview
                            // failure (network hiccup, large file, a bad
                            // parse) with no way to recover except a
                            // reload that could hit the same failure again.
                            showManageState(existing);
                            setStatus(error.message || 'Could not preview the saved orthomosaic, but analysis is still available.', true);
                        });
                }

                // --- Classification: analyze -> label clusters -> results -> sampling points ---
                // Every new orthomosaic gets its own fresh, independent
                // clustering fit (no learning carried over between uploads),
                // so its clusters always come back needing a human to name
                // them -- runClassification's response never has scoreable
                // results, only submitLabels' does.
                var analyzeBtn = document.getElementById('orthomosaicAnalyzeBtn');
                var analyzeIcon = document.getElementById('orthomosaicAnalyzeIcon');
                var analyzeLabel = document.getElementById('orthomosaicAnalyzeLabel');
                var cancelBtn = document.getElementById('orthomosaicCancelBtn');
                var progressBarEl = document.getElementById('orthomosaicProgressBar');
                var progressBarFillEl = document.getElementById('orthomosaicProgressBarFill');
                var labelingStateEl = document.getElementById('orthomosaicLabelingState');
                var qualityInfoEl = document.getElementById('orthomosaicQualityInfo');
                var clusterLabelListEl = document.getElementById('clusterLabelList');
                var submitLabelsBtn = document.getElementById('submitLabelsBtn');
                var resultsStateEl = document.getElementById('orthomosaicResultsState');
                var resultsInfoEl = document.getElementById('orthomosaicResultsInfo');
                var generatePointsBtn = document.getElementById('generateSamplingPointsBtn');
                var samplingPointsInfoEl = document.getElementById('samplingPointsInfo');
                var suitabilityToggle = document.getElementById('suitabilityLayerToggle');
                var suitabilityCheck = document.getElementById('suitabilityLayerCheck');
                var editGridCellsBtn = document.getElementById('editGridCellsBtn');
                var editGridCellsLabel = document.getElementById('editGridCellsLabel');
                var editGridCellsHint = document.getElementById('editGridCellsHint');

                var suitabilityLayerGroup = L.layerGroup().addTo(map);
                var gridCellsLayerGroup = L.layerGroup().addTo(map);
                var currentLabelsUrl = null;
                var currentSamplingPointsUrl = null;
                var currentGridCellsUrl = null;
                var currentCancelUrl = null;
                var currentPoll = null;
                var gridCellsEditMode = false;
                var gridCellsLoaded = false;

                window.renderSuitabilityOverlay = function (boundsCoordinates, overlayUrl) {
                    suitabilityLayerGroup.clearLayers();
                    if (!boundsCoordinates || boundsCoordinates.length !== 4 || !overlayUrl) return;
                    var bounds = L.latLngBounds(
                        [boundsCoordinates[0], boundsCoordinates[1]],
                        [boundsCoordinates[2], boundsCoordinates[3]]
                    );
                    // Cache-bust: the same run's overlay URL can be re-requested
                    // after a relabel produces a new file at the same path.
                    //
                    // opacity: the PNG itself is only ever fully opaque or
                    // fully transparent per pixel (see save_web_overlay_png's
                    // own docstring) -- it deliberately leaves overall
                    // blending to whoever displays it, specifically so the
                    // underlying orthomosaic stays visible underneath for
                    // visual cross-checking (does this "bare soil" area
                    // actually look like bare soil in the real photo?)
                    // instead of being fully hidden by a solid color block.
                    L.imageOverlay(overlayUrl + '?t=' + Date.now(), bounds, { opacity: 0.65 }).addTo(suitabilityLayerGroup);
                };

                function setSuitabilityLayerVisible(visible) {
                    if (visible) {
                        map.addLayer(suitabilityLayerGroup);
                    } else {
                        map.removeLayer(suitabilityLayerGroup);
                    }
                    suitabilityToggle?.setAttribute('aria-pressed', String(visible));
                    suitabilityCheck?.classList.toggle('checked', visible);
                    if (suitabilityCheck) suitabilityCheck.innerHTML = visible ? '<i class="fa-solid fa-check"></i>' : '';
                    // Legend only makes sense while the overlay it explains
                    // is actually visible -- hidden here rather than only
                    // ever hidden by renderSuitabilityLegend(null), since
                    // that path is never called when toggling visibility of
                    // an overlay that's already rendered.
                    var legendPanel = document.getElementById('suitabilityLegend');
                    if (legendPanel && !legendPanel.dataset.hasLegend) return;
                    if (legendPanel) legendPanel.hidden = !visible;
                }

                suitabilityToggle?.addEventListener('click', function () {
                    setSuitabilityLayerVisible(!map.hasLayer(suitabilityLayerGroup));
                });

                function setAnalyzeBusy(isBusy, label) {
                    if (analyzeBtn) analyzeBtn.disabled = isBusy;
                    if (analyzeIcon) analyzeIcon.className = 'fa-solid ' + (isBusy ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles');
                    if (analyzeLabel) analyzeLabel.textContent = label || 'Analyze Orthomosaic';
                    // Cancel only makes sense while something is actually in
                    // flight -- this runs at every point busy-state changes,
                    // so no separate call sites needed for its visibility.
                    if (cancelBtn) cancelBtn.hidden = !isBusy;
                    if (progressBarEl) progressBarEl.hidden = !isBusy;
                    if (!isBusy) updateProgressBar(null);
                }

                // Real progress during the first pass (percent comes from
                // ClassificationRun.processed_tiles/total_tiles, updated live
                // by ClassificationRunner while the subprocess runs). null/0
                // means "no granular signal yet" -- queued, just started, or
                // the fast cached second pass, which skips the per-tile step
                // this is derived from entirely -- shown as an animated
                // indeterminate stripe instead of a stuck-at-0 bar.
                function updateProgressBar(percent) {
                    if (!progressBarEl || !progressBarFillEl) return;
                    var hasRealProgress = typeof percent === 'number' && percent > 0;
                    progressBarEl.classList.toggle('is-indeterminate', !hasRealProgress);
                    progressBarFillEl.style.width = hasRealProgress ? percent + '%' : '';
                }

                cancelBtn?.addEventListener('click', function () {
                    if (!currentCancelUrl) return;
                    cancelBtn.disabled = true;

                    window.apiFetch(currentCancelUrl, { method: 'POST' }).then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) throw new Error(data.message || 'Could not cancel this analysis.');
                            return data;
                        });
                    }).then(function (data) {
                        currentPoll?.stop();
                        setAnalyzeBusy(false);
                        setStatus(data.message || 'Analysis cancelled.');
                    }).catch(function (error) {
                        setStatus(error.message || 'Could not cancel this analysis.', true);
                    }).finally(function () {
                        cancelBtn.disabled = false;
                    });
                });

                function showLabelingState() {
                    if (labelingStateEl) labelingStateEl.hidden = false;
                    if (resultsStateEl) resultsStateEl.hidden = true;
                }

                function showResultsState() {
                    if (labelingStateEl) labelingStateEl.hidden = true;
                    if (resultsStateEl) resultsStateEl.hidden = false;
                }

                function formatQuality(quality) {
                    if (!quality) return '';
                    var silhouette = typeof quality.silhouette === 'number' ? quality.silhouette.toFixed(3) : quality.silhouette;
                    return 'Found ' + quality.chosen_k + ' clusters (silhouette ' + silhouette + ').';
                }

                function renderClusterList(clusters, validLabels) {
                    if (!clusterLabelListEl) return;
                    clusterLabelListEl.innerHTML = '';

                    clusters.forEach(function (cluster) {
                        var row = document.createElement('div');
                        row.className = 'cluster-label-row';

                        var thumbs = document.createElement('div');
                        thumbs.className = 'cluster-label-thumbs';
                        (cluster.sample_tile_urls || []).forEach(function (url) {
                            var img = document.createElement('img');
                            img.src = url;
                            img.alt = 'Cluster ' + cluster.cluster_id + ' sample tile';
                            thumbs.appendChild(img);
                        });

                        var meta = document.createElement('div');
                        meta.className = 'cluster-label-meta';

                        var title = document.createElement('span');
                        title.textContent = 'Cluster ' + cluster.cluster_id + ' (' + cluster.tile_count + ' tiles)';

                        var select = document.createElement('select');
                        select.className = 'mission-select cluster-label-select';
                        select.dataset.clusterId = String(cluster.cluster_id);

                        // A real, disabled placeholder -- without this, a
                        // cluster with no genuine prior label silently fell
                        // back to plain <select> behavior (whichever
                        // <option> was appended first, i.e. always
                        // VALID_LABELS[0] = "bare_soil") regardless of what
                        // its sample tiles actually show. That looked like
                        // the system confidently mislabeling every unlabeled
                        // cluster as bare_soil, and was easy to submit
                        // without noticing.
                        var placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = 'Select a label...';
                        placeholder.disabled = true;
                        select.appendChild(placeholder);

                        validLabels.forEach(function (label) {
                            var option = document.createElement('option');
                            option.value = label;
                            option.textContent = label;
                            select.appendChild(option);
                        });
                        // Pre-fill from the last time this orthomosaic was
                        // labeled, if the backend sent one -- still just a
                        // default, reviewable/changeable before submitting.
                        // Otherwise leave the placeholder selected so
                        // nothing is silently pre-chosen.
                        if (cluster.default_label && validLabels.indexOf(cluster.default_label) !== -1) {
                            select.value = cluster.default_label;
                        } else {
                            placeholder.selected = true;
                        }

                        meta.appendChild(title);
                        meta.appendChild(select);
                        row.appendChild(thumbs);
                        row.appendChild(meta);
                        clusterLabelListEl.appendChild(row);
                    });
                }

                function loadClusters(clustersUrl) {
                    return window.apiFetch(clustersUrl).then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) throw new Error(data.message || 'Could not load clusters.');
                            return data;
                        });
                    }).then(function (data) {
                        renderClusterList(data.clusters || [], data.valid_labels || []);
                        showLabelingState();
                    });
                }

                // Classification now runs on a queued worker, not inline in
                // the request (a real CPU/memory-heavy OS process shouldn't
                // block a web request or run unbounded per concurrent user
                // -- see the plan doc). Both the analyze and submit-labels
                // actions return almost immediately with a status_url; this
                // polls it until the job reaches a terminal state.
                function pollClassificationStatus(statusUrl, onDone, onFailed) {
                    var startedAt = Date.now();
                    var maxDurationMs = 10 * 60 * 1000;
                    var stopped = false;

                    // The classification job survives regardless of this
                    // page's lifetime (it's a queued job on a dedicated
                    // worker, not tied to the browser), so leaving mid-run
                    // never actually loses anything -- but it's easy to
                    // navigate away by reflex (back button, an internal
                    // link) without realizing an analysis is still going,
                    // and then wonder where it went. classificationRunActive
                    // drives a beforeunload confirmation for exactly that
                    // window; cleared the moment this poll settles either way.
                    window.classificationRunActive = true;

                    function finish(callback, data) {
                        window.classificationRunActive = false;
                        callback(data);
                    }

                    function poll() {
                        if (stopped) return;

                        if (Date.now() - startedAt > maxDurationMs) {
                            finish(onFailed, { error_message: 'Classification is taking longer than expected. Try again later.' });
                            return;
                        }

                        window.apiFetch(statusUrl).then(function (response) {
                            return response.json().then(function (data) {
                                if (!response.ok) throw new Error(data.message || 'Could not check classification status.');
                                return data;
                            });
                        }).then(function (data) {
                            if (stopped) return;

                            if (data.status === 'processing') {
                                if (data.cancel_url) currentCancelUrl = data.cancel_url;
                                var percent = data.progress_percent;
                                setStatus(data.queued
                                    ? 'Waiting in queue — another analysis is running first...'
                                    : (percent > 0
                                        ? 'Running classification — ' + percent.toFixed(0) + '%...'
                                        : 'Running classification — this can take a moment...'));
                                updateProgressBar(data.queued ? null : percent);
                                setTimeout(poll, 3000);
                            } else if (data.status === 'failed') {
                                finish(onFailed, data);
                            } else if (data.status === 'cancelled') {
                                // Reached if a different tab/session cancelled
                                // this same run -- this poll loop's own tab
                                // already stops itself via currentPoll.stop()
                                // when it's the one clicking Cancel.
                                finish(onFailed, { error_message: 'Analysis was cancelled.' });
                            } else {
                                finish(onDone, data);
                            }
                        }).catch(function (error) {
                            finish(onFailed, { error_message: error.message });
                        });
                    }

                    poll();

                    return {
                        stop: function () {
                            stopped = true;
                            window.classificationRunActive = false;
                        }
                    };
                }

                analyzeBtn?.addEventListener('click', function () {
                    var classifyUrl = window.workspaceConfig?.existingOrthomosaic?.classify_url;
                    if (!classifyUrl) return;

                    // A previous run's overlay/points/labeling panel are now
                    // stale -- starting a fresh analysis produces a brand
                    // new run with its own new clusters, so leaving the old
                    // visuals up would show a completed-looking overlay
                    // alongside a labeling panel for an unrelated run.
                    // Verified this exact mix is what "messy, confusing
                    // results" looked like in practice.
                    suitabilityLayerGroup.clearLayers();
                    if (suitabilityToggle) suitabilityToggle.hidden = true;
                    // Sampling points no longer render on this page's own map
                    // (moved to the dedicated Sampling Points route) -- this
                    // used to also clear a local samplingPointsLayerGroup
                    // here, a leftover reference to a variable that only
                    // exists in that other route's separate script scope.
                    // Referencing it threw a ReferenceError on every click,
                    // silently aborting before the actual analyze request
                    // ever went out -- confirmed directly, not assumed: no
                    // new ClassificationRun row appeared in the database
                    // when reproducing the click.
                    if (samplingPointsInfoEl) samplingPointsInfoEl.hidden = true;
                    if (resultsStateEl) resultsStateEl.hidden = true;
                    if (labelingStateEl) labelingStateEl.hidden = true;

                    setAnalyzeBusy(true, 'Analyzing...');
                    setStatus('Running classification — this can take a moment...');

                    window.apiFetch(classifyUrl, { method: 'POST' }).then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) throw new Error(data.error || data.message || 'Analysis failed.');
                            return data;
                        });
                    }).then(function (data) {
                        setStatus('Running classification — this can take a moment...');
                        currentCancelUrl = data.cancel_url;
                        // Clusters are auto-labeled from tile pixel content
                        // now (see classification_service's
                        // cluster_labeling.py) -- there's no human labeling
                        // round trip left to wait on, so this run goes
                        // straight from "processing" to a fully scored
                        // "completed" with its overlay and sampling points
                        // already generated. Renders identically to what the
                        // page-load restore path already does for a
                        // completed run, just live instead of after a reload.
                        currentPoll = pollClassificationStatus(data.status_url, function (statusData) {
                            currentSamplingPointsUrl = statusData.sampling_points_url;
                            currentGridCellsUrl = statusData.grid_cells_url;
                            if (qualityInfoEl) qualityInfoEl.textContent = formatQuality(statusData.quality);
                            if (resultsInfoEl) {
                                resultsInfoEl.textContent = statusData.score_summary
                                    ? statusData.score_summary.eligible_count + ' suitable cell(s), ' + statusData.score_summary.excluded_count + ' excluded.'
                                    : 'Results ready.';
                            }
                            var bounds = window.workspaceConfig?.existingOrthomosaic?.bounds_coordinates;
                            window.renderSuitabilityOverlay(bounds, statusData.overlay_url);
                            renderSuitabilityLegend(statusData.legend);
                            if (suitabilityToggle) suitabilityToggle.hidden = false;
                            setSuitabilityLayerVisible(true);
                            if (statusData.points && statusData.points.length) {
                                // Deliberately not rendered on this page's map
                                // -- points are only shown on the dedicated
                                // Sampling Points route now, so this map
                                // doesn't get cluttered with two overlapping
                                // concerns (suitability + point pins) at once.
                                if (samplingPointsInfoEl) {
                                    samplingPointsInfoEl.hidden = false;
                                    samplingPointsInfoEl.textContent = statusData.points.length + ' sampling point(s) generated.';
                                }
                            }
                            showResultsState();
                            setStatus('Results ready.');
                            setAnalyzeBusy(false);
                        }, function (statusData) {
                            setStatus(statusData.error_message || 'Analysis failed.', true);
                            setAnalyzeBusy(false);
                        });
                    }).catch(function (error) {
                        setStatus(error.message || 'Analysis failed.', true);
                        setAnalyzeBusy(false);
                    });
                });

                submitLabelsBtn?.addEventListener('click', function () {
                    if (!currentLabelsUrl || !clusterLabelListEl) return;

                    var labels = {};
                    var unlabeledCount = 0;
                    clusterLabelListEl.querySelectorAll('.cluster-label-select').forEach(function (select) {
                        if (!select.value) unlabeledCount++;
                        labels[select.dataset.clusterId] = select.value;
                    });

                    if (unlabeledCount > 0) {
                        setStatus(unlabeledCount + ' cluster' + (unlabeledCount === 1 ? '' : 's') + ' still need' + (unlabeledCount === 1 ? 's' : '') + ' a label before you can submit.', true);
                        return;
                    }

                    submitLabelsBtn.disabled = true;
                    setStatus('Applying labels and scoring results...');
                    // This wait uses submitLabelsBtn.disabled, not
                    // setAnalyzeBusy() -- the analyze button isn't part of
                    // this step, but Cancel still applies (the second pass
                    // is a real queued job too), so it's shown/hidden
                    // directly here instead.
                    if (cancelBtn) cancelBtn.hidden = false;

                    window.apiFetch(currentLabelsUrl, { method: 'POST', json: { labels: labels } }).then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) throw new Error(data.error || data.message || 'Could not apply labels.');
                            return data;
                        });
                    }).then(function (data) {
                        setStatus('Applying labels and scoring results...');
                        currentCancelUrl = data.cancel_url;
                        currentPoll = pollClassificationStatus(data.status_url, function (statusData) {
                            currentSamplingPointsUrl = statusData.sampling_points_url || currentSamplingPointsUrl;
                            currentGridCellsUrl = statusData.grid_cells_url || currentGridCellsUrl;
                            if (resultsInfoEl) {
                                resultsInfoEl.textContent = statusData.score_summary
                                    ? statusData.score_summary.eligible_count + ' suitable cell(s), ' + statusData.score_summary.excluded_count + ' excluded.'
                                    : 'Results ready.';
                            }
                            var bounds = window.workspaceConfig?.existingOrthomosaic?.bounds_coordinates;
                            window.renderSuitabilityOverlay(bounds, statusData.overlay_url);
                            renderSuitabilityLegend(statusData.legend);
                            if (suitabilityToggle) suitabilityToggle.hidden = false;
                            setSuitabilityLayerVisible(true);
                            showResultsState();
                            setStatus('Results ready.');
                            submitLabelsBtn.disabled = false;
                            if (cancelBtn) cancelBtn.hidden = true;
                        }, function (statusData) {
                            setStatus(statusData.error_message || 'Could not apply labels.', true);
                            submitLabelsBtn.disabled = false;
                            if (cancelBtn) cancelBtn.hidden = true;
                        });
                    }).catch(function (error) {
                        setStatus(error.message || 'Could not apply labels.', true);
                        submitLabelsBtn.disabled = false;
                        if (cancelBtn) cancelBtn.hidden = true;
                    });
                });

                generatePointsBtn?.addEventListener('click', function () {
                    if (!currentSamplingPointsUrl) return;

                    generatePointsBtn.disabled = true;
                    setStatus('Generating sampling points...');

                    window.apiFetch(currentSamplingPointsUrl, { method: 'POST' }).then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) throw new Error(data.error || data.message || 'Could not generate sampling points.');
                            return data;
                        });
                    }).then(function (data) {
                        // Not rendered here -- see the note on the analyze
                        // completion handler above; points display only on
                        // the Sampling Points route.
                        if (samplingPointsInfoEl) {
                            samplingPointsInfoEl.hidden = false;
                            samplingPointsInfoEl.textContent = data.message || (data.point_count + ' sampling point(s) generated.');
                        }
                        setStatus(data.message || 'Sampling points generated.');
                    }).catch(function (error) {
                        setStatus(error.message || 'Could not generate sampling points.', true);
                    }).finally(function () {
                        generatePointsBtn.disabled = false;
                    });
                });

                // --- Per-cell manual correction ---
                // The cluster-labeling step above assigns one label to
                // potentially hundreds of tiles at once -- this lets a
                // researcher fix specific cells the automated pipeline got
                // wrong, without redoing the whole analysis. Cells are
                // rendered as invisible clickable rectangles while edit
                // mode is on (using each cell's real geo bounds, not a
                // redraw of the raster overlay); a cell that's been
                // manually corrected gets a small colored, bordered patch
                // that stays visible even after edit mode is turned off,
                // so the correction is never invisible/lost-looking.
                var LABEL_COLORS = {
                    bare_soil: '#1EA03C',
                    vegetation: '#8C7A2E',
                    open_water: '#6482A0',
                    unclassified: '#B4B4B4'
                };

                var gridCellLayersById = {};

                function styleForCell(cell, isHighlighted) {
                    if (cell.manually_overridden) {
                        return {
                            color: '#fff',
                            weight: 2,
                            fillColor: LABEL_COLORS[cell.dominant_cover] || '#B4B4B4',
                            fillOpacity: 0.75,
                            interactive: gridCellsEditMode
                        };
                    }
                    if (isHighlighted) {
                        return { color: '#fff', weight: 1, fillColor: '#fff', fillOpacity: 0.25, interactive: true };
                    }
                    return { color: 'transparent', weight: 0, fillOpacity: 0, interactive: gridCellsEditMode };
                }

                function closeCellEditPopup() {
                    map.closePopup();
                }

                function openCellEditPopup(cell, layer) {
                    var container = document.createElement('div');
                    container.className = 'grid-cell-edit-popup';

                    var select = document.createElement('select');
                    select.className = 'mission-select';
                    ['bare_soil', 'vegetation', 'open_water', 'unclassified'].forEach(function (label) {
                        var option = document.createElement('option');
                        option.value = label;
                        option.textContent = label;
                        if (label === cell.dominant_cover) option.selected = true;
                        select.appendChild(option);
                    });

                    var saveBtn = document.createElement('button');
                    saveBtn.type = 'button';
                    saveBtn.className = 'mission-generate';
                    saveBtn.textContent = 'Save';
                    saveBtn.style.marginTop = '8px';

                    saveBtn.addEventListener('click', function () {
                        saveBtn.disabled = true;
                        saveBtn.textContent = 'Saving...';

                        window.apiFetch(cell.update_url, { method: 'PATCH', json: { dominant_cover: select.value } })
                            .then(function (response) {
                                return response.json().then(function (data) {
                                    if (!response.ok) throw new Error(data.message || 'Could not update this cell.');
                                    return data;
                                });
                            })
                            .then(function (data) {
                                cell.dominant_cover = data.cell.dominant_cover;
                                cell.manually_overridden = true;
                                layer.setStyle(styleForCell(cell, false));
                                closeCellEditPopup();
                                setStatus('Cell updated.');
                            })
                            .catch(function (error) {
                                setStatus(error.message || 'Could not update this cell.', true);
                            })
                            .finally(function () {
                                saveBtn.disabled = false;
                                saveBtn.textContent = 'Save';
                            });
                    });

                    container.appendChild(select);
                    container.appendChild(saveBtn);
                    layer.bindPopup(container).openPopup();
                }

                function renderGridCells(cells) {
                    gridCellsLayerGroup.clearLayers();
                    gridCellLayersById = {};

                    cells.forEach(function (cell) {
                        var bounds = L.latLngBounds([cell.bounds[0], cell.bounds[1]], [cell.bounds[2], cell.bounds[3]]);
                        var layer = L.rectangle(bounds, styleForCell(cell, false)).addTo(gridCellsLayerGroup);
                        layer._cellRef = cell;
                        gridCellLayersById[cell.id] = layer;

                        layer.on('mouseover', function () {
                            if (gridCellsEditMode && !cell.manually_overridden) layer.setStyle(styleForCell(cell, true));
                        });
                        layer.on('mouseout', function () {
                            if (!cell.manually_overridden) layer.setStyle(styleForCell(cell, false));
                        });
                        layer.on('click', function () {
                            if (!gridCellsEditMode) return;
                            openCellEditPopup(cell, layer);
                        });
                    });
                }

                editGridCellsBtn?.addEventListener('click', function () {
                    if (!currentGridCellsUrl) return;

                    gridCellsEditMode = !gridCellsEditMode;
                    editGridCellsBtn.setAttribute('aria-pressed', String(gridCellsEditMode));
                    if (editGridCellsLabel) editGridCellsLabel.textContent = gridCellsEditMode ? 'Done Correcting' : 'Correct Grid Cells';
                    if (editGridCellsHint) editGridCellsHint.hidden = !gridCellsEditMode;

                    if (!gridCellsLoaded) {
                        editGridCellsBtn.disabled = true;
                        window.apiFetch(currentGridCellsUrl).then(function (response) {
                            return response.json().then(function (data) {
                                if (!response.ok) throw new Error(data.message || 'Could not load grid cells.');
                                return data;
                            });
                        }).then(function (data) {
                            gridCellsLoaded = true;
                            renderGridCells(data.cells);
                        }).catch(function (error) {
                            setStatus(error.message || 'Could not load grid cells.', true);
                            gridCellsEditMode = false;
                            editGridCellsBtn.setAttribute('aria-pressed', 'false');
                            if (editGridCellsLabel) editGridCellsLabel.textContent = 'Correct Grid Cells';
                            if (editGridCellsHint) editGridCellsHint.hidden = true;
                        }).finally(function () {
                            editGridCellsBtn.disabled = false;
                        });
                        return;
                    }

                    // Already loaded -- just re-apply styles so non-
                    // overridden cells become invisible/non-interactive
                    // again when edit mode turns off, while manually
                    // corrected cells stay visible either way.
                    Object.keys(gridCellLayersById).forEach(function (id) {
                        gridCellLayersById[id].setStyle(styleForCell(gridCellLayersById[id]._cellRef || {}, false));
                    });
                });

                // Restore an in-progress or completed classification run on
                // reload, same "don't lose it on refresh" treatment the
                // orthomosaic/AOI/mission restores above already get.
                var existingClassification = window.workspaceConfig?.existingClassification;
                if (existingClassification) {
                    currentLabelsUrl = existingClassification.labels_url;
                    currentSamplingPointsUrl = existingClassification.sampling_points_url;
                    currentGridCellsUrl = existingClassification.grid_cells_url;

                    if (existingClassification.status === 'processing') {
                        // A run was still in flight (queued/running on the
                        // worker) when this page loaded -- resume polling
                        // instead of leaving it a dead end until a manual
                        // refresh happens to catch it in a finished state.
                        setAnalyzeBusy(true, 'Analyzing...');
                        setStatus('Running classification — this can take a moment...');
                        currentCancelUrl = existingClassification.cancel_url;
                        currentPoll = pollClassificationStatus(existingClassification.status_url, function (statusData) {
                            setAnalyzeBusy(false);
                            if (statusData.overlay_url) {
                                currentLabelsUrl = null;
                                currentSamplingPointsUrl = statusData.sampling_points_url || currentSamplingPointsUrl;
                            currentGridCellsUrl = statusData.grid_cells_url || currentGridCellsUrl;
                                var bounds = window.workspaceConfig?.existingOrthomosaic?.bounds_coordinates;
                                window.renderSuitabilityOverlay(bounds, statusData.overlay_url);
                                renderSuitabilityLegend(statusData.legend);
                                if (suitabilityToggle) suitabilityToggle.hidden = false;
                                setSuitabilityLayerVisible(true);
                                if (statusData.points && statusData.points.length) {
                                    // Not rendered here -- points display
                                    // only on the Sampling Points route.
                                    if (samplingPointsInfoEl) {
                                        samplingPointsInfoEl.hidden = false;
                                        samplingPointsInfoEl.textContent = statusData.points.length + ' sampling point(s) generated.';
                                    }
                                }
                                showResultsState();
                                setStatus('Results ready.');
                            } else {
                                currentLabelsUrl = statusData.labels_url;
                                if (qualityInfoEl) qualityInfoEl.textContent = formatQuality(statusData.quality);
                                setStatus('Analysis complete.');
                                loadClusters(statusData.clusters_url);
                            }
                        }, function (statusData) {
                            setAnalyzeBusy(false);
                            setStatus(statusData.error_message || 'Analysis failed.', true);
                        });
                    } else if (existingClassification.status === 'pending_labels') {
                        loadClusters(existingClassification.clusters_url);
                    } else if (existingClassification.status === 'completed') {
                        if (resultsInfoEl) {
                            resultsInfoEl.textContent = existingClassification.score_summary
                                ? existingClassification.score_summary.eligible_count + ' suitable cell(s), ' + existingClassification.score_summary.excluded_count + ' excluded.'
                                : 'Results ready.';
                        }
                        if (existingClassification.overlay_url) {
                            var bounds = window.workspaceConfig?.existingOrthomosaic?.bounds_coordinates;
                            window.renderSuitabilityOverlay(bounds, existingClassification.overlay_url);
                            renderSuitabilityLegend(existingClassification.legend);
                        }
                        if (existingClassification.points && existingClassification.points.length) {
                            // Not rendered here -- points display only on
                            // the Sampling Points route.
                            if (samplingPointsInfoEl) {
                                samplingPointsInfoEl.hidden = false;
                                samplingPointsInfoEl.textContent = existingClassification.points.length + ' sampling point(s) generated.';
                            }
                        }
                        showResultsState();
                    }
                }
            })();

            // --- Compass / cursor coordinates / elevation HUD (top-right) ---
            (function () {
                var compassLat = document.getElementById('compassLat');
                var compassLng = document.getElementById('compassLng');
                var compassElevation = document.getElementById('compassElevation');
                var compassEyeAltitude = document.getElementById('compassEyeAltitude');

                function toDms(value, positiveSuffix, negativeSuffix) {
                    var direction = value >= 0 ? positiveSuffix : negativeSuffix;
                    var absolute = Math.abs(value);
                    var degrees = Math.floor(absolute);
                    var minutesFloat = (absolute - degrees) * 60;
                    var minutes = Math.floor(minutesFloat);
                    var seconds = ((minutesFloat - minutes) * 60).toFixed(2);
                    return degrees + '° ' + String(minutes).padStart(2, '0') + '\' '
                        + String(seconds).padStart(5, '0') + '" ' + direction;
                }

                function updateCompassCoords(latlng) {
                    if (compassLat) compassLat.textContent = toDms(latlng.lat, 'N', 'S');
                    if (compassLng) compassLng.textContent = toDms(latlng.lng, 'E', 'W');
                }

                // "Eye altitude" approximates a Google-Earth-style camera height from
                // the current zoom level — there's no real camera in a 2D Leaflet map,
                // so this derives a plausible altitude from standard Web Mercator
                // ground resolution (meters/pixel) plus an assumed vertical field of
                // view. It's a cosmetic estimate for the HUD, not a precise value.
                var ASSUMED_VERTICAL_FOV_DEG = 40;

                function updateEyeAltitude() {
                    if (!compassEyeAltitude) return;
                    var zoom = map.getZoom();
                    var lat = map.getCenter().lat;
                    var metersPerPixel = 156543.03392 * Math.cos(lat * Math.PI / 180) / Math.pow(2, zoom);
                    var mapHeightPx = map.getSize().y;
                    var altitude = (metersPerPixel * mapHeightPx / 2) / Math.tan((ASSUMED_VERTICAL_FOV_DEG / 2) * Math.PI / 180);
                    compassEyeAltitude.textContent = 'Eye altitude ' + Math.round(altitude).toLocaleString() + ' m';
                }

                // Elevation needs a real lookup — Open-Elevation is a free public API,
                // so requests are heavily debounced (fire only once the cursor pauses)
                // and cached by a coarse (~100 m) grid key to avoid hammering it while
                // the mouse lingers in roughly the same spot.
                var elevationCache = {};
                var elevationAbortController = null;
                var elevationDebounceTimer = null;

                function renderElevation(elevation) {
                    if (!compassElevation) return;
                    compassElevation.textContent = 'Elevation ' + (elevation === null ? 'N/A' : elevation + ' m');
                }

                function fetchElevation(lat, lng) {
                    var key = lat.toFixed(3) + ',' + lng.toFixed(3);

                    if (Object.prototype.hasOwnProperty.call(elevationCache, key)) {
                        renderElevation(elevationCache[key]);
                        return;
                    }

                    if (elevationAbortController) elevationAbortController.abort();
                    elevationAbortController = new AbortController();

                    fetch('https://api.open-elevation.com/api/v1/lookup?locations=' + lat + ',' + lng, {
                        signal: elevationAbortController.signal
                    })
                        .then(function (res) { return res.ok ? res.json() : null; })
                        .then(function (data) {
                            var result = data && Array.isArray(data.results) ? data.results[0] : null;
                            var elevation = result && typeof result.elevation === 'number' ? Math.round(result.elevation) : null;
                            elevationCache[key] = elevation;
                            renderElevation(elevation);
                        })
                        .catch(function (err) {
                            if (err.name === 'AbortError') return; // superseded by a newer cursor position
                            renderElevation(null);
                        });
                }

                map.on('mousemove', function (e) {
                    updateCompassCoords(e.latlng);

                    window.clearTimeout(elevationDebounceTimer);
                    elevationDebounceTimer = window.setTimeout(function () {
                        fetchElevation(e.latlng.lat, e.latlng.lng);
                    }, 400);
                });

                map.on('zoomend moveend', updateEyeAltitude);
                updateEyeAltitude();
            })();

            // --- Sampling Points (read-only view): the orthomosaic +
            // suitability overlay + generated points, no upload/analyze
            // controls -- this route is just "where are they and how
            // many," not a workflow step of its own the way Orthomosaic
            // is. Deliberately its own IIFE rather than added to the
            // orthomosaic-upload one above: that one bails out immediately
            // (`if (!fileInput || !uploadBtn) return;`) since this page
            // has no upload UI at all.
            (function () {
                if (window.workspaceConfig?.currentRoute !== 'sampling-points') return;
                var map = window.workspaceMap;
                if (!map) return;

                var suitabilityLayerGroup = L.layerGroup().addTo(map);
                var samplingPointsLayerGroup = L.layerGroup().addTo(map);
                var existing = window.workspaceConfig?.existingOrthomosaic;
                var classification = window.workspaceConfig?.existingClassification;

                if (existing && existing.preview_url && existing.bounds_coordinates) {
                    // Same fast path as the Orthomosaic route -- a small
                    // pre-downscaled PNG instead of fetching/parsing the
                    // full-resolution GeoTIFF client-side.
                    var previewB = existing.bounds_coordinates;
                    var previewBounds2 = L.latLngBounds([previewB[0], previewB[1]], [previewB[2], previewB[3]]);
                    L.imageOverlay(existing.preview_url, previewBounds2).addTo(map);
                    // Same delayed-fitBounds fix as the Orthomosaic route --
                    // see that block's comment for why.
                    window.setTimeout(function () {
                        map.fitBounds(previewBounds2);
                    }, 200);
                } else if (existing && existing.file_url) {
                    fetch(existing.file_url)
                        .then(function (response) {
                            if (!response.ok) throw new Error('Could not load the orthomosaic.');
                            return response.arrayBuffer();
                        })
                        .then(function (buffer) { return parseGeoraster(buffer); })
                        .then(function (georaster) {
                            new GeoRasterLayer({ georaster: georaster, resolution: 256 }).addTo(map);
                            map.fitBounds(L.latLngBounds(
                                [georaster.ymin, georaster.xmin],
                                [georaster.ymax, georaster.xmax]
                            ));
                        })
                        .catch(function () {
                            // The suitability overlay and point markers below
                            // are independent of this preview -- they still
                            // render (georeferenced against the same bounds
                            // the server stored) even if the raw GeoTIFF
                            // preview itself fails to fetch/parse.
                        });
                }

                if (classification && classification.overlay_url && existing?.bounds_coordinates) {
                    var b = existing.bounds_coordinates;
                    var overlayBounds = L.latLngBounds([b[0], b[1]], [b[2], b[3]]);
                    L.imageOverlay(classification.overlay_url + '?t=' + Date.now(), overlayBounds, { opacity: 0.65 }).addTo(suitabilityLayerGroup);
                    renderSuitabilityLegend(classification.legend);
                }

                (classification?.points || []).forEach(function (point) {
                    L.marker([point.latitude, point.longitude], {
                        icon: samplingPointIcon(samplingPointColor(point.status), point.priority_rank)
                    }).bindTooltip('Point #' + point.priority_rank).addTo(samplingPointsLayerGroup);
                });

                wireLayerToggle('samplingPointsLayerToggle', 'samplingPointsLayerCheck', samplingPointsLayerGroup);
            })();

            // --- Sampling Points: assign a point to a field technician ---
            // "Assigning" a point IS "transmitting" it -- the mobile app
            // pulls its own assigned list on demand (no separate push
            // step), so this one action is the whole hand-off.
            (function () {
                if (window.workspaceConfig?.currentRoute !== 'sampling-points') return;

                var technicianSelect = document.getElementById('samplingAssignTechnician');
                var pointSelect = document.getElementById('samplingAssignPoint');
                var assignBtn = document.getElementById('samplingAssignBtn');
                var assignAllBtn = document.getElementById('samplingAssignAllBtn');
                var statusEl = document.getElementById('samplingAssignStatus');
                if (!technicianSelect || !pointSelect || !assignBtn) return;

                var urlTemplate = window.workspaceConfig?.assignPointUrlTemplate;
                var assignAllUrl = window.workspaceConfig?.assignAllPointsUrl;
                var points = window.workspaceConfig?.existingClassification?.points || [];

                function setStatus(message, isError) {
                    if (!statusEl) return;
                    statusEl.textContent = message || '';
                    statusEl.hidden = !message;
                    statusEl.classList.toggle('is-error', !!isError);
                    statusEl.classList.toggle('is-success', !!message && !isError);
                }

                function refreshPointOptions() {
                    var previousValue = pointSelect.value;
                    pointSelect.innerHTML = '<option value="" disabled selected>Select a point</option>';
                    points.forEach(function (point) {
                        var option = document.createElement('option');
                        option.value = point.id;
                        var statusLabel = point.status === 'collected' ? ' (collected)' : point.status === 'transmitted' ? ' (already assigned)' : '';
                        option.textContent = 'Point #' + point.priority_rank + statusLabel;
                        if (point.status === 'collected') option.disabled = true;
                        pointSelect.appendChild(option);
                    });
                    if (previousValue) pointSelect.value = previousValue;
                }

                function updateAssignBtnState() {
                    assignBtn.disabled = !technicianSelect.value || !pointSelect.value;
                    if (assignAllBtn) assignAllBtn.disabled = !technicianSelect.value;
                }

                refreshPointOptions();
                technicianSelect.addEventListener('change', updateAssignBtnState);
                pointSelect.addEventListener('change', updateAssignBtnState);

                assignBtn.addEventListener('click', function () {
                    if (!urlTemplate || !technicianSelect.value || !pointSelect.value) return;
                    var url = urlTemplate.replace('__POINT_ID__', pointSelect.value);
                    assignBtn.disabled = true;
                    setStatus('');

                    window.apiFetch(url, {
                        method: 'POST',
                        json: { technician_id: technicianSelect.value },
                    }).then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) throw new Error(data.message || 'Could not assign this point.');
                            return data;
                        });
                    }).then(function (data) {
                        setStatus(data.message || 'Point assigned.');
                        var updated = points.find(function (p) { return p.id === pointSelect.value; });
                        if (updated) {
                            updated.status = 'transmitted';
                            updated.assigned_to = technicianSelect.value;
                        }
                        refreshPointOptions();
                        if (typeof window.renderSamplingPoints === 'function') {
                            window.renderSamplingPoints(points);
                        }
                    }).catch(function (error) {
                        setStatus(error.message || 'Could not assign this point.', true);
                    }).finally(function () {
                        updateAssignBtnState();
                    });
                });

                if (assignAllBtn) {
                    assignAllBtn.addEventListener('click', function () {
                        if (!assignAllUrl || !technicianSelect.value) return;
                        assignAllBtn.disabled = true;
                        setStatus('');

                        window.apiFetch(assignAllUrl, {
                            method: 'POST',
                            json: { technician_id: technicianSelect.value },
                        }).then(function (response) {
                            return response.json().then(function (data) {
                                if (!response.ok) throw new Error(data.message || 'Could not assign these points.');
                                return data;
                            });
                        }).then(function (data) {
                            setStatus(data.message || 'Points assigned.');
                            points.forEach(function (point) {
                                if (point.status !== 'collected') {
                                    point.status = 'transmitted';
                                    point.assigned_to = technicianSelect.value;
                                }
                            });
                            refreshPointOptions();
                            if (typeof window.renderSamplingPoints === 'function') {
                                window.renderSamplingPoints(points);
                            }
                        }).catch(function (error) {
                            setStatus(error.message || 'Could not assign these points.', true);
                        }).finally(function () {
                            updateAssignBtnState();
                        });
                    });
                }
            })();

            // --- Soil Readings (read-only view): same orthomosaic-preview
            // pattern as Sampling Points above, but markers are colored by
            // recorded suitability rating instead of collection status, and
            // clicking one opens a popup with that point's full reading --
            // there's no entry form on this route anymore, readings arrive
            // from the mobile app.
            (function () {
                if (window.workspaceConfig?.currentRoute !== 'soil-readings') return;
                var map = window.workspaceMap;
                if (!map) return;

                var samplingPointsLayerGroup = L.layerGroup().addTo(map);
                var existing = window.workspaceConfig?.existingOrthomosaic;
                var classification = window.workspaceConfig?.existingClassification;

                if (existing && existing.preview_url && existing.bounds_coordinates) {
                    var previewB = existing.bounds_coordinates;
                    var previewBounds = L.latLngBounds([previewB[0], previewB[1]], [previewB[2], previewB[3]]);
                    L.imageOverlay(existing.preview_url, previewBounds).addTo(map);
                    // Same delayed-fitBounds fix as the other read-only map
                    // views -- see the Orthomosaic route's block for why.
                    window.setTimeout(function () {
                        map.fitBounds(previewBounds);
                    }, 200);
                } else if (existing && existing.file_url) {
                    fetch(existing.file_url)
                        .then(function (response) {
                            if (!response.ok) throw new Error('Could not load the orthomosaic.');
                            return response.arrayBuffer();
                        })
                        .then(function (buffer) { return parseGeoraster(buffer); })
                        .then(function (georaster) {
                            new GeoRasterLayer({ georaster: georaster, resolution: 256 }).addTo(map);
                            map.fitBounds(L.latLngBounds(
                                [georaster.ymin, georaster.xmin],
                                [georaster.ymax, georaster.xmax]
                            ));
                        })
                        .catch(function () {
                            // Point markers below are independent of this
                            // preview and still render either way.
                        });
                }

                function formatReadingPopup(point) {
                    var reading = point.reading;
                    var title = '<strong>Point #' + point.priority_rank + '</strong>';
                    if (!reading) {
                        return title + '<br>Not yet collected.';
                    }
                    var header = title;
                    // The interactive touch the user asked for -- a small
                    // reference photo + the species' full common name at
                    // the top of the popup, when one was recorded for this
                    // reading. Species comes from the mobile app/probe
                    // submission itself, not something set here.
                    if (reading.species_label) {
                        header = (reading.species_photo_url
                            ? '<img src="' + reading.species_photo_url + '" class="popup-species-photo" alt="' + reading.species_label + '">'
                            : '')
                            + '<div class="popup-species-label">' + reading.species_label + '</div>'
                            + header;
                    }
                    var lines = [header];
                    var ratingLabel = reading.suitability_rating
                        ? reading.suitability_rating.charAt(0).toUpperCase() + reading.suitability_rating.slice(1)
                        : 'Not rated';
                    lines.push(ratingLabel);
                    if (reading.ph_avg !== null && reading.ph_avg !== undefined) lines.push('pH: ' + reading.ph_avg);
                    if (reading.salinity_avg !== null && reading.salinity_avg !== undefined) lines.push('Salinity: ' + reading.salinity_avg + ' ppt');
                    if (reading.moisture_avg !== null && reading.moisture_avg !== undefined) lines.push('Moisture: ' + reading.moisture_avg + '%');
                    if (reading.nitrogen_avg !== null && reading.nitrogen_avg !== undefined
                        || reading.phosphorus_avg !== null && reading.phosphorus_avg !== undefined
                        || reading.potassium_avg !== null && reading.potassium_avg !== undefined) {
                        lines.push('N/P/K: ' + (reading.nitrogen_avg ?? '--') + ' / ' + (reading.phosphorus_avg ?? '--') + ' / ' + (reading.potassium_avg ?? '--'));
                    }
                    if (reading.collected_by) lines.push('Collected by ' + reading.collected_by + (reading.collected_at ? ' on ' + reading.collected_at : ''));
                    return lines.join('<br>');
                }

                (classification?.points || []).forEach(function (point) {
                    L.marker([point.latitude, point.longitude], {
                        icon: samplingPointIcon(soilReadingColor(point), point.priority_rank)
                    }).bindPopup(formatReadingPopup(point)).addTo(samplingPointsLayerGroup);
                });

                wireLayerToggle('samplingPointsLayerToggle', 'samplingPointsLayerCheck', samplingPointsLayerGroup);

                // --- Map View / Table View toggle ---
                // The table needs real width to be useful (12 columns) --
                // far more than the 300px left dock has -- so switching to
                // it swaps the whole main content area (map -> the wide
                // table panel) rather than trying to cram the table into
                // the sidebar alongside the map.
                var mapViewBtn = document.getElementById('soilReadingsMapViewBtn');
                var tableViewBtn = document.getElementById('soilReadingsTableViewBtn');
                var tablePanel = document.getElementById('soilReadingsTablePanel');
                var mapContainer = document.getElementById('workspace-map');

                function setView(view) {
                    var isTable = view === 'table';
                    mapViewBtn?.classList.toggle('active', !isTable);
                    tableViewBtn?.classList.toggle('active', isTable);
                    if (mapContainer) mapContainer.hidden = isTable;
                    if (tablePanel) tablePanel.hidden = !isTable;
                    if (!isTable) {
                        // Same "let layout settle, then re-measure" pattern
                        // used elsewhere in this file (dock collapse,
                        // report mini-map) -- Leaflet doesn't redraw
                        // correctly if its container was hidden (0-size)
                        // while something changed.
                        window.setTimeout(function () { map.invalidateSize(); }, 50);
                    }
                }

                mapViewBtn?.addEventListener('click', function () { setView('map'); });
                tableViewBtn?.addEventListener('click', function () { setView('table'); });

                // --- Table filter/sort (pure client-side -- all rows are
                // already rendered server-side, this just shows/hides and
                // reorders them) ---
                var filterSelect = document.getElementById('soilReadingsFilterRating');
                var sortSelect = document.getElementById('soilReadingsSort');
                var table = document.getElementById('soilReadingsTable');

                function applyFilterAndSort() {
                    if (!table) return;
                    var tbody = table.querySelector('tbody');
                    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                    var ratingFilter = filterSelect ? filterSelect.value : 'all';

                    rows.forEach(function (row) {
                        var matches = ratingFilter === 'all' || row.dataset.rating === ratingFilter;
                        row.hidden = !matches;
                    });

                    var sortBy = sortSelect ? sortSelect.value : 'newest';
                    rows.sort(function (a, b) {
                        if (sortBy === 'point') {
                            return (Number(a.dataset.pointRank) || 0) - (Number(b.dataset.pointRank) || 0);
                        }
                        var aTime = Number(a.dataset.collectedAt) || 0;
                        var bTime = Number(b.dataset.collectedAt) || 0;
                        return sortBy === 'oldest' ? aTime - bTime : bTime - aTime;
                    });
                    rows.forEach(function (row) { tbody.appendChild(row); });
                }

                filterSelect?.addEventListener('change', applyFilterAndSort);
                sortSelect?.addEventListener('change', applyFilterAndSort);
            })();

            var config = window.workspaceConfig || {};
            var mapShell = document.getElementById('mapShell');
            var instruction = document.getElementById('aoiInstruction');
            var infoPanel = document.getElementById('aoiInfoPanel');
            var redrawSection = document.getElementById('aoiRedrawSection');
            var missionParametersPanel = document.getElementById('missionParametersPanel');
            var missionSummarySection = document.getElementById('missionSummarySection');
            var missionPathLegend = document.getElementById('missionPathLegend');
            var aoiRedrawModalBackdrop = document.getElementById('aoiRedrawModalBackdrop');
            var aoiRedrawModalCard = document.getElementById('aoiRedrawModalCard');
            var modeToggle = document.getElementById('aoiToolbar');
            var corridorWidthPanel = document.getElementById('aoiCorridorWidth');
            var corridorWidthInput = document.getElementById('corridorWidthInput');
            var instructionText = document.getElementById('aoiInstructionText');
            var panelTitle = document.getElementById('aoiPanelTitle');
            var centerValue = document.getElementById('aoiCenterValue');
            var areaValue = document.getElementById('aoiAreaValue');
            var dimensionValue = document.getElementById('aoiDimensionValue');
            var panelActions = document.getElementById('aoiPanelActions');
            var confirmBtn = document.getElementById('confirmAoiBtn');
            var aoiToast = document.querySelector('.aoi-toast');
            var mapToast = document.getElementById('aoiMapToast');
            // Mirrors App\Services\Geo\AoiValidator::MAX_AREA_M2 / MAX_DIMENSION_M —
            // keep these two in sync if the caps ever change server-side.
            var AOI_MAX_AREA_M2 = 40000;
            var AOI_MAX_DIMENSION_M = 200;
            var aoiOverCap = false;
            var aoiLayerGroup = L.layerGroup().addTo(map);
            var aoiPolygonLayer = null;
            var missionPathLayerGroup = L.layerGroup().addTo(map);

            var missionStartIcon = L.divIcon({
                className: 'mission-endpoint-marker mission-start-marker',
                html: '<i class="fa-solid fa-plane-departure"></i>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            var missionEndIcon = L.divIcon({
                className: 'mission-endpoint-marker mission-end-marker',
                html: '<i class="fa-solid fa-flag-checkered"></i>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });

            function missionArrowIcon(bearingDeg) {
                return L.divIcon({
                    className: 'mission-direction-arrow',
                    html: '<i class="fa-solid fa-caret-up" style="display:block;transform:rotate(' + bearingDeg + 'deg);"></i>',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                });
            }

            // Renders the actual server-computed flight path (exact lat/lng
            // waypoints from MissionGridGenerator, already clipped to the
            // confirmed AOI boundary) — called from droneMissionPanel's
            // generateMission() once the API responds. Not a client-side
            // approximation; these are the same coordinates written into the
            // downloaded KMZ.
            window.renderMissionPath = function (waypoints) {
                missionPathLayerGroup.clearLayers();
                var legend = document.getElementById('missionPathLegend');

                if (!Array.isArray(waypoints) || waypoints.length === 0) {
                    if (legend) legend.hidden = true;
                    return;
                }

                var latlngs = waypoints.map(function (wp) { return [wp.lat, wp.lng]; });

                // Dark casing drawn first so the path stays legible over busy
                // satellite imagery, regardless of what's underneath it.
                L.polyline(latlngs, {
                    color: '#1B1F1C',
                    weight: 7,
                    opacity: 0.55,
                    interactive: false
                }).addTo(missionPathLayerGroup);

                L.polyline(latlngs, {
                    color: '#F0A500',
                    weight: 4,
                    opacity: 0.95,
                    dashArray: '8 7',
                    interactive: false
                }).addTo(missionPathLayerGroup);

                // Direction-of-travel arrows at each leg's midpoint, rotated
                // to that leg's real computed bearing (same value written
                // into the KMZ) — so the flight order is visible at a glance,
                // not just inferable from the start/end markers.
                for (var i = 0; i < waypoints.length - 1; i++) {
                    var a = waypoints[i];
                    var b = waypoints[i + 1];
                    var midLat = (a.lat + b.lat) / 2;
                    var midLng = (a.lng + b.lng) / 2;
                    L.marker([midLat, midLng], {
                        icon: missionArrowIcon(a.heading || 0),
                        interactive: false,
                        keyboard: false
                    }).addTo(missionPathLayerGroup);
                }

                waypoints.forEach(function (wp, index) {
                    var isStart = index === 0;
                    var isEnd = index === waypoints.length - 1;

                    if (isStart || isEnd) {
                        L.marker([wp.lat, wp.lng], {
                            icon: isStart ? missionStartIcon : missionEndIcon,
                            interactive: false,
                            keyboard: false,
                            zIndexOffset: 1000
                        }).addTo(missionPathLayerGroup);
                        return;
                    }

                    L.circleMarker([wp.lat, wp.lng], {
                        radius: 4,
                        color: '#F8F9FA',
                        weight: 1.5,
                        fillColor: '#F0A500',
                        fillOpacity: 1,
                        interactive: false
                    }).addTo(missionPathLayerGroup);
                });

                if (legend) legend.hidden = false;

                map.fitBounds(L.latLngBounds(latlngs), { padding: [40, 40] });
            };
            var centerMarker = null;
            var aoiPinIcon = L.divIcon({
                className: 'aoi-pin-marker',
                html: '<i class="fa-solid fa-location-dot"></i>',
                iconSize: [46, 46],
                iconAnchor: [23, 45]
            });
            var currentAoi = null;
            var placementLocked = false;
            // Corridor mode: draw a path (centerline) along the coast, buffer
            // it by a chosen width into the actual AOI polygon. Purely a
            // construction aid for the frontend — the buffered result flows
            // through the exact same drawAoiPolygon()/validation/save pipeline
            // as a freehand polygon, so nothing downstream needs to know how
            // the shape was built.
            //
            // activeDrawer is (re)created fresh each time drawing starts,
            // rather than keeping long-lived Polygon/Polyline instances
            // around and toggling enable()/disable() on both — juggling two
            // persistent handlers on the same map raced when switching modes
            // right after page load (one handler's teardown and the other's
            // setup could interleave), silently leaving neither handler
            // properly bound to map clicks. A fresh instance per draw avoids
            // that class of bug entirely.
            var activeDrawer = null;
            var aoiMode = 'polygon';
            var currentCorridorPath = null;

            function createDrawer(mode) {
                if (mode === 'corridor') {
                    return new L.Draw.Polyline(map, {
                        shapeOptions: { color: '#2D6A4F', weight: 3 },
                        showLength: true,
                        metric: true
                    });
                }

                if (mode === 'rectangle') {
                    return new L.Draw.Rectangle(map, {
                        shapeOptions: { color: '#2D6A4F', weight: 2, fillColor: '#74C69D', fillOpacity: 0.22 },
                        showArea: false,
                        metric: true
                    });
                }

                return new L.Draw.Polygon(map, {
                    shapeOptions: { color: '#2D6A4F', weight: 2, fillColor: '#74C69D', fillOpacity: 0.22 },
                    showLength: false,
                    metric: true,
                    allowIntersection: false,
                    showArea: false
                });
            }
                document.getElementById('dismissAoiInstruction')?.addEventListener('click', function () {
                    if (instruction) instruction.hidden = true;
                });

                // Client-side area/longest-span estimate for the live info panel
                // only — a lightweight preview, not the authoritative check.
                // App\Services\Geo\AoiValidator (mirrored by
                // mission_generation_service/validation.py) is what actually
                // enforces the 4 ha / 200 m / self-intersection rules
                // server-side when the AOI is confirmed.
                function estimateAoiMetrics(latlngs) {
                    if (!latlngs || latlngs.length < 3) return { areaM2: 0, longestM: 0 };

                    var refLat = latlngs.reduce(function (sum, p) { return sum + p.lat; }, 0) / latlngs.length;
                    var refLng = latlngs.reduce(function (sum, p) { return sum + p.lng; }, 0) / latlngs.length;
                    var cosRef = Math.cos(refLat * Math.PI / 180);

                    var points = latlngs.map(function (p) {
                        return [(p.lng - refLng) * 111320 * cosRef, (p.lat - refLat) * 111320];
                    });

                    var area = 0;
                    for (var i = 0; i < points.length; i++) {
                        var a = points[i], b = points[(i + 1) % points.length];
                        area += a[0] * b[1] - b[0] * a[1];
                    }
                    area = Math.abs(area) / 2;

                    var longest = 0;
                    for (var i = 0; i < points.length; i++) {
                        for (var j = i + 1; j < points.length; j++) {
                            var d = Math.hypot(points[i][0] - points[j][0], points[i][1] - points[j][1]);
                            if (d > longest) longest = d;
                        }
                    }

                    return { areaM2: area, longestM: longest };
                }

                function formatArea(areaM2) {
                    return areaM2 >= 10000
                        ? (areaM2 / 10000).toFixed(2) + ' ha'
                        : Math.round(areaM2).toLocaleString() + ' m²';
                }

                function drawAoiPolygon(latlngs, locked, shouldFit) {
                    aoiLayerGroup.clearLayers();

                    var bounds = L.latLngBounds(latlngs);
                    var center = bounds.getCenter();

                    aoiPolygonLayer = L.polygon(latlngs, {
                        color: '#2D6A4F',
                        weight: locked ? 3 : 2,
                        fillColor: '#74C69D',
                        fillOpacity: locked ? 0.28 : 0.22,
                        interactive: false
                    }).addTo(aoiLayerGroup);

                    centerMarker = (config.isAoiRoute
                        ? L.marker(center, { icon: aoiPinIcon, interactive: false })
                        : L.circleMarker(center, { radius: 7, color: '#F8F9FA', fillColor: '#2D6A4F', fillOpacity: 1, weight: 3, interactive: false })
                    ).addTo(aoiLayerGroup);

                    currentAoi = { corners: latlngs, center: center, bounds: bounds };

                    if (centerValue) centerValue.textContent = center.lat.toFixed(5) + ', ' + center.lng.toFixed(5);
                    if (infoPanel) infoPanel.hidden = false;
                    if (redrawSection) redrawSection.hidden = false;
                    if (confirmBtn) confirmBtn.hidden = false;

                    var metrics = estimateAoiMetrics(latlngs);
                    if (areaValue) areaValue.textContent = formatArea(metrics.areaM2);
                    if (dimensionValue) dimensionValue.textContent = Math.round(metrics.longestM) + ' m';
                    updateCapWarning(metrics);

                    if (shouldFit) {
                        map.fitBounds(bounds.pad(0.5), { animate: true, duration: 0.35 });
                    }

                    return currentAoi;
                }

                // Live client-side warning only (the estimate above is a
                // cheap preview, not the exact server calculation) — flags
                // an over-cap shape before the user wastes a round trip on
                // "Confirm AOI", but the server's AoiValidator is still the
                // authority that actually rejects it.
                function updateCapWarning(metrics) {
                    var overArea = metrics.areaM2 > AOI_MAX_AREA_M2;
                    var overDimension = metrics.longestM > AOI_MAX_DIMENSION_M;
                    var wasOverCap = aoiOverCap;
                    aoiOverCap = overArea || overDimension;

                    if (areaValue) areaValue.classList.toggle('aoi-metric-value-warning', overArea);
                    if (dimensionValue) dimensionValue.classList.toggle('aoi-metric-value-warning', overDimension);

                    if (confirmBtn) {
                        confirmBtn.disabled = aoiOverCap;
                        confirmBtn.title = aoiOverCap ? 'Redraw within the size caps before confirming' : '';
                    }

                    // Warned on the map itself, not tucked into a Properties
                    // panel notice easy to miss — fires once on the
                    // OK-to-over-cap transition rather than on every vertex
                    // move while already over, so it doesn't spam during
                    // active drawing. The disabled Confirm button + red
                    // metric values above stay as the ongoing indicator
                    // once the toast fades.
                    if (aoiOverCap && !wasOverCap) {
                        var reasons = [];
                        if (overArea) reasons.push('exceeds the 4 hectare area cap');
                        if (overDimension) reasons.push('exceeds the 200 m longest-dimension cap');
                        showMapToast('This shape ' + reasons.join(' and ') + ' — redraw a smaller area before confirming.', 'error');
                    }
                }

                // Corridor mode: buffer a drawn centerline path by half the
                // chosen width on each side, using Turf's polygon-buffer
                // (handles bends/joins properly — hand-rolling this for
                // multi-segment paths is exactly the kind of thing a
                // well-tested library should do instead of a custom offset
                // routine). Returns a flat array of L.LatLng, same shape
                // drawAoiPolygon() already expects from freehand drawing.
                function bufferPathToPolygon(pathLatLngs, widthMeters) {
                    var coords = pathLatLngs.map(function (p) { return [p.lng, p.lat]; });
                    var line = turf.lineString(coords);
                    var buffered = turf.buffer(line, widthMeters / 2, { units: 'meters' });

                    if (!buffered || !buffered.geometry) return null;

                    var geom = buffered.geometry;
                    var ring = geom.type === 'Polygon' ? geom.coordinates[0] : geom.coordinates[0][0];
                    if (!ring) return null;

                    return ring.map(function (c) { return L.latLng(c[1], c[0]); });
                }

                function renderCorridorFromPath(shouldFit) {
                    if (!currentCorridorPath) return;
                    var width = Math.max(10, Number(corridorWidthInput?.value) || 50);
                    var polygonLatLngs = bufferPathToPolygon(currentCorridorPath, width);

                    if (!polygonLatLngs) {
                        showMapToast('Could not build a corridor from this path — try a simpler route.', 'error');
                        return;
                    }

                    drawAoiPolygon(polygonLatLngs, false, shouldFit);
                    if (panelTitle) panelTitle.textContent = 'AOI Preview';
                    if (aoiToast) aoiToast.hidden = true;
                }

                window.getWorkspaceAoiPolygon = function () {
                    if (aoiPolygonLayer) {
                        var latlngs = aoiPolygonLayer.getLatLngs();
                        var ring = Array.isArray(latlngs[0]) ? latlngs[0] : latlngs;

                        return ring.map(function (point) {
                            return [point.lat, point.lng];
                        });
                    }

                    if (currentAoi?.corners) {
                        return currentAoi.corners.map(function (point) {
                            return [point.lat, point.lng];
                        });
                    }

                    return config.savedAoi?.boundary_coordinates || null;
                };


                function setAoiMode(mode) {
                    aoiMode = mode;
                    currentCorridorPath = null;

                    if (modeToggle) {
                        modeToggle.querySelectorAll('.aoi-toolbar-btn').forEach(function (btn) {
                            btn.classList.toggle('active', btn.dataset.aoiMode === mode);
                        });
                    }
                    if (corridorWidthPanel) corridorWidthPanel.hidden = mode !== 'corridor';
                    if (instructionText) {
                        var instructionsByMode = {
                            corridor: 'Click along the coastline to draw a path, then double-click to finish. The strip width below controls how wide the AOI is built around it. Max 4 hectares (200 m x 200 m), 200 m longest dimension. Press Esc anytime to cancel.',
                            rectangle: 'Click and drag to draw a rectangular Area of Interest. Max 4 hectares (200 m x 200 m), 200 m longest dimension. Press Esc anytime to cancel.',
                            polygon: 'Click on the map to place each corner of your Area of Interest, then click the first point again (or double-click) to close the shape. Max 4 hectares (200 m x 200 m), 200 m longest dimension. Press Esc anytime to cancel.'
                        };
                        instructionText.textContent = instructionsByMode[mode] || instructionsByMode.polygon;
                    }
                }

                modeToggle?.querySelectorAll('.aoi-toolbar-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (placementLocked) return;
                        setAoiMode(btn.dataset.aoiMode);
                        startDrawing();
                    });
                });

                corridorWidthInput?.addEventListener('input', function () {
                    if (currentCorridorPath) renderCorridorFromPath(false);
                });

                function startDrawing() {
                    if (placementLocked) return;
                    aoiLayerGroup.clearLayers();
                    missionPathLayerGroup.clearLayers();
                    currentAoi = null;
                    currentCorridorPath = null;
                    aoiOverCap = false;
                    if (areaValue) areaValue.classList.remove('aoi-metric-value-warning');
                    if (dimensionValue) dimensionValue.classList.remove('aoi-metric-value-warning');
                    if (infoPanel) infoPanel.hidden = true;
                    if (redrawSection) redrawSection.hidden = true;
                    // Redrawing means the old AOI (and any mission planned
                    // for it) is gone -- hide the mission-planning UI along
                    // with the AOI-summary UI so nothing stale (a form still
                    // showing last mission's numbers, a legend for a flight
                    // path that no longer exists) lingers on screen.
                    if (missionParametersPanel) missionParametersPanel.hidden = true;
                    if (missionSummarySection) missionSummarySection.hidden = true;
                    if (missionPathLegend) missionPathLegend.hidden = true;
                    if (panelTitle) panelTitle.textContent = 'Draw AOI';
                    if (instruction) instruction.hidden = false;
                    if (activeDrawer) activeDrawer.disable();
                    activeDrawer = createDrawer(aoiMode);
                    activeDrawer.enable();
                }

                // Cancel drawing entirely (button, or Esc). Leaflet.Draw's own
                // Esc handling only clears the in-progress vertices of the
                // current shape — it doesn't know this app has a previously
                // confirmed AOI to fall back to, so we handle that ourselves:
                // if one exists (we got here via "Redraw"), restore it and
                // re-lock the view; otherwise just reset the in-progress shape.
                function cancelDrawing() {
                    if (placementLocked) return;

                    if (config.savedAoi && Array.isArray(config.savedAoi.boundary_coordinates) && config.savedAoi.boundary_coordinates.length >= 3) {
                        // Swapping out of draw mode entirely (back to the
                        // read-only saved shape), so the active handler
                        // genuinely needs to go away here.
                        if (activeDrawer) activeDrawer.disable();
                        var savedLatLngs = config.savedAoi.boundary_coordinates.map(function (pair) {
                            return L.latLng(pair[0], pair[1]);
                        });
                        drawAoiPolygon(savedLatLngs, true, true);
                        setAoiReadOnly();
                    } else {
                        // Nothing to revert to -- stay in draw mode, just
                        // reset for a fresh shape. The actual re-arming of a
                        // fresh handler happens in the draw:canceled/DRAWSTOP
                        // listeners below (this function is only ever
                        // reached via the Escape keydown handler further
                        // down, which always co-occurs with the native
                        // keyup Leaflet.Draw itself listens for -- setting
                        // the flag here too would be redundant, and risks
                        // it going stale if that pairing was ever broken).
                        if (instruction) instruction.hidden = false;
                    }
                }

                // Leaflet.Draw binds its OWN Escape handling independently of
                // ours -- L.Draw.Feature.addHooks() does
                // `L.DomEvent.on(this._container, 'keyup', this._cancelDrawing, this)`,
                // a container `keyup` listener, not a `document` `keydown`
                // one like the app's own handler below. Confirmed directly
                // by reading leaflet-draw@1.0.4's real source
                // (_cancelDrawing fires the map events 'draw:canceled' then
                // calls disable(), which itself fires L.Draw.Event.DRAWSTOP
                // once fully disabled) -- not guessed from memory.
                //
                // The previous fix here tried to win this timing race with a
                // blind setTimeout(0) after our own keydown handler, hoping
                // Leaflet's keyup handling would have already run by the
                // next tick. That's still a guess about event ordering, not
                // a guarantee. This listens for Leaflet's own real events
                // instead: 'draw:canceled' fires specifically (and only)
                // when Leaflet's native Esc handling triggered the cancel
                // (armed here only when there's no saved AOI to restore to
                // -- the has-savedAoi branch above already takes over
                // instead), and DRAWSTOP fires once the old handler's
                // disable() has actually finished running -- the exact,
                // correctly-sequenced moment to safely create and enable a
                // fresh one, with no timing guesswork at all.
                var pendingEscapeRearm = false;

                map.on('draw:canceled', function () {
                    if (config.isAoiRoute && !placementLocked && !(config.savedAoi && Array.isArray(config.savedAoi.boundary_coordinates) && config.savedAoi.boundary_coordinates.length >= 3)) {
                        pendingEscapeRearm = true;
                    }
                });

                map.on(L.Draw.Event.DRAWSTOP, function () {
                    if (!pendingEscapeRearm) return;
                    pendingEscapeRearm = false;
                    if (activeDrawer) activeDrawer.disable(); // no-op if Leaflet's own handling already disabled it -- safe either way
                    activeDrawer = createDrawer(aoiMode);
                    activeDrawer.enable();
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key !== 'Escape') return;

                    if (aoiRedrawModalCard && aoiRedrawModalCard.classList.contains('is-open')) {
                        closeAoiRedrawModal();
                        return;
                    }

                    if (config.isAoiRoute && !placementLocked) {
                        cancelDrawing();
                    }
                });

                if (config.isAoiRoute) {
                    map.on(L.Draw.Event.DRAWVERTEX, function () {
                        if (instruction) instruction.hidden = true;
                    });

                    map.on(L.Draw.Event.CREATED, function (event) {
                        // Box mode is a single click-drag gesture with no
                        // intermediate vertices, so DRAWVERTEX above never
                        // fires for it — hide the instruction here too so a
                        // completed box doesn't leave it stuck on top of the
                        // preview panel like polygon/corridor already do.
                        if (instruction) instruction.hidden = true;

                        var isPolyline = event.layerType === 'polyline';
                        var latlngs = isPolyline ? event.layer.getLatLngs() : event.layer.getLatLngs()[0];

                        var outOfBounds = restrictToDavao && latlngs.some(function (point) {
                            return !davaoOccidentalBounds.contains(point);
                        });
                        if (outOfBounds) {
                            showMapToast('Search is limited to Davao Occidental for now.', 'error');
                            startDrawing();
                            return;
                        }

                        if (isPolyline) {
                            currentCorridorPath = latlngs;
                            renderCorridorFromPath(false);
                            return;
                        }

                        drawAoiPolygon(latlngs, false, false);
                        if (panelTitle) panelTitle.textContent = 'AOI Preview';
                        if (aoiToast) aoiToast.hidden = true;
                    });
                }

                // Live geocoding via Nominatim (OpenStreetMap) — no local
                // place database, this queries a real geocoder each time,
                // restricted to a viewbox around Davao Occidental so results
                // outside the province are excluded up front. Nominatim's
                // usage policy allows light, keyless use (~1 request/sec);
                // this app debounces input so it stays well under that.
                // Results are still re-checked against davaoOccidentalBounds
                // client-side before anything is selectable, matching the
                // same enforcement used for manual coordinate entry.
                var NOMINATIM_VIEWBOX = '125.15,6.95,126.2,5.15'; // west,north,east,south
                var geocodeAbortController = null;

                function geocodeSearch(query) {
                    if (geocodeAbortController) geocodeAbortController.abort();
                    geocodeAbortController = new AbortController();

                    var url = 'https://nominatim.openstreetmap.org/search'
                        + '?format=jsonv2&addressdetails=0&limit=6'
                        + (restrictToDavao ? '&viewbox=' + NOMINATIM_VIEWBOX + '&bounded=1&countrycodes=ph' : '')
                        + '&q=' + encodeURIComponent(query);

                    return fetch(url, { signal: geocodeAbortController.signal, headers: { 'Accept-Language': 'en' } })
                        .then(function (res) { return res.ok ? res.json() : []; })
                        .then(function (results) {
                            return (results || [])
                                .map(function (r) {
                                    return {
                                        label: shortenDisplayName(r.display_name),
                                        lat: parseFloat(r.lat),
                                        lng: parseFloat(r.lon)
                                    };
                                })
                                .filter(function (r) {
                                    return isFinite(r.lat) && isFinite(r.lng)
                                        && (!restrictToDavao || davaoOccidentalBounds.contains(L.latLng(r.lat, r.lng)));
                                });
                        })
                        .catch(function (err) {
                            if (err.name === 'AbortError') return null; // superseded by a newer keystroke
                            return [];
                        });
                }

                function shortenDisplayName(displayName) {
                    // Nominatim's display_name is a long comma chain (place,
                    // barangay, municipality, province, region, country,
                    // postal code...). Every result here is already known to
                    // be in Davao Occidental, so trim the redundant tail.
                    var parts = (displayName || '').split(',').map(function (p) { return p.trim(); });
                    return parts.slice(0, Math.min(parts.length, 3)).join(', ');
                }

                function lockMapToAoi(aoi) {
                    var focusBounds = aoi.bounds.pad(0.65);
                    // Only actually narrow maxBounds when restricted -- in
                    // unlocked/testing mode this still centers the view on
                    // the AOI as a starting point, but doesn't trap the map
                    // there, so an uploaded orthomosaic whose real location
                    // doesn't overlap this AOI (e.g. sample imagery from
                    // somewhere else) can still fitBounds() to where it
                    // actually is afterward.
                    if (aoiLockEnabled) map.setMaxBounds(focusBounds);
                    map.fitBounds(focusBounds, { animate: true, duration: 0.35 });
                }

                function isAoiFullyVisible(aoi) {
                    var mapSize = map.getSize();
                    var edgeMargin = 12;

                    return aoi.corners.every(function (corner) {
                        var point = map.latLngToContainerPoint(corner);

                        return point.x > edgeMargin &&
                            point.y > edgeMargin &&
                            point.x < mapSize.x - edgeMargin &&
                            point.y < mapSize.y - edgeMargin;
                    });
                }

                function showMapToast(message, type) {
                    if (!mapToast) return;
                    mapToast.textContent = message;
                    mapToast.className = 'floating-panel glass-panel aoi-map-toast ' + (type || '');
                    mapToast.hidden = false;
                    if (window.uiSound) window.uiSound.play(type === 'error' ? 'error' : 'success');
                    window.clearTimeout(showMapToast.timeout);
                    showMapToast.timeout = window.setTimeout(function () {
                        mapToast.hidden = true;
                    }, 3600);
                }

                function setAoiReadOnly() {
                    placementLocked = true;
                    mapShell?.classList.remove('aoi-placement-mode');
                    if (instruction) instruction.hidden = true;
                    if (confirmBtn) confirmBtn.hidden = true;
                    // AOI confirmed -- reveal mission planning in place
                    // (same page, no reload). The flight-path legend stays
                    // hidden until a mission is actually generated;
                    // renderMissionPath() unhides it itself when that
                    // happens.
                    if (missionParametersPanel) missionParametersPanel.hidden = false;
                    if (missionSummarySection) missionSummarySection.hidden = false;
                    // Bring the mission form into view: tuck Layers away
                    // (still expandable) and jump the dock back to the top,
                    // where the CSS ordering puts Mission Parameters.
                    var layersPanel = document.getElementById('layersPanel');
                    if (layersPanel && !layersPanel.classList.contains('collapsed')) {
                        layersPanel.classList.add('collapsed');
                        layersPanel.querySelector('.dock-section-toggle')?.setAttribute('aria-expanded', 'false');
                    }
                    document.querySelector('.dock-scroll--aoi')?.scrollTo({ top: 0, behavior: 'smooth' });
                    if (panelTitle) panelTitle.textContent = 'AOI Summary';
                    // Nothing to "proceed to" anymore -- Mission Parameters
                    // is already visible in the left dock now that AOI and
                    // Mission are one page, so the Properties action area
                    // just clears rather than linking away.
                    if (panelActions) panelActions.innerHTML = '';
                    if (currentAoi) lockMapToAoi(currentAoi);

                    if (config.isAoiRoute) {
                        document.querySelectorAll('[data-workflow-route="aoi"]').forEach(function (step) {
                            step.classList.remove('in-progress', 'pending', 'locked');
                            step.classList.add('complete');
                        });
                    }
                }

                // Inverse of setAoiReadOnly() — called once a redraw has
                // actually cleared the AOI server-side, so the sidebar/nav
                // reflect "no AOI yet" immediately instead of still showing
                // the old confirmed/unlocked state while the map is in draw mode.
                function setAoiInProgress() {
                    if (!config.isAoiRoute) return;

                    document.querySelectorAll('[data-workflow-route="aoi"]').forEach(function (step) {
                        step.classList.remove('complete', 'pending', 'locked');
                        step.classList.add('in-progress');
                    });
                }

                // --- Google Maps-style location search docked to the top of
                // every map-bearing route. Reuses the geocodeSearch helper
                // above for "Place" mode, just pans/zooms the map instead of
                // feeding into AOI corner placement. "Coordinates" mode is a
                // separate, explicit toggle (not autodetected from the
                // input text) that parses "lat, lng" directly and jumps
                // there with no geocoding API call and no Davao Occidental
                // restriction -- a researcher typing an exact coordinate is
                // a deliberate act, not a place search that should be
                // silently filtered. ---
                (function () {
                    var missionSearchForm = document.getElementById('missionMapSearchForm');
                    var missionSearchInput = document.getElementById('missionMapSearchInput');
                    var missionSearchSuggestions = document.getElementById('missionMapSearchSuggestions');
                    var modePlaceBtn = document.getElementById('mapSearchModePlace');
                    var modeCoordsBtn = document.getElementById('mapSearchModeCoords');
                    var coordsHint = document.getElementById('mapSearchCoordsHint');
                    if (!missionSearchForm || !missionSearchInput) return;

                    var searchMode = 'place';

                    function setSearchMode(mode) {
                        searchMode = mode;
                        modePlaceBtn?.classList.toggle('active', mode === 'place');
                        modeCoordsBtn?.classList.toggle('active', mode === 'coordinates');
                        missionSearchInput.placeholder = mode === 'coordinates' ? 'Enter latitude, longitude...' : 'Search a location...';
                        if (coordsHint) coordsHint.hidden = mode !== 'coordinates';
                        missionSearchInput.value = '';
                        hideMissionSuggestions();
                    }

                    modePlaceBtn?.addEventListener('click', function () { setSearchMode('place'); });
                    modeCoordsBtn?.addEventListener('click', function () { setSearchMode('coordinates'); });

                    // Accepts "lat, lng", "lat lng", or either with extra
                    // whitespace. Returns null (not a coordinate pair, or
                    // out of valid lat/lng range) rather than throwing, so
                    // the caller can show a clear error instead of a silent
                    // failure.
                    function parseCoordinates(text) {
                        var match = text.trim().match(/^(-?\d+(?:\.\d+)?)[,\s]+(-?\d+(?:\.\d+)?)$/);
                        if (!match) return null;
                        var lat = parseFloat(match[1]);
                        var lng = parseFloat(match[2]);
                        if (!isFinite(lat) || !isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
                        return { lat: lat, lng: lng };
                    }

                    var missionGeocodeDebounceTimer = null;

                    function hideMissionSuggestions() {
                        if (missionSearchSuggestions) {
                            missionSearchSuggestions.hidden = true;
                            missionSearchSuggestions.innerHTML = '';
                        }
                    }

                    function goToResult(result) {
                        missionSearchInput.value = result.label;
                        hideMissionSuggestions();
                        map.setView(L.latLng(result.lat, result.lng), Math.max(map.getZoom(), 15), { animate: true });
                    }

                    function renderMissionSuggestions(results) {
                        if (!missionSearchSuggestions || results === null) return;

                        if (!results.length) {
                            missionSearchSuggestions.innerHTML = '<div class="aoi-suggestion-empty">'
                                + (restrictToDavao ? 'No matches in Davao Occidental.' : 'No matches.') + '</div>';
                            missionSearchSuggestions.hidden = false;
                            return;
                        }

                        missionSearchSuggestions.innerHTML = '';
                        results.forEach(function (result) {
                            var item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'aoi-suggestion-item';
                            item.textContent = result.label;
                            item.addEventListener('click', function () {
                                goToResult(result);
                            });
                            missionSearchSuggestions.appendChild(item);
                        });
                        missionSearchSuggestions.hidden = false;
                    }

                    missionSearchInput.addEventListener('input', function () {
                        if (searchMode === 'coordinates') {
                            hideMissionSuggestions(); // no live suggestions for coordinates -- only acts on submit
                            return;
                        }

                        var query = missionSearchInput.value.trim();
                        window.clearTimeout(missionGeocodeDebounceTimer);

                        if (query.length < 3) {
                            hideMissionSuggestions();
                            return;
                        }

                        missionGeocodeDebounceTimer = window.setTimeout(function () {
                            geocodeSearch(query).then(renderMissionSuggestions);
                        }, 400);
                    });

                    missionSearchForm.addEventListener('submit', function (event) {
                        event.preventDefault();

                        var query = missionSearchInput.value.trim();
                        if (!query) return;

                        if (searchMode === 'coordinates') {
                            var parsed = parseCoordinates(query);
                            if (!parsed) {
                                if (missionSearchSuggestions) {
                                    missionSearchSuggestions.innerHTML = '<div class="aoi-suggestion-empty">Enter as latitude, longitude (e.g. 6.0596, -77.3629).</div>';
                                    missionSearchSuggestions.hidden = false;
                                }
                                return;
                            }
                            goToResult({ label: parsed.lat.toFixed(5) + ', ' + parsed.lng.toFixed(5), lat: parsed.lat, lng: parsed.lng });
                            return;
                        }

                        window.clearTimeout(missionGeocodeDebounceTimer);
                        geocodeSearch(query).then(function (results) {
                            if (results === null) return; // superseded by a newer query
                            if (!results.length) {
                                hideMissionSuggestions();
                                return;
                            }
                            goToResult(results[0]); // closest/top match, like Google Maps' "Enter"
                        });
                    });

                    document.addEventListener('click', function (event) {
                        if (missionSearchSuggestions && !missionSearchSuggestions.hidden
                            && !missionSearchSuggestions.contains(event.target)
                            && event.target !== missionSearchInput) {
                            hideMissionSuggestions();
                        }
                    });
                })();

                function openAoiRedrawModal() {
                    aoiRedrawModalBackdrop?.classList.add('is-open');
                    aoiRedrawModalCard?.classList.add('is-open');
                }

                function closeAoiRedrawModal() {
                    aoiRedrawModalBackdrop?.classList.remove('is-open');
                    aoiRedrawModalCard?.classList.remove('is-open');
                }

                function beginAoiReposition() {
                    if (!config.aoiCanBeRepositioned) {
                        showMapToast('AOI can no longer be repositioned because the drone mission is completed.', 'error');
                        return;
                    }

                    openAoiRedrawModal();
                }

                aoiRedrawModalBackdrop?.addEventListener('click', closeAoiRedrawModal);
                document.getElementById('aoiRedrawModalCancel')?.addEventListener('click', closeAoiRedrawModal);
                document.getElementById('aoiRedrawModalConfirm')?.addEventListener('click', function () {
                    var confirmBtn = this;

                    // Clears the AOI server-side right away, rather than waiting
                    // for a replacement shape to be confirmed — so if the user
                    // abandons the redraw (navigates away, logs out) before
                    // finishing a new shape, the AOI is genuinely gone rather
                    // than silently reverting to the old one on next load.
                    if (!config.clearAoiUrl) {
                        closeAoiRedrawModal();
                        return;
                    }

                    confirmBtn.disabled = true;

                    window.apiFetch(config.clearAoiUrl, { method: 'POST' })
                        .then(function (response) {
                            return response.json().then(function (data) {
                                if (!response.ok) throw new Error(data.message || 'Unable to clear the AOI.');
                                return data;
                            });
                        })
                        .then(function () {
                            config.savedAoi = null;
                            closeAoiRedrawModal();
                            setAoiInProgress();

                            placementLocked = false;
                            map.setMaxBounds(restrictToDavao ? davaoOccidentalBounds : null);
                            mapShell?.classList.add('aoi-placement-mode');
                            startDrawing();
                        })
                        .catch(function (error) {
                            closeAoiRedrawModal();
                            showMapToast(error.message || 'Unable to clear the AOI. Please try again.', 'error');
                        })
                        .finally(function () {
                            confirmBtn.disabled = false;
                        });
                });

                function saveCurrentAoi(button) {
                    if (!currentAoi || !config.confirmAoiUrl) return;

                    if (aoiOverCap) {
                        showMapToast('This shape exceeds the size caps — redraw a smaller area before confirming.', 'error');
                        return;
                    }

                    if (!isAoiFullyVisible(currentAoi)) {
                        showMapToast('AOI boundary touches the visible map edge. Zoom out or redraw before confirming.', 'error');
                        return;
                    }

                    var buttonLabel = button.querySelector('span');
                    button.disabled = true;
                    if (buttonLabel) buttonLabel.textContent = 'Saving...';

                    window.apiFetch(config.confirmAoiUrl, {
                        method: 'POST',
                        json: {
                            center_latitude: currentAoi.center.lat,
                            center_longitude: currentAoi.center.lng,
                            boundary_coordinates: currentAoi.corners.map(function (point) {
                                return [point.lat, point.lng];
                            })
                        }
                    })
                        .then(function (response) {
                            return response.json().then(function (data) {
                                if (!response.ok) throw new Error(data.message || 'Unable to save AOI.');
                                return data;
                            });
                        })
                        .then(function (data) {
                            drawAoiPolygon(currentAoi.corners, true, false);
                            setAoiReadOnly();
                            showMapToast(data.message || 'AOI confirmed. Proceed to Drone Mission to generate your flight plan.', 'success');
                        })
                        .catch(function (error) {
                            showMapToast(error.message || 'AOI could not be confirmed. Please try again.', 'error');
                            button.disabled = false;
                            if (buttonLabel) buttonLabel.textContent = 'Confirm AOI';
                        });
                }

                confirmBtn?.addEventListener('click', function () {
                    saveCurrentAoi(confirmBtn);
                });

                document.getElementById('repositionAoiBtn')?.addEventListener('click', beginAoiReposition);

                if (config.savedAoi && Array.isArray(config.savedAoi.boundary_coordinates) && config.savedAoi.boundary_coordinates.length >= 3) {
                    var savedLatLngs = config.savedAoi.boundary_coordinates.map(function (pair) {
                        return L.latLng(pair[0], pair[1]);
                    });
                    drawAoiPolygon(savedLatLngs, true, true);
                    setAoiReadOnly();
                } else if (config.isAoiRoute) {
                    startDrawing();
                }

                // Restore a previously generated flight path on page load —
                // otherwise it only ever existed in the one-time API
                // response and vanished on refresh/navigation even though
                // the mission (and its .kmz) still exist server-side.
                if (config.isMissionRoute && config.savedMission?.waypoints?.length) {
                    window.renderMissionPath(config.savedMission.waypoints);
                }

            setTimeout(function () {
                map.invalidateSize();
            }, 120);
        });
