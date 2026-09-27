"""
Builds a DJI WPML-format .kmz (wpmz/template.kml + wpmz/waylines.wpml),
matching the structural conventions confirmed by diffing a real WaypointMap
export (`output_FINAL.kmz`) against the old, untested script's output
(`mangrove-mission-4.kmz`):

- Coordinates in `longitude,latitude` order (KML standard).
- `wpml:distance` / `wpml:duration` in the Folder header are legitimately 0
  in a real export — not computed here.
- The first waypoint gets two action groups (an absolute `gimbalRotate` at
  actionGroupId=1, plus a `gimbalEvenlyRotate` at actionGroupId=2); every
  waypoint after that gets exactly one action group, and its
  `actionGroupId` is the literal value 2 REUSED on every such group (not
  incremented per waypoint — that was the old script's bug). Total action
  group elements = N waypoints + 1.
- `waypointHeadingAngleEnable` is 1 only on the first waypoint, 0 on every
  other waypoint — but every waypoint (including Enable=0 ones) carries a
  real computed heading angle, not a lazy 0 default.
"""
from __future__ import annotations

import time
import zipfile


def _format_coord(value: float) -> str:
    return f"{value:.10f}"


def build_kmz(
    waypoints: list[dict],
    drone_profile: dict,
    mission_params: dict,
    output_path: str,
) -> str:
    template_kml = _build_template_kml(drone_profile, mission_params)
    waylines_wpml = _build_waylines_wpml(waypoints, drone_profile, mission_params)

    with zipfile.ZipFile(output_path, "w", zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("wpmz/template.kml", template_kml)
        zf.writestr("wpmz/waylines.wpml", waylines_wpml)

    return output_path


def _mission_config_block(drone_profile: dict, mission_params: dict, indent: str) -> str:
    trans_speed = mission_params.get(
        "transitional_speed_mps", drone_profile["global_transitional_speed_mps"]
    )
    enum_value = drone_profile["drone_enum_value"]
    sub_enum_value = drone_profile["drone_sub_enum_value"]
    # Defaults to goHome (a real, validated WaypointMap export behavior)
    # rather than the old hardcoded noAction — still fully overridable per
    # mission via mission_params. Valid WPML values: noAction, autoLand,
    # gotoFirstWaypoint, goHome.
    finish_action = mission_params.get("finish_action", "goHome")

    # RC-signal-lost behavior. Defaults to goHome rather than hover — over
    # water/mangrove terrain, hovering in place just drains the battery
    # waiting for a signal that may not come back, whereas goHome gets the
    # aircraft flying toward a safe, known point immediately. Still
    # overridable per mission via mission_params. Valid WPML values: goHome,
    # landing, hover.
    rc_lost_action = mission_params.get("execute_rc_lost_action", "goHome")

    return (
        f"{indent}<wpml:missionConfig>\n"
        f"{indent}  <wpml:flyToWaylineMode>safely</wpml:flyToWaylineMode>\n"
        f"{indent}  <wpml:finishAction>{finish_action}</wpml:finishAction>\n"
        f"{indent}  <wpml:exitOnRCLost>executeLostAction</wpml:exitOnRCLost>\n"
        f"{indent}  <wpml:executeRCLostAction>{rc_lost_action}</wpml:executeRCLostAction>\n"
        f"{indent}  <wpml:globalTransitionalSpeed>{trans_speed}</wpml:globalTransitionalSpeed>\n"
        f"{indent}  <wpml:droneInfo>\n"
        f"{indent}    <wpml:droneEnumValue>{enum_value}</wpml:droneEnumValue>\n"
        f"{indent}    <wpml:droneSubEnumValue>{sub_enum_value}</wpml:droneSubEnumValue>\n"
        f"{indent}  </wpml:droneInfo>\n"
        f"{indent}</wpml:missionConfig>\n"
    )


def _build_template_kml(drone_profile: dict, mission_params: dict) -> str:
    now_ms = str(round(time.time() * 1000))
    mission_config = _mission_config_block(drone_profile, mission_params, "    ")

    return (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:wpml="http://www.dji.com/wpmz/1.0.2">\n'
        "  <Document>\n"
        f"    <wpml:author>mangrove</wpml:author>\n"
        f"    <wpml:createTime>{now_ms}</wpml:createTime>\n"
        f"    <wpml:updateTime>{now_ms}</wpml:updateTime>\n"
        f"{mission_config}"
        "  </Document>\n"
        "</kml>"
    )


def _action_group_xml(
    action_group_id: int,
    start_index: int,
    end_index: int,
    action_id: int,
    actuator_func: str,
    gimbal_pitch_deg: float,
    absolute_gimbal_rotate: bool,
) -> str:
    if absolute_gimbal_rotate:
        action_body = (
            "          <wpml:gimbalHeadingYawBase>aircraft</wpml:gimbalHeadingYawBase>\n"
            "          <wpml:gimbalRotateMode>absoluteAngle</wpml:gimbalRotateMode>\n"
            "          <wpml:gimbalPitchRotateEnable>1</wpml:gimbalPitchRotateEnable>\n"
            f"          <wpml:gimbalPitchRotateAngle>{gimbal_pitch_deg}</wpml:gimbalPitchRotateAngle>\n"
            "          <wpml:gimbalRollRotateEnable>0</wpml:gimbalRollRotateEnable>\n"
            "          <wpml:gimbalRollRotateAngle>0</wpml:gimbalRollRotateAngle>\n"
            "          <wpml:gimbalYawRotateEnable>0</wpml:gimbalYawRotateEnable>\n"
            "          <wpml:gimbalYawRotateAngle>0</wpml:gimbalYawRotateAngle>\n"
            "          <wpml:gimbalRotateTimeEnable>0</wpml:gimbalRotateTimeEnable>\n"
            "          <wpml:gimbalRotateTime>0</wpml:gimbalRotateTime>\n"
            "          <wpml:payloadPositionIndex>0</wpml:payloadPositionIndex>\n"
        )
    else:
        action_body = (
            f"          <wpml:gimbalPitchRotateAngle>{gimbal_pitch_deg}</wpml:gimbalPitchRotateAngle>\n"
            "          <wpml:payloadPositionIndex>0</wpml:payloadPositionIndex>\n"
        )

    return (
        "        <wpml:actionGroup>\n"
        f"          <wpml:actionGroupId>{action_group_id}</wpml:actionGroupId>\n"
        f"          <wpml:actionGroupStartIndex>{start_index}</wpml:actionGroupStartIndex>\n"
        f"          <wpml:actionGroupEndIndex>{end_index}</wpml:actionGroupEndIndex>\n"
        "          <wpml:actionGroupMode>parallel</wpml:actionGroupMode>\n"
        "          <wpml:actionTrigger>\n"
        "            <wpml:actionTriggerType>reachPoint</wpml:actionTriggerType>\n"
        "          </wpml:actionTrigger>\n"
        "          <wpml:action>\n"
        f"            <wpml:actionId>{action_id}</wpml:actionId>\n"
        f"            <wpml:actionActuatorFunc>{actuator_func}</wpml:actionActuatorFunc>\n"
        "            <wpml:actionActuatorFuncParam>\n"
        f"{action_body}"
        "            </wpml:actionActuatorFuncParam>\n"
        "          </wpml:action>\n"
        "        </wpml:actionGroup>\n"
    )


def _build_waylines_wpml(waypoints: list[dict], drone_profile: dict, mission_params: dict) -> str:
    altitude_m = mission_params.get("altitude_m", drone_profile["altitude_m"])
    waypoint_speed = mission_params.get("waypoint_speed_mps", drone_profile["waypoint_speed_mps"])
    gimbal_pitch_deg = mission_params.get("gimbal_pitch_deg", drone_profile["gimbal_pitch_deg"])
    trans_speed = mission_params.get(
        "transitional_speed_mps", drone_profile["global_transitional_speed_mps"]
    )

    action_id = 1
    placemarks = []
    last_index = len(waypoints) - 1

    for wp in waypoints:
        i = wp["index"]
        lat, lng = wp["lat"], wp["lng"]
        heading = wp["heading_deg"]
        heading_enable = wp["heading_enable"]

        action_groups_xml = ""
        if i == 0:
            action_groups_xml += _action_group_xml(
                action_group_id=1,
                start_index=0,
                end_index=0,
                action_id=action_id,
                actuator_func="gimbalRotate",
                gimbal_pitch_deg=gimbal_pitch_deg,
                absolute_gimbal_rotate=True,
            )
            action_id += 1

        # Every waypoint (including the first) gets a gimbalEvenlyRotate
        # group. Its actionGroupId stays at the literal value 2 for every
        # waypoint after the first, matching the real export's convention
        # rather than incrementing a unique id per waypoint. The group's end
        # index spans to the *next* waypoint (not the same one) so the
        # gimbal eases across the coming leg rather than snapping at a
        # single point — confirmed against a real WaypointMap/DJI Fly
        # export, which uses i+1 for every waypoint except the last.
        action_groups_xml += _action_group_xml(
            action_group_id=2,
            start_index=i,
            end_index=min(i + 1, last_index),
            action_id=action_id,
            actuator_func="gimbalEvenlyRotate",
            gimbal_pitch_deg=gimbal_pitch_deg,
            absolute_gimbal_rotate=False,
        )
        action_id += 1

        turn_mode = (
            "toPointAndStopWithContinuityCurvature"
            if i == 0
            else "toPointAndPassWithContinuityCurvature"
        )

        placemarks.append(
            "      <Placemark>\n"
            "        <Point>\n"
            "          <coordinates>\n"
            f"            {_format_coord(lng)},{_format_coord(lat)}\n"
            "          </coordinates>\n"
            "        </Point>\n"
            f"        <wpml:index>{i}</wpml:index>\n"
            f"        <wpml:executeHeight>{altitude_m}</wpml:executeHeight>\n"
            f"        <wpml:waypointSpeed>{waypoint_speed}</wpml:waypointSpeed>\n"
            "        <wpml:waypointHeadingParam>\n"
            "          <wpml:waypointHeadingMode>smoothTransition</wpml:waypointHeadingMode>\n"
            f"          <wpml:waypointHeadingAngle>{heading:.2f}</wpml:waypointHeadingAngle>\n"
            "          <wpml:waypointPoiPoint>0.000000,0.000000,0.000000</wpml:waypointPoiPoint>\n"
            f"          <wpml:waypointHeadingAngleEnable>{heading_enable}</wpml:waypointHeadingAngleEnable>\n"
            "          <wpml:waypointHeadingPathMode>followBadArc</wpml:waypointHeadingPathMode>\n"
            "        </wpml:waypointHeadingParam>\n"
            "        <wpml:waypointTurnParam>\n"
            f"          <wpml:waypointTurnMode>{turn_mode}</wpml:waypointTurnMode>\n"
            "          <wpml:waypointTurnDampingDist>0</wpml:waypointTurnDampingDist>\n"
            "        </wpml:waypointTurnParam>\n"
            "        <wpml:useStraightLine>0</wpml:useStraightLine>\n"
            f"{action_groups_xml}"
            "      </Placemark>\n"
        )

    mission_config = _mission_config_block(drone_profile, mission_params, "\t\t")

    return (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:wpml="http://www.dji.com/wpmz/1.0.2">\n'
        "\t<Document>\n"
        f"{mission_config}"
        "\t\t<Folder>\n"
        "\t\t\t<wpml:templateId>0</wpml:templateId>\n"
        "\t\t\t<wpml:executeHeightMode>relativeToStartPoint</wpml:executeHeightMode>\n"
        "\t\t\t<wpml:waylineId>0</wpml:waylineId>\n"
        "\t\t\t<wpml:distance>0</wpml:distance>\n"
        "\t\t\t<wpml:duration>0</wpml:duration>\n"
        f"\t\t\t<wpml:autoFlightSpeed>{trans_speed}</wpml:autoFlightSpeed>\n"
        + "".join(placemarks)
        + "\t\t</Folder>\n"
        "\t</Document>\n"
        "</kml>"
    )
