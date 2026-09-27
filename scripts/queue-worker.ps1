# Keeps the classification queue worker alive.
#
# Without this, "php artisan queue:work" only runs while someone has a
# terminal open running it -- close the terminal (or let it crash/hit its
# memory limit) and every classification job silently sits queued forever,
# which is exactly what happened before: a run stuck at status=processing
# with started_at never set, because no worker was listening.
#
# This script just loops queue:work forever. If it exits for any reason
# (crash, --timeout, Laravel's own memory-limit restart), the loop starts
# it again a few seconds later instead of leaving the queue unattended.
#
# Real gap found and fixed 2026-09-07: an AtLogOn-only scheduled trigger
# only launches this ONCE per Windows logon. The whole process tree
# (this script's while-loop included, not just the inner php.exe) was
# killed outright by a console-close-style signal (Task Scheduler recorded
# exit code 0xC000013A) mid-session, and nothing relaunched it until
# manually restarted -- confirmed directly, not assumed, when a real
# dispatched run sat at started_at=null for several minutes with zero
# php.exe queue:work process running. The scheduled task now ALSO has a
# repeating time trigger (see queue-worker-task.ps1) as a second layer, so
# recovery doesn't depend on a logon event happening again. That trigger
# fires this same script repeatedly, so this guard makes repeat launches a
# safe no-op instead of spawning duplicate workers.
$alreadyRunning = Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
    Where-Object { $_.CommandLine -like '*queue:work*classification*' }
if ($alreadyRunning) {
    exit 0
}

$root = Split-Path -Parent $PSScriptRoot
Set-Location -Path $root

$logPath = Join-Path $root 'storage\logs\classification-worker.log'

while ($true) {
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Add-Content -Path $logPath -Value "[$timestamp] Starting classification queue worker..."

    php artisan queue:work --queue=classification --tries=3 --timeout=600 *>> $logPath

    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Add-Content -Path $logPath -Value "[$timestamp] Worker exited, restarting in 5s..."
    Start-Sleep -Seconds 5
}
