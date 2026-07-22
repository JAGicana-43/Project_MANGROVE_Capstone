        document.addEventListener('DOMContentLoaded', function () {
            var davaoOccidentalBounds = L.latLngBounds([5.15, 125.15], [6.95, 126.2]);

            var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxNativeZoom: 17,
                maxZoom: 20,
                attribution: 'Tiles &copy; Esri, Maxar, Earthstar Geographics, and the GIS User Community'
            });

            var map = L.map('workspace-map', {
                center: [6.4150, 125.6117],
                zoom: 10,
                minZoom: 9,
                maxZoom: 20,
                maxBounds: davaoOccidentalBounds,
                maxBoundsViscosity: 0.9,
                zoomControl: false,
                layers: [satellite]
            });

            L.control.zoom({ position: 'bottomright' }).addTo(map);
            window.workspaceMap = map;

            var layerVisible = true;
            document.getElementById('layerToggleBtn')?.addEventListener('click', function () {
                if (layerVisible) {
                    map.removeLayer(satellite);
                } else {
                    satellite.addTo(map);
                }
                layerVisible = !layerVisible;
            });

            document.getElementById('locateMeBtn')?.addEventListener('click', function () {
                if (!navigator.geolocation) return;
                navigator.geolocation.getCurrentPosition(function (position) {
                    var latlng = [position.coords.latitude, position.coords.longitude];
                    if (!davaoOccidentalBounds.contains(latlng)) {
                        map.fitBounds(davaoOccidentalBounds, { animate: true });
                        return;
                    }
                    map.setView(latlng, 15);
                    L.circleMarker(latlng, {
                        radius: 8,
                        color: '#2D6A4F',
                        fillColor: '#74C69D',
                        fillOpacity: 0.85,
                        weight: 3
                    }).addTo(map);
                });
            });

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

                function showManageState(meta) {
                    currentRemoveUrl = (meta && meta.remove_url) || null;
                    if (manageInfoEl) {
                        manageInfoEl.textContent = meta && meta.uploaded_at
                            ? 'Orthomosaic uploaded ' + meta.uploaded_at
                            : 'Orthomosaic uploaded.';
                    }
                    if (uploadStateEl) uploadStateEl.hidden = true;
                    if (manageStateEl) manageStateEl.hidden = false;
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
                        resolution: 128
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

                    return fetch(uploadUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': window.workspaceConfig.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
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
                    }).finally(function () {
                        fileInput.value = '';
                    });
                });

                removeBtn?.addEventListener('click', function () {
                    if (!currentRemoveUrl) return;
                    if (!window.confirm('Remove this orthomosaic? This cannot be undone.')) return;

                    removeBtn.disabled = true;

                    fetch(currentRemoveUrl, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': window.workspaceConfig.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    }).then(function (response) {
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
                        removeBtn.disabled = false;
                    });
                });

                // Re-render the project's most recently uploaded orthomosaic (if
                // any) when the section is revisited, so it isn't lost on reload.
                var existing = window.workspaceConfig?.existingOrthomosaic;
                if (existing && existing.file_url) {
                    setStatus('Loading saved orthomosaic...');
                    showManageState(existing);
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
                            setStatus('Showing the last uploaded orthomosaic.');
                        })
                        .catch(function (error) {
                            setStatus(error.message, true);
                        });
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

            var config = window.workspaceConfig || {};
            var mapShell = document.getElementById('mapShell');
            var instruction = document.getElementById('aoiInstruction');
            var searchPanel = document.getElementById('aoiSearchPanel');
            var searchForm = document.getElementById('aoiSearchForm');
            var searchInput = document.getElementById('aoiLocationSearch');
            var searchSuggestions = document.getElementById('aoiSearchSuggestions');
            var aoiSearchModeToggle = document.getElementById('aoiSearchModeToggle');
            var coordsForm = document.getElementById('aoiCoordsForm');
            var coordsLatInput = document.getElementById('aoiCoordsLat');
            var coordsLngInput = document.getElementById('aoiCoordsLng');
            var pulse = document.getElementById('aoiPulse');
            var infoPanel = document.getElementById('aoiInfoPanel');
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
            var capWarning = document.getElementById('aoiCapWarning');
            var panelActions = document.getElementById('aoiPanelActions');
            var confirmBtn = document.getElementById('confirmAoiBtn');
            var aoiToast = document.querySelector('.aoi-toast');
            var mapToast = document.getElementById('aoiMapToast');
            // Mirrors App\Services\Geo\AoiValidator::MAX_AREA_M2 / MAX_DIMENSION_M —
            // keep these two in sync if the caps ever change server-side.
            var AOI_MAX_AREA_M2 = 250000;
            var AOI_MAX_DIMENSION_M = 500;
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
                // enforces the 25 ha / 500 m / self-intersection rules
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
                    aoiOverCap = overArea || overDimension;

                    if (areaValue) areaValue.classList.toggle('aoi-metric-value-warning', overArea);
                    if (dimensionValue) dimensionValue.classList.toggle('aoi-metric-value-warning', overDimension);

                    if (!capWarning) return;

                    if (!aoiOverCap) {
                        capWarning.hidden = true;
                        capWarning.textContent = '';
                        return;
                    }

                    var reasons = [];
                    if (overArea) reasons.push('exceeds the 25 hectare area cap');
                    if (overDimension) reasons.push('exceeds the 500 m longest-dimension cap');
                    capWarning.hidden = false;
                    capWarning.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> This shape ' + reasons.join(' and ') + ' — redraw a smaller area before confirming.';
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
                    renderAoiPreviewActions();
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

                function renderAoiPreviewActions() {
                    if (!panelActions) return;
                    var disabledAttr = aoiOverCap ? 'disabled title="Redraw within the size caps before confirming"' : '';
                    panelActions.innerHTML = '<button class="aoi-action secondary" type="button" data-aoi-action="reposition">Redraw</button><button class="aoi-action primary" type="button" data-aoi-action="confirm"' + (disabledAttr ? ' ' + disabledAttr : '') + '>Confirm AOI</button>';
                }

                function renderAoiSummaryActions() {
                    if (!panelActions) return;
                    var repositionButton = config.aoiCanBeRepositioned
                        ? '<button class="aoi-action secondary" type="button" data-aoi-action="reposition">Redraw AOI</button>'
                        : '';
                    panelActions.innerHTML = repositionButton + '<a class="aoi-action primary" href="' + config.missionUrl + '">Proceed to Drone Mission</a>';
                }

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
                            corridor: 'Click along the coastline to draw a path, then double-click to finish. The strip width below controls how wide the AOI is built around it. Max 25 hectares (500 m x 500 m), 500 m longest dimension. Press Esc anytime to cancel.',
                            rectangle: 'Click and drag to draw a rectangular Area of Interest. Max 25 hectares (500 m x 500 m), 500 m longest dimension. Press Esc anytime to cancel.',
                            polygon: 'Click on the map to place each corner of your Area of Interest, then click the first point again (or double-click) to close the shape. Max 25 hectares (500 m x 500 m), 500 m longest dimension. Press Esc anytime to cancel.'
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
                    if (capWarning) capWarning.hidden = true;
                    if (infoPanel) infoPanel.hidden = true;
                    if (panelTitle) panelTitle.textContent = 'Draw AOI';
                    if (instruction) instruction.hidden = false;
                    if (pulse) pulse.hidden = false;
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
                    if (activeDrawer) activeDrawer.disable();

                    if (config.savedAoi && Array.isArray(config.savedAoi.boundary_coordinates) && config.savedAoi.boundary_coordinates.length >= 3) {
                        var savedLatLngs = config.savedAoi.boundary_coordinates.map(function (pair) {
                            return L.latLng(pair[0], pair[1]);
                        });
                        drawAoiPolygon(savedLatLngs, true, true);
                        setAoiReadOnly();
                    } else {
                        startDrawing();
                    }
                }

                document.addEventListener('keydown', function (event) {
                    if (event.key !== 'Escape') return;

                    if (aoiRedrawModalCard && aoiRedrawModalCard.classList.contains('is-open')) {
                        closeAoiRedrawModal();
                        return;
                    }

                    if (searchSuggestions && !searchSuggestions.hidden) {
                        hideSuggestions();
                        return;
                    }

                    if (config.isAoiRoute && !placementLocked) {
                        cancelDrawing();
                    }
                });

                if (config.isAoiRoute) {
                    map.on(L.Draw.Event.DRAWVERTEX, function () {
                        if (instruction) instruction.hidden = true;
                        if (pulse) pulse.hidden = true;
                    });

                    map.on(L.Draw.Event.CREATED, function (event) {
                        // Box mode is a single click-drag gesture with no
                        // intermediate vertices, so DRAWVERTEX above never
                        // fires for it — hide the instruction/pulse here too
                        // so a completed box doesn't leave them stuck on top
                        // of the preview panel like polygon/corridor already do.
                        if (instruction) instruction.hidden = true;
                        if (pulse) pulse.hidden = true;

                        var isPolyline = event.layerType === 'polyline';
                        var latlngs = isPolyline ? event.layer.getLatLngs() : event.layer.getLatLngs()[0];

                        var outOfBounds = latlngs.some(function (point) {
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
                        renderAoiPreviewActions();
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
                var geocodeDebounceTimer = null;

                function geocodeSearch(query) {
                    if (geocodeAbortController) geocodeAbortController.abort();
                    geocodeAbortController = new AbortController();

                    var url = 'https://nominatim.openstreetmap.org/search'
                        + '?format=jsonv2&addressdetails=0&limit=6'
                        + '&viewbox=' + NOMINATIM_VIEWBOX + '&bounded=1'
                        + '&countrycodes=ph'
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
                                    return isFinite(r.lat) && isFinite(r.lng) && davaoOccidentalBounds.contains(L.latLng(r.lat, r.lng));
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

                function hideSuggestions() {
                    if (searchSuggestions) {
                        searchSuggestions.hidden = true;
                        searchSuggestions.innerHTML = '';
                    }
                }

                function renderSuggestions(results) {
                    if (!searchSuggestions || results === null) return; // null = superseded query, leave prior state

                    if (!results.length) {
                        searchSuggestions.innerHTML = '<div class="aoi-suggestion-empty">No matches in Davao Occidental.</div>';
                        searchSuggestions.hidden = false;
                        return;
                    }

                    searchSuggestions.innerHTML = '';
                    results.forEach(function (result) {
                        var item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'aoi-suggestion-item';
                        item.textContent = result.label;
                        item.addEventListener('click', function () {
                            selectSearchResult(result);
                        });
                        searchSuggestions.appendChild(item);
                    });
                    searchSuggestions.hidden = false;
                }

                function selectSearchResult(result) {
                    if (searchInput) searchInput.value = result.label;
                    hideSuggestions();
                    var latlng = L.latLng(result.lat, result.lng);
                    map.setView(latlng, Math.max(map.getZoom(), 15), { animate: true });
                }

                searchInput?.addEventListener('input', function () {
                    var query = searchInput.value.trim();
                    window.clearTimeout(geocodeDebounceTimer);

                    if (query.length < 3) {
                        hideSuggestions();
                        return;
                    }

                    geocodeDebounceTimer = window.setTimeout(function () {
                        geocodeSearch(query).then(renderSuggestions);
                    }, 400);
                });

                document.addEventListener('click', function (event) {
                    if (searchSuggestions && !searchSuggestions.hidden
                        && !searchSuggestions.contains(event.target)
                        && event.target !== searchInput) {
                        hideSuggestions();
                    }
                });

                function lockMapToAoi(aoi) {
                    var focusBounds = aoi.bounds.pad(0.65);
                    map.setMaxBounds(focusBounds);
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
                    window.clearTimeout(showMapToast.timeout);
                    showMapToast.timeout = window.setTimeout(function () {
                        mapToast.hidden = true;
                    }, 3600);
                }

                function setAoiReadOnly() {
                    placementLocked = true;
                    mapShell?.classList.remove('aoi-placement-mode');
                    if (pulse) pulse.hidden = true;
                    if (instruction) instruction.hidden = true;
                    if (searchPanel) searchPanel.hidden = true;
                    if (panelTitle) panelTitle.textContent = 'AOI Summary';
                    renderAoiSummaryActions();
                    if (currentAoi) lockMapToAoi(currentAoi);

                    if (config.isAoiRoute) {
                        document.querySelectorAll('[data-route="drone-mission"]').forEach(function (link) {
                            link.classList.remove('locked');
                            link.href = link.dataset.url || config.missionUrl;
                            link.setAttribute('aria-disabled', 'false');
                            link.title = 'Drone Mission';
                            link.querySelector('.lock-icon')?.remove();
                        });

                        document.querySelectorAll('[data-workflow-route="aoi"]').forEach(function (step) {
                            step.classList.remove('in-progress', 'pending', 'locked');
                            step.classList.add('complete');
                        });

                        document.querySelectorAll('[data-workflow-route="drone-mission"]').forEach(function (step) {
                            step.classList.remove('pending', 'locked', 'complete');
                            step.classList.add('in-progress');
                        });
                    }
                }

                // Inverse of setAoiReadOnly() — called once a redraw has
                // actually cleared the AOI server-side, so the sidebar/nav
                // reflect "no AOI yet" immediately instead of still showing
                // the old confirmed/unlocked state while the map is in draw mode.
                function setAoiInProgress() {
                    if (!config.isAoiRoute) return;

                    document.querySelectorAll('[data-route="drone-mission"]').forEach(function (link) {
                        link.classList.add('locked');
                        link.href = '#';
                        link.setAttribute('aria-disabled', 'true');
                        link.title = 'Drone Mission is locked until the previous step is confirmed';
                        if (!link.querySelector('.lock-icon')) {
                            var icon = document.createElement('i');
                            icon.className = 'fa-solid fa-lock lock-icon';
                            link.appendChild(icon);
                        }
                    });

                    document.querySelectorAll('[data-workflow-route="aoi"]').forEach(function (step) {
                        step.classList.remove('complete', 'pending', 'locked');
                        step.classList.add('in-progress');
                    });

                    document.querySelectorAll('[data-workflow-route="drone-mission"]').forEach(function (step) {
                        step.classList.remove('complete', 'in-progress');
                        step.classList.add('pending', 'locked');
                    });
                }

                searchForm?.addEventListener('submit', function (event) {
                    event.preventDefault();
                    if (placementLocked) return;

                    var query = (searchInput?.value || '').trim();
                    if (!query) return;

                    window.clearTimeout(geocodeDebounceTimer);
                    geocodeSearch(query).then(function (results) {
                        if (results === null) return; // superseded by a newer query
                        if (!results.length) {
                            showMapToast('No matches found in Davao Occidental.', 'error');
                            hideSuggestions();
                            return;
                        }
                        selectSearchResult(results[0]); // closest/top match, like Google Maps' "Enter"
                    });
                });

                aoiSearchModeToggle?.querySelectorAll('.aoi-search-mode-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var mode = btn.dataset.searchMode;
                        aoiSearchModeToggle.querySelectorAll('.aoi-search-mode-btn').forEach(function (b) {
                            b.classList.toggle('active', b === btn);
                        });
                        if (searchForm) searchForm.hidden = mode !== 'name';
                        if (coordsForm) coordsForm.hidden = mode !== 'coords';
                    });
                });

                coordsForm?.addEventListener('submit', function (event) {
                    event.preventDefault();
                    if (placementLocked) return;

                    var lat = parseFloat(coordsLatInput?.value);
                    var lng = parseFloat(coordsLngInput?.value);

                    if (!isFinite(lat) || !isFinite(lng)) {
                        showMapToast('Enter both a latitude and longitude.', 'error');
                        return;
                    }

                    var latlng = L.latLng(lat, lng);
                    if (!davaoOccidentalBounds.contains(latlng)) {
                        showMapToast('Those coordinates fall outside Davao Occidental — search is limited to this province for now.', 'error');
                        return;
                    }

                    map.setView(latlng, Math.max(map.getZoom(), 15), { animate: true });
                });

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

                    fetch(config.clearAoiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken
                        }
                    })
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
                            map.setMaxBounds(davaoOccidentalBounds);
                            mapShell?.classList.add('aoi-placement-mode');
                            if (searchPanel) searchPanel.hidden = false;
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

                    button.disabled = true;
                    button.textContent = 'Saving...';

                    fetch(config.confirmAoiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken
                        },
                        body: JSON.stringify({
                            center_latitude: currentAoi.center.lat,
                            center_longitude: currentAoi.center.lng,
                            boundary_coordinates: currentAoi.corners.map(function (point) {
                                return [point.lat, point.lng];
                            })
                        })
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
                            button.textContent = 'Confirm AOI';
                        });
                }

                panelActions?.addEventListener('click', function (event) {
                    var repositionButton = event.target.closest('[data-aoi-action="reposition"], #repositionAoiBtn');
                    if (repositionButton) {
                        beginAoiReposition();
                        return;
                    }

                    var saveButton = event.target.closest('[data-aoi-action="confirm"], #confirmAoiBtn');
                    if (saveButton) {
                        saveCurrentAoi(saveButton);
                    }
                });

                if (config.savedAoi && Array.isArray(config.savedAoi.boundary_coordinates) && config.savedAoi.boundary_coordinates.length >= 3) {
                    var savedLatLngs = config.savedAoi.boundary_coordinates.map(function (pair) {
                        return L.latLng(pair[0], pair[1]);
                    });
                    drawAoiPolygon(savedLatLngs, true, true);
                    setAoiReadOnly();
                } else if (config.isAoiRoute) {
                    startDrawing();
                }

            setTimeout(function () {
                map.invalidateSize();
            }, 120);
        });
