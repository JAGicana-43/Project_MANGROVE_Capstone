import xml.etree.ElementTree as ET
import zipfile

from mission_generation_service.pipeline import generate_mission
from mission_generation_service.tests.fixtures import SMALL_AXIS_ALIGNED_RECT

NS = {"kml": "http://www.opengis.net/kml/2.2", "wpml": "http://www.dji.com/wpmz/1.0.2"}


def test_kmz_structure_matches_reference_conventions(tmp_path):
    output_path = str(tmp_path / "mission.kmz")
    summary = generate_mission(SMALL_AXIS_ALIGNED_RECT, "dji_mini_3", overlap_percent=75.0, output_path=output_path)
    n = summary["waypoint_count"]

    with zipfile.ZipFile(output_path) as zf:
        names = set(zf.namelist())
        assert names == {"wpmz/template.kml", "wpmz/waylines.wpml"}
        waylines_xml = zf.read("wpmz/waylines.wpml")

    root = ET.fromstring(waylines_xml)
    folder = root.find(".//kml:Folder", NS)
    assert folder is not None

    assert folder.find("wpml:distance", NS).text == "0"
    assert folder.find("wpml:duration", NS).text == "0"

    placemarks = folder.findall("kml:Placemark", NS)
    assert len(placemarks) == n

    total_action_groups = 0
    for i, placemark in enumerate(placemarks):
        coords_text = placemark.find("kml:Point/kml:coordinates", NS)
        lng_str, lat_str = coords_text.text.strip().split(",")
        # Coordinates must be lng,lat (KML standard) — lng near 125.6, lat near 6.9
        # for our test fixture, so a swapped order would fail this sanity check.
        assert 125.0 < float(lng_str) < 126.0
        assert 6.0 < float(lat_str) < 7.0

        heading_enable = placemark.find("wpml:waypointHeadingParam/wpml:waypointHeadingAngleEnable", NS).text
        action_groups = placemark.findall("wpml:actionGroup", NS)
        total_action_groups += len(action_groups)

        if i == 0:
            assert heading_enable == "1"
            assert len(action_groups) == 2
            group_ids = [g.find("wpml:actionGroupId", NS).text for g in action_groups]
            assert group_ids == ["1", "2"]
            funcs = [g.find("wpml:action/wpml:actionActuatorFunc", NS).text for g in action_groups]
            assert funcs == ["gimbalRotate", "gimbalEvenlyRotate"]
        else:
            assert heading_enable == "0"
            assert len(action_groups) == 1
            assert action_groups[0].find("wpml:actionGroupId", NS).text == "2"
            assert action_groups[0].find("wpml:action/wpml:actionActuatorFunc", NS).text == "gimbalEvenlyRotate"

    # Total action group elements = N waypoints + 1 (the first waypoint's extra group).
    assert total_action_groups == n + 1

    template_xml = zipfile.ZipFile(output_path).read("wpmz/template.kml")
    template_root = ET.fromstring(template_xml)
    trans_speed = template_root.find(".//wpml:globalTransitionalSpeed", NS).text
    assert trans_speed == "2.5"
