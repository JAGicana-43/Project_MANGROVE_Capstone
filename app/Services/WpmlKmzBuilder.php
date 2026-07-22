<?php

namespace App\Services;

/**
 * Builds a DJI WPML-format .kmz (wpmz/template.kml + wpmz/waylines.wpml)
 * from a waypoint array produced by MissionGridGenerator, matching the
 * structural conventions confirmed by diffing a real WaypointMap export
 * (`output_FINAL.kmz`) against the old, untested script's output
 * (`mangrove-mission-4.kmz`):
 *
 * - Coordinates in `longitude,latitude` order (KML standard).
 * - `wpml:distance` / `wpml:duration` in the Folder header are legitimately
 *   0 in a real export — not computed here.
 * - The first waypoint gets two action groups (an absolute `gimbalRotate` at
 *   actionGroupId=1, plus a `gimbalEvenlyRotate` at actionGroupId=2); every
 *   waypoint after that gets exactly one action group, and its
 *   `actionGroupId` is the literal value 2 REUSED on every such group (not
 *   incremented per waypoint — that was the old script's bug). Total action
 *   group elements = N waypoints + 1.
 * - `waypointHeadingAngleEnable` is 1 only on the first waypoint, 0 on every
 *   other waypoint — but every waypoint (including Enable=0 ones) carries a
 *   real computed heading angle, not a lazy 0 default.
 *
 * Fresh rewrite (not a patch) — mirrors mission_generation_service/kmz_builder.py.
 */
class WpmlKmzBuilder
{
    public function build(
        array $waypoints,
        array $droneConfig,
        array $missionParams,
        string $outputPath
    ): string {
        $templateKml = $this->buildTemplateKml($droneConfig, $missionParams);
        $waylinesWpml = $this->buildWaylinesWpml($waypoints, $droneConfig, $missionParams);

        $zip = new \ZipArchive();
        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create KMZ at {$outputPath}");
        }

        $zip->addFromString('wpmz/template.kml', $templateKml);
        $zip->addFromString('wpmz/waylines.wpml', $waylinesWpml);
        $zip->close();

        return $outputPath;
    }

    private function missionConfigBlock(array $droneConfig, array $missionParams, string $indent): string
    {
        $transSpeed = $missionParams['transitionalSpeed'] ?? ($droneConfig['globalTransitionalSpeed'] ?? 2.5);
        $enumValue = $droneConfig['enumValue'] ?? 68;
        $subEnumValue = $droneConfig['subEnumValue'] ?? 0;
        // Defaults to goHome (a real, validated WaypointMap export behavior)
        // rather than the old hardcoded noAction — still fully overridable
        // per mission via $missionParams. Valid WPML values: noAction,
        // autoLand, gotoFirstWaypoint, goHome.
        $finishAction = $missionParams['finishAction'] ?? 'goHome';

        return <<<XML
{$indent}<wpml:missionConfig>
{$indent}  <wpml:flyToWaylineMode>safely</wpml:flyToWaylineMode>
{$indent}  <wpml:finishAction>{$finishAction}</wpml:finishAction>
{$indent}  <wpml:exitOnRCLost>executeLostAction</wpml:exitOnRCLost>
{$indent}  <wpml:executeRCLostAction>hover</wpml:executeRCLostAction>
{$indent}  <wpml:globalTransitionalSpeed>{$transSpeed}</wpml:globalTransitionalSpeed>
{$indent}  <wpml:droneInfo>
{$indent}    <wpml:droneEnumValue>{$enumValue}</wpml:droneEnumValue>
{$indent}    <wpml:droneSubEnumValue>{$subEnumValue}</wpml:droneSubEnumValue>
{$indent}  </wpml:droneInfo>
{$indent}</wpml:missionConfig>

XML;
    }

    protected function buildTemplateKml(array $droneConfig, array $missionParams): string
    {
        $now = (string) round(microtime(true) * 1000);
        $missionConfig = $this->missionConfigBlock($droneConfig, $missionParams, '    ');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:wpml="http://www.dji.com/wpmz/1.0.2">
  <Document>
    <wpml:author>mangrove</wpml:author>
    <wpml:createTime>{$now}</wpml:createTime>
    <wpml:updateTime>{$now}</wpml:updateTime>
{$missionConfig}  </Document>
</kml>
XML;
    }

    private function actionGroupXml(
        int $actionGroupId,
        int $startIndex,
        int $endIndex,
        int $actionId,
        string $actuatorFunc,
        float $gimbalPitchDeg,
        bool $absoluteGimbalRotate
    ): string {
        if ($absoluteGimbalRotate) {
            $actionBody = <<<XML
              <wpml:gimbalHeadingYawBase>aircraft</wpml:gimbalHeadingYawBase>
              <wpml:gimbalRotateMode>absoluteAngle</wpml:gimbalRotateMode>
              <wpml:gimbalPitchRotateEnable>1</wpml:gimbalPitchRotateEnable>
              <wpml:gimbalPitchRotateAngle>{$gimbalPitchDeg}</wpml:gimbalPitchRotateAngle>
              <wpml:gimbalRollRotateEnable>0</wpml:gimbalRollRotateEnable>
              <wpml:gimbalRollRotateAngle>0</wpml:gimbalRollRotateAngle>
              <wpml:gimbalYawRotateEnable>0</wpml:gimbalYawRotateEnable>
              <wpml:gimbalYawRotateAngle>0</wpml:gimbalYawRotateAngle>
              <wpml:gimbalRotateTimeEnable>0</wpml:gimbalRotateTimeEnable>
              <wpml:gimbalRotateTime>0</wpml:gimbalRotateTime>
              <wpml:payloadPositionIndex>0</wpml:payloadPositionIndex>
XML;
        } else {
            $actionBody = <<<XML
              <wpml:gimbalPitchRotateAngle>{$gimbalPitchDeg}</wpml:gimbalPitchRotateAngle>
              <wpml:payloadPositionIndex>0</wpml:payloadPositionIndex>
XML;
        }

        return <<<XML
        <wpml:actionGroup>
          <wpml:actionGroupId>{$actionGroupId}</wpml:actionGroupId>
          <wpml:actionGroupStartIndex>{$startIndex}</wpml:actionGroupStartIndex>
          <wpml:actionGroupEndIndex>{$endIndex}</wpml:actionGroupEndIndex>
          <wpml:actionGroupMode>parallel</wpml:actionGroupMode>
          <wpml:actionTrigger>
            <wpml:actionTriggerType>reachPoint</wpml:actionTriggerType>
          </wpml:actionTrigger>
          <wpml:action>
            <wpml:actionId>{$actionId}</wpml:actionId>
            <wpml:actionActuatorFunc>{$actuatorFunc}</wpml:actionActuatorFunc>
            <wpml:actionActuatorFuncParam>
{$actionBody}
            </wpml:actionActuatorFuncParam>
          </wpml:action>
        </wpml:actionGroup>

XML;
    }

    protected function buildWaylinesWpml(array $waypoints, array $droneConfig, array $missionParams): string
    {
        $altitude = $missionParams['altitude'] ?? 100;
        $speed = $missionParams['waypointSpeed'] ?? 3;
        $gimbalPitch = (float) ($missionParams['gimbalAngle'] ?? -90);
        $transSpeed = $missionParams['transitionalSpeed'] ?? ($droneConfig['globalTransitionalSpeed'] ?? 2.5);

        $actionId = 1;
        $placemarks = '';

        foreach ($waypoints as $wp) {
            $i = $wp['index'];
            $lat = $wp['lat'];
            $lng = $wp['lng'];
            $heading = round($wp['heading'] ?? 0, 2);
            $headingEnable = $wp['headingEnable'] ?? ($i === 0 ? 1 : 0);

            $actionGroupsXml = '';
            if ($i === 0) {
                $actionGroupsXml .= $this->actionGroupXml(
                    actionGroupId: 1,
                    startIndex: 0,
                    endIndex: 0,
                    actionId: $actionId,
                    actuatorFunc: 'gimbalRotate',
                    gimbalPitchDeg: $gimbalPitch,
                    absoluteGimbalRotate: true,
                );
                $actionId++;
            }

            // Every waypoint (including the first) gets a gimbalEvenlyRotate
            // group. Its actionGroupId stays at the literal value 2 for
            // every waypoint after the first, matching the real export's
            // convention rather than incrementing a unique id per waypoint.
            $actionGroupsXml .= $this->actionGroupXml(
                actionGroupId: 2,
                startIndex: $i,
                endIndex: $i,
                actionId: $actionId,
                actuatorFunc: 'gimbalEvenlyRotate',
                gimbalPitchDeg: $gimbalPitch,
                absoluteGimbalRotate: false,
            );
            $actionId++;

            $turnMode = $i === 0
                ? 'toPointAndStopWithContinuityCurvature'
                : 'toPointAndPassWithContinuityCurvature';

            $placemarks .= <<<XML
      <Placemark>
        <Point>
          <coordinates>
            {$this->formatCoord($lng)},{$this->formatCoord($lat)}
          </coordinates>
        </Point>
        <wpml:index>{$i}</wpml:index>
        <wpml:executeHeight>{$altitude}</wpml:executeHeight>
        <wpml:waypointSpeed>{$speed}</wpml:waypointSpeed>
        <wpml:waypointHeadingParam>
          <wpml:waypointHeadingMode>smoothTransition</wpml:waypointHeadingMode>
          <wpml:waypointHeadingAngle>{$heading}</wpml:waypointHeadingAngle>
          <wpml:waypointPoiPoint>0.000000,0.000000,0.000000</wpml:waypointPoiPoint>
          <wpml:waypointHeadingAngleEnable>{$headingEnable}</wpml:waypointHeadingAngleEnable>
          <wpml:waypointHeadingPathMode>followBadArc</wpml:waypointHeadingPathMode>
        </wpml:waypointHeadingParam>
        <wpml:waypointTurnParam>
          <wpml:waypointTurnMode>{$turnMode}</wpml:waypointTurnMode>
          <wpml:waypointTurnDampingDist>0</wpml:waypointTurnDampingDist>
        </wpml:waypointTurnParam>
        <wpml:useStraightLine>0</wpml:useStraightLine>
{$actionGroupsXml}      </Placemark>

XML;
        }

        $missionConfig = $this->missionConfigBlock($droneConfig, $missionParams, "\t\t");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:wpml="http://www.dji.com/wpmz/1.0.2">
\t<Document>
{$missionConfig}\t\t<Folder>
\t\t\t<wpml:templateId>0</wpml:templateId>
\t\t\t<wpml:executeHeightMode>relativeToStartPoint</wpml:executeHeightMode>
\t\t\t<wpml:waylineId>0</wpml:waylineId>
\t\t\t<wpml:distance>0</wpml:distance>
\t\t\t<wpml:duration>0</wpml:duration>
\t\t\t<wpml:autoFlightSpeed>{$transSpeed}</wpml:autoFlightSpeed>
{$placemarks}\t\t</Folder>
\t</Document>
</kml>
XML;
    }

    private function formatCoord(float $value): string
    {
        return number_format($value, 10, '.', '');
    }
}
