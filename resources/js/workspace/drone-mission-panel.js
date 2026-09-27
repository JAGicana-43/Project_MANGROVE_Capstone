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
                finishAction: 'goHome',
                executeRcLostAction: 'goHome',
                transitionalSpeed: 2.5,
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

                    // Restore a previously generated mission's summary,
                    // download link, AND the actual form inputs it was
                    // generated with on page load — map-workspace.js handles
                    // redrawing the flight path itself. Without restoring
                    // the inputs too, the form would keep showing whatever
                    // drone's defaults happened to be selected, silently
                    // mismatching the waypoints/summary already on screen
                    // and making it look like the parameters don't belong to
                    // the mission the user is looking at.
                    if (config.savedMission?.waypoints?.length) {
                        var savedDroneIndex = this.droneModels.findIndex(function (model) {
                            return model.key === config.savedMission.drone_key;
                        });
                        if (savedDroneIndex !== -1) this.droneModelIndex = savedDroneIndex;

                        if (config.savedMission.altitude != null) this.altitude = Number(config.savedMission.altitude);
                        if (config.savedMission.waypoint_speed_mps != null) this.waypointSpeed = Number(config.savedMission.waypoint_speed_mps);
                        if (config.savedMission.overlap_percent != null) this.overlap = Number(config.savedMission.overlap_percent);
                        if (config.savedMission.gimbal_angle != null) this.gimbalAngle = Number(config.savedMission.gimbal_angle);
                        if (config.savedMission.finish_action) this.finishAction = config.savedMission.finish_action;
                        if (config.savedMission.execute_rc_lost_action) this.executeRcLostAction = config.savedMission.execute_rc_lost_action;
                        if (config.savedMission.transitional_speed_mps != null) this.transitionalSpeed = Number(config.savedMission.transitional_speed_mps);

                        this.applyMissionSummary(
                            config.savedMission.waypoint_count || config.savedMission.waypoints.length,
                            config.savedMission.total_distance_meters,
                            this.waypointSpeed
                        );
                        this.downloadUrl = config.savedMission.download_url || null;
                        this.statusMessage = 'Loaded previously generated mission.';
                        this.statusType = 'success';
                    }
                },

                // Shared by the page-load restore above and generateMission()
                // below — both need to turn a waypoint count + total
                // distance + speed into the same three displayed summary
                // strings.
                applyMissionSummary(waypointCount, totalDistanceMeters, waypointSpeed) {
                    var distance = Number(totalDistanceMeters) || 0;
                    var speed = Number(waypointSpeed) || 0;

                    this.summary.waypointCount = waypointCount || 0;
                    this.summary.estimatedDistance = distance
                        ? distance.toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' m'
                        : '0 m';
                    this.summary.estimatedFlightTime = distance && speed
                        ? (distance / speed / 60).toFixed(1) + ' min'
                        : '0 min';
                },

                selectedDroneModel() {
                    return this.droneModels[this.droneModelIndex] || this.droneModels[0];
                },

                // Pre-fills altitude/speed/gimbal with the selected drone's
                // baseline whenever the drone model changes. The Mission
                // Parameters UI still lets the user edit these afterward —
                // this only sets the starting point for a newly picked drone.
                applyDroneDefaults() {
                    var model = this.selectedDroneModel();
                    if (!model) return;
                    this.altitude = model.altitudeM ?? 100;
                    this.waypointSpeed = model.waypointSpeedMps ?? 3;
                    this.gimbalAngle = model.gimbalPitchDeg ?? -90;
                    this.transitionalSpeed = model.globalTransitionalSpeed ?? 2.5;
                },

                // Advanced (finish-action / RC-lost-action / transitional
                // speed) settings only, not altitude/speed/overlap/gimbal —
                // those already have their own always-visible controls and
                // don't need a separate reset. Transitional speed resets to
                // the selected drone's own baseline, matching
                // applyDroneDefaults(); finish/RC-lost-action reset to the
                // same safe defaults the backend falls back to when nothing
                // is sent at all.
                resetAdvancedSettings() {
                    this.finishAction = 'goHome';
                    this.executeRcLostAction = 'goHome';
                    this.transitionalSpeed = this.selectedDroneModel()?.globalTransitionalSpeed ?? 2.5;
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

                        var response = await window.apiFetch(config.generateMissionUrl, {
                            method: 'POST',
                            json: {
                                project_id: config.projectId,
                                aoi_id: config.savedAoi?.id || null,
                                aoi_polygon: aoiPolygon,
                                drone_key: this.selectedDroneModel()?.key,
                                altitude_m: this.altitude,
                                waypoint_speed_mps: this.waypointSpeed,
                                gimbal_angle_deg: this.gimbalAngle,
                                overlap_percent: this.overlap,
                                finish_action: this.finishAction,
                                execute_rc_lost_action: this.executeRcLostAction,
                                transitional_speed_mps: this.transitionalSpeed
                            }
                        });
                        var data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.error || data.message || 'Mission file could not be generated.');
                        }

                        this.applyMissionSummary(data.waypoint_count, data.total_distance_meters, this.waypointSpeed);
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
