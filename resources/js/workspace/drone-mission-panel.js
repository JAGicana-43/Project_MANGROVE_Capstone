        window.updateGridPreview = function (parameters) {
            window.dispatchEvent(new CustomEvent('mission-grid-preview:update', {
                detail: parameters || {}
            }));
        };

        window.droneMissionPanel = function (config) {
            return {
                droneModels: config.droneModels || [],
                droneModelIndex: 0,
                altitude: 100,
                waypointSpeed: 3,
                overlap: 91.6,
                gimbalAngle: -90,
                generating: false,
                statusMessage: '',
                statusType: 'success',
                downloadUrl: null,
                summary: {
                    waypointCount: 0,
                    estimatedDistance: '0 m',
                    estimatedFlightTime: '0 min'
                },

                init() {
                    if (this.droneModels.length === 0) {
                        this.droneModels = [{
                            key: 'dji_mini_3', name: 'DJI Mini 3', enumValue: 68, subEnumValue: 0,
                            altitudeM: 100, waypointSpeedMps: 3, gimbalPitchDeg: -90
                        }];
                    }

                    this.applyDroneDefaults();
                    this.$watch('droneModelIndex', () => this.applyDroneDefaults());
                    this.$watch('altitude', () => this.updateGridPreview());
                    this.$watch('overlap', () => this.updateGridPreview());
                    this.updateGridPreview();
                },

                selectedDroneModel() {
                    return this.droneModels[this.droneModelIndex] || this.droneModels[0];
                },

                // Altitude/speed/gimbal are fixed per drone (locked in the
                // UI, see the disabled inputs above) — this is the only
                // place that sets them, driven by whichever drone model is
                // selected, rather than free per-mission user input.
                applyDroneDefaults() {
                    var model = this.selectedDroneModel();
                    if (!model) return;
                    this.altitude = model.altitudeM ?? 100;
                    this.waypointSpeed = model.waypointSpeedMps ?? 3;
                    this.gimbalAngle = model.gimbalPitchDeg ?? -90;
                },

                updateGridPreview() {
                    window.updateGridPreview({
                        altitude: this.altitude,
                        overlap: this.overlap,
                        waypointSpeed: this.waypointSpeed,
                        gimbalAngle: this.gimbalAngle,
                        aoi: window.getWorkspaceAoiPolygon ? window.getWorkspaceAoiPolygon() : (config.savedAoi?.boundary_coordinates || null)
                    });
                },

                async generateMission() {
                    this.generating = true;
                    this.statusMessage = '';
                    this.statusType = 'success';
                    this.downloadUrl = null;

                    try {
                        var aoiPolygon = window.getWorkspaceAoiPolygon ? window.getWorkspaceAoiPolygon() : (config.savedAoi?.boundary_coordinates || null);

                        if (!aoiPolygon || aoiPolygon.length < 3) {
                            throw new Error('Draw or confirm an AOI polygon before generating a mission file.');
                        }

                        var response = await fetch(config.generateMissionUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                project_id: config.projectId,
                                aoi_id: config.savedAoi?.id || null,
                                aoi_polygon: aoiPolygon,
                                drone_key: this.selectedDroneModel()?.key,
                                overlap_percent: this.overlap
                            })
                        });
                        var data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.error || data.message || 'Mission file could not be generated.');
                        }

                        this.summary.waypointCount = data.waypoint_count || 0;
                        this.summary.estimatedDistance = data.total_distance_meters
                            ? Number(data.total_distance_meters).toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' m'
                            : '0 m';
                        this.summary.estimatedFlightTime = data.total_distance_meters && this.waypointSpeed
                            ? (Number(data.total_distance_meters) / Number(this.waypointSpeed) / 60).toFixed(1) + ' min'
                            : '0 min';
                        this.statusMessage = data.message || 'Mission file generated successfully.';

                        if (Array.isArray(data.waypoints)) {
                            window.renderMissionPath?.(data.waypoints);
                        }

                        this.downloadUrl = data.download_url || null;
                    } catch (error) {
                        this.statusType = 'error';
                        this.statusMessage = error.message || 'Mission file could not be generated.';
                    } finally {
                        this.generating = false;
                    }
                }
            };
        };
